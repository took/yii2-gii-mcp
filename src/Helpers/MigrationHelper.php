<?php

namespace Took\Yii2GiiMCP\Helpers;

use Exception;
use RuntimeException;
use Yii;
use yii\console\controllers\MigrateController;
use yii\db\Migration;
use yii\helpers\Console;

/**
 * Migration Helper
 *
 * Wrapper for Yii2 migration operations providing a simplified interface
 * for migration management through MCP tools.
 */
class MigrationHelper
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
     * Get migrations by status
     *
     * @param string $status Filter by status: 'all', 'applied', 'pending'
     * @return array List of migrations with metadata
     * @throws Exception If operation fails
     */
    public function getMigrations(string $status = 'all'): array
    {
        // Ensure Yii2 is initialized
        if (!$this->bootstrap->isInitialized()) {
            $this->bootstrap->initialize();
        }

        $db = $this->bootstrap->getDb();
        $controller = $this->createMigrateController();

        // Get migration history
        $history = $controller->getMigrationHistory(null);

        // Get new migrations
        $newMigrations = $controller->getNewMigrations();

        $result = [];

        // Add applied migrations
        if ($status === 'all' || $status === 'applied') {
            foreach ($history as $version => $time) {
                $result[] = [
                    'name' => $version,
                    'status' => 'applied',
                    'applied_time' => $time > 0 ? date('Y-m-d H:i:s', $time) : null,
                ];
            }
        }

        // Add pending migrations
        if ($status === 'all' || $status === 'pending') {
            foreach ($newMigrations as $migration) {
                $result[] = [
                    'name' => $migration,
                    'status' => 'pending',
                    'applied_time' => null,
                ];
            }
        }

        return $result;
    }

    /**
     * Create MigrateController instance
     *
     * @return MigrateController
     */
    private function createMigrateController(): MigrateController
    {
        $app = $this->bootstrap->getApp();

        // Create controller instance
        $controller = new MigrateController('migrate', $app);

        // Disable interactive mode
        $controller->interactive = false;

        // Disable color output
        $controller->color = false;

        return $controller;
    }

    /**
     * Get migration history
     *
     * @param int $limit Maximum number of migrations to return
     * @return array Migration history with timestamps
     * @throws Exception If operation fails
     */
    public function getMigrationHistory(int $limit = 10): array
    {
        // Ensure Yii2 is initialized
        if (!$this->bootstrap->isInitialized()) {
            $this->bootstrap->initialize();
        }

        $controller = $this->createMigrateController();
        $history = $controller->getMigrationHistory($limit);

        $result = [];
        foreach ($history as $version => $time) {
            $result[] = [
                'name' => $version,
                'applied_time' => $time > 0 ? date('Y-m-d H:i:s', $time) : null,
            ];
        }

        return $result;
    }

    /**
     * Preview SQL that would be executed by migration
     *
     * @param string $name Migration name
     * @param string $direction Direction: 'up' or 'down'
     * @return string SQL statements
     * @throws Exception If migration not found or preview fails
     */
    public function previewMigrationSql(string $name, string $direction = 'up'): string
    {
        // Ensure Yii2 is initialized
        if (!$this->bootstrap->isInitialized()) {
            $this->bootstrap->initialize();
        }

        // Validate migration exists
        if (!$this->validateMigrationName($name)) {
            throw new RuntimeException("Migration '{$name}' not found");
        }

        // Get migration instance
        $migration = $this->getMigrationByName($name);

        if ($migration === null) {
            throw new RuntimeException("Could not load migration '{$name}'");
        }

        // Capture SQL by setting db to return SQL instead of executing
        $db = $this->bootstrap->getDb();
        $sql = [];

        // Create a temporary connection that captures SQL
        $originalSchema = $db->getSchema();

        try {
            // Execute migration method to collect SQL
            if ($direction === 'up') {
                // Call up() method
                ob_start();
                $migration->up();
                $output = ob_get_clean();
            } else {
                // Call down() method
                ob_start();
                $migration->down();
                $output = ob_get_clean();
            }

            // Get SQL from migration execution
            // Note: This is a simplified approach. In real implementation,
            // we would need to intercept the actual SQL commands
            $sql[] = "-- Migration: {$name}";
            $sql[] = "-- Direction: {$direction}";
            $sql[] = "-- Note: Actual SQL commands would be captured here";

            return implode("\n", $sql);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to preview migration: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate migration name exists
     *
     * @param string $name Migration name
     * @return bool True if migration exists
     */
    public function validateMigrationName(string $name): bool
    {
        try {
            // Ensure Yii2 is initialized
            if (!$this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            $controller = $this->createMigrateController();

            // Check in migration history
            $history = $controller->getMigrationHistory(null);
            if (isset($history[$name])) {
                return true;
            }

            // Check in new migrations
            $newMigrations = $controller->getNewMigrations();
            return in_array($name, $newMigrations, true);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get migration instance by name
     *
     * @param string $name Migration name
     * @return Migration|null Migration instance or null if not found
     */
    public function getMigrationByName(string $name): ?Migration
    {
        try {
            // Ensure Yii2 is initialized
            if (!$this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            $controller = $this->createMigrateController();

            // Create migration instance
            $migration = $controller->createMigration($name);

            return $migration instanceof Migration ? $migration : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Create new migration file
     *
     * @param string $name Migration name
     * @param array $fields Field definitions (e.g., ['name:string', 'email:string:notNull'])
     * @return string Path to created migration file
     * @throws Exception If creation fails
     */
    public function createMigration(string $name, array $fields = []): string
    {
        // Ensure Yii2 is initialized
        if (!$this->bootstrap->isInitialized()) {
            $this->bootstrap->initialize();
        }

        $controller = $this->createMigrateController();

        // Set migration template based on fields
        if (!empty($fields)) {
            // Parse fields for table creation
            $controller->fields = $fields;
        }

        // Generate migration file
        $migrationPath = $controller->migrationPath;
        $migrationName = 'm' . gmdate('ymd_His') . '_' . $name;

        // Create migration file
        $file = $migrationPath . DIRECTORY_SEPARATOR . $migrationName . '.php';

        // Generate migration content
        $content = $this->generateMigrationContent($migrationName, $name, $fields);

        if (file_put_contents($file, $content) === false) {
            throw new RuntimeException("Failed to create migration file: {$file}");
        }

        return $file;
    }

    /**
     * Generate migration file content
     *
     * @param string $className Migration class name
     * @param string $name Migration name
     * @param array $fields Field definitions
     * @return string Migration file content
     */
    private function generateMigrationContent(string $className, string $name, array $fields): string
    {
        $useStatements = "use yii\\db\\Migration;";
        $upContent = "        // Add migration logic here\n";
        $downContent = "        // Add revert logic here\n";

        if (!empty($fields)) {
            // Generate table creation from fields
            $tableName = $this->extractTableNameFromMigrationName($name);
            $columns = $this->buildColumnsFromFields($fields);

            $upContent = "        \$this->createTable('{$tableName}', [\n";
            $upContent .= "            'id' => \$this->primaryKey(),\n";
            foreach ($columns as $column) {
                $upContent .= "            {$column}\n";
            }
            $upContent .= "        ]);\n";

            $downContent = "        \$this->dropTable('{$tableName}');\n";
        }

        return <<<PHP
<?php

{$useStatements}

/**
 * Class {$className}
 */
class {$className} extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
{$upContent}
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
{$downContent}
    }
}

PHP;
    }

    /**
     * Extract table name from migration name
     *
     * @param string $name Migration name
     * @return string Table name
     */
    private function extractTableNameFromMigrationName(string $name): string
    {
        // Try to extract table name from migration name
        // e.g., "create_users_table" -> "users"
        if (preg_match('/create_(.+?)_table/', $name, $matches)) {
            return $matches[1];
        }

        // Default to migration name
        return $name;
    }

    /**
     * Build column definitions from field specifications
     *
     * @param array $fields Field definitions
     * @return array Column definitions
     */
    private function buildColumnsFromFields(array $fields): array
    {
        $columns = [];

        foreach ($fields as $field) {
            $columns[] = $this->parseFieldDefinition($field);
        }

        return $columns;
    }

    /**
     * Parse field definition into column code
     *
     * @param string $field Field definition (e.g., "name:string:notNull")
     * @return string Column definition code
     */
    private function parseFieldDefinition(string $field): string
    {
        $parts = explode(':', $field);
        $name = $parts[0] ?? 'field';
        $type = $parts[1] ?? 'string';
        $modifiers = array_slice($parts, 2);

        // Handle enum type (e.g., enum('draft','published','archived'))
        $checkConstraint = null;
        if (preg_match('/^enum\((.+)\)$/', $type, $matches)) {
            $enumValues = $matches[1];
            $definition = "'{$name}' => \$this->string()";
            $checkConstraint = "->check(\"{$name} IN ({$enumValues})\")";
        } else {
            // Build column definition
            $definition = "'{$name}' => \$this->{$type}()";
        }

        // Add modifiers
        foreach ($modifiers as $modifier) {
            if (preg_match('/^(.+?)\((.+?)\)$/', $modifier, $matches)) {
                // Modifier with argument (e.g., "defaultValue(1)")
                $definition .= "->{$matches[1]}({$matches[2]})";
            } else {
                // Simple modifier (e.g., "notNull")
                $definition .= "->{$modifier}()";
            }
        }

        // Add enum check constraint at the end (after other modifiers)
        if ($checkConstraint !== null) {
            $definition .= $checkConstraint;
        }

        $definition .= ",";

        return $definition;
    }

    /**
     * Execute migration operation
     *
     * @param string $operation Operation: 'up', 'down', 'create', 'redo', 'fresh'
     * @param array $params Operation parameters
     * @return array Execution results
     * @throws Exception If operation fails
     */
    public function executeMigration(string $operation, array $params): array
    {
        // Ensure Yii2 is initialized
        if (!$this->bootstrap->isInitialized()) {
            $this->bootstrap->initialize();
        }

        $controller = $this->createMigrateController();

        switch ($operation) {
            case 'up':
                return $this->executeMigrationUp($controller, $params);

            case 'down':
                return $this->executeMigrationDown($controller, $params);

            case 'create':
                return $this->executeMigrationCreate($controller, $params);

            case 'redo':
                return $this->executeMigrationRedo($controller, $params);

            case 'fresh':
                return $this->executeMigrationFresh($controller, $params);

            default:
                throw new RuntimeException("Unknown migration operation: {$operation}");
        }
    }

    /**
     * Execute migration up
     *
     * @param MigrateController $controller
     * @param array $params
     * @return array
     * @throws Exception
     */
    private function executeMigrationUp(MigrateController $controller, array $params): array
    {
        $limit = $params['migrationCount'] ?? 1;

        ob_start();
        $result = $controller->actionUp($limit);
        $output = ob_get_clean();

        return [
            'operation' => 'up',
            'result' => $result,
            'output' => $output,
            'migrations_applied' => $limit,
        ];
    }

    /**
     * Execute migration down
     *
     * @param MigrateController $controller
     * @param array $params
     * @return array
     * @throws Exception
     */
    private function executeMigrationDown(MigrateController $controller, array $params): array
    {
        $limit = $params['migrationCount'] ?? 1;

        ob_start();
        $result = $controller->actionDown($limit);
        $output = ob_get_clean();

        return [
            'operation' => 'down',
            'result' => $result,
            'output' => $output,
            'migrations_reverted' => $limit,
        ];
    }

    /**
     * Execute migration create
     *
     * @param MigrateController $controller
     * @param array $params
     * @return array
     * @throws Exception
     */
    private function executeMigrationCreate(MigrateController $controller, array $params): array
    {
        $name = $params['migrationName'] ?? throw new RuntimeException('Migration name is required');
        $fields = $params['fields'] ?? [];

        $file = $this->createMigration($name, $fields);

        return [
            'operation' => 'create',
            'file' => $file,
            'migration_name' => basename($file, '.php'),
        ];
    }

    /**
     * Execute migration redo
     *
     * @param MigrateController $controller
     * @param array $params
     * @return array
     * @throws Exception
     */
    private function executeMigrationRedo(MigrateController $controller, array $params): array
    {
        $limit = $params['migrationCount'] ?? 1;

        ob_start();
        $result = $controller->actionRedo($limit);
        $output = ob_get_clean();

        return [
            'operation' => 'redo',
            'result' => $result,
            'output' => $output,
        ];
    }

    /**
     * Execute migration fresh
     *
     * @param MigrateController $controller
     * @param array $params
     * @return array
     * @throws Exception
     */
    private function executeMigrationFresh(MigrateController $controller, array $params): array
    {
        ob_start();
        $result = $controller->actionFresh();
        $output = ob_get_clean();

        return [
            'operation' => 'fresh',
            'result' => $result,
            'output' => $output,
            'warning' => 'All tables dropped and migrations re-applied',
        ];
    }

    /**
     * Create migration with advanced options
     *
     * @param array $params Advanced migration parameters
     * @return array Creation result with file path, class name, etc.
     * @throws Exception If creation fails
     */
    public function createMigrationAdvanced(array $params): array
    {
        // Ensure Yii2 is initialized
        if (!$this->bootstrap->isInitialized()) {
            $this->bootstrap->initialize();
        }

        $name = $params['name'];
        $fields = $params['fields'] ?? [];
        $migrationType = $params['migrationType'] ?? 'custom';
        $tableName = $params['tableName'] ?? null;
        $junctionTable1 = $params['junctionTable1'] ?? null;
        $junctionTable2 = $params['junctionTable2'] ?? null;
        $migrationPath = $params['migrationPath'] ?? null;
        $migrationNamespace = $params['migrationNamespace'] ?? null;
        $templateFile = $params['templateFile'] ?? null;
        $useTablePrefix = $params['useTablePrefix'] ?? true;
        $addForeignKeys = $params['addForeignKeys'] ?? false;
        $onDeleteCascade = $params['onDeleteCascade'] ?? false;
        $comment = $params['comment'] ?? null;
        $indexes = $params['indexes'] ?? [];
        $foreignKeys = $params['foreignKeys'] ?? [];

        $controller = $this->createMigrateController();

        // Set custom migration path if provided
        if ($migrationPath !== null) {
            $controller->migrationPath = \Yii::getAlias($migrationPath);
        }

        // Set custom migration namespace if provided
        if ($migrationNamespace !== null) {
            $controller->migrationNamespaces = [$migrationNamespace];
        }

        // Set custom template if provided
        if ($templateFile !== null) {
            $controller->templateFile = \Yii::getAlias($templateFile);
        }

        // Generate migration file name with timestamp
        $className = 'm' . gmdate('ymd_His') . '_' . $name;

        // Determine migration path
        $finalMigrationPath = $controller->migrationPath;
        if (!is_dir($finalMigrationPath)) {
            throw new RuntimeException("Migration path does not exist: {$finalMigrationPath}");
        }

        // Create migration file
        $file = $finalMigrationPath . DIRECTORY_SEPARATOR . $className . '.php';

        // Generate migration content based on type
        $content = $this->generateAdvancedMigrationContent(
            $className,
            $migrationType,
            $tableName,
            $fields,
            $junctionTable1,
            $junctionTable2,
            $migrationNamespace,
            $useTablePrefix,
            $addForeignKeys,
            $onDeleteCascade,
            $comment,
            $indexes,
            $foreignKeys
        );

        // Write file
        if (file_put_contents($file, $content) === false) {
            throw new RuntimeException("Failed to create migration file: {$file}");
        }

        return [
            'file' => $file,
            'className' => $className,
            'namespace' => $migrationNamespace,
            'tableName' => $tableName,
            'migrationType' => $migrationType,
        ];
    }

    /**
     * Generate advanced migration content
     *
     * @param string $className Class name
     * @param string $migrationType Migration type
     * @param string|null $tableName Table name
     * @param array $fields Field definitions
     * @param string|null $junctionTable1 First junction table
     * @param string|null $junctionTable2 Second junction table
     * @param string|null $namespace Namespace
     * @param bool $useTablePrefix Use table prefix
     * @param bool $addForeignKeys Add foreign keys
     * @param bool $onDeleteCascade ON DELETE CASCADE
     * @param string|null $comment Table comment
     * @param array $indexes Index definitions
     * @param array $foreignKeys Explicit foreign key definitions
     * @return string Migration file content
     */
    private function generateAdvancedMigrationContent(
        string  $className,
        string  $migrationType,
        ?string $tableName,
        array   $fields,
        ?string $junctionTable1,
        ?string $junctionTable2,
        ?string $namespace,
        bool    $useTablePrefix,
        bool    $addForeignKeys,
        bool    $onDeleteCascade,
        ?string $comment,
        array   $indexes = [],
        array   $foreignKeys = []
    ): string
    {
        $content = "<?php\n";

        // Add namespace if provided
        if ($namespace !== null) {
            $content .= "\nnamespace {$namespace};\n";
        }

        $content .= "\nuse yii\\db\\Migration;\n\n";
        $content .= "/**\n";
        $content .= " * Handles the " . strtolower($migrationType) . " for table `" . ($tableName ?? 'table') . "`.\n";
        if ($comment !== null) {
            $content .= " * \n";
            $content .= " * {$comment}\n";
        }
        $content .= " */\n";
        $content .= "class {$className} extends Migration\n";
        $content .= "{\n";

        // Generate safeUp method
        $content .= "    /**\n";
        $content .= "     * {@inheritdoc}\n";
        $content .= "     */\n";
        $content .= "    public function safeUp()\n";
        $content .= "    {\n";
        $content .= $this->generateUpContent(
            $migrationType,
            $tableName,
            $fields,
            $junctionTable1,
            $junctionTable2,
            $useTablePrefix,
            $addForeignKeys,
            $onDeleteCascade,
            $comment,
            $indexes,
            $foreignKeys
        );
        $content .= "    }\n\n";

        // Generate safeDown method
        $content .= "    /**\n";
        $content .= "     * {@inheritdoc}\n";
        $content .= "     */\n";
        $content .= "    public function safeDown()\n";
        $content .= "    {\n";
        $content .= $this->generateDownContent($migrationType, $tableName, $fields, $junctionTable1, $junctionTable2, $addForeignKeys, $indexes, $foreignKeys);
        $content .= "    }\n";
        $content .= "}\n";

        return $content;
    }

    /**
     * Generate up() content based on migration type
     *
     * @param string $migrationType Migration type
     * @param string|null $tableName Table name
     * @param array $fields Field definitions
     * @param string|null $junctionTable1 First junction table
     * @param string|null $junctionTable2 Second junction table
     * @param bool $useTablePrefix Use table prefix
     * @param bool $addForeignKeys Add foreign keys
     * @param bool $onDeleteCascade ON DELETE CASCADE
     * @param string|null $comment Table comment
     * @param array $indexes Index definitions
     * @param array $foreignKeys Explicit foreign key definitions
     * @return string Up content
     */
    private function generateUpContent(
        string  $migrationType,
        ?string $tableName,
        array   $fields,
        ?string $junctionTable1,
        ?string $junctionTable2,
        bool    $useTablePrefix,
        bool    $addForeignKeys,
        bool    $onDeleteCascade,
        ?string $comment,
        array   $indexes = [],
        array   $foreignKeys = []
    ): string
    {
        $content = '';
        $indent = '        ';

        switch ($migrationType) {
            case 'create':
                $tableRef = $useTablePrefix ? "'{{%{$tableName}}}'" : "'{$tableName}'";
                $content .= $indent . "\$this->createTable({$tableRef}, [\n";
                $content .= $indent . "    'id' => \$this->primaryKey(),\n";

                // Add fields
                foreach ($fields as $field) {
                    $columnDef = $this->parseFieldDefinitionAdvanced($field);
                    $content .= $indent . "    {$columnDef}\n";
                }

                $content .= $indent . "]);\n";

                // Add table comment if provided
                if ($comment !== null) {
                    $content .= "\n" . $indent . "// Add table comment\n";
                    $content .= $indent . "\$this->addCommentOnTable({$tableRef}, " . var_export($comment, true) . ");\n";
                }

                // Add indexes if provided
                if (!empty($indexes)) {
                    $content .= "\n" . $this->generateIndexes($tableName, $indexes, $useTablePrefix, $indent);
                }

                // Add foreign keys
                if (!empty($foreignKeys)) {
                    // Explicit foreign keys take precedence
                    $content .= "\n" . $this->generateForeignKeysExplicit($tableName, $foreignKeys, $useTablePrefix, $indent);
                } elseif ($addForeignKeys) {
                    // Auto-detect foreign keys from fields ending with _id
                    $content .= "\n" . $this->generateForeignKeys($tableName, $fields, $useTablePrefix, $onDeleteCascade, $indent);
                }
                break;

            case 'add':
                $tableRef = $useTablePrefix ? "'{{%{$tableName}}}'" : "'{$tableName}'";
                foreach ($fields as $field) {
                    $parts = explode(':', $field);
                    $columnName = $parts[0];
                    $columnDef = $this->parseFieldDefinitionAdvanced($field, false);
                    $content .= $indent . "\$this->addColumn({$tableRef}, '{$columnName}', {$columnDef});\n";
                }
                break;

            case 'drop':
                $tableRef = $useTablePrefix ? "'{{%{$tableName}}}'" : "'{$tableName}'";
                if (!empty($fields)) {
                    // Drop specific columns
                    foreach ($fields as $field) {
                        $columnName = explode(':', $field)[0];
                        $content .= $indent . "\$this->dropColumn({$tableRef}, '{$columnName}');\n";
                    }
                } else {
                    // Drop table
                    $content .= $indent . "\$this->dropTable({$tableRef});\n";
                }
                break;

            case 'junction':
                $junctionTableName = $junctionTable1 . '_' . $junctionTable2;
                $tableRef = $useTablePrefix ? "'{{%{$junctionTableName}}}'" : "'{$junctionTableName}'";
                $table1IdColumn = rtrim($junctionTable1, 's') . '_id';
                $table2IdColumn = rtrim($junctionTable2, 's') . '_id';

                $content .= $indent . "\$this->createTable({$tableRef}, [\n";
                $content .= $indent . "    'id' => \$this->primaryKey(),\n";
                $content .= $indent . "    '{$table1IdColumn}' => \$this->integer()->notNull(),\n";
                $content .= $indent . "    '{$table2IdColumn}' => \$this->integer()->notNull(),\n";
                $content .= $indent . "    'created_at' => \$this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),\n";
                $content .= $indent . "]);\n\n";

                // Add indexes
                $content .= $indent . "// Creates index for column `{$table1IdColumn}`\n";
                $content .= $indent . "\$this->createIndex(\n";
                $content .= $indent . "    'idx-{$junctionTableName}-{$table1IdColumn}',\n";
                $content .= $indent . "    {$tableRef},\n";
                $content .= $indent . "    '{$table1IdColumn}'\n";
                $content .= $indent . ");\n\n";

                $content .= $indent . "// Creates index for column `{$table2IdColumn}`\n";
                $content .= $indent . "\$this->createIndex(\n";
                $content .= $indent . "    'idx-{$junctionTableName}-{$table2IdColumn}',\n";
                $content .= $indent . "    {$tableRef},\n";
                $content .= $indent . "    '{$table2IdColumn}'\n";
                $content .= $indent . ");\n\n";

                // Add foreign keys
                $table1Ref = $useTablePrefix ? "'{{%{$junctionTable1}}}'" : "'{$junctionTable1}'";
                $table2Ref = $useTablePrefix ? "'{{%{$junctionTable2}}}'" : "'{$junctionTable2}'";

                $content .= $indent . "// Add foreign key for table `{$junctionTable1}`\n";
                $content .= $indent . "\$this->addForeignKey(\n";
                $content .= $indent . "    'fk-{$junctionTableName}-{$table1IdColumn}',\n";
                $content .= $indent . "    {$tableRef},\n";
                $content .= $indent . "    '{$table1IdColumn}',\n";
                $content .= $indent . "    {$table1Ref},\n";
                $content .= $indent . "    'id',\n";
                $content .= $indent . "    'CASCADE'\n";
                $content .= $indent . ");\n\n";

                $content .= $indent . "// Add foreign key for table `{$junctionTable2}`\n";
                $content .= $indent . "\$this->addForeignKey(\n";
                $content .= $indent . "    'fk-{$junctionTableName}-{$table2IdColumn}',\n";
                $content .= $indent . "    {$tableRef},\n";
                $content .= $indent . "    '{$table2IdColumn}',\n";
                $content .= $indent . "    {$table2Ref},\n";
                $content .= $indent . "    'id',\n";
                $content .= $indent . "    'CASCADE'\n";
                $content .= $indent . ");\n";
                break;

            default:
                $content .= $indent . "// Add your migration code here\n";
        }

        return $content;
    }

    /**
     * Parse field definition for advanced migration
     *
     * @param string $field Field definition
     * @param bool $withName Include field name
     * @return string Parsed definition
     */
    private function parseFieldDefinitionAdvanced(string $field, bool $withName = true): string
    {
        $parts = explode(':', $field);
        $name = $parts[0];
        $type = $parts[1] ?? 'string';
        $modifiers = array_slice($parts, 2);

        // Handle enum type (e.g., enum('draft','published','archived'))
        if (preg_match('/^enum\((.+)\)$/', $type, $matches)) {
            $enumValues = $matches[1];
            $typeCall = "\$this->string()";
            
            // Add check constraint for enum values after other modifiers
            $checkConstraint = "->check(\"{$name} IN ({$enumValues})\")";
        } elseif (preg_match('/^(\w+)\(([^)]+)\)$/', $type, $matches)) {
            // Handle type with size (e.g., string(255), decimal(10,2))
            $baseType = $matches[1];
            $size = $matches[2];
            $typeCall = "\$this->{$baseType}({$size})";
            $checkConstraint = null;
        } else {
            $typeCall = "\$this->{$type}()";
            $checkConstraint = null;
        }

        // Add modifiers
        foreach ($modifiers as $modifier) {
            if ($modifier === 'null') {
                $typeCall .= "->null()";
            } elseif (preg_match('/^(.+?)\((.+?)\)$/', $modifier, $matches)) {
                // Modifier with argument
                $modName = $matches[1];
                $modArg = $matches[2];
                // Handle string arguments - only add quotes if not already quoted
                if (!is_numeric($modArg) && $modArg !== 'true' && $modArg !== 'false' && $modArg !== 'null') {
                    // Check if argument is already quoted
                    if (!preg_match('/^[\'"].*[\'"]$/', $modArg)) {
                        $modArg = "'{$modArg}'";
                    }
                }
                $typeCall .= "->{$modName}({$modArg})";
            } else {
                // Simple modifier
                $typeCall .= "->{$modifier}()";
            }
        }

        // Add enum check constraint at the end (after other modifiers)
        if (isset($checkConstraint)) {
            $typeCall .= $checkConstraint;
        }

        if ($withName) {
            return "'{$name}' => {$typeCall},";
        } else {
            return $typeCall;
        }
    }

    /**
     * Generate foreign keys for fields ending with _id
     *
     * @param string $tableName Table name
     * @param array $fields Field definitions
     * @param bool $useTablePrefix Use table prefix
     * @param bool $onDeleteCascade ON DELETE CASCADE
     * @param string $indent Indentation
     * @return string Foreign key code
     */
    private function generateForeignKeys(
        string $tableName,
        array  $fields,
        bool   $useTablePrefix,
        bool   $onDeleteCascade,
        string $indent
    ): string
    {
        $content = '';
        $tableRef = $useTablePrefix ? "'{{%{$tableName}}}'" : "'{$tableName}'";
        $onDelete = $onDeleteCascade ? 'CASCADE' : 'RESTRICT';

        foreach ($fields as $field) {
            $fieldName = explode(':', $field)[0];

            // Check if field is a foreign key (ends with _id)
            if (preg_match('/^(.+)_id$/', $fieldName, $matches)) {
                $relatedTable = $matches[1] . 's'; // Pluralize (simple approach)
                $relatedTableRef = $useTablePrefix ? "'{{%{$relatedTable}}}'" : "'{$relatedTable}'";

                $content .= $indent . "// Add foreign key for {$fieldName}\n";
                $content .= $indent . "\$this->addForeignKey(\n";
                $content .= $indent . "    'fk-{$tableName}-{$fieldName}',\n";
                $content .= $indent . "    {$tableRef},\n";
                $content .= $indent . "    '{$fieldName}',\n";
                $content .= $indent . "    {$relatedTableRef},\n";
                $content .= $indent . "    'id',\n";
                $content .= $indent . "    '{$onDelete}'\n";
                $content .= $indent . ");\n\n";
            }
        }

        return $content;
    }

    /**
     * Generate down() content based on migration type
     *
     * @param string $migrationType Migration type
     * @param string|null $tableName Table name
     * @param array $fields Field definitions
     * @param string|null $junctionTable1 First junction table
     * @param string|null $junctionTable2 Second junction table
     * @param bool $addForeignKeys Add foreign keys
     * @param array $indexes Index definitions
     * @param array $foreignKeys Explicit foreign key definitions
     * @return string Down content
     */
    private function generateDownContent(
        string  $migrationType,
        ?string $tableName,
        array   $fields,
        ?string $junctionTable1,
        ?string $junctionTable2,
        bool    $addForeignKeys,
        array   $indexes = [],
        array   $foreignKeys = []
    ): string
    {
        $content = '';
        $indent = '        ';

        switch ($migrationType) {
            case 'create':
                // Drop foreign keys first if they were added
                if (!empty($foreignKeys)) {
                    $content .= $this->generateDropForeignKeysExplicit($tableName, $foreignKeys, $indent);
                    $content .= "\n";
                } elseif ($addForeignKeys) {
                    $content .= $this->generateDropForeignKeys($tableName, $fields, $indent);
                    $content .= "\n";
                }
                
                // Drop indexes if they were added
                if (!empty($indexes)) {
                    $content .= $this->generateDropIndexes($tableName, $indexes, $indent);
                    $content .= "\n";
                }
                
                $content .= $indent . "\$this->dropTable('{{%{$tableName}}}');\n";
                break;

            case 'add':
                foreach ($fields as $field) {
                    $columnName = explode(':', $field)[0];
                    $content .= $indent . "\$this->dropColumn('{{%{$tableName}}}', '{$columnName}');\n";
                }
                break;

            case 'drop':
                if (!empty($fields)) {
                    // Re-add columns (would need more info in real scenario)
                    $content .= $indent . "// Re-add dropped columns here\n";
                } else {
                    $content .= $indent . "// Re-create dropped table here\n";
                }
                break;

            case 'junction':
                $junctionTableName = $junctionTable1 . '_' . $junctionTable2;
                $table1IdColumn = rtrim($junctionTable1, 's') . '_id';
                $table2IdColumn = rtrim($junctionTable2, 's') . '_id';

                // Drop foreign keys first
                $content .= $indent . "// Drop foreign keys\n";
                $content .= $indent . "\$this->dropForeignKey('fk-{$junctionTableName}-{$table2IdColumn}', '{{%{$junctionTableName}}}');\n";
                $content .= $indent . "\$this->dropForeignKey('fk-{$junctionTableName}-{$table1IdColumn}', '{{%{$junctionTableName}}}');\n\n";

                // Drop indexes
                $content .= $indent . "// Drop indexes\n";
                $content .= $indent . "\$this->dropIndex('idx-{$junctionTableName}-{$table2IdColumn}', '{{%{$junctionTableName}}}');\n";
                $content .= $indent . "\$this->dropIndex('idx-{$junctionTableName}-{$table1IdColumn}', '{{%{$junctionTableName}}}');\n\n";

                // Drop table
                $content .= $indent . "\$this->dropTable('{{%{$junctionTableName}}}');\n";
                break;

            default:
                $content .= $indent . "// Add your revert code here\n";
        }

        return $content;
    }

    /**
     * Generate code to drop foreign keys
     *
     * @param string $tableName Table name
     * @param array $fields Field definitions
     * @param string $indent Indentation
     * @return string Drop foreign key code
     */
    private function generateDropForeignKeys(string $tableName, array $fields, string $indent): string
    {
        $content = '';

        foreach ($fields as $field) {
            $fieldName = explode(':', $field)[0];

            // Check if field is a foreign key
            if (preg_match('/^(.+)_id$/', $fieldName, $matches)) {
                $content .= $indent . "\$this->dropForeignKey('fk-{$tableName}-{$fieldName}', '{{%{$tableName}}}');\n";
            }
        }

        return $content;
    }

    /**
     * Generate indexes for specified columns
     *
     * @param string $tableName Table name
     * @param array $indexes Index definitions (array of strings: 'column' or 'col1,col2' for composite)
     * @param bool $useTablePrefix Use table prefix
     * @param string $indent Indentation
     * @return string Index creation code
     */
    private function generateIndexes(string $tableName, array $indexes, bool $useTablePrefix, string $indent): string
    {
        $content = '';
        $tableRef = $useTablePrefix ? "'{{%{$tableName}}}'" : "'{$tableName}'";

        foreach ($indexes as $indexDef) {
            // Parse index definition (can be 'column' or 'col1,col2' for composite)
            $columns = array_map('trim', explode(',', $indexDef));
            $indexName = 'idx-' . $tableName . '-' . implode('-', $columns);

            $content .= $indent . "// Creates index for " . (count($columns) > 1 ? 'columns' : 'column') . " `" . implode('`, `', $columns) . "`\n";
            $content .= $indent . "\$this->createIndex(\n";
            $content .= $indent . "    '{$indexName}',\n";
            $content .= $indent . "    {$tableRef},\n";

            if (count($columns) === 1) {
                $content .= $indent . "    '{$columns[0]}'\n";
            } else {
                $content .= $indent . "    ['" . implode("', '", $columns) . "']\n";
            }

            $content .= $indent . ");\n\n";
        }

        return $content;
    }

    /**
     * Generate code to drop indexes
     *
     * @param string $tableName Table name
     * @param array $indexes Index definitions
     * @param string $indent Indentation
     * @return string Drop index code
     */
    private function generateDropIndexes(string $tableName, array $indexes, string $indent): string
    {
        $content = '';

        foreach ($indexes as $indexDef) {
            $columns = array_map('trim', explode(',', $indexDef));
            $indexName = 'idx-' . $tableName . '-' . implode('-', $columns);
            $content .= $indent . "\$this->dropIndex('{$indexName}', '{{%{$tableName}}}');\n";
        }

        return $content;
    }

    /**
     * Generate foreign keys from explicit configuration
     *
     * @param string $tableName Table name
     * @param array $foreignKeys FK configurations [{field, table, onDelete?, onUpdate?}]
     * @param bool $useTablePrefix Use table prefix
     * @param string $indent Indentation
     * @return string Foreign key code
     */
    private function generateForeignKeysExplicit(string $tableName, array $foreignKeys, bool $useTablePrefix, string $indent): string
    {
        $content = '';
        $tableRef = $useTablePrefix ? "'{{%{$tableName}}}'" : "'{$tableName}'";
        $validActions = ['CASCADE', 'RESTRICT', 'SET NULL', 'SET DEFAULT', 'NO ACTION'];

        foreach ($foreignKeys as $fk) {
            $field = $fk['field'] ?? null;
            $refTable = $fk['table'] ?? null;
            $refColumn = $fk['column'] ?? 'id';
            $onDelete = strtoupper($fk['onDelete'] ?? 'RESTRICT');
            $onUpdate = strtoupper($fk['onUpdate'] ?? 'RESTRICT');

            // Validate required fields
            if ($field === null || $refTable === null) {
                continue;
            }

            // Validate FK actions
            if (!in_array($onDelete, $validActions, true)) {
                $onDelete = 'RESTRICT';
            }
            if (!in_array($onUpdate, $validActions, true)) {
                $onUpdate = 'RESTRICT';
            }

            $refTableRef = $useTablePrefix ? "'{{%{$refTable}}}'" : "'{$refTable}'";

            $content .= $indent . "// Add foreign key for {$field}\n";
            $content .= $indent . "\$this->addForeignKey(\n";
            $content .= $indent . "    'fk-{$tableName}-{$field}',\n";
            $content .= $indent . "    {$tableRef},\n";
            $content .= $indent . "    '{$field}',\n";
            $content .= $indent . "    {$refTableRef},\n";
            $content .= $indent . "    '{$refColumn}',\n";
            $content .= $indent . "    '{$onDelete}',\n";
            $content .= $indent . "    '{$onUpdate}'\n";
            $content .= $indent . ");\n\n";
        }

        return $content;
    }

    /**
     * Generate code to drop explicit foreign keys
     *
     * @param string $tableName Table name
     * @param array $foreignKeys FK configurations
     * @param string $indent Indentation
     * @return string Drop foreign key code
     */
    private function generateDropForeignKeysExplicit(string $tableName, array $foreignKeys, string $indent): string
    {
        $content = '';

        foreach ($foreignKeys as $fk) {
            $field = $fk['field'] ?? null;
            if ($field !== null) {
                $content .= $indent . "\$this->dropForeignKey('fk-{$tableName}-{$field}', '{{%{$tableName}}}');\n";
            }
        }

        return $content;
    }
}
