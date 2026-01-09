<?php

/**
 * MCP Configuration for Docker Environments
 *
 * This configuration is designed for use with Docker Desktop.
 * Copy this file to your project root as config-mcp.php and adjust settings.
 *
 * IMPORTANT: Add config-mcp.php to your .gitignore!
 */

// Auto-detect project structure
$basePath = dirname(__DIR__, 2);

// Detect Yii2 template type
$isAdvanced = file_exists($basePath . '/frontend') &&
    file_exists($basePath . '/backend') &&
    file_exists($basePath . '/common');

// Database configuration for Docker
// Choose appropriate host based on your setup:
// - Host-based MCP: Use '127.0.0.1' (Docker exposes port 3306 to host)
// - Container-based MCP: Use 'mysql' or your Docker service name

$config = [
    'id' => 'mcp-console',
    'basePath' => $basePath,
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',

            // Option A: Host-based MCP (recommended)
            'dsn' => 'mysql:host=127.0.0.1;dbname=yii2_app',

            // Option B: Container-based MCP (uncomment if running inside Docker)
            // 'dsn' => 'mysql:host=mysql;dbname=yii2_app',  // Use Docker service name

            'username' => 'root',
            'password' => 'secret',  // CHANGE THIS!
            'charset' => 'utf8',
            'enableSchemaCache' => false,  // Disable for development
        ],
    ],
    'modules' => [
        'gii' => [
            'class' => 'yii\gii\Module',
        ],
    ],
];

// For Advanced Template: Load common config if available
if ($isAdvanced && file_exists($basePath . '/common/config/main.php')) {
    $commonConfig = require($basePath . '/common/config/main.php');

    // Merge database config from common if exists
    if (isset($commonConfig['components']['db'])) {
        // Override with Docker-specific host if needed
        $dbConfig = $commonConfig['components']['db'];

        // Parse existing DSN to replace host
        if (isset($dbConfig['dsn'])) {
            $dsn = $dbConfig['dsn'];
            // For host-based: use 127.0.0.1
            $dsn = preg_replace('/host=[^;]+/', 'host=127.0.0.1', $dsn);
            // For container-based: use service name (uncomment below)
            // $dsn = preg_replace('/host=[^;]+/', 'host=mysql', $dsn);

            $config['components']['db']['dsn'] = $dsn;
            $config['components']['db']['username'] = $dbConfig['username'] ?? 'root';
            $config['components']['db']['password'] = $dbConfig['password'] ?? '';
            $config['components']['db']['charset'] = $dbConfig['charset'] ?? 'utf8';
        }
    }
}

return $config;
