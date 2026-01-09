<?php

/**
 * MCP Server Example Runner
 *
 * This script demonstrates how to start the MCP server.
 * It can be run in two modes:
 * 1. As a standalone MCP server (default) - reads from stdin, writes to stdout
 * 2. As a test/demo mode - shows example messages
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Took\Yii2GiiMCP\Config\ServerConfig;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\MCPServer;
use Took\Yii2GiiMCP\ToolRegistry;
use Took\Yii2GiiMCP\Tools\GenerateController;
use Took\Yii2GiiMCP\Tools\GenerateCrud;
use Took\Yii2GiiMCP\Tools\GenerateExtension;
use Took\Yii2GiiMCP\Tools\GenerateForm;
use Took\Yii2GiiMCP\Tools\GenerateModel;
use Took\Yii2GiiMCP\Tools\GenerateModule;
use Took\Yii2GiiMCP\Tools\InspectDatabase;
use Took\Yii2GiiMCP\Tools\ListTables;

// Check if running in demo mode
$demoMode = in_array('--demo', $argv ?? []);
$debug = in_array('--debug', $argv ?? []);

if ($demoMode) {
    echo "=== MCP Server Demo Mode ===\n\n";
    echo "Example 1: Initialize Request\n";
    echo json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'clientInfo' => [
                    'name' => 'example-client',
                    'version' => '1.1.0',
                ],
                'capabilities' => [],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

    echo "Example 2: Tools List Request\n";
    echo json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

    echo "Example 3: Tool Call Request (list-tables)\n";
    echo json_encode([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list-tables',
                'arguments' => [
                    'detailed' => true,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

    echo "=== Configuration ===\n\n";
    echo "Required environment variables:\n";
    echo "  YII2_CONFIG_PATH - Path to Yii2 config file (e.g., /path/to/app/config/web.php)\n";
    echo "  YII2_APP_PATH    - Path to Yii2 application root (optional, inferred from config)\n\n";
    echo "To run the MCP server:\n";
    echo "  YII2_CONFIG_PATH=/path/to/config.php php examples/run.php\n\n";
    echo "To enable debug logging:\n";
    echo "  YII2_CONFIG_PATH=/path/to/config.php php examples/run.php --debug\n\n";
    exit(0);
}

// Load server configuration
$config = new ServerConfig();

// Validate configuration (only if Yii2 integration is needed)
// For basic MCP server without tools, this can be skipped
if (!empty($_ENV['YII2_CONFIG_PATH']) || !empty($_ENV['YII2_APP_PATH'])) {
    $errors = $config->validate();
    if (!empty($errors)) {
        fwrite(STDERR, "Configuration errors:\n");
        foreach ($errors as $error) {
            fwrite(STDERR, "  - {$error}\n");
        }
        fwrite(STDERR, "\nSet YII2_CONFIG_PATH environment variable to your Yii2 config file.\n");
        fwrite(STDERR, "Example: YII2_CONFIG_PATH=/path/to/app/config/web.php php examples/run.php\n\n");
        exit(1);
    }
}

// Create and configure the MCP server
$server = new MCPServer(debug: $debug);

// Create tool registry
$toolRegistry = new ToolRegistry();

// Register tools (only if Yii2 is configured)
if ($config->isValid()) {
    try {
        $bootstrap = Yii2Bootstrap::getInstance($config);

        // Register all available tools
        $toolRegistry->register(new ListTables($bootstrap));
        $toolRegistry->register(new InspectDatabase($bootstrap));
        $toolRegistry->register(new GenerateModel($bootstrap));
        $toolRegistry->register(new GenerateCrud($bootstrap));
        $toolRegistry->register(new GenerateController($bootstrap));
        $toolRegistry->register(new GenerateForm($bootstrap));
        $toolRegistry->register(new GenerateModule($bootstrap));
        $toolRegistry->register(new GenerateExtension($bootstrap));
    } catch (Throwable $e) {
        fwrite(STDERR, "Warning: Could not register Yii2 tools: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Server will start without Yii2 tools.\n\n");
    }
}

$server->setToolRegistry($toolRegistry);

// Start the server (runs until stdin closes)
$server->start();
