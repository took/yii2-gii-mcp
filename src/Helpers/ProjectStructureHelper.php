<?php

namespace Took\Yii2GiiMCP\Helpers;

/**
 * Project Structure Helper
 *
 * Utility methods for detecting and analyzing Yii2 project structure including:
 * - Template type detection (Basic/Advanced/Advanced+API)
 * - Application directory discovery
 * - Module detection
 * - Environment configuration analysis (init system, config files, .env)
 */
class ProjectStructureHelper
{
    /**
     * Detect Yii2 template type
     *
     * @param string $basePath Base path to scan
     * @return string 'basic', 'advanced', or 'advanced-api'
     */
    public static function detectTemplateType(string $basePath): string
    {
        // Check for Advanced Template structure
        // Advanced Template has /common and /console directories
        if (is_dir($basePath . '/common') && is_dir($basePath . '/console')) {
            // Check for Advanced + API variant
            if (is_dir($basePath . '/api')) {
                return 'advanced-api';
            }

            return 'advanced';
        }

        // Check for Basic Template structure
        // Basic Template has /app and /config directories
        if (is_dir($basePath . '/app') && is_dir($basePath . '/config')) {
            return 'basic';
        }

        // Default to basic if structure is unclear
        return 'basic';
    }

    /**
     * Find application directories based on template type
     *
     * @param string $basePath Base path to scan
     * @return array<string, string> Application name => path mapping
     */
    public static function findApplicationDirs(string $basePath): array
    {
        $templateType = self::detectTemplateType($basePath);
        $applications = [];

        if ($templateType === 'basic') {
            // Basic template has single app directory
            if (is_dir($basePath . '/app')) {
                $applications['app'] = $basePath . '/app';
            }
        } else {
            // Advanced template - scan for common application directories
            $possibleApps = ['frontend', 'backend', 'console', 'api'];

            foreach ($possibleApps as $appName) {
                $appPath = $basePath . '/' . $appName;
                if (is_dir($appPath)) {
                    $applications[$appName] = $appPath;
                }
            }
        }

        return $applications;
    }

    /**
     * Check if a directory is a valid Yii2 application
     *
     * @param string $path Path to check
     * @return bool True if valid Yii2 application
     */
    public static function isYii2Application(string $path): bool
    {
        // Check for config directory
        if (is_dir($path . '/config')) {
            return true;
        }

        // Check for web entry point
        if (file_exists($path . '/web/index.php')) {
            return true;
        }

        // Check for console entry point
        if (file_exists($path . '/yii')) {
            return true;
        }

        return false;
    }

    /**
     * Determine application type
     *
     * @param string $path Path to application directory
     * @return string 'web', 'console', 'api', or 'unknown'
     */
    public static function getApplicationType(string $path): string
    {
        $dirName = basename($path);

        // Check for API by directory name
        if ($dirName === 'api') {
            return 'api';
        }

        // Check for console by directory name
        if ($dirName === 'console') {
            return 'console';
        }

        // Check for web application by presence of web directory
        if (is_dir($path . '/web') || is_dir($path . '/www')) {
            return 'web';
        }

        // Check for console by presence of yii script
        if (file_exists($path . '/yii')) {
            return 'console';
        }

        return 'unknown';
    }

    /**
     * Find modules in application
     *
     * @param string $appPath Application path
     * @return array<array{id: string, path: string, class: string|null}> Module information
     */
    public static function findModules(string $appPath): array
    {
        $modules = [];

        // Scan for modules directory
        $moduleDirs = [
            $appPath . '/modules',
            dirname($appPath) . '/common/modules', // For advanced template
        ];

        foreach ($moduleDirs as $moduleDir) {
            if (! is_dir($moduleDir)) {
                continue;
            }

            $items = scandir($moduleDir);
            if ($items === false) {
                continue;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $modulePath = $moduleDir . '/' . $item;
                if (! is_dir($modulePath)) {
                    continue;
                }

                // Check if it looks like a module
                if (file_exists($modulePath . '/Module.php')) {
                    $modules[] = [
                        'id' => $item,
                        'path' => $modulePath,
                        'class' => null, // Would need to parse Module.php to get class name
                    ];
                }
            }
        }

        return $modules;
    }

    /**
     * Detect available and current environments
     *
     * Analyzes:
     * - environments/ folder (Advanced template init system)
     * - index.php files (YII_ENV and YII_DEBUG constants)
     * - Config file patterns (*-local.php, *-prod.php, etc.)
     * - .env files
     *
     * @param string $basePath Base path to scan
     * @return array{available: array, current: string|null, currentDetails: array, sources: array} Environment information
     */
    public static function detectEnvironments(string $basePath): array
    {
        $result = [
            'available' => [],
            'current' => null,
            'currentDetails' => [],
            'sources' => [],
        ];

        // Check for environments folder (Advanced template)
        $environmentsPath = $basePath . '/environments';
        if (is_dir($environmentsPath)) {
            $result['available'] = self::scanEnvironmentsFolder($basePath);
            $result['sources'][] = 'environments-folder';

            // Try to detect current environment from index.php files
            $currentEnv = self::detectCurrentEnvironment($basePath);
            if ($currentEnv) {
                $result['current'] = $currentEnv['environment'];
                $result['currentDetails'] = $currentEnv['details'];
            }
        }

        // Scan for config file patterns
        $configEnvs = self::detectEnvironmentsFromConfigFiles($basePath);
        if (! empty($configEnvs)) {
            $result['available'] = array_unique(array_merge($result['available'], $configEnvs));
            $result['sources'][] = 'config-files';
        }

        // Check for .env files
        if (self::hasEnvFiles($basePath)) {
            $result['sources'][] = 'env-files';
        }

        return $result;
    }

    /**
     * Scan environments folder for available environment configs
     *
     * @param string $basePath Base path
     * @return array<string> Environment names
     */
    public static function scanEnvironmentsFolder(string $basePath): array
    {
        $environmentsPath = $basePath . '/environments';
        if (! is_dir($environmentsPath)) {
            return [];
        }

        $environments = [];
        $items = scandir($environmentsPath);
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $envPath = $environmentsPath . '/' . $item;
            if (is_dir($envPath)) {
                $environments[] = $item;
            }
        }

        return $environments;
    }

    /**
     * Detect current environment by comparing index.php files
     *
     * @param string $basePath Base path
     * @return array{environment: string, details: array}|null Environment info or null
     */
    public static function detectCurrentEnvironment(string $basePath): ?array
    {
        // Paths to check for index files
        $indexPaths = [
            'frontend/web/index.php',
            'backend/web/index.php',
            'api/web/index.php',
        ];

        $envData = null;

        foreach ($indexPaths as $indexPath) {
            $fullPath = $basePath . '/' . $indexPath;
            if (! file_exists($fullPath)) {
                continue;
            }

            // Parse the index file
            $parsed = self::parseIndexPhpFile($fullPath);
            if (! empty($parsed)) {
                $envData = [
                    'YII_ENV' => $parsed['YII_ENV'] ?? null,
                    'YII_DEBUG' => $parsed['YII_DEBUG'] ?? null,
                    'detectedFrom' => $indexPath,
                ];

                // Try to match with environment templates
                $matchedEnv = self::compareIndexFiles($fullPath, $basePath);
                if ($matchedEnv) {
                    return [
                        'environment' => $matchedEnv,
                        'details' => $envData,
                    ];
                }

                // If no template match, use YII_ENV value
                if (isset($parsed['YII_ENV'])) {
                    return [
                        'environment' => $parsed['YII_ENV'],
                        'details' => $envData,
                    ];
                }
            }
        }

        if ($envData) {
            return [
                'environment' => 'unknown',
                'details' => $envData,
            ];
        }

        return null;
    }

    /**
     * Parse index.php file for YII_ENV and YII_DEBUG constants
     *
     * @param string $filePath Path to index.php
     * @return array{YII_ENV: string|null, YII_DEBUG: bool|null} Parsed values
     */
    public static function parseIndexPhpFile(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $result = [
            'YII_ENV' => null,
            'YII_DEBUG' => null,
        ];

        // Parse YII_ENV using regex
        if (preg_match("/defined\s*\(\s*'YII_ENV'\s*\)\s*or\s+define\s*\(\s*'YII_ENV'\s*,\s*'([^']+)'\s*\)/", $content, $matches)) {
            $result['YII_ENV'] = $matches[1];
        }

        // Parse YII_DEBUG using regex
        if (preg_match("/defined\s*\(\s*'YII_DEBUG'\s*\)\s*or\s+define\s*\(\s*'YII_DEBUG'\s*,\s*(true|false)\s*\)/", $content, $matches)) {
            $result['YII_DEBUG'] = $matches[1] === 'true';
        }

        return $result;
    }

    /**
     * Compare actual index file with environment templates
     *
     * @param string $actualFile Path to actual index.php
     * @param string $basePath Base path
     * @return string|null Matched environment name or null
     */
    public static function compareIndexFiles(string $actualFile, string $basePath): ?string
    {
        $environmentsPath = $basePath . '/environments';
        if (! is_dir($environmentsPath)) {
            return null;
        }

        $actualContent = file_get_contents($actualFile);
        if ($actualContent === false) {
            return null;
        }

        // Normalize actual file path to relative path
        $relativePath = str_replace($basePath . '/', '', $actualFile);

        // Get available environments
        $environments = self::scanEnvironmentsFolder($basePath);

        foreach ($environments as $env) {
            $templateFile = $environmentsPath . '/' . $env . '/' . $relativePath;
            if (! file_exists($templateFile)) {
                continue;
            }

            $templateContent = file_get_contents($templateFile);
            if ($templateContent === false) {
                continue;
            }

            // Simple comparison - check if key parts match
            $actualParsed = self::parseIndexPhpFile($actualFile);
            $templateParsed = self::parseIndexPhpFile($templateFile);

            if ($actualParsed['YII_ENV'] === $templateParsed['YII_ENV'] &&
                $actualParsed['YII_DEBUG'] === $templateParsed['YII_DEBUG']) {
                return $env;
            }
        }

        return null;
    }

    /**
     * Detect environments from config file patterns
     *
     * @param string $basePath Base path
     * @return array<string> Environment names
     */
    public static function detectEnvironmentsFromConfigFiles(string $basePath): array
    {
        $environments = [];
        $patterns = ['local', 'prod', 'dev', 'test', 'staging'];

        $searchPaths = [
            $basePath . '/config',
            $basePath . '/common/config',
            $basePath . '/frontend/config',
            $basePath . '/backend/config',
            $basePath . '/console/config',
            $basePath . '/api/config',
        ];

        foreach ($searchPaths as $searchPath) {
            if (! is_dir($searchPath)) {
                continue;
            }

            $files = scandir($searchPath);
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                foreach ($patterns as $pattern) {
                    if (strpos($file, '-' . $pattern . '.php') !== false) {
                        $environments[] = $pattern;
                    }
                }
            }
        }

        return array_unique($environments);
    }

    /**
     * Check for .env files
     *
     * @param string $basePath Base path
     * @return bool True if .env files found
     */
    public static function hasEnvFiles(string $basePath): bool
    {
        $envFiles = [
            '.env',
            '.env.local',
            '.env.prod',
            '.env.dev',
            '.env.test',
            '.env.staging',
        ];

        foreach ($envFiles as $envFile) {
            if (file_exists($basePath . '/' . $envFile)) {
                return true;
            }
        }

        return false;
    }
}
