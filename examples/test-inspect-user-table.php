#!/usr/bin/env php
<?php

/**
 * Test script to demonstrate MCP server usage: Inspect user table
 *
 * This script shows how to:
 * 1. Start the MCP server programmatically
 * 2. Initialize the MCP protocol connection
 * 3. Call the inspect-database tool for the user table
 * 4. Parse and display the results
 *
 * Usage:
 *   php examples/test-inspect-user-table.php
 *
 * Requirements:
 *   - YII2_CONFIG_PATH environment variable must be set
 *   - YII2_APP_PATH environment variable should be set (optional)
 *
 * Example:
 *   YII2_CONFIG_PATH=/path/to/config-mcp.php php examples/test-inspect-user-table.php
 */

// Configuration
$serverPath = __DIR__ . '/../bin/yii2-gii-mcp';
$configPath = getenv('YII2_CONFIG_PATH');
$appPath = getenv('YII2_APP_PATH') ?: dirname($configPath);

// Validate configuration
if (empty($configPath)) {
    die("ERROR: YII2_CONFIG_PATH environment variable must be set.\n\n" .
            "Example:\n" .
            "  YII2_CONFIG_PATH=/path/to/config-mcp.php php examples/test-inspect-user-table.php\n");
}

if (! file_exists($configPath)) {
    die("ERROR: Configuration file not found: $configPath\n");
}

if (! file_exists($serverPath)) {
    die("ERROR: MCP server executable not found: $serverPath\n");
}

echo "Starting MCP server test...\n";
echo "Config: $configPath\n";
echo "App Path: $appPath\n\n";

// Start the MCP server process
$descriptorspec = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
];

$env = [
        'YII2_CONFIG_PATH' => $configPath,
        'YII2_APP_PATH' => $appPath,
];

$process = proc_open("php $serverPath", $descriptorspec, $pipes, $appPath, $env);

if (! is_resource($process)) {
    die("ERROR: Failed to start MCP server\n");
}

// Step 1: Initialize the server
echo "Sending initialize request...\n";
$initRequest = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
                'clientInfo' => [
                        'name' => 'test-client',
                        'version' => '1.0',
                ],
        ],
]);

fwrite($pipes[0], $initRequest . "\n");
fflush($pipes[0]);

// Read initialization response
$initResponse = fgets($pipes[1]);
$initData = json_decode($initResponse, true);

if (isset($initData['error'])) {
    echo "ERROR: Initialization failed\n";
    echo "Error: " . json_encode($initData['error'], JSON_PRETTY_PRINT) . "\n";
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    exit(1);
}

echo "✓ Server initialized successfully\n";
echo "  Server: {$initData['result']['serverInfo']['name']} v{$initData['result']['serverInfo']['version']}\n";
echo "  Protocol: {$initData['result']['protocolVersion']}\n\n";

// Step 2: Call inspect-database tool for user table
echo "Calling inspect-database tool for 'user' table...\n";
$inspectRequest = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
                'name' => 'inspect-database',
                'arguments' => [
                        'tablePattern' => 'user',
                ],
        ],
]);

fwrite($pipes[0], $inspectRequest . "\n");
fflush($pipes[0]);

// Read inspect-database response
$inspectResponse = fgets($pipes[1]);
$response = json_decode($inspectResponse, true);

if (isset($response['error'])) {
    echo "ERROR: inspect-database call failed\n";
    echo "Error: " . json_encode($response['error'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "✓ Successfully retrieved user table structure\n\n";
    echo str_repeat('=', 80) . "\n";
    echo "USER TABLE STRUCTURE\n";
    echo str_repeat('=', 80) . "\n\n";

    if (isset($response['result']['content'][0]['text'])) {
        echo $response['result']['content'][0]['text'] . "\n";
    } else {
        echo json_encode($response['result'], JSON_PRETTY_PRINT) . "\n";
    }
}

// Close pipes and process
fclose($pipes[0]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);

if (! empty($stderr)) {
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "SERVER DEBUG OUTPUT (stderr)\n";
    echo str_repeat('=', 80) . "\n";
    echo $stderr . "\n";
}

proc_close($process);

echo "\nTest completed successfully!\n";
