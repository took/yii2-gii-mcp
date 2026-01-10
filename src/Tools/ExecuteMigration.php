<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\MigrationHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Execute Migration Tool
 *
 * Execute migration operations with mandatory human confirmation.
 * This tool can modify the database structure and requires explicit confirmation.
 *
 * SAFETY FEATURES:
 * - Always requires 'confirmation' parameter with exact value "yes"
 * - Destructive operations (down/fresh/redo) require additional 'destructiveConfirmation'
 * - Preview mode enabled by default
 * - All operations logged to stderr
 */
class ExecuteMigration extends AbstractTool
{
    private Yii2Bootstrap $bootstrap;
    private MigrationHelper $migrationHelper;

    /**
     * @param Yii2Bootstrap $bootstrap Yii2 bootstrap instance
     */
    public function __construct(Yii2Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
        $this->migrationHelper = new MigrationHelper($bootstrap);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'execute-migration';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Execute migration operations (up, down, create, redo, fresh) with mandatory confirmation. ' .
            'Can also preview SQL statements that would be executed by an existing migration without running it. ' .
            '⚠️  WARNING: This tool can modify the database structure. ' .
            'REQUIRED: confirmation="yes" for all operations. ' .
            'REQUIRED: destructiveConfirmation="I understand this will modify the database" for down/fresh/redo. ' .
            'Preview mode is enabled by default - set preview=false to execute. ' .
            'SQL Preview: Set preview=true with migrationName and direction to see SQL without executing.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'description' => 'Migration operation to execute',
                    'enum' => ['up', 'down', 'create', 'redo', 'fresh'],
                ],
                'migrationName' => [
                    'type' => 'string',
                    'description' => 'Migration name (required for down/redo; for create: new migration name). ' .
                        'For SQL preview: name of existing migration to preview.',
                ],
                'direction' => [
                    'type' => 'string',
                    'enum' => ['up', 'down'],
                    'default' => 'up',
                    'description' => 'Migration direction for SQL preview (up or down). ' .
                        'Only used when preview=true and migrationName is provided.',
                ],
                'migrationCount' => [
                    'type' => 'integer',
                    'description' => 'Number of migrations to apply/revert (for up/down)',
                    'default' => 1,
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Field definitions for create operation (e.g., ["name:string", "email:string:notNull:unique"])',
                ],
                'confirmation' => [
                    'type' => 'string',
                    'description' => 'REQUIRED: Must be exact string "yes" to execute',
                ],
                'destructiveConfirmation' => [
                    'type' => 'string',
                    'description' => 'REQUIRED for down/fresh/redo: Must be exact string "I understand this will modify the database"',
                ],
                'preview' => [
                    'type' => 'boolean',
                    'description' => 'Preview mode - show what would happen without executing (default: true)',
                    'default' => true,
                ],
            ],
            'required' => ['operation', 'confirmation'],
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
            $operation = $this->getRequiredParam($arguments, 'operation');
            $confirmation = $this->getRequiredParam($arguments, 'confirmation');
            $migrationName = $this->getOptionalParam($arguments, 'migrationName', null);
            $direction = $this->getOptionalParam($arguments, 'direction', 'up');
            $migrationCount = $this->getOptionalParam($arguments, 'migrationCount', 1);
            $fields = $this->getOptionalParam($arguments, 'fields', []);
            $destructiveConfirmation = $this->getOptionalParam($arguments, 'destructiveConfirmation', null);
            $preview = $this->getOptionalParam($arguments, 'preview', true);

            // Validate operation parameter
            $validOperations = ['up', 'down', 'create', 'redo', 'fresh'];
            if (! in_array($operation, $validOperations, true)) {
                return $this->createError(
                    'Invalid operation. Must be one of: ' . implode(', ', $validOperations),
                    ['operation' => $operation]
                );
            }

            // Validate direction parameter
            if (! in_array($direction, ['up', 'down'], true)) {
                return $this->createError(
                    'Invalid direction parameter. Must be one of: up, down',
                    ['direction' => $direction]
                );
            }

            // Validate confirmations
            $confirmationError = $this->validateConfirmations(
                $operation,
                $confirmation,
                $destructiveConfirmation
            );
            if ($confirmationError !== null) {
                return $confirmationError;
            }

            // Ensure Yii2 is initialized
            if (! $this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Validate operation-specific requirements
            $validationError = $this->validateOperationRequirements(
                $operation,
                $migrationName,
                $fields
            );
            if ($validationError !== null) {
                return $validationError;
            }

            // Preview mode - don't execute, just show what would happen
            if ($preview) {
                // SQL preview mode: if migrationName is provided, show SQL for that migration
                if ($migrationName !== null && $operation !== 'create') {
                    return $this->createSqlPreviewResult($migrationName, $direction);
                }

                // Operation preview mode: show what operation would do
                return $this->createPreviewResult($operation, $migrationName, $migrationCount, $fields);
            }

            // Log operation (to stderr for debugging)
            if (getenv('DEBUG')) {
                fwrite(STDERR, "[MIGRATION] Executing {$operation} operation\n");
            }

            // Execute migration
            $params = [
                'migrationName' => $migrationName,
                'migrationCount' => $migrationCount,
                'fields' => $fields,
            ];

            $result = $this->migrationHelper->executeMigration($operation, $params);

            // Format output
            $output = $this->formatExecutionResult($operation, $result);

            return $this->createResult($output);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to execute migration: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Validate confirmations
     *
     * @param string $operation Operation name
     * @param string $confirmation Main confirmation
     * @param string|null $destructiveConfirmation Destructive confirmation
     * @return array|null Error result or null if valid
     */
    private function validateConfirmations(
        string  $operation,
        string  $confirmation,
        ?string $destructiveConfirmation
    ): ?array {
        // Check main confirmation
        if ($confirmation !== 'yes') {
            return $this->createError(
                'Confirmation required: confirmation parameter must be exactly "yes"',
                [
                    'provided' => $confirmation,
                    'required' => 'yes',
                    'hint' => 'Set confirmation="yes" to execute this operation',
                ]
            );
        }

        // Check destructive confirmation for dangerous operations
        $destructiveOperations = ['down', 'fresh', 'redo'];
        if (in_array($operation, $destructiveOperations, true)) {
            $requiredDestructiveConfirmation = 'I understand this will modify the database';

            if ($destructiveConfirmation !== $requiredDestructiveConfirmation) {
                return $this->createError(
                    "Destructive operation '{$operation}' requires additional confirmation",
                    [
                        'provided' => $destructiveConfirmation,
                        'required' => $requiredDestructiveConfirmation,
                        'hint' => "Set destructiveConfirmation=\"{$requiredDestructiveConfirmation}\" to proceed",
                        'warning' => "Operation '{$operation}' will modify or revert database schema",
                    ]
                );
            }
        }

        return null;
    }

    /**
     * Validate operation-specific requirements
     *
     * @param string $operation Operation name
     * @param string|null $migrationName Migration name
     * @param array $fields Field definitions
     * @return array|null Error result or null if valid
     */
    private function validateOperationRequirements(
        string  $operation,
        ?string $migrationName,
        array   $fields
    ): ?array {
        switch ($operation) {
            case 'down':
            case 'redo':
                // These operations typically need a migration name or work on the last migration
                // Validation depends on Yii2 implementation
                break;

            case 'create':
                if (empty($migrationName)) {
                    return $this->createError(
                        'Migration name is required for create operation',
                        [
                            'operation' => $operation,
                            'hint' => 'Set migrationName parameter (e.g., "create_users_table")',
                        ]
                    );
                }

                break;

            case 'fresh':
                // No specific requirements
                break;
        }

        return null;
    }

    /**
     * Create SQL preview result for a migration
     *
     * @param string $migrationName Migration name
     * @param string $direction Direction (up/down)
     * @return array Preview result with SQL
     */
    private function createSqlPreviewResult(string $migrationName, string $direction): array
    {
        try {
            // Validate migration exists
            if (! $this->migrationHelper->validateMigrationName($migrationName)) {
                return $this->createError(
                    "Migration '{$migrationName}' not found",
                    [
                        'migrationName' => $migrationName,
                        'hint' => 'Use list-migrations tool to see available migrations',
                    ]
                );
            }

            // Get SQL preview
            $sql = $this->migrationHelper->previewMigrationSql($migrationName, $direction);

            // Format output
            $output = $this->formatSqlPreview($migrationName, $direction, $sql);

            return $this->createResult($output);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to preview migration SQL: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'migrationName' => $migrationName,
                    'direction' => $direction,
                ]
            );
        }
    }

    /**
     * Format SQL preview output
     *
     * @param string $migrationName Migration name
     * @param string $direction Direction (up/down)
     * @param string $sql SQL statements
     * @return string Formatted output
     */
    private function formatSqlPreview(string $migrationName, string $direction, string $sql): string
    {
        $output = "=== SQL Preview for Migration ===\n\n";
        $output .= "Migration: {$migrationName}\n";
        $output .= "Direction: {$direction}\n";
        $output .= "\n";
        $output .= "--- SQL Statements ---\n\n";
        $output .= $sql;
        $output .= "\n\n";
        $output .= "Note: This is a preview. No database changes have been made.\n";
        $output .= "To execute this migration, use this tool with preview=false and appropriate operation.\n";

        return $output;
    }

    /**
     * Create preview result
     *
     * @param string $operation Operation name
     * @param string|null $migrationName Migration name
     * @param int $migrationCount Migration count
     * @param array $fields Field definitions
     * @return array Preview result
     */
    private function createPreviewResult(
        string  $operation,
        ?string $migrationName,
        int     $migrationCount,
        array   $fields
    ): array {
        $output = "=== Migration Preview ===\n\n";
        $output .= "Operation: {$operation}\n";

        if ($migrationName !== null) {
            $output .= "Migration Name: {$migrationName}\n";
        }

        if ($operation === 'up' || $operation === 'down') {
            $output .= "Migration Count: {$migrationCount}\n";
        }

        if ($operation === 'create' && ! empty($fields)) {
            $output .= "Fields:\n";
            foreach ($fields as $field) {
                $output .= "  - {$field}\n";
            }
        }

        $output .= "\n";
        $output .= "⚠️  PREVIEW MODE - No changes have been made\n\n";

        switch ($operation) {
            case 'up':
                $output .= "This will apply {$migrationCount} pending migration(s) to the database.\n";

                break;
            case 'down':
                $output .= "This will revert {$migrationCount} applied migration(s) from the database.\n";
                $output .= "⚠️  WARNING: This is a destructive operation that will modify the database schema.\n";

                break;
            case 'create':
                $output .= "This will create a new migration file: {$migrationName}\n";
                if (! empty($fields)) {
                    $output .= "The migration will include field definitions for table creation.\n";
                }

                break;
            case 'redo':
                $output .= "This will revert and re-apply {$migrationCount} migration(s).\n";
                $output .= "⚠️  WARNING: This is a destructive operation that will modify the database schema.\n";

                break;
            case 'fresh':
                $output .= "This will drop all tables and re-apply all migrations.\n";
                $output .= "⚠️  DANGER: This will DELETE ALL DATA in the database!\n";

                break;
        }

        $output .= "\n";
        $output .= "To execute this operation:\n";
        $output .= "1. Ensure you have backups\n";
        $output .= "2. Set preview=false\n";
        $output .= "3. Provide confirmation=\"yes\"\n";

        if (in_array($operation, ['down', 'fresh', 'redo'], true)) {
            $output .= "4. Provide destructiveConfirmation=\"I understand this will modify the database\"\n";
        }

        return $this->createResult($output);
    }

    /**
     * Format execution result
     *
     * @param string $operation Operation name
     * @param array $result Execution result
     * @return string Formatted output
     */
    private function formatExecutionResult(string $operation, array $result): string
    {
        $output = "=== Migration Execution Result ===\n\n";
        $output .= "Operation: {$operation}\n";
        $output .= "Status: Completed\n\n";

        // Add operation-specific details
        if (isset($result['file'])) {
            $output .= "Created File: {$result['file']}\n";
            $output .= "Migration Name: {$result['migration_name']}\n";
        }

        if (isset($result['migrations_applied'])) {
            $output .= "Migrations Applied: {$result['migrations_applied']}\n";
        }

        if (isset($result['migrations_reverted'])) {
            $output .= "Migrations Reverted: {$result['migrations_reverted']}\n";
        }

        if (isset($result['output']) && ! empty($result['output'])) {
            $output .= "\nOutput:\n{$result['output']}\n";
        }

        if (isset($result['warning'])) {
            $output .= "\n⚠️  WARNING: {$result['warning']}\n";
        }

        return $output;
    }
}
