<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\MigrationHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Create Migration Tool
 *
 * Tool to create new migration files with comprehensive options matching Yii2's migrate/create command.
 * Supports automatic field definitions, custom templates, namespaces, and migration types.
 *
 * FEATURES:
 * - Create table migrations with field definitions
 * - Add/drop column migrations
 * - Junction table migrations (for many-to-many relationships)
 * - Custom migration paths and namespaces
 * - Custom template support
 * - Table prefix handling
 * - Preview mode by default
 *
 * FIELD DEFINITION FORMAT:
 * - Basic: name:type
 * - With size: name:string(255)
 * - With modifiers: name:string:notNull:unique
 * - With default: status:integer:defaultValue(1)
 * - Complex: price:decimal(10,2):notNull:defaultValue(0.00)
 * - Enum: status:enum('draft','published','archived'):notNull:defaultValue('draft')
 *
 * SUPPORTED TYPES:
 * - string, text, smallint, integer, bigint, float, double, decimal
 * - datetime, timestamp, time, date, binary, boolean, money, json
 * - enum (e.g., enum('value1','value2','value3'))
 *
 * SUPPORTED MODIFIERS:
 * - notNull, unique, unsigned
 * - defaultValue(value)
 * - comment('text')
 * - check('condition')
 * - append('RAW SQL')
 */
class CreateMigration extends AbstractTool
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
        return 'create-migration';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Create a new migration file with comprehensive options. ' .
            'Supports table creation with field definitions, add/drop column migrations, junction tables, ' .
            'custom templates, namespaces, and migration paths. ' .
            'Field format: "name:type[:size][:modifier[:modifier...]]". ' .
            'Examples: "name:string(255):notNull", "email:string:notNull:unique", "status:integer:defaultValue(1)". ' .
            'Preview mode enabled by default.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Migration name (e.g., "create_users_table", "add_status_to_posts", "drop_old_users_table"). ' .
                        'Use snake_case. Will be prefixed with timestamp automatically.',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Field definitions for table creation or column addition. ' .
                        'Format: "name:type[:size][:modifier[:modifier...]]". ' .
                        'Examples: ["name:string(255):notNull", "email:string:notNull:unique", "status:integer:defaultValue(1)", ' .
                        '"price:decimal(10,2):notNull", "is_active:boolean:defaultValue(true)", "created_at:timestamp:notNull", ' .
                        '"status:enum(\'draft\',\'published\',\'archived\'):notNull:defaultValue(\'draft\')"]',
                ],
                'migrationType' => [
                    'type' => 'string',
                    'enum' => ['create', 'add', 'drop', 'junction', 'custom'],
                    'default' => 'custom',
                    'description' => 'Type of migration to generate. ' .
                        '"create": Create table migration (uses fields). ' .
                        '"add": Add columns to existing table (uses fields). ' .
                        '"drop": Drop table or columns migration. ' .
                        '"junction": Create junction table for many-to-many relationships. ' .
                        '"custom": Empty migration with safeUp/safeDown methods.',
                ],
                'tableName' => [
                    'type' => 'string',
                    'description' => 'Explicit table name for create/add/drop/junction types. ' .
                        'If not provided, will be extracted from migration name. ' .
                        'Example: "users", "posts", "user_posts"',
                ],
                'junctionTable1' => [
                    'type' => 'string',
                    'description' => 'First table for junction type (e.g., "users"). ' .
                        'Required when migrationType="junction". Creates junction table like "user_posts".',
                ],
                'junctionTable2' => [
                    'type' => 'string',
                    'description' => 'Second table for junction type (e.g., "posts"). ' .
                        'Required when migrationType="junction".',
                ],
                'migrationPath' => [
                    'type' => 'string',
                    'description' => 'Custom migration path (directory where migration file will be created). ' .
                        'Default: @app/migrations or configured in Yii2 application. ' .
                        'Example: "@app/modules/api/migrations", "/var/www/migrations"',
                ],
                'migrationNamespace' => [
                    'type' => 'string',
                    'description' => 'Migration namespace (for namespaced migrations). ' .
                        'Example: "app\\migrations", "app\\modules\\api\\migrations". ' .
                        'When set, migration will use namespace instead of being in global scope.',
                ],
                'templateFile' => [
                    'type' => 'string',
                    'description' => 'Custom template file path for migration generation. ' .
                        'Default: Yii2 built-in templates. ' .
                        'Use "@app/templates/migration.php" or absolute path. ' .
                        'Template should contain {ClassName}, {namespace}, etc. placeholders.',
                ],
                'useTablePrefix' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Whether to use table prefix from database configuration. ' .
                        'If true and tablePrefix is set in db config, it will be applied to table names. ' .
                        'Example: prefix="app_" and tableName="users" creates "app_users".',
                ],
                'addTimestamps' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Automatically add created_at and updated_at timestamp columns. ' .
                        'Only applicable for "create" migration type. ' .
                        'Adds: "created_at:timestamp:notNull", "updated_at:timestamp:notNull"',
                ],
                'addSoftDelete' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Automatically add deleted_at timestamp column for soft delete support. ' .
                        'Only applicable for "create" migration type. ' .
                        'Adds: "deleted_at:timestamp:null"',
                ],
                'addForeignKeys' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Automatically add foreign key constraints for fields ending with "_id". ' .
                        'Only applicable for "create" migration type. ' .
                        'Example: "user_id:integer:notNull" will add FK to users table.',
                ],
                'onDeleteCascade' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Use CASCADE for ON DELETE when creating foreign keys. ' .
                        'Only used when addForeignKeys=true. ' .
                        'Default is RESTRICT.',
                ],
                'comment' => [
                    'type' => 'string',
                    'description' => 'Table comment to add to the migration. ' .
                        'Example: "User accounts table" or "Stores user authentication data"',
                ],
                'indexes' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Index definitions for specific columns. ' .
                        'Format: array of column names (single or comma-separated for composite). ' .
                        'Examples: ["email"], ["username"], ["user_id,post_id"] for composite index. ' .
                        'Index names are auto-generated as idx-{table}-{column1}-{column2}.',
                ],
                'foreignKeys' => [
                    'type' => 'array',
                    'items' => ['type' => 'object'],
                    'description' => 'Explicit foreign key definitions with custom actions. ' .
                        'Each item: {field: "user_id", table: "users", column: "id" (optional, default: "id"), ' .
                        'onDelete: "CASCADE|RESTRICT|SET NULL|SET DEFAULT|NO ACTION" (optional, default: "RESTRICT"), ' .
                        'onUpdate: "CASCADE|RESTRICT|SET NULL|SET DEFAULT|NO ACTION" (optional, default: "RESTRICT")}. ' .
                        'Takes precedence over addForeignKeys auto-detection. ' .
                        'Example: [{field: "user_id", table: "users", onDelete: "CASCADE", onUpdate: "RESTRICT"}]',
                ],
                'preview' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Preview mode - show what would be created without actually creating the file. ' .
                        'Set to false to create the migration file.',
                ],
            ],
            'required' => ['name'],
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
            $name = $this->getRequiredParam($arguments, 'name');
            $fields = $this->getOptionalParam($arguments, 'fields', []);
            $migrationType = $this->getOptionalParam($arguments, 'migrationType', 'custom');
            $tableName = $this->getOptionalParam($arguments, 'tableName', null);
            $junctionTable1 = $this->getOptionalParam($arguments, 'junctionTable1', null);
            $junctionTable2 = $this->getOptionalParam($arguments, 'junctionTable2', null);
            $migrationPath = $this->getOptionalParam($arguments, 'migrationPath', null);
            $migrationNamespace = $this->getOptionalParam($arguments, 'migrationNamespace', null);
            $templateFile = $this->getOptionalParam($arguments, 'templateFile', null);
            $useTablePrefix = $this->getOptionalParam($arguments, 'useTablePrefix', true);
            $addTimestamps = $this->getOptionalParam($arguments, 'addTimestamps', false);
            $addSoftDelete = $this->getOptionalParam($arguments, 'addSoftDelete', false);
            $addForeignKeys = $this->getOptionalParam($arguments, 'addForeignKeys', false);
            $onDeleteCascade = $this->getOptionalParam($arguments, 'onDeleteCascade', false);
            $comment = $this->getOptionalParam($arguments, 'comment', null);
            $indexes = $this->getOptionalParam($arguments, 'indexes', []);
            $foreignKeys = $this->getOptionalParam($arguments, 'foreignKeys', []);
            $preview = $this->getOptionalParam($arguments, 'preview', true);

            // Validate migration type
            $validTypes = ['create', 'add', 'drop', 'junction', 'custom'];
            if (! in_array($migrationType, $validTypes, true)) {
                return $this->createError(
                    'Invalid migrationType. Must be one of: ' . implode(', ', $validTypes),
                    ['migrationType' => $migrationType]
                );
            }

            // Validate junction table parameters
            if ($migrationType === 'junction') {
                if (empty($junctionTable1) || empty($junctionTable2)) {
                    return $this->createError(
                        'Junction migration requires both junctionTable1 and junctionTable2 parameters',
                        [
                            'migrationType' => $migrationType,
                            'junctionTable1' => $junctionTable1,
                            'junctionTable2' => $junctionTable2,
                        ]
                    );
                }
            }

            // Validate migration name format
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                return $this->createError(
                    'Invalid migration name format. Use snake_case (lowercase letters, numbers, underscores)',
                    [
                        'name' => $name,
                        'hint' => 'Examples: "create_users_table", "add_status_to_posts", "drop_old_field"',
                    ]
                );
            }

            // Ensure Yii2 is initialized
            if (! $this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Augment fields based on options
            $fields = $this->augmentFields($fields, $migrationType, $addTimestamps, $addSoftDelete);

            // Extract or validate table name
            if ($tableName === null) {
                $tableName = $this->extractTableName($name, $migrationType);
            }

            // Build migration parameters
            $params = [
                'name' => $name,
                'fields' => $fields,
                'migrationType' => $migrationType,
                'tableName' => $tableName,
                'junctionTable1' => $junctionTable1,
                'junctionTable2' => $junctionTable2,
                'migrationPath' => $migrationPath,
                'migrationNamespace' => $migrationNamespace,
                'templateFile' => $templateFile,
                'useTablePrefix' => $useTablePrefix,
                'addForeignKeys' => $addForeignKeys,
                'onDeleteCascade' => $onDeleteCascade,
                'comment' => $comment,
                'indexes' => $indexes,
                'foreignKeys' => $foreignKeys,
            ];

            // Preview mode - show what would be created
            if ($preview) {
                return $this->createPreviewResult($params);
            }

            // Log operation (to stderr for debugging)
            if (getenv('DEBUG')) {
                fwrite(STDERR, "[MIGRATION] Creating migration: {$name}\n");
            }

            // Create migration file
            $result = $this->migrationHelper->createMigrationAdvanced($params);

            // Format output
            $output = $this->formatCreationResult($result);

            return $this->createResult($output);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to create migration: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Augment fields with automatic additions
     *
     * @param array $fields Base fields
     * @param string $migrationType Migration type
     * @param bool $addTimestamps Add timestamp columns
     * @param bool $addSoftDelete Add soft delete column
     * @return array Augmented fields
     */
    private function augmentFields(
        array  $fields,
        string $migrationType,
        bool   $addTimestamps,
        bool   $addSoftDelete
    ): array {
        // Only add automatic fields for create migrations
        if ($migrationType !== 'create') {
            return $fields;
        }

        $augmented = $fields;

        // Add timestamps
        if ($addTimestamps) {
            $augmented[] = 'created_at:timestamp:notNull';
            $augmented[] = 'updated_at:timestamp:notNull';
        }

        // Add soft delete
        if ($addSoftDelete) {
            $augmented[] = 'deleted_at:timestamp:null';
        }

        return $augmented;
    }

    /**
     * Extract table name from migration name
     *
     * @param string $name Migration name
     * @param string $migrationType Migration type
     * @return string|null Table name or null
     */
    private function extractTableName(string $name, string $migrationType): ?string
    {
        // Try to extract table name based on migration type
        switch ($migrationType) {
            case 'create':
                if (preg_match('/^create_(.+?)_table$/', $name, $matches)) {
                    return $matches[1];
                }

                break;

            case 'add':
                if (preg_match('/^add_.+?_to_(.+?)$/', $name, $matches)) {
                    return $matches[1];
                }

                break;

            case 'drop':
                if (preg_match('/^drop_(.+?)_table$/', $name, $matches)) {
                    return $matches[1];
                }
                if (preg_match('/^drop_.+?_from_(.+?)$/', $name, $matches)) {
                    return $matches[1];
                }

                break;
        }

        return null;
    }

    /**
     * Create preview result
     *
     * @param array $params Migration parameters
     * @return array Preview result
     */
    private function createPreviewResult(array $params): array
    {
        $output = "=== Migration Preview ===\n\n";
        $output .= "Migration Name: {$params['name']}\n";
        $output .= "Migration Type: {$params['migrationType']}\n";

        // Generate expected migration file name
        $timestamp = gmdate('ymd_His');
        $className = 'm' . $timestamp . '_' . $params['name'];
        $output .= "Generated Class: {$className}\n";

        if ($params['tableName'] !== null) {
            $output .= "Table Name: {$params['tableName']}\n";
        }

        if ($params['migrationType'] === 'junction') {
            $output .= "Junction Tables: {$params['junctionTable1']} <-> {$params['junctionTable2']}\n";
        }

        if ($params['migrationNamespace'] !== null) {
            $output .= "Namespace: {$params['migrationNamespace']}\n";
        }

        if ($params['migrationPath'] !== null) {
            $output .= "Path: {$params['migrationPath']}\n";
        }

        if (! empty($params['fields'])) {
            $output .= "\nFields:\n";
            foreach ($params['fields'] as $field) {
                $output .= "  - {$field}\n";
            }
        }

        if ($params['comment'] !== null) {
            $output .= "\nTable Comment: {$params['comment']}\n";
        }

        if (! empty($params['indexes'])) {
            $output .= "\nIndexes:\n";
            foreach ($params['indexes'] as $index) {
                $output .= "  - {$index}\n";
            }
        }

        if (! empty($params['foreignKeys'])) {
            $output .= "\nForeign Keys (Explicit):\n";
            foreach ($params['foreignKeys'] as $fk) {
                $field = $fk['field'] ?? '?';
                $table = $fk['table'] ?? '?';
                $onDelete = $fk['onDelete'] ?? 'RESTRICT';
                $onUpdate = $fk['onUpdate'] ?? 'RESTRICT';
                $output .= "  - {$field} -> {$table} (onDelete: {$onDelete}, onUpdate: {$onUpdate})\n";
            }
        }

        $output .= "\nOptions:\n";
        $output .= "  - Use Table Prefix: " . ($params['useTablePrefix'] ? 'Yes' : 'No') . "\n";
        $output .= "  - Add Foreign Keys (Auto): " . ($params['addForeignKeys'] ? 'Yes' : 'No') . "\n";
        if ($params['addForeignKeys'] && empty($params['foreignKeys'])) {
            $output .= "  - ON DELETE: " . ($params['onDeleteCascade'] ? 'CASCADE' : 'RESTRICT') . "\n";
        }

        if ($params['templateFile'] !== null) {
            $output .= "  - Custom Template: {$params['templateFile']}\n";
        }

        $output .= "\n⚠️  PREVIEW MODE - No files have been created\n\n";

        // Generate preview of migration content
        $output .= "--- Migration Content Preview ---\n\n";
        $output .= $this->generateMigrationPreview($params, $className);

        $output .= "\n\nTo create this migration:\n";
        $output .= "1. Review the generated code above\n";
        $output .= "2. Set preview=false\n";
        $output .= "3. The migration file will be created\n";

        return $this->createResult($output);
    }

    /**
     * Generate migration content preview
     *
     * @param array $params Migration parameters
     * @param string $className Class name
     * @return string Preview content
     */
    private function generateMigrationPreview(array $params, string $className): string
    {
        $content = "<?php\n\n";

        if ($params['migrationNamespace'] !== null) {
            $content .= "namespace {$params['migrationNamespace']};\n\n";
        }

        $content .= "use yii\\db\\Migration;\n\n";
        $content .= "class {$className} extends Migration\n";
        $content .= "{\n";
        $content .= "    public function safeUp()\n";
        $content .= "    {\n";

        // Generate content based on type
        switch ($params['migrationType']) {
            case 'create':
                $content .= "        \$this->createTable('{$params['tableName']}', [\n";
                $content .= "            'id' => \$this->primaryKey(),\n";
                foreach ($params['fields'] as $field) {
                    $content .= "            // {$field}\n";
                }
                $content .= "        ]);\n";

                break;

            case 'add':
                $content .= "        // Add columns to {$params['tableName']}\n";
                foreach ($params['fields'] as $field) {
                    $content .= "        // \$this->addColumn('{$params['tableName']}', ...); // {$field}\n";
                }

                break;

            case 'drop':
                $content .= "        // \$this->dropTable('{$params['tableName']}');\n";

                break;

            case 'junction':
                $content .= "        // Create junction table for {$params['junctionTable1']} <-> {$params['junctionTable2']}\n";

                break;

            default:
                $content .= "        // Add migration logic here\n";
        }

        $content .= "    }\n\n";
        $content .= "    public function safeDown()\n";
        $content .= "    {\n";
        $content .= "        // Add revert logic here\n";
        $content .= "    }\n";
        $content .= "}\n";

        return $content;
    }

    /**
     * Format creation result
     *
     * @param array $result Creation result
     * @return string Formatted output
     */
    private function formatCreationResult(array $result): string
    {
        $output = "=== Migration Created Successfully ===\n\n";
        $output .= "File: {$result['file']}\n";
        $output .= "Class: {$result['className']}\n";

        if (isset($result['namespace'])) {
            $output .= "Namespace: {$result['namespace']}\n";
        }

        if (isset($result['tableName'])) {
            $output .= "Table: {$result['tableName']}\n";
        }

        $output .= "\nNext Steps:\n";
        $output .= "1. Review the generated migration file\n";
        $output .= "2. Customize if needed\n";
        $output .= "3. Use execute-migration tool with preview=true, migrationName, and direction to see SQL\n";
        $output .= "4. Use execute-migration tool with operation='up' to apply\n";

        return $output;
    }
}
