<?php

namespace Took\Yii2GiiMCP\Tools;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use Took\Yii2GiiMCP\Helpers\ComponentAnalyzer;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Inspect Components Tool
 *
 * List and analyze components for specified application/module including:
 * - Controllers: List all controllers with actions, filters, behaviors
 * - Models: List ActiveRecord models, form models with attributes, rules, relations
 * - Views: List view files organized by controller
 *
 * This is a read-only tool that helps understand application structure.
 */
class InspectComponents extends AbstractTool
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
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'inspect-components';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'List and analyze application components including controllers (with actions, filters, behaviors), ' .
            'models (with attributes, rules, relations), and views. Supports filtering by application, ' .
            'module, and component type. This is a read-only operation that helps you understand your ' .
            'application structure and available components.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'application' => [
                    'type' => 'string',
                    'description' => 'Application name to inspect (e.g., frontend, backend, console, api). ' .
                        'If not specified, inspects the current application.',
                    'default' => '',
                ],
                'module' => [
                    'type' => 'string',
                    'description' => 'Module name within application to inspect. If not specified, inspects the main application.',
                    'default' => '',
                ],
                'componentType' => [
                    'type' => 'string',
                    'description' => 'Filter by component type: controllers, models, views, or all',
                    'enum' => ['controllers', 'models', 'views', 'all'],
                    'default' => 'all',
                ],
                'includeDetails' => [
                    'type' => 'boolean',
                    'description' => 'Include detailed metadata (actions, rules, relations, etc.)',
                    'default' => true,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function doExecute(array $arguments): array
    {
        try {
            // Get parameters
            $applicationName = $this->getOptionalParam($arguments, 'application', '');
            $moduleName = $this->getOptionalParam($arguments, 'module', '');
            $componentType = $this->getOptionalParam($arguments, 'componentType', 'all');
            $includeDetails = $this->getOptionalParam($arguments, 'includeDetails', true);

            // Determine base path
            $basePath = $this->getBasePath($applicationName);
            if ($basePath === null) {
                return $this->createError("Could not determine application path for: $applicationName");
            }

            // If module is specified, adjust path
            if (! empty($moduleName)) {
                $modulePath = $basePath . '/modules/' . $moduleName;
                if (! is_dir($modulePath)) {
                    return $this->createError("Module not found: $moduleName at $modulePath");
                }
                $basePath = $modulePath;
            }

            // Collect components
            $components = [
                'application' => $applicationName ?: 'current',
                'module' => $moduleName ?: null,
                'basePath' => $basePath,
                'controllers' => [],
                'models' => [],
                'views' => [],
            ];

            // Scan for components based on type filter
            if ($componentType === 'all' || $componentType === 'controllers') {
                $components['controllers'] = $this->scanControllers($basePath, $includeDetails);
            }

            if ($componentType === 'all' || $componentType === 'models') {
                $components['models'] = $this->scanModels($basePath, $includeDetails);
            }

            if ($componentType === 'all' || $componentType === 'views') {
                $components['views'] = $this->scanViews($basePath);
            }

            // Format output
            $output = $this->formatOutput($components, $includeDetails);

            return $this->createResult($output);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to inspect components: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Get base path for application
     *
     * @param string $applicationName Application name
     * @return string|null Base path or null if not found
     */
    private function getBasePath(string $applicationName): ?string
    {
        $appPath = $this->bootstrap->getApp()->getBasePath();
        $templateType = $this->bootstrap->detectTemplateType();

        if (empty($applicationName)) {
            // Use current application
            return $appPath;
        }

        // For advanced template, get root and then find application
        if ($templateType !== 'basic') {
            $rootPath = dirname($appPath);
            $targetPath = $rootPath . '/' . $applicationName;

            if (is_dir($targetPath)) {
                return $targetPath;
            }
        }

        return null;
    }

    /**
     * Scan for controllers in directory
     *
     * @param string $basePath Base path to scan
     * @param bool $includeDetails Include detailed analysis
     * @return array List of controllers
     */
    private function scanControllers(string $basePath, bool $includeDetails): array
    {
        $controllersPath = $basePath . '/controllers';
        if (! is_dir($controllersPath)) {
            return [];
        }

        $controllers = [];
        $files = $this->findPhpFiles($controllersPath);

        foreach ($files as $file) {
            if (! str_ends_with($file, 'Controller.php')) {
                continue;
            }

            if ($includeDetails) {
                $analysis = ComponentAnalyzer::analyzeController($file);
                if ($analysis !== null) {
                    $controllers[] = $analysis;
                }
            } else {
                // Just collect basic info
                $relativePath = str_replace($basePath . '/', '', $file);
                $controllers[] = [
                    'file' => $relativePath,
                    'name' => basename($file, '.php'),
                ];
            }
        }

        return $controllers;
    }

    /**
     * Scan for models in directory
     *
     * @param string $basePath Base path to scan
     * @param bool $includeDetails Include detailed analysis
     * @return array List of models
     */
    private function scanModels(string $basePath, bool $includeDetails): array
    {
        $modelsPath = $basePath . '/models';
        if (! is_dir($modelsPath)) {
            return [];
        }

        $models = [];
        $files = $this->findPhpFiles($modelsPath);

        foreach ($files as $file) {
            // Skip search models
            if (str_ends_with($file, 'Search.php')) {
                continue;
            }

            if ($includeDetails) {
                $analysis = ComponentAnalyzer::analyzeModel($file);
                if ($analysis !== null) {
                    $models[] = $analysis;
                }
            } else {
                // Just collect basic info
                $relativePath = str_replace($basePath . '/', '', $file);
                $models[] = [
                    'file' => $relativePath,
                    'name' => basename($file, '.php'),
                ];
            }
        }

        return $models;
    }

    /**
     * Scan for views in directory
     *
     * @param string $basePath Base path to scan
     * @return array List of views organized by controller
     */
    private function scanViews(string $basePath): array
    {
        $viewsPath = $basePath . '/views';
        if (! is_dir($viewsPath)) {
            return [];
        }

        $views = [];

        // Scan view directories (each directory corresponds to a controller)
        $directories = glob($viewsPath . '/*', GLOB_ONLYDIR);
        if ($directories === false) {
            return [];
        }

        foreach ($directories as $dir) {
            $controllerName = basename($dir);
            $viewFiles = glob($dir . '/*.php');
            if ($viewFiles === false) {
                continue;
            }

            $views[$controllerName] = array_map(function ($file) {
                return basename($file);
            }, $viewFiles);
        }

        return $views;
    }

    /**
     * Find all PHP files in directory recursively
     *
     * @param string $directory Directory to search
     * @return array List of file paths
     */
    private function findPhpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        } catch (Throwable $e) {
            // Ignore errors
        }

        return $files;
    }

    /**
     * Format output for display
     *
     * @param array $components Components data
     * @param bool $includeDetails Whether details are included
     * @return string Formatted output
     */
    private function formatOutput(array $components, bool $includeDetails): string
    {
        $output = "Component Inspection Report\n";
        $output .= str_repeat('=', 50) . "\n\n";

        $output .= "Application: {$components['application']}\n";
        if ($components['module']) {
            $output .= "Module: {$components['module']}\n";
        }
        $output .= "Base Path: {$components['basePath']}\n\n";

        // Controllers
        $output .= "Controllers (" . count($components['controllers']) . "):\n";
        $output .= str_repeat('-', 50) . "\n";

        foreach ($components['controllers'] as $controller) {
            if ($includeDetails) {
                $output .= "\n[{$controller['shortName']}]\n";
                $output .= "  Class: {$controller['class']}\n";
                $output .= "  File: {$controller['file']}\n";
                if ($controller['parent']) {
                    $output .= "  Parent: {$controller['parent']}\n";
                }

                if (! empty($controller['actions'])) {
                    $output .= "  Actions (" . count($controller['actions']) . "):\n";
                    foreach ($controller['actions'] as $action) {
                        $output .= "    - {$action['id']}";
                        if (isset($action['method'])) {
                            $output .= " ({$action['method']})";
                        }
                        $output .= "\n";
                    }
                }

                if (! empty($controller['filters'])) {
                    $output .= "  Filters (" . count($controller['filters']) . "):\n";
                    foreach ($controller['filters'] as $filter) {
                        $output .= "    - {$filter['name']}: {$filter['class']}\n";
                    }
                }

                if (! empty($controller['behaviors']) && empty($controller['filters'])) {
                    $output .= "  Behaviors (" . count($controller['behaviors']) . "):\n";
                    foreach ($controller['behaviors'] as $behavior) {
                        $output .= "    - {$behavior['name']}: {$behavior['class']}\n";
                    }
                }
            } else {
                $output .= "  - {$controller['name']}\n";
            }
        }

        // Models
        $output .= "\n\nModels (" . count($components['models']) . "):\n";
        $output .= str_repeat('-', 50) . "\n";

        foreach ($components['models'] as $model) {
            if ($includeDetails) {
                $output .= "\n[{$model['shortName']}]\n";
                $output .= "  Class: {$model['class']}\n";
                $output .= "  File: {$model['file']}\n";
                if ($model['parent']) {
                    $output .= "  Parent: {$model['parent']}\n";
                }
                if (isset($model['tableName'])) {
                    $output .= "  Table: {$model['tableName']}\n";
                }

                if (! empty($model['attributes'])) {
                    $output .= "  Attributes (" . count($model['attributes']) . "): ";
                    $output .= implode(', ', array_slice($model['attributes'], 0, 10));
                    if (count($model['attributes']) > 10) {
                        $output .= " ... (+" . (count($model['attributes']) - 10) . " more)";
                    }
                    $output .= "\n";
                }

                if (! empty($model['rules'])) {
                    $output .= "  Validation Rules: " . count($model['rules']) . "\n";
                }

                if (! empty($model['relations'])) {
                    $output .= "  Relations (" . count($model['relations']) . "):\n";
                    foreach ($model['relations'] as $relation) {
                        $output .= "    - {$relation['name']} ({$relation['method']})\n";
                    }
                }

                if (! empty($model['scenarios'])) {
                    $output .= "  Scenarios: " . implode(', ', array_keys($model['scenarios'])) . "\n";
                }
            } else {
                $output .= "  - {$model['name']}\n";
            }
        }

        // Views
        $output .= "\n\nViews:\n";
        $output .= str_repeat('-', 50) . "\n";

        if (empty($components['views'])) {
            $output .= "  No views found\n";
        } else {
            foreach ($components['views'] as $controller => $views) {
                $output .= "\n[$controller] (" . count($views) . " views):\n";
                foreach ($views as $view) {
                    $output .= "  - $view\n";
                }
            }
        }

        // Add JSON representation for programmatic access
        $output .= "\n\n" . str_repeat('=', 50) . "\n";
        $output .= "JSON Representation:\n";
        $output .= str_repeat('=', 50) . "\n";
        $output .= json_encode($components, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $output;
    }
}
