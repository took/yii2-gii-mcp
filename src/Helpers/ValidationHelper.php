<?php

namespace Took\Yii2GiiMCP\Helpers;

/**
 * Validation Helper
 *
 * Provides validation methods for user inputs to prevent security issues
 * and ensure valid PHP/database identifiers.
 */
class ValidationHelper
{
    /**
     * Validate PHP class name
     *
     * Ensures class name follows PHP naming conventions.
     *
     * @param string $name Class name to validate
     * @return bool True if valid
     */
    public static function validateClassName(string $name): bool
    {
        // Allow alphanumeric and underscore, must start with letter or underscore
        // Allow backslash for fully qualified class names
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_\\\\]*$/', $name) === 1;
    }

    /**
     * Validate PHP namespace
     *
     * Ensures namespace follows PHP namespace conventions.
     *
     * @param string $namespace Namespace to validate
     * @return bool True if valid
     */
    public static function validateNamespace(string $namespace): bool
    {
        // Empty namespace is valid (root namespace)
        if ($namespace === '') {
            return true;
        }

        // Allow alphanumeric, underscore, and backslash
        // Each segment must start with letter or underscore
        $segments = explode('\\', $namespace);
        foreach ($segments as $segment) {
            if ($segment === '') {
                return false; // Empty segments not allowed
            }
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $segment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate file path is within project boundaries
     *
     * Prevents path traversal attacks by ensuring the resolved path
     * is within the allowed base path.
     *
     * @param string $path Path to validate
     * @param string $basePath Base path that must contain the target path
     * @return bool True if path is within boundaries
     */
    public static function validatePath(string $path, string $basePath): bool
    {
        // Resolve to absolute paths
        $realPath = realpath($path);
        $realBasePath = realpath($basePath);

        // If path doesn't exist yet, check parent directory
        if ($realPath === false) {
            $parentDir = dirname($path);
            $realPath = realpath($parentDir);
            if ($realPath === false) {
                return false; // Parent doesn't exist
            }
            // Reconstruct the path
            $realPath = $realPath . DIRECTORY_SEPARATOR . basename($path);
        }

        if ($realBasePath === false) {
            return false; // Base path doesn't exist
        }

        // Ensure path starts with base path
        return str_starts_with($realPath, $realBasePath);
    }

    /**
     * Validate controller ID
     *
     * Ensures controller ID follows Yii2 conventions (kebab-case).
     *
     * @param string $id Controller ID to validate
     * @return bool True if valid
     */
    public static function validateControllerId(string $id): bool
    {
        // Allow lowercase letters, numbers, and hyphens
        // Must start with letter
        return preg_match('/^[a-z][a-z0-9\-]*$/', $id) === 1;
    }

    /**
     * Validate model attribute name
     *
     * Ensures attribute name is a valid PHP property name.
     *
     * @param string $name Attribute name to validate
     * @return bool True if valid
     */
    public static function validateAttributeName(string $name): bool
    {
        // Allow alphanumeric and underscore, must start with letter or underscore
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) === 1;
    }

    /**
     * Sanitize table name for safe use
     *
     * Removes potentially dangerous characters from table name.
     *
     * @param string $name Table name to sanitize
     * @return string Sanitized table name
     */
    public static function sanitizeTableName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\\\\.]/', '', $name);
    }

    /**
     * Sanitize class name for safe use
     *
     * Removes potentially dangerous characters from class name.
     *
     * @param string $name Class name to sanitize
     * @return string Sanitized class name
     */
    public static function sanitizeClassName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\\\\]/', '', $name);
    }

    /**
     * Check if string contains path traversal attempts
     *
     * Detects common path traversal patterns.
     *
     * @param string $path Path to check
     * @return bool True if path traversal detected
     */
    public static function hasPathTraversal(string $path): bool
    {
        // Check for common path traversal patterns
        $patterns = [
            '../',
            '..\\',
            '/..',
            '\\..',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate multiple table names
     *
     * Validates an array of table names.
     *
     * @param array $names Table names to validate
     * @return array Array of validation results [name => isValid]
     */
    public static function validateTableNames(array $names): array
    {
        $results = [];
        foreach ($names as $name) {
            $results[$name] = self::validateTableName($name);
        }

        return $results;
    }

    /**
     * Validate database table name
     *
     * Ensures table name contains only safe characters and prevents SQL injection.
     *
     * @param string $name Table name to validate
     * @return bool True if valid
     */
    public static function validateTableName(string $name): bool
    {
        // Allow alphanumeric, underscore, and dot (for schema.table format)
        // Dot is allowed for database prefixes like 'schema.table_name'
        return preg_match('/^[a-zA-Z0-9_\\\\.]+$/', $name) === 1;
    }

    /**
     * Get validation error message for invalid table name
     *
     * @param string $name Invalid table name
     * @return string Error message
     */
    public static function getTableNameError(string $name): string
    {
        return "Invalid table name '{$name}'. " .
            "Table names must contain only alphanumeric characters, underscores, and dots.";
    }

    /**
     * Get validation error message for invalid class name
     *
     * @param string $name Invalid class name
     * @return string Error message
     */
    public static function getClassNameError(string $name): string
    {
        return "Invalid class name '{$name}'. " .
            "Class names must start with a letter or underscore and contain only alphanumeric characters, underscores, and backslashes.";
    }

    /**
     * Get validation error message for invalid namespace
     *
     * @param string $namespace Invalid namespace
     * @return string Error message
     */
    public static function getNamespaceError(string $namespace): string
    {
        return "Invalid namespace '{$namespace}'. " .
            "Namespaces must follow PHP namespace conventions with segments separated by backslashes.";
    }

    /**
     * Get validation error message for invalid path
     *
     * @param string $path Invalid path
     * @param string $basePath Base path
     * @return string Error message
     */
    public static function getPathError(string $path, string $basePath): string
    {
        return "Invalid path '{$path}'. " .
            "Path must be within the project boundaries (base: {$basePath}).";
    }
}
