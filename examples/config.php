<?php

/**
 * Example Yii2 Configuration for MCP Server
 *
 * This is a minimal Yii2 configuration file for testing the MCP server.
 * In production, point the MCP server to your actual Yii2 application config.
 */

return [
    'id' => 'yii2-gii-mcp-example',
    'basePath' => dirname(__DIR__),

    // Application name
    'name' => 'Yii2 Gii MCP Example',

    // Components
    'components' => [
        // Database connection
        'db' => [
            'class' => 'yii\db\Connection',

            // IMPORTANT: Update these settings for your database
            'dsn' => 'mysql:host=localhost;dbname=your_database',
            'username' => 'your_username',
            'password' => 'your_password',
            'charset' => 'utf8mb4',

            // Enable schema caching for better performance
            'enableSchemaCache' => true,
            'schemaCacheDuration' => 3600,
            'schemaCache' => 'cache',
        ],

        // Cache component
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
    ],

    // Gii module configuration
    'modules' => [
        'gii' => [
            'class' => 'yii\gii\Module',
            'allowedIPs' => ['*'], // Allow all IPs for MCP server
        ],
    ],

    // Bootstrap Gii module
    'bootstrap' => ['gii'],
];
