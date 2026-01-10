<?php

namespace Took\Yii2GiiMCP\Helpers;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * Component Analyzer Helper
 *
 * Analyzes PHP class files to extract metadata using PHP Reflection and nikic/php-parser.
 * Supports analysis of:
 * - Controllers (actions, filters, behaviors)
 * - Models (attributes, validation rules, relations, scenarios)
 * - Widgets (properties and options)
 * - Asset bundles (CSS/JS dependencies)
 */
class ComponentAnalyzer
{
    /**
     * Analyze a controller class file
     *
     * @param string $filePath Path to controller file
     * @return array|null Controller metadata or null on error
     */
    public static function analyzeController(string $filePath): ?array
    {
        try {
            $class = self::getClassFromFile($filePath);
            if ($class === null) {
                return null;
            }

            // Check if it's a controller
            if (! self::isController($class->getName())) {
                return null;
            }

            $result = [
                'type' => 'controller',
                'class' => $class->getName(),
                'file' => $filePath,
                'namespace' => $class->getNamespaceName(),
                'shortName' => $class->getShortName(),
                'actions' => self::extractActions($class),
                'behaviors' => self::extractBehaviors($class),
                'filters' => self::extractFilters($class),
                'parent' => $class->getParentClass() ? $class->getParentClass()->getName() : null,
            ];

            return $result;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Analyze a model class file
     *
     * @param string $filePath Path to model file
     * @return array|null Model metadata or null on error
     */
    public static function analyzeModel(string $filePath): ?array
    {
        try {
            $class = self::getClassFromFile($filePath);
            if ($class === null) {
                return null;
            }

            // Check if it's a model
            if (! self::isModel($class->getName())) {
                return null;
            }

            $result = [
                'type' => 'model',
                'class' => $class->getName(),
                'file' => $filePath,
                'namespace' => $class->getNamespaceName(),
                'shortName' => $class->getShortName(),
                'attributes' => self::extractAttributes($class),
                'rules' => self::extractValidationRules($class),
                'scenarios' => self::extractScenarios($class),
                'parent' => $class->getParentClass() ? $class->getParentClass()->getName() : null,
            ];

            // Add relations for ActiveRecord models
            if (self::isActiveRecord($class->getName())) {
                $result['relations'] = self::extractRelations($class);
                $result['tableName'] = self::extractTableName($class);
            }

            return $result;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Extract action methods from controller class
     *
     * @param ReflectionClass $class Controller class
     * @return array List of actions
     */
    public static function extractActions(ReflectionClass $class): array
    {
        $actions = [];

        // Extract action methods (actionXxx)
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            if (str_starts_with($methodName, 'action') && $methodName !== 'actions') {
                $actionId = lcfirst(substr($methodName, 6));
                $actions[] = [
                    'id' => $actionId,
                    'method' => $methodName,
                    'parameters' => self::extractMethodParameters($method),
                    'comment' => self::cleanDocComment($method->getDocComment()),
                ];
            }
        }

        // Extract inline actions from actions() method
        if ($class->hasMethod('actions')) {
            $inlineActions = self::parseMethodReturnValue($class->getMethod('actions'));
            if (is_array($inlineActions)) {
                foreach ($inlineActions as $actionId => $actionConfig) {
                    $actions[] = [
                        'id' => $actionId,
                        'method' => 'actions',
                        'type' => 'inline',
                        'class' => is_array($actionConfig) ? ($actionConfig['class'] ?? 'unknown') : $actionConfig,
                    ];
                }
            }
        }

        return $actions;
    }

    /**
     * Extract behaviors from class
     *
     * @param ReflectionClass $class Class to analyze
     * @return array List of behaviors
     */
    public static function extractBehaviors(ReflectionClass $class): array
    {
        if (! $class->hasMethod('behaviors')) {
            return [];
        }

        $behaviors = self::parseMethodReturnValue($class->getMethod('behaviors'));
        if (! is_array($behaviors)) {
            return [];
        }

        $result = [];
        foreach ($behaviors as $name => $config) {
            if (is_string($config)) {
                $result[] = ['name' => $name, 'class' => $config];
            } elseif (is_array($config) && isset($config['class'])) {
                $result[] = ['name' => $name, 'class' => $config['class'], 'config' => $config];
            }
        }

        return $result;
    }

    /**
     * Extract filters from controller behaviors
     *
     * @param ReflectionClass $class Controller class
     * @return array List of filters
     */
    public static function extractFilters(ReflectionClass $class): array
    {
        $behaviors = self::extractBehaviors($class);
        $filters = [];

        foreach ($behaviors as $behavior) {
            $behaviorClass = $behavior['class'] ?? '';
            if (
                str_contains($behaviorClass, 'Filter') ||
                str_contains($behaviorClass, 'AccessControl') ||
                str_contains($behaviorClass, 'VerbFilter')
            ) {
                $filters[] = $behavior;
            }
        }

        return $filters;
    }

    /**
     * Extract validation rules from model class
     *
     * @param ReflectionClass $class Model class
     * @return array List of validation rules
     */
    public static function extractValidationRules(ReflectionClass $class): array
    {
        if (! $class->hasMethod('rules')) {
            return [];
        }

        $rules = self::parseMethodReturnValue($class->getMethod('rules'));
        if (! is_array($rules)) {
            return [];
        }

        return $rules;
    }

    /**
     * Extract scenarios from model class
     *
     * @param ReflectionClass $class Model class
     * @return array Scenarios configuration
     */
    public static function extractScenarios(ReflectionClass $class): array
    {
        if (! $class->hasMethod('scenarios')) {
            return [];
        }

        $scenarios = self::parseMethodReturnValue($class->getMethod('scenarios'));
        if (! is_array($scenarios)) {
            return [];
        }

        return $scenarios;
    }

    /**
     * Extract attributes from model class
     *
     * @param ReflectionClass $class Model class
     * @return array List of attributes
     */
    public static function extractAttributes(ReflectionClass $class): array
    {
        $attributes = [];

        // Try to extract from attributes() method if available
        if ($class->hasMethod('attributes')) {
            $result = self::parseMethodReturnValue($class->getMethod('attributes'));
            if (is_array($result)) {
                return $result;
            }
        }

        // For ActiveRecord, we can't easily get attributes without database connection
        // so we'll extract from public properties and rules
        $properties = $class->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            if (! $property->isStatic()) {
                $attributes[] = $property->getName();
            }
        }

        // Also extract from rules
        $rules = self::extractValidationRules($class);
        foreach ($rules as $rule) {
            if (isset($rule[0])) {
                $ruleAttributes = is_array($rule[0]) ? $rule[0] : [$rule[0]];
                foreach ($ruleAttributes as $attr) {
                    if (is_string($attr) && ! in_array($attr, $attributes)) {
                        $attributes[] = $attr;
                    }
                }
            }
        }

        return $attributes;
    }

    /**
     * Extract relations from ActiveRecord model
     *
     * @param ReflectionClass $class Model class
     * @return array List of relations
     */
    public static function extractRelations(ReflectionClass $class): array
    {
        $relations = [];

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            // Check for getXxx methods that might be relations
            if (str_starts_with($methodName, 'get') && $methodName !== 'get') {
                $comment = $method->getDocComment();
                if ($comment && (
                    str_contains($comment, 'hasOne') ||
                    str_contains($comment, 'hasMany') ||
                    str_contains($comment, '@return')
                )) {
                    $relationName = lcfirst(substr($methodName, 3));
                    $relations[] = [
                        'name' => $relationName,
                        'method' => $methodName,
                        'comment' => self::cleanDocComment($comment),
                    ];
                }
            }
        }

        return $relations;
    }

    /**
     * Extract table name from ActiveRecord model
     *
     * @param ReflectionClass $class Model class
     * @return string|null Table name or null
     */
    public static function extractTableName(ReflectionClass $class): ?string
    {
        if (! $class->hasMethod('tableName')) {
            return null;
        }

        try {
            $result = self::parseMethodReturnValue($class->getMethod('tableName'));

            return is_string($result) ? $result : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Parse method return value using php-parser
     *
     * @param ReflectionMethod $method Method to parse
     * @return mixed Parsed return value or null
     */
    public static function parseMethodReturnValue(ReflectionMethod $method): mixed
    {
        try {
            $fileName = $method->getFileName();
            if ($fileName === false || ! file_exists($fileName)) {
                return null;
            }

            $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
            $code = file_get_contents($fileName);
            $ast = $parser->parse($code);

            if ($ast === null) {
                return null;
            }

            $nodeFinder = new NodeFinder();
            $methodName = $method->getName();

            // Find the method node
            $methodNode = $nodeFinder->findFirst($ast, function (Node $node) use ($methodName) {
                return $node instanceof ClassMethod && (string)$node->name === $methodName;
            });

            if ($methodNode === null || ! ($methodNode instanceof ClassMethod)) {
                return null;
            }

            // Find return statements
            $returnNodes = $nodeFinder->findInstanceOf($methodNode, Node\Stmt\Return_::class);

            if (empty($returnNodes)) {
                return null;
            }

            // Try to evaluate the first return statement
            $firstReturn = $returnNodes[0];
            if (! $firstReturn instanceof Node\Stmt\Return_ || $firstReturn->expr === null) {
                return null;
            }

            return self::evaluateNode($firstReturn->expr);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Evaluate AST node to extract value
     *
     * @param Node $node AST node
     * @return mixed Evaluated value or null
     */
    private static function evaluateNode(Node $node): mixed
    {
        // Handle arrays
        if ($node instanceof Node\Expr\Array_) {
            $result = [];
            foreach ($node->items as $item) {
                if ($item === null) {
                    continue;
                }

                $key = $item->key ? self::evaluateNode($item->key) : null;
                $value = self::evaluateNode($item->value);

                if ($key !== null) {
                    $result[$key] = $value;
                } else {
                    $result[] = $value;
                }
            }

            return $result;
        }

        // Handle strings
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        // Handle integers
        if ($node instanceof Node\Scalar\LNumber) {
            return $node->value;
        }

        // Handle floats
        if ($node instanceof Node\Scalar\DNumber) {
            return $node->value;
        }

        // Handle booleans
        if ($node instanceof Node\Expr\ConstFetch) {
            $name = $node->name->toString();
            if ($name === 'true') {
                return true;
            }
            if ($name === 'false') {
                return false;
            }
            if ($name === 'null') {
                return null;
            }
        }

        // Handle class constants and static calls (return as string representation)
        if ($node instanceof Node\Expr\ClassConstFetch) {
            $class = $node->class instanceof Node\Name ? $node->class->toString() : 'unknown';
            $const = $node->name instanceof Node\Identifier ? $node->name->toString() : 'unknown';

            return $class . '::' . $const;
        }

        // For complex expressions, return a placeholder
        return '[complex expression]';
    }

    /**
     * Get ReflectionClass from file path
     *
     * @param string $filePath Path to PHP file
     * @return ReflectionClass|null ReflectionClass instance or null on error
     */
    public static function getClassFromFile(string $filePath): ?ReflectionClass
    {
        try {
            if (! file_exists($filePath) || ! is_readable($filePath)) {
                return null;
            }

            // Parse the file to extract namespace and class name
            $content = file_get_contents($filePath);
            if ($content === false) {
                return null;
            }

            $namespace = '';
            $className = '';

            // Extract namespace
            if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                $namespace = trim($matches[1]);
            }

            // Extract class name
            if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                $className = $matches[1];
            }

            if (empty($className)) {
                return null;
            }

            $fullClassName = $namespace ? $namespace . '\\' . $className : $className;

            // Try to load the class
            if (! class_exists($fullClassName)) {
                // Try to include the file (for testing)
                require_once $filePath;
            }

            return new ReflectionClass($fullClassName);
        } catch (ReflectionException $e) {
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Check if a class is a controller
     *
     * @param string $className Fully qualified class name
     * @return bool True if class is a controller
     */
    public static function isController(string $className): bool
    {
        return str_ends_with($className, 'Controller') || str_contains($className, '\\controllers\\');
    }

    /**
     * Check if a class is a model
     *
     * @param string $className Fully qualified class name
     * @return bool True if class is a model
     */
    public static function isModel(string $className): bool
    {
        // Check if it's in a models namespace
        if (str_contains($className, '\\models\\')) {
            return true;
        }

        // Check if it extends common Yii2 model classes
        try {
            $class = new ReflectionClass($className);
            $parent = $class->getParentClass();
            while ($parent) {
                $parentName = $parent->getName();
                if (
                    $parentName === 'yii\\db\\ActiveRecord' ||
                    $parentName === 'yii\\base\\Model' ||
                    str_ends_with($parentName, '\\Model')
                ) {
                    return true;
                }
                $parent = $parent->getParentClass();
            }
        } catch (ReflectionException $e) {
            return false;
        }

        return false;
    }

    /**
     * Check if a class is an ActiveRecord model
     *
     * @param string $className Fully qualified class name
     * @return bool True if class is an ActiveRecord
     */
    public static function isActiveRecord(string $className): bool
    {
        try {
            $class = new ReflectionClass($className);
            $parent = $class->getParentClass();
            while ($parent) {
                if ($parent->getName() === 'yii\\db\\ActiveRecord') {
                    return true;
                }
                $parent = $parent->getParentClass();
            }
        } catch (ReflectionException $e) {
            return false;
        }

        return false;
    }

    /**
     * Extract method parameters information
     *
     * @param ReflectionMethod $method Method to analyze
     * @return array Parameters information
     */
    private static function extractMethodParameters(ReflectionMethod $method): array
    {
        $parameters = [];
        foreach ($method->getParameters() as $param) {
            $parameters[] = [
                'name' => $param->getName(),
                'type' => $param->getType() ? $param->getType()->__toString() : null,
                'optional' => $param->isOptional(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            ];
        }

        return $parameters;
    }

    /**
     * Clean and format doc comment
     *
     * @param string|false $docComment Raw doc comment
     * @return string|null Cleaned comment or null
     */
    private static function cleanDocComment(string|false $docComment): ?string
    {
        if ($docComment === false || empty($docComment)) {
            return null;
        }

        // Remove /** and */
        $comment = trim($docComment);
        $comment = preg_replace('/^\/\*\*\s*/', '', $comment);
        $comment = preg_replace('/\s*\*\/$/', '', $comment);

        // Remove leading * from each line
        $lines = explode("\n", $comment);
        $lines = array_map(function ($line) {
            return preg_replace('/^\s*\*\s?/', '', $line);
        }, $lines);

        $comment = implode("\n", $lines);
        $comment = trim($comment);

        return empty($comment) ? null : $comment;
    }
}
