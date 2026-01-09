<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\GiiHelper;
use Took\Yii2GiiMCP\Helpers\ValidationHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Generate Model Tool
 *
 * Generates Yii2 ActiveRecord model from database table.
 * Defaults to preview mode for safety.
 */
class GenerateModel extends AbstractTool
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
        return 'generate-model';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate Yii2 ActiveRecord model from a database table. ' .
            'Automatically detects Basic or Advanced Template and uses appropriate default namespace ' .
            '(common\\models for Advanced Template, app\\models for Basic Template). ' .
            'For Advanced Template projects, you can specify frontend\\models, backend\\models, api\\models, or common\\models. ' .
            'By default, runs in preview mode (no files written). ' .
            'Set preview=false to write files to disk. ' .
            'This tool will check for file conflicts and validate inputs before generation.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tableName' => [
                    'type' => 'string',
                    'description' => 'Database table name to generate model from',
                ],
                'modelClass' => [
                    'type' => 'string',
                    'description' => 'Model class name (optional, auto-generated from table name if not provided)',
                ],
                'namespace' => [
                    'type' => 'string',
                    'description' => 'Namespace for the model class. Defaults to common\\models for Advanced Template or app\\models for Basic Template. For Advanced Template, you can specify: common\\models (shared), frontend\\models, backend\\models, or api\\models depending on where the model should be used.',
                    'default' => 'common\\models',
                ],
                'baseClass' => [
                    'type' => 'string',
                    'description' => 'Base class for the model',
                    'default' => 'yii\\db\\ActiveRecord',
                ],
                'db' => [
                    'type' => 'string',
                    'description' => 'Database connection component ID',
                    'default' => 'db',
                ],
                'generateRelations' => [
                    'type' => 'string',
                    'description' => 'Generate relations (all, none)',
                    'enum' => ['all', 'none'],
                    'default' => 'all',
                ],
                'generateLabelsFromComments' => [
                    'type' => 'boolean',
                    'description' => 'Generate labels from database column comments',
                    'default' => true,
                ],
                'preview' => [
                    'type' => 'boolean',
                    'description' => 'Preview mode (true) or write files (false)',
                    'default' => true,
                ],
            ],
            'required' => ['tableName'],
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
            $tableName = $this->getRequiredParam($arguments, 'tableName');
            $preview = $this->getOptionalParam($arguments, 'preview', true);

            // Validate table name
            if (!ValidationHelper::validateTableName($tableName)) {
                return $this->createError(
                    ValidationHelper::getTableNameError($tableName)
                );
            }

            // Validate namespace if provided
            // Use dynamic default based on template type (common\models for Advanced, app\models for Basic)
            $defaultNamespace = $this->bootstrap->getDefaultModelNamespace();
            $namespace = $this->getOptionalParam($arguments, 'namespace', $defaultNamespace);
            if (!ValidationHelper::validateNamespace($namespace)) {
                return $this->createError(
                    ValidationHelper::getNamespaceError($namespace)
                );
            }

            // Validate model class if provided
            if (isset($arguments['modelClass'])) {
                $modelClass = $arguments['modelClass'];
                if (!ValidationHelper::validateClassName($modelClass)) {
                    return $this->createError(
                        ValidationHelper::getClassNameError($modelClass)
                    );
                }
            }

            // Ensure Yii2 is initialized
            if (!$this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Verify table exists
            $db = $this->bootstrap->getDb($this->getOptionalParam($arguments, 'db', 'db'));
            $tableSchema = $db->getSchema()->getTableSchema($tableName);

            if ($tableSchema === null) {
                return $this->createError(
                    "Table '{$tableName}' not found in database",
                    ['tableName' => $tableName, 'database' => $db->dsn]
                );
            }

            // Prepare options for Gii
            $options = [
                'modelClass' => $this->getOptionalParam($arguments, 'modelClass'),
                'namespace' => $namespace,
                'baseClass' => $this->getOptionalParam($arguments, 'baseClass', 'yii\\db\\ActiveRecord'),
                'db' => $this->getOptionalParam($arguments, 'db', 'db'),
                'generateRelations' => $this->getOptionalParam($arguments, 'generateRelations', 'all'),
                'generateLabelsFromComments' => $this->getOptionalParam($arguments, 'generateLabelsFromComments', true),
            ];

            // Remove null values
            $options = array_filter($options, fn($v) => $v !== null);

            // Generate or preview
            if ($preview) {
                $result = $this->giiHelper->previewModel($tableName, $options);
            } else {
                $result = $this->giiHelper->generateModel($tableName, $options);
            }

            // Format result
            return $this->formatGiiResult($result, $preview);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to generate model: ' . $e->getMessage(),
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
