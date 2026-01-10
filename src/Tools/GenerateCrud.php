<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\GiiHelper;
use Took\Yii2GiiMCP\Helpers\ValidationHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Generate CRUD Tool
 *
 * Generates Yii2 CRUD scaffolding (controller + views) for a model.
 * Defaults to preview mode for safety.
 */
class GenerateCrud extends AbstractTool
{
    private Yii2Bootstrap $bootstrap;
    private GiiHelper $giiHelper;

    /**
     * @param Yii2Bootstrap $bootstrap Yii2 bootstrap instance
     */
    public function __construct(Yii2Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
        $this->giiHelper = new GiiHelper($bootstrap);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'generate-crud';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate Yii2 CRUD scaffolding (controller, search model, and views) for an existing model. ' .
            'Automatically detects Basic or Advanced Template and uses appropriate defaults. ' .
            'For Advanced Template, you can specify which component (frontend/backend/api) to generate into, ' .
            'or it will auto-detect from the model namespace. ' .
            'By default, runs in preview mode (no files written). ' .
            'Set preview=false to write files to disk. ' .
            'This tool will check for file conflicts and validate inputs before generation. ' .
            'Make sure the model class exists before generating CRUD.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'modelClass' => [
                    'type' => 'string',
                    'description' => 'Full model class name (e.g., app\\models\\User or common\\models\\User)',
                ],
                'component' => [
                    'type' => 'string',
                    'enum' => ['frontend', 'backend', 'api', 'common'],
                    'description' => 'For Advanced Template: which component to generate CRUD into (frontend/backend/api/common). If not specified, auto-detects from model namespace or uses frontend as default.',
                ],
                'controllerClass' => [
                    'type' => 'string',
                    'description' => 'Controller class name (optional, auto-generated based on model and component)',
                ],
                'viewPath' => [
                    'type' => 'string',
                    'description' => 'Path for view files (optional, defaults to standard location)',
                ],
                'baseControllerClass' => [
                    'type' => 'string',
                    'description' => 'Base controller class',
                    'default' => 'yii\\web\\Controller',
                ],
                'indexWidgetType' => [
                    'type' => 'string',
                    'description' => 'Widget type for index page',
                    'enum' => ['grid', 'list'],
                    'default' => 'grid',
                ],
                'searchModelClass' => [
                    'type' => 'string',
                    'description' => 'Search model class name (optional, auto-generated from model)',
                ],
                'enableI18N' => [
                    'type' => 'boolean',
                    'description' => 'Enable internationalization (i18n)',
                    'default' => false,
                ],
                'preview' => [
                    'type' => 'boolean',
                    'description' => 'Preview mode (true) or write files (false)',
                    'default' => true,
                ],
            ],
            'required' => ['modelClass'],
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
            $modelClass = $this->getRequiredParam($arguments, 'modelClass');
            $preview = $this->getOptionalParam($arguments, 'preview', true);

            // Validate model class name
            if (! ValidationHelper::validateClassName($modelClass)) {
                return $this->createError(
                    ValidationHelper::getClassNameError($modelClass)
                );
            }

            // Validate controller class if provided
            if (isset($arguments['controllerClass'])) {
                $controllerClass = $arguments['controllerClass'];
                if (! ValidationHelper::validateClassName($controllerClass)) {
                    return $this->createError(
                        ValidationHelper::getClassNameError($controllerClass)
                    );
                }
            }

            // Validate search model class if provided
            if (isset($arguments['searchModelClass'])) {
                $searchModelClass = $arguments['searchModelClass'];
                if (! ValidationHelper::validateClassName($searchModelClass)) {
                    return $this->createError(
                        ValidationHelper::getClassNameError($searchModelClass)
                    );
                }
            }

            // Ensure Yii2 is initialized
            if (! $this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Verify model class exists
            if (! class_exists($modelClass)) {
                return $this->createError(
                    "Model class '{$modelClass}' not found",
                    [
                        'modelClass' => $modelClass,
                        'suggestion' => 'Make sure you have generated the model first using generate-model tool.',
                    ]
                );
            }

            // Prepare options for Gii
            $options = [
                'component' => $this->getOptionalParam($arguments, 'component'),
                'controllerClass' => $this->getOptionalParam($arguments, 'controllerClass'),
                'viewPath' => $this->getOptionalParam($arguments, 'viewPath'),
                'baseControllerClass' => $this->getOptionalParam($arguments, 'baseControllerClass', 'yii\\web\\Controller'),
                'indexWidgetType' => $this->getOptionalParam($arguments, 'indexWidgetType', 'grid'),
                'searchModelClass' => $this->getOptionalParam($arguments, 'searchModelClass'),
                'enableI18N' => $this->getOptionalParam($arguments, 'enableI18N', false),
            ];

            // Remove null values
            $options = array_filter($options, fn ($v) => $v !== null);

            // Generate or preview
            if ($preview) {
                $result = $this->giiHelper->previewCrud($modelClass, $options);
            } else {
                $result = $this->giiHelper->generateCrud($modelClass, $options);
            }

            // Format result
            return $this->formatGiiResult($result, $preview);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to generate CRUD: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Format Gii result for MCP response
     *
     * @param array $result Gii result
     * @param bool $preview Preview mode flag
     * @return array Formatted result
     */
    private function formatGiiResult(array $result, bool $preview): array
    {
        if (! $result['success']) {
            // Handle validation errors
            if (isset($result['validationErrors'])) {
                $errors = [];
                foreach ($result['validationErrors'] as $field => $fieldErrors) {
                    $errors[] = "{$field}: " . implode(', ', $fieldErrors);
                }

                return $this->createError(
                    $result['error'] ?? 'Validation failed',
                    ['validationErrors' => $errors]
                );
            }

            // Handle conflicts
            if (isset($result['conflicts'])) {
                $conflicts = array_map(fn ($c) => $c['path'], $result['conflicts']);

                return $this->createError(
                    $result['error'] ?? 'File conflicts',
                    [
                        'conflicts' => $conflicts,
                        'message' => 'Files already exist. Set preview=false to see conflicts or backup existing files.',
                    ]
                );
            }

            return $this->createError($result['error'] ?? 'Generation failed', $result);
        }

        // Success - format output
        $mode = $preview ? 'Preview' : 'Generated';
        $fileCount = $result['fileCount'];

        $output = "{$mode} {$fileCount} file(s) for CRUD:\n\n";

        if ($preview) {
            // Preview mode - show file paths (content would be too large for CRUD)
            $output .= "Files to be generated:\n";
            foreach ($result['files'] as $file) {
                $output .= "- {$file['relativePath']} ({$file['operation']})\n";
            }

            $output .= "\nNote: Set preview=false to generate these files.\n";
            $output .= "Files include: controller, search model, and views (index, view, create, update, _form, _search).\n";
        } else {
            // Generation mode - show created files
            $output .= "Created: {$result['created']}\n";
            $output .= "Skipped: {$result['skipped']}\n";
            if ($result['errors'] > 0) {
                $output .= "Errors: {$result['errors']}\n";
            }
            $output .= "\nFiles:\n";

            // Group by type
            $controllers = [];
            $models = [];
            $views = [];

            foreach ($result['files'] as $file) {
                $status = strtoupper($file['status']);
                $path = $file['relativePath'] ?? $file['path'];

                if (str_contains($path, 'controllers')) {
                    $controllers[] = "  [{$status}] {$path}";
                } elseif (str_contains($path, 'models')) {
                    $models[] = "  [{$status}] {$path}";
                } elseif (str_contains($path, 'views')) {
                    $views[] = "  [{$status}] {$path}";
                }
            }

            if (! empty($controllers)) {
                $output .= "\nControllers:\n" . implode("\n", $controllers) . "\n";
            }
            if (! empty($models)) {
                $output .= "\nModels:\n" . implode("\n", $models) . "\n";
            }
            if (! empty($views)) {
                $output .= "\nViews:\n" . implode("\n", $views) . "\n";
            }
        }

        return $this->createResult($output);
    }
}
