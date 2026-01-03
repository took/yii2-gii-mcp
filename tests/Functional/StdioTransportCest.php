<?php

namespace Tests\Functional;

use Tests\FunctionalTester;
use Took\Yii2GiiMCP\Protocol\ErrorResponse;
use Took\Yii2GiiMCP\Protocol\Response;
use Took\Yii2GiiMCP\Protocol\StdioTransport;

/**
 * Functional tests for StdioTransport
 *
 * Tests stdin/stdout communication using memory streams.
 * No Yii2 dependency required.
 */
class StdioTransportCest
{
    /**
     * Test reading JSON-RPC messages from stdin
     */
    public function testReadMessage(FunctionalTester $I): void
    {
        // Arrange: Create memory stream with JSON messages
        $stdin = fopen('php://temp', 'r+');
        $stdout = fopen('php://temp', 'r+');
        $stderr = fopen('php://temp', 'r+');

        $message1 = '{"jsonrpc":"2.0","id":1,"method":"test"}';
        $message2 = '{"jsonrpc":"2.0","id":2,"method":"test2"}';

        fwrite($stdin, $message1 . "\n");
        fwrite($stdin, $message2 . "\n");
        rewind($stdin);

        // Act: Create transport and read messages
        $transport = new StdioTransport($stdin, $stdout, $stderr, false);

        $read1 = $transport->readMessage();
        $read2 = $transport->readMessage();

        // Assert: Verify messages read correctly
        $I->assertEquals($message1, $read1);
        $I->assertEquals($message2, $read2);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    /**
     * Test writing response messages to stdout
     */
    public function testWriteResponse(FunctionalTester $I): void
    {
        // Arrange: Create memory streams
        $stdin = fopen('php://temp', 'r+');
        $stdout = fopen('php://temp', 'r+');
        $stderr = fopen('php://temp', 'r+');

        $transport = new StdioTransport($stdin, $stdout, $stderr, false);
        $response = new Response(1, ['status' => 'success']);

        // Act: Write response
        $result = $transport->writeResponse($response);

        // Assert: Verify write success and output
        $I->assertTrue($result);

        rewind($stdout);
        $output = stream_get_contents($stdout);

        $I->assertNotEmpty($output);
        $I->assertJson($output);

        $decoded = json_decode($output, true);
        $I->assertEquals('2.0', $decoded['jsonrpc']);
        $I->assertEquals(1, $decoded['id']);
        $I->assertArrayHasKey('result', $decoded);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    /**
     * Test writing error messages to stdout
     */
    public function testWriteError(FunctionalTester $I): void
    {
        // Arrange: Create memory streams
        $stdin = fopen('php://temp', 'r+');
        $stdout = fopen('php://temp', 'r+');
        $stderr = fopen('php://temp', 'r+');

        $transport = new StdioTransport($stdin, $stdout, $stderr, false);
        $error = ErrorResponse::methodNotFound(1, 'test/method');

        // Act: Write error
        $result = $transport->writeError($error);

        // Assert: Verify write success and error format
        $I->assertTrue($result);

        rewind($stdout);
        $output = stream_get_contents($stdout);

        $I->assertNotEmpty($output);
        $I->assertJson($output);

        $decoded = json_decode($output, true);
        $I->assertEquals('2.0', $decoded['jsonrpc']);
        $I->assertArrayHasKey('error', $decoded);
        $I->assertEquals(-32601, $decoded['error']['code']);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    /**
     * Test EOF detection
     */
    public function testEofHandling(FunctionalTester $I): void
    {
        // Arrange: Create empty stream
        $stdin = fopen('php://temp', 'r+');
        $stdout = fopen('php://temp', 'r+');
        $stderr = fopen('php://temp', 'r+');

        // Close stdin to simulate EOF
        fclose($stdin);

        // Reopen for transport (will be at EOF)
        $stdin = fopen('php://temp', 'r');

        $transport = new StdioTransport($stdin, $stdout, $stderr, false);

        // Act: Try to read from EOF
        $message = $transport->readMessage();

        // Assert: Should return null on EOF
        $I->assertNull($message);
        $I->assertTrue($transport->isEof());

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    /**
     * Test logging to stderr
     */
    public function testLogging(FunctionalTester $I): void
    {
        // Arrange: Create memory streams
        $stdin = fopen('php://temp', 'r+');
        $stdout = fopen('php://temp', 'r+');
        $stderr = fopen('php://temp', 'r+');

        $transport = new StdioTransport($stdin, $stdout, $stderr, false);

        // Act: Log messages
        $transport->log('Test info message', false);
        $transport->log('Test error message', true);

        // Assert: Verify logs written to stderr
        rewind($stderr);
        $logs = stream_get_contents($stderr);

        $I->assertStringContainsString('[INFO] Test info message', $logs);
        $I->assertStringContainsString('[ERROR] Test error message', $logs);

        // Verify nothing written to stdout
        rewind($stdout);
        $output = stream_get_contents($stdout);
        $I->assertEmpty($output);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    /**
     * Test empty lines are skipped when reading
     */
    public function testSkipEmptyLines(FunctionalTester $I): void
    {
        // Arrange: Stream with empty lines
        $stdin = fopen('php://temp', 'r+');
        $stdout = fopen('php://temp', 'r+');
        $stderr = fopen('php://temp', 'r+');

        fwrite($stdin, "\n");
        fwrite($stdin, "\n");
        fwrite($stdin, '{"jsonrpc":"2.0","id":1,"method":"test"}' . "\n");
        rewind($stdin);

        $transport = new StdioTransport($stdin, $stdout, $stderr, false);

        // Act: Read message (should skip empty lines)
        $message = $transport->readMessage();

        // Assert: Should get the actual message
        $I->assertEquals('{"jsonrpc":"2.0","id":1,"method":"test"}', $message);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }
}
