<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\GiiHelper;
use Took\Yii2GiiMCP\Helpers\ValidationHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Generate Module Tool
 *
 * Generates Yii2 module structure with Module.php and directory scaffolding.
 * Defaults to preview mode for safety.
 */
class GenerateModule extends AbstractTool
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
        return 'generate-module';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate Yii2 module structure with Module.php class and standard directory layout ' .
            '(controllers/, models/, views/). By default, runs in preview mode (no files written). ' .
            'Set preview=false to write files to disk. Modules are self-contained sub-applications ' .
            'that can be reused across different projects.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'moduleID' => [
                    'type' => 'string',
                    'description' => 'Module ID (e.g., "admin", "api", "v1")',
                ],
                'moduleClass' => [
                    'type' => 'string',
                    'description' => 'Full module class name (optional, auto-generated from module ID)',
                ],
                'preview' => [
                    'type' => 'boolean',
                    'description' => 'Preview mode (true) or write files (false)',
                    'default' => true,
                ],
            ],
            'required' => ['moduleID'],
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
            $moduleID = $this->getRequiredParam($arguments, 'moduleID');
            $preview = $this->getOptionalParam($arguments, 'preview', true);

            // Validate module ID
            if (! $this->validateModuleID($moduleID)) {
                return $this->createError(
                    "Invalid module ID '{$moduleID}'. Use lowercase alphanumeric with dashes/underscores."
                );
            }

            // Validate module class if provided
            if (isset($arguments['moduleClass'])) {
                $moduleClass = $arguments['moduleClass'];
                if (! ValidationHelper::validateClassName($moduleClass)) {
                    return $this->createError(
                        ValidationHelper::getClassNameError($moduleClass)
                    );
                }
            }

            // Ensure Yii2 is initialized
            if (! $this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Prepare options for Gii
            $options = [];
            if (isset($arguments['moduleClass'])) {
                $options['moduleClass'] = $arguments['moduleClass'];
            }

            // Generate or preview
            if ($preview) {
                $result = $this->giiHelper->previewModule($moduleID, $options);
            } else {
                $result = $this->giiHelper->generateModule($moduleID, $options);
            }

            // Format result
            return $this->formatGiiResult($result, $preview);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to generate module: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Validate module ID
     *
     * @param string $moduleID Module ID
     * @return bool
     */
    private function validateModuleID(string $moduleID): bool
    {
        // Module ID should be lowercase, alphanumeric with optional dashes/underscores
        return preg_match('/^[a-z][a-z0-9_-]*$/', $moduleID) === 1;
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

        $output = "{$mode} {$fileCount} file(s) for module:\n\n";

        if ($preview) {
            // Preview mode - show file details with content for main files
            $output .= "Module Structure:\n\n";

            $mainFile = null;
            $otherFiles = [];

            foreach ($result['files'] as $file) {
                if (str_ends_with($file['relativePath'], 'Module.php')) {
                    $mainFile = $file;
                } else {
                    $otherFiles[] = $file;
                }
            }

            // Show main Module.php file with content
            if ($mainFile) {
                $output .= "Main Module Class:\n";
                $output .= "File: {$mainFile['relativePath']}\n";
                $output .= "```php\n{$mainFile['content']}\n```\n\n";
            }

            // Show other files (directories, views, etc.)
            if (! empty($otherFiles)) {
                $output .= "Additional Files:\n";
                foreach ($otherFiles as $file) {
                    $output .= "- {$file['relativePath']} ({$file['operation']})\n";
                }
            }

            $output .= "\nNote: Set preview=false to generate these files.\n";
            $output .= "Module will be created with: Module.php class and standard directory structure.\n";
        } else {
            // Generation mode - show created files with grouping
            $output .= "Created: {$result['created']}\n";
            $output .= "Skipped: {$result['skipped']}\n";
            if ($result['errors'] > 0) {
                $output .= "Errors: {$result['errors']}\n";
            }

            // Group files by type
            $moduleFiles = [];
            $directories = [];
            $otherFiles = [];

            foreach ($result['files'] as $file) {
                $path = $file['relativePath'] ?? $file['path'];
                $status = strtoupper($file['status']);

                if (str_ends_with($path, 'Module.php')) {
                    $moduleFiles[] = "  [{$status}] {$path}";
                } elseif (str_ends_with($path, '/')) {
                    $directories[] = "  [{$status}] {$path}";
                } else {
                    $otherFiles[] = "  [{$status}] {$path}";
                }
            }

            if (! empty($moduleFiles)) {
                $output .= "\nModule Class:\n" . implode("\n", $moduleFiles) . "\n";
            }
            if (! empty($directories)) {
                $output .= "\nDirectories:\n" . implode("\n", $directories) . "\n";
            }
            if (! empty($otherFiles)) {
                $output .= "\nOther Files:\n" . implode("\n", $otherFiles) . "\n";
            }
        }

        return $this->createResult($output);
    }
}
