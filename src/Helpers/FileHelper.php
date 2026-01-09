<?php

namespace Took\Yii2GiiMCP\Helpers;

/**
 * File Helper
 *
 * Provides file operations and conflict detection for code generation.
 * Ensures files are not accidentally overwritten.
 */
class FileHelper
{
    /**
     * Check for file conflicts
     *
     * Given an array of file paths, checks which ones already exist.
     *
     * @param array $files Array of file paths to check
     * @return array Array of conflicting files with their details
     */
    public static function checkConflicts(array $files): array
    {
        $conflicts = [];

        foreach ($files as $filePath) {
            if (file_exists($filePath)) {
                $conflicts[] = [
                    'path' => $filePath,
                    'exists' => true,
                    'readable' => is_readable($filePath),
                    'writable' => is_writable($filePath),
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath),
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Get file information
     *
     * Returns detailed information about a file.
     *
     * @param string $path File path
     * @return array|null File information or null if file doesn't exist
     */
    public static function getFileInfo(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        return [
            'path' => $path,
            'exists' => true,
            'size' => filesize($path),
            'modified' => filemtime($path),
            'readable' => is_readable($path),
            'writable' => is_writable($path),
            'isDir' => is_dir($path),
            'isFile' => is_file($path),
        ];
    }

    /**
     * Check multiple paths for writability
     *
     * Checks if multiple paths can be written to.
     *
     * @param array $paths Array of paths to check
     * @return array Array mapping paths to writability status
     */
    public static function checkWritable(array $paths): array
    {
        $results = [];
        foreach ($paths as $path) {
            $results[$path] = self::canWrite($path);
        }

        return $results;
    }

    /**
     * Check if a path is writable
     *
     * Checks if a file can be written to (either file exists and is writable,
     * or directory exists and is writable for new file creation).
     *
     * @param string $path Path to check
     * @return bool True if path is writable
     */
    public static function canWrite(string $path): bool
    {
        // If file exists, check if it's writable
        if (file_exists($path)) {
            return is_writable($path);
        }

        // If file doesn't exist, check if parent directory is writable
        $dir = dirname($path);

        return is_dir($dir) && is_writable($dir);
    }

    /**
     * Get relative path
     *
     * Converts an absolute path to relative path from base directory.
     *
     * @param string $path Absolute path
     * @param string $basePath Base directory path
     * @return string Relative path
     */
    public static function getRelativePath(string $path, string $basePath): string
    {
        // Normalize paths
        $path = str_replace('\\', '/', $path);
        $basePath = str_replace('\\', '/', rtrim($basePath, '/'));

        // If path starts with base path, remove it
        if (str_starts_with($path, $basePath)) {
            return ltrim(substr($path, strlen($basePath)), '/');
        }

        return $path;
    }

    /**
     * Read file safely
     *
     * Reads file content with error handling.
     *
     * @param string $path File path
     * @return string|null File content or null on error
     */
    public static function readFile(string $path): ?string
    {
        if (!file_exists($path) || !is_readable($path)) {
            return null;
        }

        $content = @file_get_contents($path);

        return $content !== false ? $content : null;
    }

    /**
     * Write file safely
     *
     * Writes content to file with directory creation and error handling.
     *
     * @param string $path File path
     * @param string $content Content to write
     * @return bool True on success
     */
    public static function writeFile(string $path, string $content): bool
    {
        // Ensure parent directory exists
        $dir = dirname($path);
        if (!self::ensureDirectory($dir)) {
            return false;
        }

        // Write file
        return @file_put_contents($path, $content) !== false;
    }

    /**
     * Ensure directory exists
     *
     * Creates directory if it doesn't exist (with proper permissions).
     *
     * @param string $dir Directory path
     * @param int $mode Directory permissions (default: 0755)
     * @return bool True if directory exists or was created
     */
    public static function ensureDirectory(string $dir, int $mode = 0755): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        return mkdir($dir, $mode, true);
    }

    /**
     * Create a backup of an existing file.
     *
     * Creates a backup copy of a file before it is modified.
     *
     * @param string $path The original file path.
     * @param string|null $backupSuffix Suffix for the backup file (default: '.bak').
     * @return string|null The path to the backup file, or null on error.
     */
    public static function createBackup(string $path, ?string $backupSuffix = '.bak'): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        $backupPath = $path . $backupSuffix;

        // If backup already exists, add timestamp
        if (file_exists($backupPath)) {
            $backupPath = $path . '.' . time() . $backupSuffix;
        }

        if (@copy($path, $backupPath)) {
            return $backupPath;
        }

        return null;
    }

    /**
     * Get conflict summary
     *
     * Returns a human-readable summary of file conflicts.
     *
     * @param array $conflicts Array of conflicts from checkConflicts()
     * @return string Formatted summary
     */
    public static function getConflictSummary(array $conflicts): string
    {
        if (empty($conflicts)) {
            return 'No file conflicts detected.';
        }

        $summary = count($conflicts) . ' file(s) already exist:\n\n';

        foreach ($conflicts as $conflict) {
            $path = $conflict['path'];
            $size = self::formatFileSize($conflict['size']);
            $modified = self::formatTimestamp($conflict['modified']);
            $writable = $conflict['writable'] ? 'writable' : 'read-only';

            $summary .= "- {$path}\n";
            $summary .= "  Size: {$size}, Modified: {$modified}, Status: {$writable}\n\n";
        }

        return $summary;
    }

    /**
     * Format file size for display
     *
     * Converts bytes to human-readable format.
     *
     * @param int $bytes File size in bytes
     * @return string Formatted size (e.g., "1.5 KB")
     */
    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $size = (float)$bytes;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return round($size, 2) . ' ' . $units[$index];
    }

    /**
     * Format timestamp for display
     *
     * Converts Unix timestamp to readable date/time.
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted date/time
     */
    public static function formatTimestamp(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Validate file paths are safe
     *
     * Checks that paths don't contain dangerous patterns.
     *
     * @param array $paths Array of paths to validate
     * @return array Array of validation results [path => isValid]
     */
    public static function validatePaths(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            // Check for path traversal
            $isValid = !ValidationHelper::hasPathTraversal($path);

            // Check for absolute paths outside project (if applicable)
            // For now, just check traversal
            $results[$path] = $isValid;
        }

        return $results;
    }
}
