<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\GiiHelper;
use Took\Yii2GiiMCP\Helpers\ValidationHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Generate Controller Tool
 *
 * Generates Yii2 controller with custom actions.
 * Defaults to preview mode for safety.
 */
class GenerateController extends AbstractTool
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
        return 'generate-controller';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate Yii2 controller with custom actions. ' .
            'By default, runs in preview mode (no files written). ' .
            'Set preview=false to write files to disk. ' .
            'You can specify multiple actions as a comma-separated string (e.g., "index,view,create").';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'controllerID' => [
                    'type' => 'string',
                    'description' => 'Controller ID (e.g., "user", "post", "admin/user")',
                ],
                'actions' => [
                    'type' => 'string',
                    'description' => 'Comma-separated list of action IDs (e.g., "index,view,create,update,delete")',
                    'default' => 'index',
                ],
                'namespace' => [
                    'type' => 'string',
                    'description' => 'Namespace for the controller',
                    'default' => 'app\\controllers',
                ],
                'baseClass' => [
                    'type' => 'string',
                    'description' => 'Base class for the controller',
                    'default' => 'yii\\web\\Controller',
                ],
                'preview' => [
                    'type' => 'boolean',
                    'description' => 'Preview mode (true) or write files (false)',
                    'default' => true,
                ],
            ],
            'required' => ['controllerID'],
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
            $controllerID = $this->getRequiredParam($arguments, 'controllerID');
            $preview = $this->getOptionalParam($arguments, 'preview', true);

            // Validate controller ID
            if (!$this->validateControllerID($controllerID)) {
                return $this->createError(
                    "Invalid controller ID '{$controllerID}'. " .
                    "Use lowercase with optional path (e.g., 'user', 'admin/user')."
                );
            }

            // Validate namespace if provided
            $namespace = $this->getOptionalParam($arguments, 'namespace', 'app\\controllers');
            if (!ValidationHelper::validateNamespace($namespace)) {
                return $this->createError(
                    ValidationHelper::getNamespaceError($namespace)
                );
            }

            // Validate actions if provided
            $actions = $this->getOptionalParam($arguments, 'actions', 'index');
            if (!$this->validateActions($actions)) {
                return $this->createError(
                    "Invalid actions format. Use comma-separated action IDs (e.g., 'index,view,create')."
                );
            }

            // Ensure Yii2 is initialized
            if (!$this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Prepare options for Gii
            $options = [
                'actions' => $actions,
                'namespace' => $namespace,
                'baseClass' => $this->getOptionalParam($arguments, 'baseClass', 'yii\\web\\Controller'),
            ];

            // Remove null values
            $options = array_filter($options, fn($v) => $v !== null);

            // Generate or preview
            if ($preview) {
                $result = $this->giiHelper->previewController($controllerID, $options);
            } else {
                $result = $this->giiHelper->generateController($controllerID, $options);
            }

            // Format result
            return $this->formatGiiResult($result, $preview);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to generate controller: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Validate controller ID
     *
     * @param string $controllerID Controller ID
     * @return bool
     */
    private function validateControllerID(string $controllerID): bool
    {
        // Controller ID should be lowercase, alphanumeric with optional path separator
        return preg_match('/^[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)*$/', $controllerID) === 1;
    }

    /**
     * Validate actions string
     *
     * @param string $actions Actions string
     * @return bool
     */
    private function validateActions(string $actions): bool
    {
        // Actions should be comma-separated action IDs (camelCase allowed)
        $actionList = array_map('trim', explode(',', $actions));
        foreach ($actionList as $action) {
            if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $action)) {
                return false;
            }
        }
        return true;
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
