<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * List Database Tables Tool
 *
 * Read-only tool that lists all database tables with their metadata.
 * This is a safe operation that only reads schema information.
 */
class ListTables extends AbstractTool
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
        return 'list-tables';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'List all database tables with their metadata including columns, types, keys, and foreign keys. ' .
            'This is a read-only operation that helps you understand the database structure.';
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
                    'description' => 'Database connection component ID (default: "db")',
                    'default' => 'db',
                ],
                'detailed' => [
                    'type' => 'boolean',
                    'description' => 'Include detailed column information (default: true)',
                    'default' => true,
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
            // Get parameters
            $connectionId = $this->getOptionalParam($arguments, 'connection', 'db');
            $detailed = $this->getOptionalParam($arguments, 'detailed', true);

            // Ensure Yii2 is initialized
            if (! $this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Get database connection
            $db = $this->bootstrap->getDb($connectionId);

            // Get schema
            $schema = $db->getSchema();

            // Get all table names
            $tableNames = $schema->getTableNames();

            if (empty($tableNames)) {
                return $this->createResult('No tables found in the database.');
            }

            // Build table information
            $tables = [];

            foreach ($tableNames as $tableName) {
                $tableSchema = $schema->getTableSchema($tableName);

                if ($tableSchema === null) {
                    continue; // Skip if table schema not available
                }

                $tableInfo = [
                    'name' => $tableName,
                    'fullName' => $tableSchema->fullName,
                ];

                if ($detailed) {
                    // Add column information
                    $columns = [];
                    foreach ($tableSchema->columns as $column) {
                        $columnInfo = [
                            'name' => $column->name,
                            'type' => $column->type,
                            'dbType' => $column->dbType,
                            'phpType' => $column->phpType,
                            'allowNull' => $column->allowNull,
                            'isPrimaryKey' => $column->isPrimaryKey,
                            'autoIncrement' => $column->autoIncrement,
                        ];

                        if ($column->defaultValue !== null) {
                            $columnInfo['defaultValue'] = $column->defaultValue;
                        }

                        if ($column->size !== null) {
                            $columnInfo['size'] = $column->size;
                        }

                        $columns[] = $columnInfo;
                    }

                    $tableInfo['columns'] = $columns;
                    $tableInfo['primaryKey'] = $tableSchema->primaryKey;

                    // Add foreign keys
                    if (! empty($tableSchema->foreignKeys)) {
                        $foreignKeys = [];
                        foreach ($tableSchema->foreignKeys as $fk) {
                            $referencedTable = array_shift($fk);
                            $foreignKeys[] = [
                                'referencedTable' => $referencedTable,
                                'columns' => $fk,
                            ];
                        }
                        $tableInfo['foreignKeys'] = $foreignKeys;
                    }
                }

                $tables[] = $tableInfo;
            }

            // Format output
            $summary = "Found " . count($tables) . " table(s) in database:\n\n";

            if ($detailed) {
                // Detailed output with full metadata
                $output = $summary . json_encode($tables, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                // Simple table list
                $output = $summary . implode("\n", array_map(fn ($t) => "- " . $t['name'], $tables));
            }

            return $this->createResult($output);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to list database tables: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }
}
