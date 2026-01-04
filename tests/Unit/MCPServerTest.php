<?php

namespace Tests\Unit;

use Codeception\Test\Unit;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\MockSimpleTool;
use Took\Yii2GiiMCP\MCPServer;
use Took\Yii2GiiMCP\Protocol\ErrorResponse;
use Took\Yii2GiiMCP\Protocol\Request;
use Took\Yii2GiiMCP\Protocol\Response;
use Took\Yii2GiiMCP\Protocol\StdioTransport;
use Took\Yii2GiiMCP\ToolRegistry;

/**
 * Test MCPServer class
 */
class MCPServerTest extends Unit
{
    /**
     * Test server initialization
     */
    public function testConstructor(): void
    {
        $server = new MCPServer();

        $this->assertFalse($server->isInitialized());
        $this->assertIsArray($server->getServerCapabilities());
        $this->assertEmpty($server->getClientCapabilities());
    }

    /**
     * Test server initialization with custom transport
     */
    public function testConstructorWithCustomTransport(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        $server = new MCPServer($transport);

        $this->assertFalse($server->isInitialized());
    }

    /**
     * Test setting tool registry
     */
    public function testSetToolRegistry(): void
    {
        $server = new MCPServer();
        $registry = new ToolRegistry();

        $server->setToolRegistry($registry);

        // No direct getter, but we can test through handleMessage
        $this->assertFalse($server->isInitialized());
    }

    /**
     * Test server capabilities structure
     */
    public function testGetServerCapabilities(): void
    {
        $server = new MCPServer();
        $capabilities = $server->getServerCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('tools', $capabilities);
        $this->assertIsArray($capabilities['tools']);
        $this->assertArrayHasKey('listChanged', $capabilities['tools']);
        $this->assertFalse($capabilities['tools']['listChanged']);
    }

    /**
     * Test initialization flow
     */
    public function testHandleInitializeMethod(): void
    {
        // Create mock transport
        $transport = $this->createMock(StdioTransport::class);
        $transport->expects($this->once())
            ->method('writeMessage')
            ->with($this->callback(function ($response) {
                $this->assertInstanceOf(Response::class, $response);
                $result = $response->getResult();
                $this->assertArrayHasKey('protocolVersion', $result);
                $this->assertArrayHasKey('serverInfo', $result);
                $this->assertArrayHasKey('capabilities', $result);
                return true;
            }));

        $transport->expects($this->atLeastOnce())
            ->method('log');

        $server = new MCPServer($transport);

        // Create initialize request
        $request = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
            'clientInfo' => ['name' => 'test-client'],
            'capabilities' => [],
        ]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $request->toJson());

        $this->assertTrue($server->isInitialized());
    }

    /**
     * Test double initialization throws error
     */
    public function testHandleInitializeThrowsOnDoubleInitialization(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        // First call should succeed, second call should write error
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 1) {
                    // First call should be success
                    $this->assertInstanceOf(Response::class, $response);
                } else {
                    // Second call should be error
                    $this->assertInstanceOf(ErrorResponse::class, $response);
                }
            });

        $server = new MCPServer($transport);

        // First initialization
        $request1 = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $request1->toJson());

        // Second initialization should fail
        $request2 = new Request(2, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $method->invoke($server, $request2->toJson());
    }

    /**
     * Test tools/list before initialization
     */
    public function testHandleToolsListBeforeInitialization(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        $transport->expects($this->once())
            ->method('writeMessage')
            ->with($this->callback(function ($response) {
                $this->assertInstanceOf(ErrorResponse::class, $response);
                return true;
            }));

        $server = new MCPServer($transport);

        $request = new Request(1, 'tools/list');

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $request->toJson());
    }

    /**
     * Test tools/list after initialization with empty registry
     */
    public function testHandleToolsListWithEmptyRegistry(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        // Expect two write messages: initialize response and tools/list response
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 2) {
                    // Second call is tools/list
                    $result = $response->getResult();
                    $this->assertArrayHasKey('tools', $result);
                    $this->assertIsArray($result['tools']);
                    $this->assertEmpty($result['tools']);
                }
            });

        $server = new MCPServer($transport);

        // Initialize
        $initRequest = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $initRequest->toJson());

        // List tools
        $listRequest = new Request(2, 'tools/list');
        $method->invoke($server, $listRequest->toJson());
    }

    /**
     * Test tools/list after initialization with tools
     */
    public function testHandleToolsListWithTools(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 2) {
                    // Second call is tools/list
                    $result = $response->getResult();
                    $this->assertArrayHasKey('tools', $result);
                    $this->assertCount(1, $result['tools']);
                    $this->assertEquals('test-tool', $result['tools'][0]['name']);
                }
            });

        $server = new MCPServer($transport);
        
        // Create registry with a tool
        $registry = new ToolRegistry();
        $tool = new MockSimpleTool('test-tool', 'Test tool description');
        $registry->register($tool);
        $server->setToolRegistry($registry);

        // Initialize
        $initRequest = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $initRequest->toJson());

        // List tools
        $listRequest = new Request(2, 'tools/list');
        $method->invoke($server, $listRequest->toJson());
    }

    /**
     * Test tools/call before initialization
     */
    public function testHandleToolsCallBeforeInitialization(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        $transport->expects($this->once())
            ->method('writeMessage')
            ->with($this->callback(function ($response) {
                $this->assertInstanceOf(ErrorResponse::class, $response);
                return true;
            }));

        $server = new MCPServer($transport);

        $request = new Request(1, 'tools/call', [
            'name' => 'test-tool',
            'arguments' => [],
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $request->toJson());
    }

    /**
     * Test tools/call with missing tool name
     */
    public function testHandleToolsCallWithMissingName(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 2) {
                    // Second call should be error
                    $this->assertInstanceOf(ErrorResponse::class, $response);
                }
            });

        $server = new MCPServer($transport);
        $registry = new ToolRegistry();
        $server->setToolRegistry($registry);

        // Initialize
        $initRequest = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $initRequest->toJson());

        // Call tool without name
        $callRequest = new Request(2, 'tools/call', [
            'arguments' => [],
        ]);
        $method->invoke($server, $callRequest->toJson());
    }

    /**
     * Test tools/call with non-existent tool
     */
    public function testHandleToolsCallWithNonExistentTool(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 2) {
                    // Second call should be error
                    $this->assertInstanceOf(ErrorResponse::class, $response);
                }
            });

        $server = new MCPServer($transport);
        $registry = new ToolRegistry();
        $server->setToolRegistry($registry);

        // Initialize
        $initRequest = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $initRequest->toJson());

        // Call non-existent tool
        $callRequest = new Request(2, 'tools/call', [
            'name' => 'non-existent-tool',
            'arguments' => [],
        ]);
        $method->invoke($server, $callRequest->toJson());
    }

    /**
     * Test tools/call with successful execution
     */
    public function testHandleToolsCallSuccess(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 2) {
                    // Second call should be success response
                    $this->assertInstanceOf(Response::class, $response);
                    $result = $response->getResult();
                    $this->assertArrayHasKey('content', $result);
                    $this->assertIsArray($result['content']);
                }
            });

        $server = new MCPServer($transport);
        
        // Create registry with a tool
        $registry = new ToolRegistry();
        $tool = new MockSimpleTool('test-tool', 'Test tool');
        $registry->register($tool);
        $server->setToolRegistry($registry);

        // Initialize
        $initRequest = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $initRequest->toJson());

        // Call tool
        $callRequest = new Request(2, 'tools/call', [
            'name' => 'test-tool',
            'arguments' => ['test' => 'value'],
        ]);
        $method->invoke($server, $callRequest->toJson());
    }

    /**
     * Test unknown method
     */
    public function testHandleUnknownMethod(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        $transport->expects($this->once())
            ->method('writeMessage')
            ->with($this->callback(function ($response) {
                $this->assertInstanceOf(ErrorResponse::class, $response);
                return true;
            }));

        $server = new MCPServer($transport);

        $request = new Request(1, 'unknown/method');

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $request->toJson());
    }

    /**
     * Test notification handling (no response expected)
     */
    public function testHandleNotification(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        // Should not write any message for notifications
        $transport->expects($this->never())
            ->method('writeMessage');
        
        $transport->expects($this->once())
            ->method('log')
            ->with($this->stringContains('notification'));

        $server = new MCPServer($transport);

        // Create notification (request with null ID)
        $notification = new Request(null, 'some/notification');

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $notification->toJson());
    }

    /**
     * Test invalid JSON handling
     */
    public function testHandleInvalidJson(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        $transport->expects($this->once())
            ->method('writeError')
            ->with($this->callback(function ($error) {
                $this->assertInstanceOf(ErrorResponse::class, $error);
                return true;
            }));

        $server = new MCPServer($transport);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, '{invalid json}');
    }

    /**
     * Test client capabilities are stored after initialization
     */
    public function testClientCapabilitiesStored(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $server = new MCPServer($transport);

        $clientCapabilities = [
            'experimental' => ['feature' => true],
        ];

        $request = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => $clientCapabilities,
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $request->toJson());

        $this->assertEquals($clientCapabilities, $server->getClientCapabilities());
    }

    /**
     * Test initialization with different protocol version logs warning
     */
    public function testInitializeWithDifferentProtocolVersion(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        // Expect writeMessage to be called (for the initialize response)
        $transport->expects($this->once())
            ->method('writeMessage');
        
        // Also expect log to be called with warning about protocol version
        $transport->expects($this->atLeastOnce())
            ->method('log');

        $server = new MCPServer($transport);

        $request = new Request(1, 'initialize', [
            'protocolVersion' => '2024-01-01', // Different version
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $request->toJson());
        
        $this->assertTrue($server->isInitialized());
    }

    /**
     * Test tools/call with invalid arguments type
     */
    public function testHandleToolsCallWithInvalidArgumentsType(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 2) {
                    $this->assertInstanceOf(ErrorResponse::class, $response);
                }
            });

        $server = new MCPServer($transport);
        $registry = new ToolRegistry();
        $server->setToolRegistry($registry);

        // Initialize
        $initRequest = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $initRequest->toJson());

        // Call tool with invalid arguments (not an array)
        $callRequest = new Request(2, 'tools/call', [
            'name' => 'test-tool',
            'arguments' => 'invalid', // Should be array
        ]);
        $method->invoke($server, $callRequest->toJson());
    }

    /**
     * Test tools/call without tool registry configured
     */
    public function testHandleToolsCallWithoutRegistry(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 2) {
                    $this->assertInstanceOf(ErrorResponse::class, $response);
                }
            });

        $server = new MCPServer($transport);
        // Note: NOT setting tool registry

        // Initialize
        $initRequest = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $initRequest->toJson());

        // Try to call tool without registry
        $callRequest = new Request(2, 'tools/call', [
            'name' => 'test-tool',
            'arguments' => [],
        ]);
        $method->invoke($server, $callRequest->toJson());
    }

    /**
     * Test initialization stores client info
     */
    public function testInitializeStoresClientInfo(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->atLeastOnce())
            ->method('log')
            ->with($this->stringContains('test-client'));

        $server = new MCPServer($transport);

        $request = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
            'clientInfo' => ['name' => 'test-client', 'version' => '1.0'],
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $request->toJson());
    }

    /**
     * Test handleMessage with throwable error
     */
    public function testHandleMessageWithThrowableError(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->once())
            ->method('writeError')
            ->with($this->callback(function ($error) {
                $this->assertInstanceOf(ErrorResponse::class, $error);
                return true;
            }));

        $server = new MCPServer($transport);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        // Send invalid request that will throw exception
        $method->invoke($server, '{"jsonrpc":"2.0"}'); // Missing required fields
    }

    /**
     * Test tools/call with tool that throws InvalidArgumentException
     */
    public function testHandleToolsCallWithToolValidationError(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 2) {
                    $this->assertInstanceOf(ErrorResponse::class, $response);
                }
            });

        $server = new MCPServer($transport);
        
        // Create mock tool that throws InvalidArgumentException
        $tool = $this->createMock(\Took\Yii2GiiMCP\Tools\ToolInterface::class);
        $tool->method('getName')->willReturn('failing-tool');
        $tool->method('getDescription')->willReturn('Test');
        $tool->method('getInputSchema')->willReturn(['type' => 'object']);
        $tool->method('execute')->willThrowException(
            new InvalidArgumentException('Invalid parameters')
        );
        
        $registry = new ToolRegistry();
        $registry->register($tool);
        $server->setToolRegistry($registry);

        // Initialize
        $initRequest = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $initRequest->toJson());

        // Call tool that will throw error
        $callRequest = new Request(2, 'tools/call', [
            'name' => 'failing-tool',
            'arguments' => [],
        ]);
        $method->invoke($server, $callRequest->toJson());
    }

    /**
     * Test tools/call with tool that throws generic exception
     */
    public function testHandleToolsCallWithToolExecutionError(): void
    {
        $transport = $this->createMock(StdioTransport::class);
        
        $transport->expects($this->exactly(2))
            ->method('writeMessage')
            ->willReturnCallback(function ($response) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 2) {
                    $this->assertInstanceOf(ErrorResponse::class, $response);
                }
            });

        $server = new MCPServer($transport);
        
        // Create mock tool that throws generic exception
        $tool = $this->createMock(\Took\Yii2GiiMCP\Tools\ToolInterface::class);
        $tool->method('getName')->willReturn('error-tool');
        $tool->method('getDescription')->willReturn('Test');
        $tool->method('getInputSchema')->willReturn(['type' => 'object']);
        $tool->method('execute')->willThrowException(
            new RuntimeException('Execution failed')
        );
        
        $registry = new ToolRegistry();
        $registry->register($tool);
        $server->setToolRegistry($registry);

        // Initialize
        $initRequest = new Request(1, 'initialize', [
            'protocolVersion' => '2024-11-05',
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleMessage');
        $method->setAccessible(true);

        $method->invoke($server, $initRequest->toJson());

        // Call tool that will throw error
        $callRequest = new Request(2, 'tools/call', [
            'name' => 'error-tool',
            'arguments' => [],
        ]);
        $method->invoke($server, $callRequest->toJson());
    }
}
