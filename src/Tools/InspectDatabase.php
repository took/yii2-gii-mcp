<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use yii\db\TableSchema;

/**
 * Inspect Database Tool
 *
 * Detailed database schema inspection tool.
 * Returns comprehensive information about tables, columns, indexes, foreign keys, and constraints.
 * Read-only operation.
 */
class InspectDatabase extends AbstractTool
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
        return 'inspect-database';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Inspect database schema with detailed information about tables, columns, indexes, ' .
            'foreign keys, and constraints. This is a read-only tool that does not modify anything. ' .
            'You can filter results by table name pattern.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection' => [
                    'type' => 'string',
                    'description' => 'Database connection component ID',
                    'default' => 'db',
                ],
                'tablePattern' => [
                    'type' => 'string',
                    'description' => 'Filter tables by pattern (supports glob patterns like "user*", "*_log" or SQL LIKE patterns like "user%", "%_log")',
                ],
                'includeViews' => [
                    'type' => 'boolean',
                    'description' => 'Include database views in results',
                    'default' => false,
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
            // Ensure Yii2 is initialized
            if (!$this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            $connectionId = $this->getOptionalParam($arguments, 'connection', 'db');
            $tablePattern = $this->getOptionalParam($arguments, 'tablePattern');
            $includeViews = $this->getOptionalParam($arguments, 'includeViews', false);

            // Get database connection
            $db = $this->bootstrap->getDb($connectionId);

            if ($db === null) {
                return $this->createError(
                    "Database connection '{$connectionId}' not found",
                    ['connection' => $connectionId]
                );
            }

            // Get schema
            $schema = $db->getSchema();

            // Get all table names
            $tableNames = $schema->getTableNames('', !$includeViews);

            // Filter by pattern if provided
            if ($tablePattern !== null) {
                $tableNames = array_filter($tableNames, function ($tableName) use ($tablePattern) {
                    // Support both SQL LIKE patterns (%, _) and shell glob patterns (*, ?)
                    // First convert glob to SQL LIKE if needed
                    $sqlPattern = str_replace(['*', '?'], ['%', '_'], $tablePattern);
                    
                    // Convert SQL LIKE pattern to regex
                    // We need to escape regex special chars but preserve % and _
                    // Strategy: Replace % and _ with unique markers (using non-conflicting chars), escape, then replace markers
                    $percentMarker = '§§PERCENT§§';  // Using § which won't appear in table names
                    $underscoreMarker = '§§UNDERSCORE§§';
                    
                    $sqlPattern = str_replace(['%', '_'], [$percentMarker, $underscoreMarker], $sqlPattern);
                    $regexPattern = preg_quote($sqlPattern, '/');
                    $regexPattern = str_replace([$percentMarker, $underscoreMarker], ['.*', '.'], $regexPattern);
                    
                    return preg_match("/^{$regexPattern}$/", $tableName) === 1;
                });
            }

            // Sort table names
            sort($tableNames);

            // Get detailed info for each table
            $tables = [];
            foreach ($tableNames as $tableName) {
                $tableSchema = $schema->getTableSchema($tableName);
                if ($tableSchema !== null) {
                    $tables[] = $this->formatTableInfo($tableSchema);
                }
            }

            // Format output
            $output = $this->formatOutput($tables, $db->dsn);

            return $this->createResult($output);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to inspect database: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Format table schema information
     *
     * @param TableSchema $tableSchema Table schema
     * @return array Formatted table info
     */
    private function formatTableInfo(TableSchema $tableSchema): array
    {
        $info = [
            'name' => $tableSchema->fullName ?? $tableSchema->name,
            'schemaName' => $tableSchema->schemaName,
            'primaryKey' => $tableSchema->primaryKey,
            'sequenceName' => $tableSchema->sequenceName,
            'columns' => [],
            'foreignKeys' => [],
            'indexes' => [],
        ];

        // Format columns
        foreach ($tableSchema->columns as $column) {
            $info['columns'][] = [
                'name' => $column->name,
                'type' => $column->type,
                'phpType' => $column->phpType,
                'dbType' => $column->dbType,
                'size' => $column->size,
                'precision' => $column->precision,
                'scale' => $column->scale,
                'allowNull' => $column->allowNull,
                'isPrimaryKey' => $column->isPrimaryKey,
                'autoIncrement' => $column->autoIncrement,
                'defaultValue' => $column->defaultValue,
                'comment' => $column->comment,
                'enumValues' => $column->enumValues,
            ];
        }

        // Format foreign keys
        foreach ($tableSchema->foreignKeys as $name => $foreignKey) {
            $refTable = array_shift($foreignKey);
            $info['foreignKeys'][] = [
                'name' => is_string($name) ? $name : null,
                'referencedTable' => $refTable,
                'columns' => $foreignKey,
            ];
        }

        return $info;
    }

    /**
     * Format output
     *
     * @param array $tables Tables info
     * @param string $dsn Database DSN
     * @return string Formatted output
     */
    private function formatOutput(array $tables, string $dsn): string
    {
        $count = count($tables);
        $output = "Database Schema Inspection\n";
        $output .= "==========================\n\n";
        $output .= "DSN: {$dsn}\n";
        $output .= "Tables: {$count}\n\n";

        if ($count === 0) {
            $output .= "No tables found.\n";
            return $output;
        }

        // Format as JSON for better AI consumption
        $output .= "Schema Details (JSON):\n";
        $output .= "```json\n";
        $output .= json_encode($tables, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $output .= "\n```\n\n";

        // Add summary table
        $output .= "Summary:\n";
        $headers = ['Table', 'Columns', 'Primary Key', 'Foreign Keys'];
        $rows = array_map(function ($table) {
            return [
                $table['name'],
                (string)count($table['columns']),
                implode(', ', $table['primaryKey'] ?: ['-']),
                (string)count($table['foreignKeys']),
            ];
        }, $tables);
        $output .= $this->formatTable($headers, $rows);

        return $output;
    }
}
