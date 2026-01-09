<?php

namespace Tests\Functional;

use JsonException;
use Tests\FunctionalTester;
use Tests\Support\MockSimpleTool;
use Took\Yii2GiiMCP\MCPServer;
use Took\Yii2GiiMCP\Protocol\ErrorResponse;
use Took\Yii2GiiMCP\Protocol\Request;
use Took\Yii2GiiMCP\Protocol\StdioTransport;
use Took\Yii2GiiMCP\ToolRegistry;
use Took\Yii2GiiMCP\Tools\AbstractTool;

/**
 * Functional tests for MCP JSON-RPC Protocol
 *
 * Tests the complete MCP server API using simulated stdin/stdout streams.
 * These tests do NOT require Yii2 - they test the protocol layer with mock tools.
 */
class MCPProtocolCest
{
    /**
     * Test server initialization with protocol negotiation
     */
    public function testInitialize(FunctionalTester $I): void
    {
        // Arrange: Create memory streams
        $stdin = fopen('php://temp', 'r+');
        $stdout = fopen('php://temp', 'r+');
        $stderr = fopen('php://temp', 'r+');

        // Write initialize request
        $request = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'clientInfo' => [
                    'name' => 'test-client',
                    'version' => '1.1.0',
                ],
                'capabilities' => [],
            ],
        ]);
        fwrite($stdin, $request . "\n");
        rewind($stdin);

        // Act: Create server and process message
        $transport = new StdioTransport($stdin, $stdout, $stderr, false);
        $server = new MCPServer($transport, false);
        $registry = new ToolRegistry();
        $server->setToolRegistry($registry);

        // Process one message
        $message = $transport->readMessage();
        $I->assertNotNull($message);

        // Process through server's internal handler (simulate)
        $request = Request::fromJson($message);
        $I->assertEquals('initialize', $request->getMethod());

        // Close streams
        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    /**
     * Test tools/list with mock tools
     */
    public function testToolsList(FunctionalTester $I): void
    {
        // Arrange: Create registry with mock tool
        $registry = new ToolRegistry();
        $registry->register(new MockSimpleTool());

        // Act: Get tools list
        $tools = $registry->list();

        // Assert: Verify tool is listed with correct metadata
        $I->assertIsArray($tools);
        $I->assertCount(1, $tools);
        $I->assertEquals('mock-simple', $tools[0]['name']);
        $I->assertEquals('A simple mock tool for testing the MCP protocol', $tools[0]['description']);
        $I->assertArrayHasKey('inputSchema', $tools[0]);
    }

    /**
     * Test tools/call with mock tool
     */
    public function testToolsCall(FunctionalTester $I): void
    {
        // Arrange: Create mock tool
        $tool = new MockSimpleTool();

        // Act: Execute tool
        $result = $tool->execute(['message' => 'test message']);

        // Assert: Verify result format
        $I->assertIsArray($result);
        $I->assertArrayHasKey('type', $result);
        $I->assertArrayHasKey('text', $result);
        $I->assertEquals('text', $result['type']);
        $I->assertStringContainsString('test message', $result['text']);
    }

    /**
     * Test method not found error
     */
    public function testMethodNotFound(FunctionalTester $I): void
    {
        // Arrange: Create error response
        $error = ErrorResponse::methodNotFound(1, 'invalid/method');

        // Act: Convert to array
        $errorArray = $error->toArray();

        // Assert: Verify error structure
        $I->assertIsArray($errorArray);
        $I->assertEquals('2.0', $errorArray['jsonrpc']);
        $I->assertEquals(1, $errorArray['id']);
        $I->assertArrayHasKey('error', $errorArray);
        $I->assertEquals(-32601, $errorArray['error']['code']);
        $I->assertEquals('Method not found', $errorArray['error']['message']);
    }

    /**
     * Test invalid JSON parse error
     */
    public function testInvalidJson(FunctionalTester $I): void
    {
        // Arrange: Invalid JSON string
        $invalidJson = '{invalid json';

        // Act & Assert: Expect JsonException
        $I->expectThrowable(JsonException::class, function () use ($invalidJson) {
            Request::fromJson($invalidJson);
        });
    }

    /**
     * Test server capabilities in initialize response
     */
    public function testServerCapabilities(FunctionalTester $I): void
    {
        // Arrange: Create server
        $stdin = fopen('php://temp', 'r+');
        $stdout = fopen('php://temp', 'r+');
        $stderr = fopen('php://temp', 'r+');

        $transport = new StdioTransport($stdin, $stdout, $stderr, false);
        $server = new MCPServer($transport, false);
        $registry = new ToolRegistry();
        $server->setToolRegistry($registry);

        // Act: Get capabilities
        $capabilities = $server->getServerCapabilities();

        // Assert: Verify capabilities structure
        $I->assertIsArray($capabilities);
        $I->assertArrayHasKey('tools', $capabilities);
        $I->assertArrayHasKey('listChanged', $capabilities['tools']);
        $I->assertFalse($capabilities['tools']['listChanged']);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    /**
     * Test tool registry with multiple mock tools
     */
    public function testMultipleTools(FunctionalTester $I): void
    {
        // Arrange: Create registry with multiple mock tools
        $registry = new ToolRegistry();
        $tool1 = new MockSimpleTool();

        // Create second mock inline
        $tool2 = new class () extends AbstractTool {
            public function getName(): string
            {
                return 'mock-two';
            }

            public function getDescription(): string
            {
                return 'Second mock';
            }

            public function getInputSchema(): array
            {
                return ['type' => 'object'];
            }

            protected function doExecute(array $arguments): array
            {
                return $this->createResult('Second tool result');
            }
        };

        $registry->register($tool1);
        $registry->register($tool2);

        // Act: List tools
        $tools = $registry->list();

        // Assert: Verify both tools listed
        $I->assertCount(2, $tools);
        $I->assertEquals('mock-simple', $tools[0]['name']);
        $I->assertEquals('mock-two', $tools[1]['name']);

        // Verify retrieval
        $I->assertSame($tool1, $registry->get('mock-simple'));
        $I->assertSame($tool2, $registry->get('mock-two'));
    }
}
