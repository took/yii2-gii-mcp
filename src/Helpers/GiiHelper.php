<?php

namespace Took\Yii2GiiMCP\Helpers;

use Exception;
use RuntimeException;
use yii\gii\CodeFile;
use yii\gii\generators\controller\Generator as ControllerGenerator;
use yii\gii\generators\crud\Generator as CrudGenerator;
use yii\gii\generators\extension\Generator as ExtensionGenerator;
use yii\gii\generators\form\Generator as FormGenerator;
use yii\gii\generators\model\Generator as ModelGenerator;
use yii\gii\generators\module\Generator as ModuleGenerator;

/**
 * Gii Helper
 *
 * Wrapper for Yii2 Gii generators providing a simplified interface
 * for code generation operations.
 */
class GiiHelper
{
    private Yii2Bootstrap $bootstrap;

    /**
     * @param Yii2Bootstrap $bootstrap Yii2 bootstrap instance
     */
    public function __construct(Yii2Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    /**
     * Preview model code generation
     *
     * Generates model code without writing files.
     *
     * @param string $tableName Database table name
     * @param array $options Generation options
     * @return array Preview result with files array
     * @throws Exception If generation fails
     */
    public function previewModel(string $tableName, array $options = []): array
    {
        $generator = $this->createModelGenerator($tableName, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Model validation failed', $generator->getErrors());
        }

        // Generate files (preview mode)
        $files = $generator->generate();

        return $this->formatPreviewResult($files);
    }

    /**
     * Create model generator instance
     *
     * @param string $tableName Table name
     * @param array $options Generator options
     * @return ModelGenerator
     */
    private function createModelGenerator(string $tableName, array $options): ModelGenerator
    {
        $generator = new ModelGenerator();

        // Set required attributes
        $generator->tableName = $tableName;

        // Set optional attributes from options
        $generator->modelClass = $options['modelClass'] ?? $this->guessModelClass($tableName);
        $generator->ns = $options['namespace'] ?? 'app\\models';
        $generator->baseClass = $options['baseClass'] ?? 'yii\\db\\ActiveRecord';
        $generator->db = $options['db'] ?? 'db';
        $generator->generateRelations = $options['generateRelations'] ?? ModelGenerator::RELATIONS_ALL;
        $generator->generateLabelsFromComments = $options['generateLabelsFromComments'] ?? true;

        if (isset($options['queryNs'])) {
            $generator->queryNs = $options['queryNs'];
        }
        if (isset($options['queryClass'])) {
            $generator->queryClass = $options['queryClass'];
        }
        if (isset($options['queryBaseClass'])) {
            $generator->queryBaseClass = $options['queryBaseClass'];
        }

        return $generator;
    }

    /**
     * Guess model class name from table name
     *
     * @param string $tableName Table name
     * @return string Model class name
     */
    private function guessModelClass(string $tableName): string
    {
        // Remove common prefixes
        $tableName = preg_replace('/^(tbl_|t_)/', '', $tableName);

        // Convert to PascalCase
        $parts = explode('_', $tableName);
        $className = implode('', array_map('ucfirst', $parts));

        return $className;
    }

    /**
     * Format validation errors
     *
     * @param string $message Error message
     * @param array $errors Validation errors
     * @return array Formatted error result
     */
    private function formatErrors(string $message, array $errors): array
    {
        return [
            'success' => false,
            'error' => $message,
            'validationErrors' => $errors,
        ];
    }

    /**
     * Format preview result
     *
     * @param CodeFile[] $files Array of CodeFile objects
     * @return array Formatted result
     */
    private function formatPreviewResult(array $files): array
    {
        $formatted = [];

        foreach ($files as $file) {
            $formatted[] = [
                'path' => $file->path,
                'relativePath' => $file->getRelativePath(),
                'operation' => $file->operation,
                'content' => $file->content,
            ];
        }

        return [
            'success' => true,
            'preview' => true,
            'fileCount' => count($formatted),
            'files' => $formatted,
        ];
    }

    /**
     * Generate model files
     *
     * Generates and writes model code to disk.
     *
     * @param string $tableName Database table name
     * @param array $options Generation options
     * @return array Generation result with created files
     * @throws Exception If generation fails
     */
    public function generateModel(string $tableName, array $options = []): array
    {
        $generator = $this->createModelGenerator($tableName, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Model validation failed', $generator->getErrors());
        }

        // Generate files
        $files = $generator->generate();

        // Check for conflicts
        $conflicts = $this->checkFileConflicts($files);
        if (! empty($conflicts)) {
            return $this->formatConflicts($conflicts);
        }

        // Save files
        $results = $this->saveFiles($files);

        return $this->formatGenerateResult($results);
    }

    /**
     * Check for file conflicts
     *
     * @param CodeFile[] $files Array of CodeFile objects
     * @return array Array of conflicting files
     */
    private function checkFileConflicts(array $files): array
    {
        $conflicts = [];

        foreach ($files as $file) {
            if ($file->operation === CodeFile::OP_OVERWRITE || $file->operation === CodeFile::OP_SKIP) {
                $conflicts[] = [
                    'path' => $file->path,
                    'operation' => $file->operation,
                    'exists' => file_exists($file->path),
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Format conflict result
     *
     * @param array $conflicts Array of conflicts
     * @return array Formatted conflict result
     */
    private function formatConflicts(array $conflicts): array
    {
        return [
            'success' => false,
            'error' => 'File conflicts detected',
            'conflicts' => $conflicts,
            'message' => 'Some files already exist. Use force option to overwrite or remove existing files.',
        ];
    }

    /**
     * Save generated files to disk
     *
     * @param CodeFile[] $files Array of CodeFile objects
     * @return array Array of save results
     */
    private function saveFiles(array $files): array
    {
        $results = [];
        $errors = '';

        foreach ($files as $file) {
            if ($file->operation === CodeFile::OP_SKIP) {
                $results[] = [
                    'path' => $file->path,
                    'status' => 'skipped',
                    'operation' => $file->operation,
                ];

                continue;
            }

            // Save file
            if ($file->save()) {
                $results[] = [
                    'path' => $file->path,
                    'status' => 'created',
                    'operation' => $file->operation,
                    'relativePath' => $file->getRelativePath(),
                ];
            } else {
                $error = $file->error ?? 'Unknown error';
                $errors .= "Failed to save {$file->path}: {$error}\n";
                $results[] = [
                    'path' => $file->path,
                    'status' => 'error',
                    'error' => $error,
                ];
            }
        }

        if ($errors !== '') {
            throw new RuntimeException("File save errors:\n" . $errors);
        }

        return $results;
    }

    /**
     * Format generation result
     *
     * @param array $results Array of save results
     * @return array Formatted result
     */
    private function formatGenerateResult(array $results): array
    {
        $created = array_filter($results, fn ($r) => $r['status'] === 'created');
        $skipped = array_filter($results, fn ($r) => $r['status'] === 'skipped');
        $errors = array_filter($results, fn ($r) => $r['status'] === 'error');

        return [
            'success' => empty($errors),
            'preview' => false,
            'fileCount' => count($results),
            'created' => count($created),
            'skipped' => count($skipped),
            'errors' => count($errors),
            'files' => $results,
        ];
    }

    /**
     * Preview CRUD code generation
     *
     * Generates CRUD code without writing files.
     *
     * @param string $modelClass Full model class name
     * @param array $options Generation options
     * @return array Preview result with files array
     * @throws Exception If generation fails
     */
    public function previewCrud(string $modelClass, array $options = []): array
    {
        $generator = $this->createCrudGenerator($modelClass, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('CRUD validation failed', $generator->getErrors());
        }

        // Generate files (preview mode)
        $files = $generator->generate();

        return $this->formatPreviewResult($files);
    }

    /**
     * Create CRUD generator instance
     *
     * @param string $modelClass Model class name
     * @param array $options Generator options
     * @return CrudGenerator
     */
    private function createCrudGenerator(string $modelClass, array $options): CrudGenerator
    {
        $generator = new CrudGenerator();

        // Set required attributes
        $generator->modelClass = $modelClass;

        // Get component if specified
        $component = $options['component'] ?? null;

        // Set optional attributes from options with template-aware defaults
        $generator->controllerClass = $options['controllerClass'] ?? $this->guessControllerClass($modelClass, $component);
        $generator->searchModelClass = $options['searchModelClass'] ?? $this->guessSearchModelClass($modelClass, $component);
        $generator->viewPath = $options['viewPath'] ?? $this->guessViewPath($modelClass, $component);
        $generator->baseControllerClass = $options['baseControllerClass'] ?? 'yii\\web\\Controller';
        $generator->indexWidgetType = $options['indexWidgetType'] ?? 'grid';

        if (isset($options['enableI18N'])) {
            $generator->enableI18N = $options['enableI18N'];
        }

        return $generator;
    }

    /**
     * Guess controller class name from model class
     *
     * @param string $modelClass Model class name
     * @param string|null $component Component name for Advanced Template (frontend/backend/api/common)
     * @return string Controller class name
     */
    private function guessControllerClass(string $modelClass, ?string $component = null): string
    {
        // Extract base class name (without namespace)
        $baseName = basename(str_replace('\\', '/', $modelClass));

        // Detect template type
        $templateType = $this->bootstrap->detectTemplateType();

        if ($templateType === 'advanced') {
            // For Advanced Template, determine component
            if ($component === null) {
                // Try to detect from model namespace
                $component = $this->detectComponentFromModel($modelClass);
            }

            // Generate controller class name for Advanced Template
            return $component . '\\controllers\\' . $baseName . 'Controller';
        }

        // For Basic Template, use app\controllers
        return 'app\\controllers\\' . $baseName . 'Controller';
    }

    /**
     * Detect component from model class namespace
     *
     * @param string $modelClass Model class name
     * @return string Component name (frontend/frontpage/backend/backoffice/api/common, defaults to frontend)
     */
    private function detectComponentFromModel(string $modelClass): string
    {
        // Check if model is in common\models (shared models)
        if (str_starts_with($modelClass, 'common\\models\\')) {
            // For common models, default to frontend
            return 'frontend';
        }

        // Check if model is in specific component (support both standard and alternative naming)
        if (str_starts_with($modelClass, 'frontend\\models\\')) {
            return 'frontend';
        }

        if (str_starts_with($modelClass, 'frontpage\\models\\')) {
            return 'frontpage';
        }

        if (str_starts_with($modelClass, 'backend\\models\\')) {
            return 'backend';
        }

        if (str_starts_with($modelClass, 'backoffice\\models\\')) {
            return 'backoffice';
        }

        if (str_starts_with($modelClass, 'api\\models\\')) {
            return 'api';
        }

        // Default to frontend for Advanced Template
        return 'frontend';
    }

    /**
     * Guess search model class name
     *
     * @param string $modelClass Model class name
     * @param string|null $component Component name for Advanced Template
     * @return string Search model class name
     */
    private function guessSearchModelClass(string $modelClass, ?string $component = null): string
    {
        // Extract base class name (without namespace)
        $baseName = basename(str_replace('\\', '/', $modelClass));

        // Detect template type
        $templateType = $this->bootstrap->detectTemplateType();

        if ($templateType === 'advanced') {
            // For Advanced Template, determine component
            if ($component === null) {
                $component = $this->detectComponentFromModel($modelClass);
            }

            // Search model goes in the same component as controller
            return $component . '\\models\\' . $baseName . 'Search';
        }

        // For Basic Template, search model goes in app\models
        return 'app\\models\\' . $baseName . 'Search';
    }

    /**
     * Guess view path for CRUD
     *
     * @param string $modelClass Model class name
     * @param string|null $component Component name for Advanced Template
     * @return string|null View path (null for default)
     */
    private function guessViewPath(string $modelClass, ?string $component = null): ?string
    {
        // Detect template type
        $templateType = $this->bootstrap->detectTemplateType();

        if ($templateType === 'advanced') {
            // For Advanced Template, determine component
            if ($component === null) {
                $component = $this->detectComponentFromModel($modelClass);
            }

            // Extract base class name and convert to lowercase with dashes
            $baseName = basename(str_replace('\\', '/', $modelClass));
            $viewName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $baseName));

            // Return view path as @component/views/model-name
            return '@' . $component . '/views/' . $viewName;
        }

        // For Basic Template, use default (null)
        return null;
    }

    /**
     * Generate CRUD files
     *
     * Generates and writes CRUD code to disk.
     *
     * @param string $modelClass Full model class name
     * @param array $options Generation options
     * @return array Generation result with created files
     * @throws Exception If generation fails
     */
    public function generateCrud(string $modelClass, array $options = []): array
    {
        $generator = $this->createCrudGenerator($modelClass, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('CRUD validation failed', $generator->getErrors());
        }

        // Generate files
        $files = $generator->generate();

        // Check for conflicts
        $conflicts = $this->checkFileConflicts($files);
        if (! empty($conflicts)) {
            return $this->formatConflicts($conflicts);
        }

        // Save files
        $results = $this->saveFiles($files);

        return $this->formatGenerateResult($results);
    }

    /**
     * Preview controller code generation
     *
     * Generates controller code without writing files.
     *
     * @param string $controllerID Controller ID (e.g., 'user', 'post')
     * @param array $options Generation options
     * @return array Preview result with files array
     * @throws Exception If generation fails
     */
    public function previewController(string $controllerID, array $options = []): array
    {
        $generator = $this->createControllerGenerator($controllerID, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Controller validation failed', $generator->getErrors());
        }

        // Generate files (preview mode)
        $files = $generator->generate();

        return $this->formatPreviewResult($files);
    }

    /**
     * Create controller generator instance
     *
     * @param string $controllerID Controller ID
     * @param array $options Generator options
     * @return ControllerGenerator
     */
    private function createControllerGenerator(string $controllerID, array $options): ControllerGenerator
    {
        $generator = new ControllerGenerator();

        // Set required attributes
        $generator->controller = $controllerID;

        // Set optional attributes from options
        $generator->actions = $options['actions'] ?? 'index';
        $generator->ns = $options['namespace'] ?? 'app\\controllers';
        $generator->baseClass = $options['baseClass'] ?? 'yii\\web\\Controller';

        return $generator;
    }

    /**
     * Generate controller files
     *
     * Generates and writes controller code to disk.
     *
     * @param string $controllerID Controller ID
     * @param array $options Generation options
     * @return array Generation result with created files
     * @throws Exception If generation fails
     */
    public function generateController(string $controllerID, array $options = []): array
    {
        $generator = $this->createControllerGenerator($controllerID, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Controller validation failed', $generator->getErrors());
        }

        // Generate files
        $files = $generator->generate();

        // Check for conflicts
        $conflicts = $this->checkFileConflicts($files);
        if (! empty($conflicts)) {
            return $this->formatConflicts($conflicts);
        }

        // Save files
        $results = $this->saveFiles($files);

        return $this->formatGenerateResult($results);
    }

    /**
     * Preview form code generation
     *
     * Generates form model code without writing files.
     *
     * @param string $modelClass Model class name
     * @param array $options Generation options
     * @return array Preview result with files array
     * @throws Exception If generation fails
     */
    public function previewForm(string $modelClass, array $options = []): array
    {
        $generator = $this->createFormGenerator($modelClass, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Form validation failed', $generator->getErrors());
        }

        // Generate files (preview mode)
        $files = $generator->generate();

        return $this->formatPreviewResult($files);
    }

    /**
     * Create form generator instance
     *
     * @param string $modelClass Model class name
     * @param array $options Generator options
     * @return FormGenerator
     */
    private function createFormGenerator(string $modelClass, array $options): FormGenerator
    {
        $generator = new FormGenerator();

        // Set required attributes
        $generator->modelClass = $modelClass;

        // Set optional attributes from options
        $generator->ns = $options['namespace'] ?? 'app\\models';
        $generator->viewPath = $options['viewPath'] ?? '@app/views';
        $generator->scenarioName = $options['scenarioName'] ?? 'default';

        if (isset($options['viewName'])) {
            $generator->viewName = $options['viewName'];
        }

        return $generator;
    }

    /**
     * Generate form files
     *
     * Generates and writes form model code to disk.
     *
     * @param string $modelClass Model class name
     * @param array $options Generation options
     * @return array Generation result with created files
     * @throws Exception If generation fails
     */
    public function generateForm(string $modelClass, array $options = []): array
    {
        $generator = $this->createFormGenerator($modelClass, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Form validation failed', $generator->getErrors());
        }

        // Generate files
        $files = $generator->generate();

        // Check for conflicts
        $conflicts = $this->checkFileConflicts($files);
        if (! empty($conflicts)) {
            return $this->formatConflicts($conflicts);
        }

        // Save files
        $results = $this->saveFiles($files);

        return $this->formatGenerateResult($results);
    }

    /**
     * Preview module code generation (stub)
     *
     * Generates module code without writing files.
     *
     * @param string $moduleID Module ID
     * @param array $options Generation options
     * @return array Preview result with files array
     * @throws Exception If generation fails
     */
    public function previewModule(string $moduleID, array $options = []): array
    {
        $generator = $this->createModuleGenerator($moduleID, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Module validation failed', $generator->getErrors());
        }

        // Generate files (preview mode)
        $files = $generator->generate();

        return $this->formatPreviewResult($files);
    }

    /**
     * Create module generator instance (stub)
     *
     * @param string $moduleID Module ID
     * @param array $options Generator options
     * @return ModuleGenerator
     */
    private function createModuleGenerator(string $moduleID, array $options): ModuleGenerator
    {
        $generator = new ModuleGenerator();

        // Set required attributes
        $generator->moduleID = $moduleID;

        // Set optional attributes from options
        $generator->moduleClass = $options['moduleClass'] ?? 'app\\modules\\' . $moduleID . '\\Module';

        return $generator;
    }

    /**
     * Generate module files (stub)
     *
     * Generates and writes module code to disk.
     *
     * @param string $moduleID Module ID
     * @param array $options Generation options
     * @return array Generation result with created files
     * @throws Exception If generation fails
     */
    public function generateModule(string $moduleID, array $options = []): array
    {
        $generator = $this->createModuleGenerator($moduleID, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Module validation failed', $generator->getErrors());
        }

        // Generate files
        $files = $generator->generate();

        // Check for conflicts
        $conflicts = $this->checkFileConflicts($files);
        if (! empty($conflicts)) {
            return $this->formatConflicts($conflicts);
        }

        // Save files
        $results = $this->saveFiles($files);

        return $this->formatGenerateResult($results);
    }

    /**
     * Preview extension code generation (stub)
     *
     * Generates extension code without writing files.
     *
     * @param string $vendorName Vendor name
     * @param string $packageName Package name
     * @param array $options Generation options
     * @return array Preview result with files array
     * @throws Exception If generation fails
     */
    public function previewExtension(string $vendorName, string $packageName, array $options = []): array
    {
        $generator = $this->createExtensionGenerator($vendorName, $packageName, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Extension validation failed', $generator->getErrors());
        }

        // Generate files (preview mode)
        $files = $generator->generate();

        return $this->formatPreviewResult($files);
    }

    /**
     * Create extension generator instance (stub)
     *
     * @param string $vendorName Vendor name
     * @param string $packageName Package name
     * @param array $options Generator options
     * @return ExtensionGenerator
     */
    private function createExtensionGenerator(string $vendorName, string $packageName, array $options): ExtensionGenerator
    {
        $generator = new ExtensionGenerator();

        // Set required attributes
        $generator->vendorName = $vendorName;
        $generator->packageName = $packageName;

        // Set optional attributes from options
        if (isset($options['namespace'])) {
            $generator->namespace = $options['namespace'];
        }
        if (isset($options['type'])) {
            $generator->type = $options['type'];
        }
        if (isset($options['keywords'])) {
            $generator->keywords = $options['keywords'];
        }
        if (isset($options['title'])) {
            $generator->title = $options['title'];
        }
        if (isset($options['description'])) {
            $generator->description = $options['description'];
        }
        if (isset($options['authorName'])) {
            $generator->authorName = $options['authorName'];
        }
        if (isset($options['authorEmail'])) {
            $generator->authorEmail = $options['authorEmail'];
        }

        return $generator;
    }

    /**
     * Generate extension files (stub)
     *
     * Generates and writes extension code to disk.
     *
     * @param string $vendorName Vendor name
     * @param string $packageName Package name
     * @param array $options Generation options
     * @return array Generation result with created files
     * @throws Exception If generation fails
     */
    public function generateExtension(string $vendorName, string $packageName, array $options = []): array
    {
        $generator = $this->createExtensionGenerator($vendorName, $packageName, $options);

        // Validate generator
        if (! $generator->validate()) {
            return $this->formatErrors('Extension validation failed', $generator->getErrors());
        }

        // Generate files
        $files = $generator->generate();

        // Check for conflicts
        $conflicts = $this->checkFileConflicts($files);
        if (! empty($conflicts)) {
            return $this->formatConflicts($conflicts);
        }

        // Save files
        $results = $this->saveFiles($files);

        return $this->formatGenerateResult($results);
    }

    /**
     * Validate files for conflicts
     *
     * Checks if generated files would overwrite existing files.
     *
     * @param CodeFile[] $files Array of CodeFile objects
     * @return array Array of conflicting files
     */
    public function validateFiles(array $files): array
    {
        return $this->checkFileConflicts($files);
    }
}
