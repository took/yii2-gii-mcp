<?php

/**
 * Simple test script for MCP Server
 *
 * This script demonstrates testing the MCP server by sending
 * JSON-RPC messages and capturing responses.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Took\Yii2GiiMCP\Protocol\ErrorResponse;
use Took\Yii2GiiMCP\Protocol\Request;
use Took\Yii2GiiMCP\Protocol\Response;

echo "=== MCP Protocol Classes Test ===\n\n";

// Test 1: Create and serialize a Request
echo "Test 1: Creating a JSON-RPC Request\n";
$request = new Request(1, 'initialize', [
    'protocolVersion' => '2024-11-05',
    'clientInfo' => ['name' => 'test-client'],
]);
echo $request->toJson() . "\n\n";

// Test 2: Parse a Request from JSON
echo "Test 2: Parsing a JSON-RPC Request\n";
$json = '{"jsonrpc":"2.0","id":2,"method":"tools/list"}';
$parsed = Request::fromJson($json);
echo "Method: " . $parsed->getMethod() . "\n";
echo "ID: " . $parsed->getId() . "\n\n";

// Test 3: Create a Response
echo "Test 3: Creating a JSON-RPC Response\n";
$response = new Response(1, [
    'protocolVersion' => '2024-11-05',
    'serverInfo' => ['name' => 'yii2-gii-mcp', 'version' => '1.1.0'],
]);
echo $response->toJson() . "\n\n";

// Test 4: Create an Error Response
echo "Test 4: Creating a JSON-RPC Error Response\n";
$error = ErrorResponse::methodNotFound(3, 'unknown/method');
echo $error->toJson() . "\n\n";

// Test 5: Test various error types
echo "Test 5: Standard Error Responses\n";
echo "Parse Error: " . ErrorResponse::parseError('Invalid JSON')->toJson() . "\n";
echo "Invalid Request: " . ErrorResponse::invalidRequest(null, 'Missing method')->toJson() . "\n";
echo "Invalid Params: " . ErrorResponse::invalidParams(4, 'Missing required parameter')->toJson() . "\n\n";

echo "=== Test Complete ===\n";
echo "\nTo test the full server, pipe JSON-RPC messages:\n";
echo "echo '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{\"protocolVersion\":\"2024-11-05\"}}' | php examples/run.php\n";
