<?php

namespace Took\Yii2GiiMCP\Helpers;

use yii\db\Connection;

/**
 * Log Reader Helper
 *
 * Utility methods for reading and filtering Yii2 application logs from:
 * - File-based logs (FileTarget - runtime/logs/app.log)
 * - Database logs (DbTarget - log table)
 *
 * Supports filtering by level, category, time range, and full-text search.
 */
class LogReaderHelper
{
    /**
     * Find log files across applications
     *
     * @param string $basePath Base path of Yii2 project
     * @param string|null $application Specific application or null for all
     * @return array<string, string> Application name => log directory path
     */
    public static function findLogFiles(string $basePath, ?string $application = null): array
    {
        $logDirs = [];

        // Get all applications
        $applications = ProjectStructureHelper::findApplicationDirs($basePath);

        foreach ($applications as $appName => $appPath) {
            // Filter by specific application if requested
            if ($application !== null && $appName !== $application) {
                continue;
            }

            // Check for runtime/logs directory
            $logDir = $appPath . '/runtime/logs';
            if (is_dir($logDir)) {
                $logDirs[$appName] = $logDir;
            }
        }

        return $logDirs;
    }

    /**
     * Read logs from file
     *
     * @param string $filePath Path to log file
     * @param array $filters Filters to apply
     * @return array Log entries
     */
    public static function readLogFile(string $filePath, array $filters = []): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $logs = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $currentEntry = null;

        foreach ($lines as $line) {
            $parsed = self::parseLogLine($line);

            if ($parsed !== null) {
                // New log entry - save previous if exists
                if ($currentEntry !== null) {
                    $logs[] = $currentEntry;
                }
                $currentEntry = $parsed;
            } elseif ($currentEntry !== null) {
                // Continuation of previous entry (stack trace, etc.)
                $currentEntry['trace'] .= "\n" . $line;
            }
        }

        // Don't forget the last entry
        if ($currentEntry !== null) {
            $logs[] = $currentEntry;
        }

        // Apply filters
        return self::applyFilters($logs, $filters);
    }

    /**
     * Parse a single log line
     *
     * Format: YYYY-MM-DD HH:MM:SS [IP][userId][sessionId][level][category] message
     *
     * @param string $line Log line
     * @return array|null Parsed log entry or null if not a log line
     */
    public static function parseLogLine(string $line): ?array
    {
        // Match Yii2 log format
        $pattern = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\s+(.*)$/';

        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'ip' => $matches[2],
                'userId' => $matches[3],
                'sessionId' => $matches[4],
                'level' => $matches[5],
                'category' => $matches[6],
                'message' => $matches[7],
                'trace' => '',
            ];
        }

        return null;
    }

    /**
     * Read logs from database
     *
     * @param Connection $db Database connection
     * @param array $filters Filters to apply
     * @return array Log entries
     */
    public static function readDbLogs(Connection $db, array $filters = []): array
    {
        $tableName = self::getLogTableName($db);
        if ($tableName === null) {
            return [];
        }

        // Build query
        $query = $db->createCommand("SELECT * FROM {{%{$tableName}}}");
        $whereClauses = [];
        $params = [];

        // Apply level filter
        if (isset($filters['level'])) {
            $levelNum = self::logLevelToNumber($filters['level']);
            $whereClauses[] = 'level = :level';
            $params[':level'] = $levelNum;
        }

        // Apply category filter
        if (isset($filters['category']) && !empty($filters['category'])) {
            $category = $filters['category'];
            if (strpos($category, '*') !== false) {
                // Wildcard support
                $whereClauses[] = 'category LIKE :category';
                $params[':category'] = str_replace('*', '%', $category);
            } else {
                $whereClauses[] = 'category = :category';
                $params[':category'] = $category;
            }
        }

        // Apply time range filters
        if (isset($filters['since'])) {
            $whereClauses[] = 'log_time >= :since';
            $params[':since'] = strtotime($filters['since']);
        }

        if (isset($filters['until'])) {
            $whereClauses[] = 'log_time <= :until';
            $params[':until'] = strtotime($filters['until']);
        }

        // Apply search filter
        if (isset($filters['search']) && !empty($filters['search'])) {
            $whereClauses[] = 'message LIKE :search';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // Build WHERE clause
        if (!empty($whereClauses)) {
            $query->setSql(
                $query->getSql() . ' WHERE ' . implode(' AND ', $whereClauses)
            );
        }

        // Order by newest first
        $query->setSql($query->getSql() . ' ORDER BY log_time DESC');

        // Apply limit
        $limit = $filters['limit'] ?? 100;
        $query->setSql($query->getSql() . ' LIMIT ' . (int)$limit);

        foreach ($params as $key => $value) {
            $query->bindValue($key, $value);
        }

        $rows = $query->queryAll();

        // Convert to standard format
        $logs = [];
        foreach ($rows as $row) {
            $logs[] = [
                'timestamp' => date('Y-m-d H:i:s', $row['log_time']),
                'level' => self::mapLogLevel($row['level']),
                'category' => $row['category'],
                'message' => $row['message'],
                'trace' => '',
                'prefix' => $row['prefix'] ?? '',
            ];
        }

        return $logs;
    }

    /**
     * Get log table name from database
     *
     * @param Connection $db Database connection
     * @return string|null Table name or null if not found
     */
    public static function getLogTableName(Connection $db): ?string
    {
        // Check if log table exists (common names)
        $possibleNames = ['log', 'logs', 'yii_log'];

        foreach ($possibleNames as $name) {
            try {
                $db->createCommand("SELECT 1 FROM {{%{$name}}} LIMIT 1")->queryScalar();
                return $name;
            } catch (\Exception $e) {
                // Table doesn't exist, try next
            }
        }

        return null;
    }

    /**
     * Apply filters to log entries
     *
     * @param array $logs Log entries
     * @param array $filters Filters to apply
     * @return array Filtered log entries
     */
    public static function applyFilters(array $logs, array $filters): array
    {
        $filtered = [];

        foreach ($logs as $log) {
            // Filter by level
            if (isset($filters['level']) && $log['level'] !== $filters['level']) {
                continue;
            }

            // Filter by category
            if (isset($filters['category']) && !empty($filters['category'])) {
                $category = $filters['category'];
                if (strpos($category, '*') !== false) {
                    // Wildcard support
                    $pattern = '/^' . str_replace(['\\', '*'], ['\\\\', '.*'], $category) . '$/';
                    if (!preg_match($pattern, $log['category'])) {
                        continue;
                    }
                } else {
                    // Exact match
                    if ($log['category'] !== $category) {
                        continue;
                    }
                }
            }

            // Filter by time range
            if (isset($filters['since'])) {
                $logTime = strtotime($log['timestamp']);
                $sinceTime = strtotime($filters['since']);
                if ($logTime < $sinceTime) {
                    continue;
                }
            }

            if (isset($filters['until'])) {
                $logTime = strtotime($log['timestamp']);
                $untilTime = strtotime($filters['until']);
                if ($logTime > $untilTime) {
                    continue;
                }
            }

            // Filter by search term
            if (isset($filters['search']) && !empty($filters['search'])) {
                $searchTerm = strtolower($filters['search']);
                $message = strtolower($log['message']);
                $trace = strtolower($log['trace'] ?? '');

                if (strpos($message, $searchTerm) === false && strpos($trace, $searchTerm) === false) {
                    continue;
                }
            }

            $filtered[] = $log;
        }

        // Apply limit
        $limit = $filters['limit'] ?? 100;
        return array_slice($filtered, 0, $limit);
    }

    /**
     * Map log level from numeric to string
     *
     * @param mixed $level Numeric or string level
     * @return string String level
     */
    public static function mapLogLevel(mixed $level): string
    {
        if (is_string($level)) {
            return $level;
        }

        $map = [
            1 => 'error',
            2 => 'warning',
            4 => 'info',
            8 => 'trace',
        ];

        return $map[$level] ?? 'unknown';
    }

    /**
     * Convert log level string to numeric
     *
     * @param string $level Level string
     * @return int Numeric level
     */
    public static function logLevelToNumber(string $level): int
    {
        $map = [
            'error' => 1,
            'warning' => 2,
            'info' => 4,
            'trace' => 8,
        ];

        return $map[$level] ?? 4;
    }

    /**
     * Aggregate logs from multiple applications
     *
     * @param array $logsByApp Logs grouped by application
     * @param array $filters Filters to apply
     * @return array Aggregated and sorted logs
     */
    public static function aggregateLogs(array $logsByApp, array $filters): array
    {
        $allLogs = [];

        foreach ($logsByApp as $appName => $logs) {
            foreach ($logs as $log) {
                $log['application'] = $appName;
                $allLogs[] = $log;
            }
        }

        // Sort by timestamp (newest first)
        usort($allLogs, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        // Apply limit
        $limit = $filters['limit'] ?? 100;
        return array_slice($allLogs, 0, $limit);
    }
}
