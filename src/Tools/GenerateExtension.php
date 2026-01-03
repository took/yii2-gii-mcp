<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\GiiHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Generate Extension Tool
 *
 * Generates Yii2 extension scaffolding with composer.json, directory structure,
 * license, and documentation files. Defaults to preview mode for safety.
 */
class GenerateExtension extends AbstractTool
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
        return 'generate-extension';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate Yii2 extension scaffolding with complete project structure including composer.json, ' .
            'LICENSE, README.md, and src/ directory. By default, runs in preview mode (no files written). ' .
            'Set preview=false to write files to disk. Extensions are reusable packages that can be ' .
            'distributed via Composer and shared across multiple projects.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'vendorName' => [
                    'type' => 'string',
                    'description' => 'Vendor name for the extension (e.g., "yiisoft", "mycompany")',
                ],
                'packageName' => [
                    'type' => 'string',
                    'description' => 'Package name for the extension (e.g., "yii2-widget", "yii2-helper")',
                ],
                'namespace' => [
                    'type' => 'string',
                    'description' => 'Root namespace for the extension (optional)',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Extension type',
                    'enum' => ['yii2-extension', 'library'],
                    'default' => 'yii2-extension',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Extension title (optional)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Extension description (optional)',
                ],
                'keywords' => [
                    'type' => 'string',
                    'description' => 'Comma-separated keywords (optional)',
                ],
                'authorName' => [
                    'type' => 'string',
                    'description' => 'Author name (optional)',
                ],
                'authorEmail' => [
                    'type' => 'string',
                    'description' => 'Author email (optional)',
                ],
                'preview' => [
                    'type' => 'boolean',
                    'description' => 'Preview mode (true) or write files (false)',
                    'default' => true,
                ],
            ],
            'required' => ['vendorName', 'packageName'],
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
            $vendorName = $this->getRequiredParam($arguments, 'vendorName');
            $packageName = $this->getRequiredParam($arguments, 'packageName');
            $preview = $this->getOptionalParam($arguments, 'preview', true);

            // Validate vendor name
            if (!$this->validateName($vendorName)) {
                return $this->createError(
                    "Invalid vendor name '{$vendorName}'. Use lowercase alphanumeric with dashes."
                );
            }

            // Validate package name
            if (!$this->validateName($packageName)) {
                return $this->createError(
                    "Invalid package name '{$packageName}'. Use lowercase alphanumeric with dashes."
                );
            }

            // Ensure Yii2 is initialized
            if (!$this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Prepare options for Gii
            $options = [];

            $optionalFields = ['namespace', 'type', 'title', 'description', 'keywords', 'authorName', 'authorEmail'];
            foreach ($optionalFields as $field) {
                if (isset($arguments[$field])) {
                    $options[$field] = $arguments[$field];
                }
            }

            // Generate or preview
            if ($preview) {
                $result = $this->giiHelper->previewExtension($vendorName, $packageName, $options);
            } else {
                $result = $this->giiHelper->generateExtension($vendorName, $packageName, $options);
            }

            // Format result
            return $this->formatGiiResult($result, $preview);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to generate extension: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Validate name (vendor or package)
     *
     * @param string $name Name to validate
     * @return bool
     */
    private function validateName(string $name): bool
    {
        // Names should be lowercase, alphanumeric with optional dashes
        return preg_match('/^[a-z][a-z0-9-]*$/', $name) === 1;
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

        $output = "{$mode} {$fileCount} file(s) for extension:\n\n";

        if ($preview) {
            // Preview mode - show file details with content for key files
            $output .= "Extension Structure:\n\n";

            $composerFile = null;
            $readmeFile = null;
            $licenseFile = null;
            $otherFiles = [];

            foreach ($result['files'] as $file) {
                $relativePath = $file['relativePath'];
                if (str_ends_with($relativePath, 'composer.json')) {
                    $composerFile = $file;
                } elseif (str_ends_with($relativePath, 'README.md')) {
                    $readmeFile = $file;
                } elseif (str_contains($relativePath, 'LICENSE')) {
                    $licenseFile = $file;
                } else {
                    $otherFiles[] = $file;
                }
            }

            // Show composer.json with content
            if ($composerFile) {
                $output .= "Composer Configuration:\n";
                $output .= "File: {$composerFile['relativePath']}\n";
                $output .= "```json\n{$composerFile['content']}\n```\n\n";
            }

            // Show README.md (truncated if too long)
            if ($readmeFile) {
                $output .= "Documentation:\n";
                $output .= "File: {$readmeFile['relativePath']}\n";
                $readmeContent = $readmeFile['content'];
                if (strlen($readmeContent) > 500) {
                    $readmeContent = substr($readmeContent, 0, 500) . "\n... (truncated)";
                }
                $output .= "```markdown\n{$readmeContent}\n```\n\n";
            }

            // Show license file name
            if ($licenseFile) {
                $output .= "License: {$licenseFile['relativePath']}\n\n";
            }

            // Show other files
            if (!empty($otherFiles)) {
                $output .= "Additional Files:\n";
                foreach ($otherFiles as $file) {
                    $output .= "- {$file['relativePath']} ({$file['operation']})\n";
                }
                $output .= "\n";
            }

            $output .= "Note: Set preview=false to generate these files.\n";
            $output .= "Extension will be ready for Composer packaging and distribution.\n";
        } else {
            // Generation mode - show created files with grouping
            $output .= "Created: {$result['created']}\n";
            $output .= "Skipped: {$result['skipped']}\n";
            if ($result['errors'] > 0) {
                $output .= "Errors: {$result['errors']}\n";
            }

            // Group files by type
            $configFiles = [];
            $docFiles = [];
            $sourceFiles = [];
            $directories = [];

            foreach ($result['files'] as $file) {
                $path = $file['relativePath'] ?? $file['path'];
                $status = strtoupper($file['status']);

                if (str_ends_with($path, 'composer.json') || str_ends_with($path, '.gitignore')) {
                    $configFiles[] = "  [{$status}] {$path}";
                } elseif (str_ends_with($path, '.md') || str_contains($path, 'LICENSE')) {
                    $docFiles[] = "  [{$status}] {$path}";
                } elseif (str_ends_with($path, '/')) {
                    $directories[] = "  [{$status}] {$path}";
                } else {
                    $sourceFiles[] = "  [{$status}] {$path}";
                }
            }

            if (!empty($configFiles)) {
                $output .= "\nConfiguration Files:\n" . implode("\n", $configFiles) . "\n";
            }
            if (!empty($docFiles)) {
                $output .= "\nDocumentation:\n" . implode("\n", $docFiles) . "\n";
            }
            if (!empty($sourceFiles)) {
                $output .= "\nSource Files:\n" . implode("\n", $sourceFiles) . "\n";
            }
            if (!empty($directories)) {
                $output .= "\nDirectories:\n" . implode("\n", $directories) . "\n";
            }

            $output .= "\nNext Steps:\n";
            $output .= "1. Review and customize generated files\n";
            $output .= "2. Implement your extension functionality in src/\n";
            $output .= "3. Update README.md with usage instructions\n";
            $output .= "4. Publish to Packagist for distribution\n";
        }

        return $this->createResult($output);
    }
}
