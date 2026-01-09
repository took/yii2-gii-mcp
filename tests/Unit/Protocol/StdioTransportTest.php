<?php

namespace Tests\Unit\Protocol;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Protocol\ErrorResponse;
use Took\Yii2GiiMCP\Protocol\Response;
use Took\Yii2GiiMCP\Protocol\StdioTransport;

/**
 * Test StdioTransport class
 */
class StdioTransportTest extends Unit
{
    /**
     * Test constructor with default streams
     */
    public function testConstructorWithDefaults(): void
    {
        $transport = new StdioTransport();

        $this->assertInstanceOf(StdioTransport::class, $transport);
    }

    /**
     * Test constructor with custom streams
     */
    public function testConstructorWithCustomStreams(): void
    {
        $stdin = fopen('php://memory', 'r');
        $stdout = fopen('php://memory', 'w');
        $stderr = fopen('php://memory', 'w');

        $transport = new StdioTransport($stdin, $stdout, $stderr);

        $this->assertInstanceOf(StdioTransport::class, $transport);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    /**
     * Test constructor with debug mode
     */
    public function testConstructorWithDebugMode(): void
    {
        $stderr = fopen('php://memory', 'w');
        $transport = new StdioTransport(null, null, $stderr, true);

        $this->assertInstanceOf(StdioTransport::class, $transport);

        fclose($stderr);
    }

    /**
     * Test readMessage with valid JSON
     */
    public function testReadMessageWithValidJson(): void
    {
        $stdin = fopen('php://memory', 'r+');
        $message = '{"jsonrpc":"2.0","id":1,"method":"test"}';
        fwrite($stdin, $message . "\n");
        rewind($stdin);

        $transport = new StdioTransport($stdin);
        $result = $transport->readMessage();

        $this->assertEquals($message, $result);

        fclose($stdin);
    }

    /**
     * Test readMessage with empty lines
     */
    public function testReadMessageSkipsEmptyLines(): void
    {
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, "\n");
        fwrite($stdin, "  \n");
        fwrite($stdin, '{"jsonrpc":"2.0","id":1,"method":"test"}' . "\n");
        rewind($stdin);

        $transport = new StdioTransport($stdin);
        $result = $transport->readMessage();

        $this->assertEquals('{"jsonrpc":"2.0","id":1,"method":"test"}', $result);

        fclose($stdin);
    }

    /**
     * Test readMessage returns null on EOF
     */
    public function testReadMessageReturnsNullOnEof(): void
    {
        $stdin = fopen('php://memory', 'r');

        $transport = new StdioTransport($stdin);
        $result = $transport->readMessage();

        $this->assertNull($result);

        fclose($stdin);
    }

    /**
     * Test readMessage with debug mode
     */
    public function testReadMessageWithDebugMode(): void
    {
        $stdin = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'w+');
        $message = '{"jsonrpc":"2.0","id":1,"method":"test"}';
        fwrite($stdin, $message . "\n");
        rewind($stdin);

        $transport = new StdioTransport($stdin, null, $stderr, true);
        $result = $transport->readMessage();

        $this->assertEquals($message, $result);

        // Check debug output
        rewind($stderr);
        $log = stream_get_contents($stderr);
        $this->assertStringContainsString('Received:', $log);
        $this->assertStringContainsString($message, $log);

        fclose($stdin);
        fclose($stderr);
    }

    /**
     * Test log method with info message
     */
    public function testLogWithInfoMessage(): void
    {
        $stderr = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, null, $stderr);

        $transport->log('Test info message');

        rewind($stderr);
        $log = stream_get_contents($stderr);

        $this->assertStringContainsString('[INFO]', $log);
        $this->assertStringContainsString('Test info message', $log);

        fclose($stderr);
    }

    /**
     * Test log method with error message
     */
    public function testLogWithErrorMessage(): void
    {
        $stderr = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, null, $stderr);

        $transport->log('Test error message', true);

        rewind($stderr);
        $log = stream_get_contents($stderr);

        $this->assertStringContainsString('[ERROR]', $log);
        $this->assertStringContainsString('Test error message', $log);

        fclose($stderr);
    }

    /**
     * Test log includes timestamp
     */
    public function testLogIncludesTimestamp(): void
    {
        $stderr = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, null, $stderr);

        $transport->log('Test message');

        rewind($stderr);
        $log = stream_get_contents($stderr);

        // Should contain date in format YYYY-MM-DD
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $log);

        fclose($stderr);
    }

    /**
     * Test writeMessage with Response
     */
    public function testWriteMessageWithResponse(): void
    {
        $stdout = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, $stdout);

        $response = new Response(1, ['result' => 'success']);
        $result = $transport->writeMessage($response);

        $this->assertTrue($result);

        rewind($stdout);
        $output = stream_get_contents($stdout);

        $this->assertStringContainsString('"jsonrpc":"2.0"', $output);
        $this->assertStringContainsString('"id":1', $output);
        $this->assertStringContainsString('"result"', $output);

        fclose($stdout);
    }

    /**
     * Test writeMessage with ErrorResponse
     */
    public function testWriteMessageWithErrorResponse(): void
    {
        $stdout = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, $stdout);

        $error = ErrorResponse::internalError(1, 'Test error');
        $result = $transport->writeMessage($error);

        $this->assertTrue($result);

        rewind($stdout);
        $output = stream_get_contents($stdout);

        $this->assertStringContainsString('"error"', $output);
        $this->assertStringContainsString('Test error', $output);

        fclose($stdout);
    }

    /**
     * Test writeMessage with debug mode
     */
    public function testWriteMessageWithDebugMode(): void
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, $stdout, $stderr, true);

        $response = new Response(1, ['test' => 'data']);
        $transport->writeMessage($response);

        rewind($stderr);
        $log = stream_get_contents($stderr);

        $this->assertStringContainsString('Sending:', $log);

        fclose($stdout);
        fclose($stderr);
    }

    /**
     * Test writeResponse convenience method
     */
    public function testWriteResponse(): void
    {
        $stdout = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, $stdout);

        $response = new Response(1, ['data' => 'value']);
        $result = $transport->writeResponse($response);

        $this->assertTrue($result);

        rewind($stdout);
        $output = stream_get_contents($stdout);
        $this->assertNotEmpty($output);

        fclose($stdout);
    }

    /**
     * Test writeError convenience method
     */
    public function testWriteError(): void
    {
        $stdout = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, $stdout);

        $error = ErrorResponse::invalidParams(1, 'Bad params');
        $result = $transport->writeError($error);

        $this->assertTrue($result);

        rewind($stdout);
        $output = stream_get_contents($stdout);
        $this->assertStringContainsString('Bad params', $output);

        fclose($stdout);
    }

    /**
     * Test isEof with open stream
     */
    public function testIsEofWithOpenStream(): void
    {
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, "test\n");
        rewind($stdin);

        $transport = new StdioTransport($stdin);

        $this->assertFalse($transport->isEof());

        fclose($stdin);
    }

    /**
     * Test isEof after reading all content
     */
    public function testIsEofAfterReadingAll(): void
    {
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, "test\n");
        rewind($stdin);

        $transport = new StdioTransport($stdin);

        // Read the content
        $transport->readMessage();

        // Try to read again - this will hit EOF
        $result = $transport->readMessage();
        $this->assertNull($result);

        // Now should be at EOF
        $this->assertTrue($transport->isEof());

        fclose($stdin);
    }

    /**
     * Test close method with custom streams
     */
    public function testCloseWithCustomStreams(): void
    {
        $stdin = fopen('php://memory', 'r');
        $stdout = fopen('php://memory', 'w');
        $stderr = fopen('php://memory', 'w');

        $transport = new StdioTransport($stdin, $stdout, $stderr);
        $transport->close();

        // Streams should be closed
        $this->assertFalse(is_resource($stdin));
        $this->assertFalse(is_resource($stdout));
        $this->assertFalse(is_resource($stderr));
    }

    /**
     * Test setDebug method
     */
    public function testSetDebug(): void
    {
        $stderr = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, null, $stderr, false);

        // Initially no debug output
        $response = new Response(1, ['test' => 'data']);
        $transport->writeMessage($response);

        rewind($stderr);
        $log1 = stream_get_contents($stderr);
        $this->assertEmpty($log1);

        // Enable debug
        $transport->setDebug(true);

        // Now should have debug output
        $transport->writeMessage($response);

        rewind($stderr);
        $log2 = stream_get_contents($stderr);
        $this->assertStringContainsString('Sending:', $log2);

        fclose($stderr);
    }

    /**
     * Test message includes newline
     */
    public function testWriteMessageIncludesNewline(): void
    {
        $stdout = fopen('php://memory', 'w+');
        $transport = new StdioTransport(null, $stdout);

        $response = new Response(1, ['data' => 'test']);
        $transport->writeMessage($response);

        rewind($stdout);
        $output = stream_get_contents($stdout);

        $this->assertStringEndsWith("\n", $output);

        fclose($stdout);
    }

    /**
     * Test readMessage trims whitespace
     */
    public function testReadMessageTrimsWhitespace(): void
    {
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, '  {"jsonrpc":"2.0","id":1}  ' . "\n");
        rewind($stdin);

        $transport = new StdioTransport($stdin);
        $result = $transport->readMessage();

        $this->assertEquals('{"jsonrpc":"2.0","id":1}', $result);

        fclose($stdin);
    }

    /**
     * Test multiple messages can be read sequentially
     */
    public function testReadMultipleMessages(): void
    {
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, '{"jsonrpc":"2.0","id":1,"method":"test1"}' . "\n");
        fwrite($stdin, '{"jsonrpc":"2.0","id":2,"method":"test2"}' . "\n");
        rewind($stdin);

        $transport = new StdioTransport($stdin);

        $msg1 = $transport->readMessage();
        $msg2 = $transport->readMessage();

        $this->assertStringContainsString('test1', $msg1);
        $this->assertStringContainsString('test2', $msg2);

        fclose($stdin);
    }
}
