<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\LogReaderHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Read Logs Tool
 *
 * Read and filter Yii2 application logs from files and database.
 * Supports filtering by level, category, time range, and full-text search.
 * Can aggregate logs from multiple applications or read from specific application.
 *
 * This is a read-only tool that helps debug and monitor applications.
 */
class ReadLogs extends AbstractTool
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
        return 'read-logs';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Read and filter Yii2 application logs from files (runtime/logs) and database (log table). ' .
            'Supports filtering by level (error/warning/info/trace), category, time range, and full-text search. ' .
            'Can read from all applications or specific application (frontend/backend/console/api/app). ' .
            'This is a read-only operation that helps debug and monitor applications.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'application' => [
                    'type' => 'string',
                    'description' => 'Specific application to read logs from (frontend/backend/console/api/app). If not specified, reads from all applications.',
                    'default' => '',
                ],
                'source' => [
                    'type' => 'string',
                    'enum' => ['file', 'db', 'both'],
                    'description' => 'Log source: file (runtime/logs), db (log table), or both',
                    'default' => 'both',
                ],
                'level' => [
                    'type' => 'string',
                    'enum' => ['error', 'warning', 'info', 'trace'],
                    'description' => 'Filter by log level (optional)',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Filter by category (supports wildcards: application.*, yii\\db\\*)',
                    'default' => '',
                ],
                'since' => [
                    'type' => 'string',
                    'description' => 'Start datetime (ISO 8601 format: 2024-01-09T12:00:00 or YYYY-MM-DD HH:MM:SS)',
                    'default' => '',
                ],
                'until' => [
                    'type' => 'string',
                    'description' => 'End datetime (ISO 8601 format: 2024-01-09T18:00:00 or YYYY-MM-DD HH:MM:SS)',
                    'default' => '',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Full-text search term in log messages (case-insensitive)',
                    'default' => '',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of log entries to return (default: 100)',
                    'default' => 100,
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

            // Get parameters
            $application = $this->getOptionalParam($arguments, 'application', '');
            $source = $this->getOptionalParam($arguments, 'source', 'both');
            $filters = [
                'level' => $this->getOptionalParam($arguments, 'level', null),
                'category' => $this->getOptionalParam($arguments, 'category', ''),
                'since' => $this->getOptionalParam($arguments, 'since', ''),
                'until' => $this->getOptionalParam($arguments, 'until', ''),
                'search' => $this->getOptionalParam($arguments, 'search', ''),
                'limit' => $this->getOptionalParam($arguments, 'limit', 100),
            ];

            // Remove empty filters
            $filters = array_filter($filters, function ($value) {
                return $value !== '' && $value !== null;
            });

            // Get base path
            $basePath = $this->bootstrap->getApp()->getBasePath();
            $templateType = $this->bootstrap->detectTemplateType();
            if ($templateType !== 'basic') {
                $basePath = dirname($basePath);
            }

            // Read logs
            $allLogs = [];
            $logsByApp = [];

            // Read from file sources
            if ($source === 'file' || $source === 'both') {
                $logDirs = LogReaderHelper::findLogFiles(
                    $basePath,
                    !empty($application) ? $application : null
                );

                foreach ($logDirs as $appName => $logDir) {
                    $logFile = $logDir . '/app.log';
                    if (file_exists($logFile)) {
                        $logs = LogReaderHelper::readLogFile($logFile, $filters);
                        foreach ($logs as &$log) {
                            $log['source'] = 'file';
                        }
                        $logsByApp[$appName] = $logs;
                    }
                }
            }

            // Read from database
            if ($source === 'db' || $source === 'both') {
                try {
                    $db = $this->bootstrap->getDb();
                    $dbLogs = LogReaderHelper::readDbLogs($db, $filters);
                    foreach ($dbLogs as &$log) {
                        $log['source'] = 'db';
                    }
                    if (!empty($dbLogs)) {
                        $dbAppName = !empty($application) ? $application : 'database';
                        if (isset($logsByApp[$dbAppName])) {
                            $logsByApp[$dbAppName] = array_merge($logsByApp[$dbAppName], $dbLogs);
                        } else {
                            $logsByApp[$dbAppName] = $dbLogs;
                        }
                    }
                } catch (Throwable $e) {
                    // Database logs not available, continue with file logs
                }
            }

            // Aggregate logs if multiple applications
            if (count($logsByApp) > 1) {
                $allLogs = LogReaderHelper::aggregateLogs($logsByApp, $filters);
            } elseif (count($logsByApp) === 1) {
                $appName = array_key_first($logsByApp);
                $allLogs = $logsByApp[$appName];
                // Add application name
                foreach ($allLogs as &$log) {
                    $log['application'] = $appName;
                }
            }

            // Calculate statistics
            $stats = $this->calculateStatistics($allLogs);

            // Format output
            $output = $this->formatOutput($allLogs, $stats, $filters);

            return $this->createResult($output);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to read logs: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Calculate log statistics
     *
     * @param array $logs Log entries
     * @return array Statistics
     */
    private function calculateStatistics(array $logs): array
    {
        $stats = [
            'total' => count($logs),
            'byLevel' => [],
            'byApplication' => [],
        ];

        foreach ($logs as $log) {
            // Count by level
            $level = $log['level'];
            if (!isset($stats['byLevel'][$level])) {
                $stats['byLevel'][$level] = 0;
            }
            $stats['byLevel'][$level]++;

            // Count by application
            if (isset($log['application'])) {
                $app = $log['application'];
                if (!isset($stats['byApplication'][$app])) {
                    $stats['byApplication'][$app] = 0;
                }
                $stats['byApplication'][$app]++;
            }
        }

        return $stats;
    }

    /**
     * Format output
     *
     * @param array $logs Log entries
     * @param array $stats Statistics
     * @param array $filters Applied filters
     * @return string Formatted output
     */
    private function formatOutput(array $logs, array $stats, array $filters): string
    {
        $output = "Log Reader Results\n";
        $output .= str_repeat('=', 50) . "\n\n";

        // Applied filters
        if (!empty($filters)) {
            $output .= "Applied Filters:\n";
            foreach ($filters as $key => $value) {
                if ($key !== 'limit') {
                    $output .= "  {$key}: {$value}\n";
                }
            }
            $output .= "\n";
        }

        // Statistics
        $output .= "Statistics:\n";
        $output .= "  Total entries: {$stats['total']}\n";

        if (!empty($stats['byLevel'])) {
            $output .= "  By level:\n";
            foreach ($stats['byLevel'] as $level => $count) {
                $output .= "    - {$level}: {$count}\n";
            }
        }

        if (!empty($stats['byApplication'])) {
            $output .= "  By application:\n";
            foreach ($stats['byApplication'] as $app => $count) {
                $output .= "    - {$app}: {$count}\n";
            }
        }

        $output .= "\n";

        // Log entries
        if (empty($logs)) {
            $output .= "No log entries found matching the criteria.\n";
        } else {
            $output .= "Log Entries (showing " . count($logs) . "):\n";
            $output .= str_repeat('-', 50) . "\n\n";

            foreach ($logs as $index => $log) {
                $output .= "[" . ($index + 1) . "] ";
                $output .= "[{$log['timestamp']}] ";

                if (isset($log['application'])) {
                    $output .= "[{$log['application']}] ";
                }

                $output .= "[{$log['level']}] ";
                $output .= "[{$log['category']}]\n";
                $output .= "    {$log['message']}\n";

                if (!empty($log['trace'])) {
                    $output .= "    Trace: " . substr($log['trace'], 0, 200);
                    if (strlen($log['trace']) > 200) {
                        $output .= "...";
                    }
                    $output .= "\n";
                }

                $output .= "\n";
            }
        }

        // Add JSON representation for programmatic access
        $output .= "\n" . str_repeat('=', 50) . "\n";
        $output .= "JSON Representation:\n";
        $output .= str_repeat('=', 50) . "\n";
        $output .= json_encode([
            'total' => $stats['total'],
            'returned' => count($logs),
            'logs' => $logs,
            'summary' => $stats,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $output;
    }
}
