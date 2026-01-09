<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\GiiHelper;
use Took\Yii2GiiMCP\Helpers\ValidationHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Generate Form Tool
 *
 * Generates Yii2 form model for data collection and validation.
 * Defaults to preview mode for safety.
 */
class GenerateForm extends AbstractTool
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
        return 'generate-form';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate Yii2 form model for data collection and validation. ' .
            'Automatically detects Basic or Advanced Template and uses appropriate defaults. ' .
            'For Advanced Template, you can specify which component (frontend/backend/api) to generate into. ' .
            'By default, runs in preview mode (no files written). ' .
            'Set preview=false to write files to disk. ' .
            'Form models are useful for forms that are not directly tied to database tables (e.g., ContactForm, LoginForm).';
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
                    'description' => 'Form model class name (e.g., "ContactForm", "LoginForm")',
                ],
                'component' => [
                    'type' => 'string',
                    'enum' => ['frontend', 'backend', 'api', 'common'],
                    'description' => 'For Advanced Template: which component to generate form into (frontend/backend/api/common). Defaults to common for shared forms or frontend.',
                ],
                'namespace' => [
                    'type' => 'string',
                    'description' => 'Namespace for the form model. Defaults to app\\models for Basic Template or common\\models for Advanced Template (or {component}\\models if component specified).',
                    'default' => 'app\\models',
                ],
                'viewPath' => [
                    'type' => 'string',
                    'description' => 'Path for view files. Defaults to @app/views for Basic Template or @{component}/views for Advanced Template.',
                    'default' => '@app/views',
                ],
                'viewName' => [
                    'type' => 'string',
                    'description' => 'Name of the view file (optional, auto-generated from model class)',
                ],
                'scenarioName' => [
                    'type' => 'string',
                    'description' => 'Scenario name for the form',
                    'default' => 'default',
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
            if (!ValidationHelper::validateClassName($modelClass)) {
                return $this->createError(
                    ValidationHelper::getClassNameError($modelClass)
                );
            }

            // Determine defaults based on template type and component
            $component = $this->getOptionalParam($arguments, 'component');
            $defaultNamespace = $this->getDefaultNamespace($component);
            $defaultViewPath = $this->getDefaultViewPath($component);
            
            // Validate namespace if provided
            $namespace = $this->getOptionalParam($arguments, 'namespace', $defaultNamespace);
            if (!ValidationHelper::validateNamespace($namespace)) {
                return $this->createError(
                    ValidationHelper::getNamespaceError($namespace)
                );
            }

            // Ensure Yii2 is initialized
            if (!$this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Prepare options for Gii
            $options = [
                'namespace' => $namespace,
                'viewPath' => $this->getOptionalParam($arguments, 'viewPath', $defaultViewPath),
                'scenarioName' => $this->getOptionalParam($arguments, 'scenarioName', 'default'),
            ];

            // Add optional viewName if provided
            if (isset($arguments['viewName'])) {
                $options['viewName'] = $arguments['viewName'];
            }

            // Remove null values
            $options = array_filter($options, fn($v) => $v !== null);

            // Generate or preview
            if ($preview) {
                $result = $this->giiHelper->previewForm($modelClass, $options);
            } else {
                $result = $this->giiHelper->generateForm($modelClass, $options);
            }

            // Format result
            return $this->formatGiiResult($result, $preview);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to generate form: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Get default namespace based on template type and component
     *
     * @param string|null $component Component for Advanced Template
     * @return string Default namespace
     */
    private function getDefaultNamespace(?string $component): string
    {
        $templateType = $this->bootstrap->detectTemplateType();

        if ($templateType === 'advanced') {
            // For Advanced Template, use component or default to common (shared forms)
            $componentName = $component ?? 'common';
            return $componentName . '\\models';
        }

        // For Basic Template, use app\models
        return 'app\\models';
    }

    /**
     * Get default view path based on template type and component
     *
     * @param string|null $component Component for Advanced Template
     * @return string Default view path
     */
    private function getDefaultViewPath(?string $component): string
    {
        $templateType = $this->bootstrap->detectTemplateType();

        if ($templateType === 'advanced') {
            // For Advanced Template, use component or default to frontend
            $componentName = $component ?? 'frontend';
            return '@' . $componentName . '/views';
        }

        // For Basic Template, use @app/views
        return '@app/views';
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
        if (!$result['success']) {
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
                $conflicts = array_map(fn($c) => $c['path'], $result['conflicts']);

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

        $output = "{$mode} {$fileCount} file(s):\n\n";

        if ($preview) {
            // Preview mode - show file paths and content
            foreach ($result['files'] as $file) {
                $output .= "File: {$file['relativePath']}\n";
                $output .= "Operation: {$file['operation']}\n";
                $output .= "Content:\n```php\n{$file['content']}\n```\n\n";
            }
        } else {
            // Generation mode - show created files
            $output .= "Created: {$result['created']}\n";
            $output .= "Skipped: {$result['skipped']}\n";
            if ($result['errors'] > 0) {
                $output .= "Errors: {$result['errors']}\n";
            }
            $output .= "\nFiles:\n";
            foreach ($result['files'] as $file) {
                $status = strtoupper($file['status']);
                $path = $file['relativePath'] ?? $file['path'];
                $output .= "- [{$status}] {$path}\n";
            }
        }

        return $this->createResult($output);
    }
}
