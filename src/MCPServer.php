<?php

namespace Took\Yii2GiiMCP;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;
use Took\Yii2GiiMCP\Protocol\ErrorResponse;
use Took\Yii2GiiMCP\Protocol\Request;
use Took\Yii2GiiMCP\Protocol\Response;
use Took\Yii2GiiMCP\Protocol\StdioTransport;

/**
 * MCP (Model Context Protocol) Server
 *
 * Implements JSON-RPC 2.0 over stdio transport for MCP protocol.
 * Handles initialization, tool listing, and tool execution.
 */
class MCPServer
{
    private const PROTOCOL_VERSION = '2024-11-05';
    private const SERVER_NAME = 'yii2-gii-mcp';
    private const SERVER_VERSION = '1.0.0';

    private StdioTransport $transport;
    private ?ToolRegistry $toolRegistry = null;
    private bool $initialized = false;
    private array $serverCapabilities = [];
    private array $clientCapabilities = [];

    /**
     * @param StdioTransport|null $transport Custom transport (for testing)
     * @param bool $debug Enable debug logging
     */
    public function __construct(?StdioTransport $transport = null, bool $debug = false)
    {
        $this->transport = $transport ?? new StdioTransport(debug: $debug);

        // Define server capabilities
        $this->serverCapabilities = [
            'tools' => [
                'listChanged' => false, // Tools list does not change dynamically
            ],
        ];
    }

    /**
     * Set the tool registry
     *
     * @param ToolRegistry $registry
     */
    public function setToolRegistry(ToolRegistry $registry): void
    {
        $this->toolRegistry = $registry;
    }

    /**
     * Start the MCP server and process messages
     *
     * Runs in a loop, reading messages from stdin and responding via stdout.
     */
    public function start(): void
    {
        $this->transport->log("MCP Server starting...");

        while (!$this->transport->isEof()) {
            try {
                $message = $this->transport->readMessage();

                if ($message === null) {
                    // EOF reached or empty message
                    break;
                }

                $this->handleMessage($message);
            } catch (Throwable $e) {
                $this->transport->log("Fatal error: " . $e->getMessage(), true);
                $this->transport->writeError(
                    ErrorResponse::internalError(null, $e->getMessage())
                );
                break;
            }
        }

        $this->transport->log("MCP Server shutting down...");
        $this->transport->close();
    }

    /**
     * Handle a single incoming JSON-RPC message
     *
     * @param string $message JSON-RPC message string
     */
    private function handleMessage(string $message): void
    {
        try {
            // Parse the request
            $request = Request::fromJson($message);

            // Handle notification (no response expected)
            if ($request->isNotification()) {
                $this->transport->log("Received notification: " . $request->getMethod());
                return;
            }

            // Route to appropriate handler
            $response = $this->routeRequest($request);

            // Send response
            $this->transport->writeMessage($response);
        } catch (JsonException $e) {
            // Parse error - send error response with null ID
            $this->transport->log("Parse error: " . $e->getMessage(), true);
            $this->transport->writeError(ErrorResponse::parseError($e->getMessage()));
        } catch (InvalidArgumentException $e) {
            // Invalid request format
            $this->transport->log("Invalid request: " . $e->getMessage(), true);
            $this->transport->writeError(ErrorResponse::invalidRequest(null, $e->getMessage()));
        } catch (Throwable $e) {
            // Unexpected error
            $this->transport->log("Unexpected error: " . $e->getMessage(), true);
            $this->transport->writeError(ErrorResponse::internalError(null, $e->getMessage()));
        }
    }

    /**
     * Route request to the appropriate handler method
     *
     * @param Request $request JSON-RPC request
     * @return Response|ErrorResponse Response or error
     */
    private function routeRequest(Request $request): Response|ErrorResponse
    {
        $method = $request->getMethod();
        $id = $request->getId();

        $this->transport->log("Handling method: {$method}");

        try {
            return match ($method) {
                'initialize' => $this->handleInitialize($id, $request->getParams()),
                'tools/list' => $this->handleToolsList($id),
                'tools/call' => $this->handleToolsCall($id, $request->getParams()),
                default => ErrorResponse::methodNotFound($id, $method),
            };
        } catch (InvalidArgumentException $e) {
            return ErrorResponse::invalidParams($id, $e->getMessage());
        } catch (Throwable $e) {
            $this->transport->log("Error handling request: " . $e->getMessage(), true);
            return ErrorResponse::internalError($id, $e->getMessage());
        }
    }

    /**
     * Handle the 'initialize' method
     *
     * Performs server initialization and capability negotiation with client.
     *
     * @param string|int $id Request ID
     * @param array|null $params Request parameters
     * @return Response
     */
    private function handleInitialize(string|int $id, ?array $params): Response
    {
        if ($this->initialized) {
            throw new RuntimeException('Server already initialized');
        }

        // Extract client info and capabilities
        $protocolVersion = $params['protocolVersion'] ?? null;
        $clientInfo = $params['clientInfo'] ?? [];
        $this->clientCapabilities = $params['capabilities'] ?? [];

        // Validate protocol version
        if ($protocolVersion !== self::PROTOCOL_VERSION) {
            $this->transport->log(
                "Warning: Client protocol version ({$protocolVersion}) differs from server version (" . self::PROTOCOL_VERSION . ")",
                false
            );
        }

        $this->initialized = true;

        $this->transport->log("Server initialized successfully");
        $this->transport->log("Client: " . ($clientInfo['name'] ?? 'unknown'));

        return new Response($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
            'capabilities' => $this->serverCapabilities,
        ]);
    }

    /**
     * Handle the 'tools/list' method
     *
     * Returns list of available tools with their schemas.
     *
     * @param string|int $id Request ID
     * @return Response|ErrorResponse
     */
    private function handleToolsList(string|int $id): Response|ErrorResponse
    {
        if (!$this->initialized) {
            return ErrorResponse::internalError($id, 'Server not initialized');
        }

        if ($this->toolRegistry === null) {
            // No tools registered yet - return empty list
            return new Response($id, ['tools' => []]);
        }

        $tools = $this->toolRegistry->list();

        $this->transport->log("Listing " . count($tools) . " tool(s)");

        return new Response($id, ['tools' => $tools]);
    }

    /**
     * Handle the 'tools/call' method
     *
     * Executes a specific tool with provided arguments.
     *
     * @param string|int $id Request ID
     * @param array|null $params Request parameters
     * @return Response|ErrorResponse
     */
    private function handleToolsCall(string|int $id, ?array $params): Response|ErrorResponse
    {
        if (!$this->initialized) {
            return ErrorResponse::internalError($id, 'Server not initialized');
        }

        if ($this->toolRegistry === null) {
            return ErrorResponse::internalError($id, 'Tool registry not configured');
        }

        // Validate parameters
        if (!isset($params['name']) || !is_string($params['name'])) {
            return ErrorResponse::invalidParams($id, 'Missing or invalid "name" parameter');
        }

        $toolName = $params['name'];
        $arguments = $params['arguments'] ?? [];

        if (!is_array($arguments)) {
            return ErrorResponse::invalidParams($id, 'Tool arguments must be an array');
        }

        $this->transport->log("Calling tool: {$toolName}");

        // Get the tool
        $tool = $this->toolRegistry->get($toolName);

        if ($tool === null) {
            return ErrorResponse::methodNotFound($id, "Tool not found: {$toolName}");
        }

        try {
            // Execute the tool
            $result = $tool->execute($arguments);

            $this->transport->log("Tool executed successfully: {$toolName}");

            return new Response($id, [
                'content' => [$result],
            ]);
        } catch (InvalidArgumentException $e) {
            return ErrorResponse::invalidParams($id, $e->getMessage());
        } catch (Throwable $e) {
            $this->transport->log("Tool execution error: " . $e->getMessage(), true);
            return ErrorResponse::internalError($id, "Tool execution failed: " . $e->getMessage());
        }
    }

    /**
     * Check if server is initialized
     *
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get server capabilities
     *
     * @return array
     */
    public function getServerCapabilities(): array
    {
        return $this->serverCapabilities;
    }

    /**
     * Get client capabilities (available after initialization)
     *
     * @return array
     */
    public function getClientCapabilities(): array
    {
        return $this->clientCapabilities;
    }
}
