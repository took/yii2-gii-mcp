<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\MigrationHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * List Migrations Tool
 *
 * Read-only tool that lists available migrations with their status.
 * This is a safe operation that only reads migration information.
 */
class ListMigrations extends AbstractTool
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
        return 'list-migrations';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'List all database migrations with their status (applied, pending, or all). ' .
            'Shows migration names, status, and applied timestamps. ' .
            'This is a read-only operation that helps you understand the migration state.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter migrations by status',
                    'enum' => ['all', 'applied', 'pending'],
                    'default' => 'all',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Limit number of results (default: 10, 0 for all)',
                    'default' => 10,
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
            $status = $this->getOptionalParam($arguments, 'status', 'all');
            $limit = $this->getOptionalParam($arguments, 'limit', 10);

            // Validate status parameter
            if (! in_array($status, ['all', 'applied', 'pending'], true)) {
                return $this->createError(
                    'Invalid status parameter. Must be one of: all, applied, pending',
                    ['status' => $status]
                );
            }

            // Ensure Yii2 is initialized
            if (! $this->bootstrap->isInitialized()) {
                $this->bootstrap->initialize();
            }

            // Get migrations
            $migrations = $this->migrationHelper->getMigrations($status);

            // Apply limit if specified
            if ($limit > 0 && count($migrations) > $limit) {
                $migrations = array_slice($migrations, 0, $limit);
            }

            // Format output
            $output = $this->formatOutput($migrations, $status, $limit);

            return $this->createResult($output);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to list migrations: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Format migrations output
     *
     * @param array $migrations Migrations list
     * @param string $status Filter status
     * @param int $limit Result limit
     * @return string Formatted output
     */
    private function formatOutput(array $migrations, string $status, int $limit): string
    {
        if (empty($migrations)) {
            return "No migrations found with status: {$status}";
        }

        $count = count($migrations);
        $summary = "Found {$count} migration(s) with status '{$status}'";

        if ($limit > 0 && $limit < $count) {
            $summary .= " (showing first {$limit})";
        }

        $summary .= ":\n\n";

        // Format as JSON for structured output
        $output = $summary . json_encode($migrations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Add summary table
        $appliedCount = count(array_filter($migrations, fn ($m) => $m['status'] === 'applied'));
        $pendingCount = count(array_filter($migrations, fn ($m) => $m['status'] === 'pending'));

        $output .= "\n\nSummary:\n";
        $output .= "- Applied: {$appliedCount}\n";
        $output .= "- Pending: {$pendingCount}\n";
        $output .= "- Total: {$count}\n";

        return $output;
    }
}
