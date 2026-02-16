<?php

/**
 * MCP Server Configuration for Yii2 Templates
 *
 * This is a smart configuration file for the yii2-gii-mcp server.
 * It automatically detects your Yii2 project structure and loads
 * the appropriate configuration files.
 *
 * Supported Templates:
 * - Basic Template: Single /app directory with /config
 * - Advanced Template: /common, /console, /frontend or /frontpage, /backend or /backoffice directories
 * - Advanced + API: Advanced template with additional /api directory
 *
 * Usage:
 * 1. Copy this file to your Yii2 application root directory
 * 2. Rename it to config-mcp.php (or any name you prefer)
 * 3. Set YII2_CONFIG_PATH to point to this file
 * 4. Set YII2_APP_PATH to your application root directory
 *
 * Example:
 *   YII2_CONFIG_PATH=/path/to/your/app/config-mcp.php \
 *   YII2_APP_PATH=/path/to/your/app \
 *   php vendor/bin/yii2-gii-mcp
 *
 * Note: This file loads the Composer autoloader so that config files
 * can reference any required classes, but it does NOT bootstrap Yii2.
 * The MCP server handles Yii2 bootstrapping.
 */

// Always load the application's Composer autoloader to ensure all dependencies are available
// This is safe to call multiple times - Composer handles it gracefully
// NOTE: This assumes the config file is in your application root directory
require_once __DIR__ . '/vendor/autoload.php';

// Simple array merge function (don't need Yii for this)
if (! function_exists('mergeYii2Config')) {
    function mergeYii2Config(...$arrays)
    {
        $result = [];
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                    $result[$key] = mergeYii2Config($result[$key], $value);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}

// Helper function to safely load config files
if (! function_exists('loadConfigIfExists')) {
    function loadConfigIfExists($path)
    {
        if (file_exists($path)) {
            fwrite(STDERR, "[MCP Config] Loading: $path\n");

            return require $path;
        }
        fwrite(STDERR, "[MCP Config] Skipping (not found): $path\n");

        return [];
    }
}

// Helper function to detect YII_DEBUG and YII_ENV from entry point files
if (! function_exists('detectYiiConstantsFromFile')) {
    /**
     * Detect YII_DEBUG and YII_ENV constants from an entry point file.
     *
     * @param string $filepath Path to the entry point file
     * @return array|null Array with 'debug' and 'env' keys, or null if file doesn't exist
     */
    function detectYiiConstantsFromFile($filepath)
    {
        if (! file_exists($filepath)) {
            return null;
        }

        $content = file_get_contents($filepath);
        $result = [];

        // Detect YII_DEBUG - match: defined('YII_DEBUG') or define('YII_DEBUG', true/false);
        if (preg_match("/define\s*\(\s*['\"]YII_DEBUG['\"]\s*,\s*(true|false)\s*\)/i", $content, $matches)) {
            $result['debug'] = strtolower($matches[1]) === 'true';
        }

        // Detect YII_ENV - match: defined('YII_ENV') or define('YII_ENV', 'dev');
        if (preg_match("/define\s*\(\s*['\"]YII_ENV['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $matches)) {
            $result['env'] = $matches[1];
        }

        return ! empty($result) ? $result : null;
    }
}

// Helper function to detect constants from all entry points
if (! function_exists('detectYiiConstantsFromEntryPoints')) {
    /**
     * Detect YII_DEBUG and YII_ENV from entry points in priority order.
     *
     * @return array Array with 'debug' and 'env' keys
     */
    function detectYiiConstantsFromEntryPoints()
    {
        // Default values if nothing is detected
        $defaults = ['debug' => false, 'env' => 'mcp'];

        // Entry points in priority order
        $entryPoints = [
            'Console' => __DIR__ . '/yii',
            'Basic Web' => __DIR__ . '/web/index.php',
            'Frontend' => __DIR__ . '/frontend/web/index.php',
            'Frontpage' => __DIR__ . '/frontpage/web/index.php',
            'Backend' => __DIR__ . '/backend/web/index.php',
            'Backoffice' => __DIR__ . '/backoffice/web/index.php',
            'API' => __DIR__ . '/api/web/index.php',
        ];

        $allDetected = [];
        $firstDetected = null;

        fwrite(STDERR, "[MCP Config] Detecting YII_DEBUG and YII_ENV from entry points...\n");

        foreach ($entryPoints as $name => $path) {
            $detected = detectYiiConstantsFromFile($path);
            if ($detected !== null) {
                $allDetected[$name] = [
                    'path' => $path,
                    'constants' => $detected,
                ];

                if ($firstDetected === null) {
                    $firstDetected = ['name' => $name, 'constants' => $detected];
                }

                $debugStr = isset($detected['debug']) ? ($detected['debug'] ? 'true' : 'false') : 'not set';
                $envStr = isset($detected['env']) ? "'{$detected['env']}'" : 'not set';
                fwrite(STDERR, "[MCP Config]   $name: YII_DEBUG=$debugStr, YII_ENV=$envStr\n");
            }
        }

        if (empty($allDetected)) {
            fwrite(STDERR, "[MCP Config] No constants detected in entry points, using defaults: YII_DEBUG=false, YII_ENV='mcp'\n");

            return $defaults;
        }

        // Check if values differ across entry points
        if (count($allDetected) > 1) {
            $hasConflict = false;
            $firstDebug = $firstDetected['constants']['debug'] ?? null;
            $firstEnv = $firstDetected['constants']['env'] ?? null;

            foreach ($allDetected as $data) {
                $debug = $data['constants']['debug'] ?? null;
                $env = $data['constants']['env'] ?? null;

                if (($debug !== null && $firstDebug !== null && $debug !== $firstDebug) ||
                    ($env !== null && $firstEnv !== null && $env !== $firstEnv)) {
                    $hasConflict = true;
                    break;
                }
            }

            if ($hasConflict) {
                fwrite(STDERR, "[MCP Config] WARNING: Different constants detected across entry points!\n");
            }
        }

        // Use first detected values, merge with defaults for any missing values
        $result = array_merge($defaults, $firstDetected['constants']);
        $debugStr = $result['debug'] ? 'true' : 'false';
        fwrite(STDERR, "[MCP Config] Using: YII_DEBUG=$debugStr, YII_ENV='{$result['env']}' (from {$firstDetected['name']})\n");

        return $result;
    }
}

// Detect and set common constants
$detectedConstants = detectYiiConstantsFromEntryPoints();
defined('YII_DEBUG') or define('YII_DEBUG', $detectedConstants['debug']);
defined('YII_ENV') or define('YII_ENV', $detectedConstants['env']);

// Detect Yii2 template type
$isBasicTemplate = is_dir(__DIR__ . '/app') && is_dir(__DIR__ . '/config');
$isAdvancedTemplate = is_dir(__DIR__ . '/common') && is_dir(__DIR__ . '/console');
$hasApiDirectory = is_dir(__DIR__ . '/api');
$hasFrontend = is_dir(__DIR__ . '/frontend');
$hasFrontpage = is_dir(__DIR__ . '/frontpage');
$hasBackend = is_dir(__DIR__ . '/backend');
$hasBackoffice = is_dir(__DIR__ . '/backoffice');

fwrite(STDERR, "[MCP Config] Detecting Yii2 template structure...\n");

// Build configuration based on detected template
$configFiles = [];

if ($isBasicTemplate) {
    // Basic Template Structure
    fwrite(STDERR, "[MCP Config] Detected: Basic Template\n");
    $configFiles = [
        __DIR__ . '/config/web.php',
        __DIR__ . '/config/web-local.php',
        __DIR__ . '/config/console.php',
        __DIR__ . '/config/console-local.php',
    ];
} elseif ($isAdvancedTemplate) {
    // Advanced Template Structure
    fwrite(STDERR, "[MCP Config] Detected: Advanced Template");
    if ($hasApiDirectory) {
        fwrite(STDERR, " + API\n");
    } else {
        fwrite(STDERR, "\n");
    }

    // Always load common config
    $configFiles = [
        __DIR__ . '/common/config/main.php',
        __DIR__ . '/common/config/main-local.php',
    ];

    // Load console config (needed for Gii)
    $configFiles[] = __DIR__ . '/console/config/main.php';
    $configFiles[] = __DIR__ . '/console/config/main-local.php';

    // Load frontend or frontpage config if exists
    if ($hasFrontend) {
        fwrite(STDERR, "[MCP Config] Including: frontend\n");
        $configFiles[] = __DIR__ . '/frontend/config/main.php';
        $configFiles[] = __DIR__ . '/frontend/config/main-local.php';
    } elseif ($hasFrontpage) {
        fwrite(STDERR, "[MCP Config] Including: frontpage\n");
        $configFiles[] = __DIR__ . '/frontpage/config/main.php';
        $configFiles[] = __DIR__ . '/frontpage/config/main-local.php';
    }

    // Load backend or backoffice config if exists
    if ($hasBackend) {
        fwrite(STDERR, "[MCP Config] Including: backend\n");
        $configFiles[] = __DIR__ . '/backend/config/main.php';
        $configFiles[] = __DIR__ . '/backend/config/main-local.php';
    } elseif ($hasBackoffice) {
        fwrite(STDERR, "[MCP Config] Including: backoffice\n");
        $configFiles[] = __DIR__ . '/backoffice/config/main.php';
        $configFiles[] = __DIR__ . '/backoffice/config/main-local.php';
    }

    // Load API config if exists
    if ($hasApiDirectory) {
        fwrite(STDERR, "[MCP Config] Including: api\n");
        $configFiles[] = __DIR__ . '/api/config/main.php';
        $configFiles[] = __DIR__ . '/api/config/main-local.php';
    }
} else {
    // Unknown structure - try to provide helpful error
    fwrite(STDERR, "[MCP Config] ERROR: Could not detect Yii2 template structure!\n");
    fwrite(STDERR, "[MCP Config] Expected either:\n");
    fwrite(STDERR, "[MCP Config]   - Basic Template: /app and /config directories\n");
    fwrite(STDERR, "[MCP Config]   - Advanced Template: /common and /console directories\n");
    fwrite(STDERR, "[MCP Config] Current directory: " . __DIR__ . "\n");
    fwrite(STDERR, "[MCP Config] Please ensure this file is in your Yii2 application root.\n");

    throw new \RuntimeException('Could not detect Yii2 template structure');
}

// Load and merge all configuration files
$loadedConfigs = [];
foreach ($configFiles as $configFile) {
    $loaded = loadConfigIfExists($configFile);
    if (! empty($loaded)) {
        $loadedConfigs[] = $loaded;
    }
}

if (empty($loadedConfigs)) {
    fwrite(STDERR, "[MCP Config] ERROR: No configuration files could be loaded!\n");

    throw new \RuntimeException('No valid Yii2 configuration files found');
}

fwrite(STDERR, "[MCP Config] Successfully loaded " . count($loadedConfigs) . " configuration file(s)\n");

// Merge all configurations
$config = mergeYii2Config(...$loadedConfigs);

// Set up Yii2 path aliases for advanced template
if (! isset($config['aliases'])) {
    $config['aliases'] = [];
}

// Add path aliases for backend/backoffice, frontend/frontpage, common, and console
// This is crucial for class autoloading to work properly
if ($hasBackend) {
    $config['aliases']['@backend'] = __DIR__ . '/backend';
} elseif ($hasBackoffice) {
    $config['aliases']['@backend'] = __DIR__ . '/backoffice';
    $config['aliases']['@backoffice'] = __DIR__ . '/backoffice';
}

if ($hasFrontend) {
    $config['aliases']['@frontend'] = __DIR__ . '/frontend';
} elseif ($hasFrontpage) {
    $config['aliases']['@frontend'] = __DIR__ . '/frontpage';
    $config['aliases']['@frontpage'] = __DIR__ . '/frontpage';
}

$config['aliases']['@common'] = __DIR__ . '/common';
$config['aliases']['@console'] = __DIR__ . '/console';

if ($hasApiDirectory) {
    $config['aliases']['@api'] = __DIR__ . '/api';
}

$aliases = ['@common', '@console'];
if ($hasBackend) {
    $aliases[] = '@backend';
} elseif ($hasBackoffice) {
    $aliases[] = '@backoffice (@backend)';
}
if ($hasFrontend) {
    $aliases[] = '@frontend';
} elseif ($hasFrontpage) {
    $aliases[] = '@frontpage (@frontend)';
}
if ($hasApiDirectory) {
    $aliases[] = '@api';
}
fwrite(STDERR, "[MCP Config] Path aliases configured: " . implode(', ', $aliases) . "\n");

// Ensure Gii module is enabled for MCP server
if (! isset($config['modules']['gii'])) {
    fwrite(STDERR, "[MCP Config] WARNING: Gii module not found in config, adding default configuration\n");
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        'allowedIPs' => ['*'],
    ];
}

return $config;
