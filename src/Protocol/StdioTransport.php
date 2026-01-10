<?php

namespace Took\Yii2GiiMCP\Protocol;

use Throwable;

/**
 * Handles JSON-RPC message I/O over stdio (stdin/stdout)
 *
 * This transport reads requests from stdin and writes responses to stdout.
 * Error/debug messages should be written to stderr to avoid protocol interference.
 */
class StdioTransport
{
    private mixed $stdin;
    private mixed $stdout;
    private mixed $stderr;
    private bool $debug;

    /**
     * @param resource|null $stdin Input stream (default: STDIN)
     * @param resource|null $stdout Output stream (default: STDOUT)
     * @param resource|null $stderr Error stream (default: STDERR)
     * @param bool $debug Enable debug logging to stderr
     */
    public function __construct(
        $stdin = null,
        $stdout = null,
        $stderr = null,
        bool $debug = false
    ) {
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
        $this->debug = $debug;
    }

    /**
     * Read a single JSON-RPC message from stdin
     *
     * Reads line-by-line, expecting one complete JSON message per line.
     * Returns null when stream is closed or on EOF.
     *
     * @return string|null JSON message string or null on EOF
     */
    public function readMessage(): ?string
    {
        if (feof($this->stdin)) {
            return null;
        }

        $line = fgets($this->stdin);

        if ($line === false) {
            return null;
        }

        $message = trim($line);

        if (empty($message)) {
            // Empty line, try to read next
            return $this->readMessage();
        }

        if ($this->debug) {
            $this->log("Received: " . $message);
        }

        return $message;
    }

    /**
     * Log a message to stderr (does not interfere with JSON-RPC protocol)
     *
     * @param string $message Message to log
     * @param bool $isError Whether this is an error message
     */
    public function log(string $message, bool $isError = false): void
    {
        $prefix = $isError ? '[ERROR] ' : '[INFO] ';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$prefix}{$message}\n";

        fwrite($this->stderr, $logMessage);
        fflush($this->stderr);
    }

    /**
     * Write an error response to stdout
     *
     * @param ErrorResponse $error Error response to write
     * @return bool True on success, false on failure
     */
    public function writeError(ErrorResponse $error): bool
    {
        return $this->writeMessage($error);
    }

    /**
     * Write a JSON-RPC message to stdout
     *
     * @param Message $message Message to write
     * @return bool True on success, false on failure
     */
    public function writeMessage(Message $message): bool
    {
        try {
            $json = $message->toJson();

            if ($this->debug) {
                $this->log("Sending: " . $json);
            }

            // Write message followed by newline
            $result = fwrite($this->stdout, $json . "\n");

            if ($result === false) {
                $this->log("Failed to write message to stdout", true);

                return false;
            }

            // Flush to ensure message is sent immediately
            fflush($this->stdout);

            return true;
        } catch (Throwable $e) {
            $this->log("Error writing message: " . $e->getMessage(), true);

            return false;
        }
    }

    /**
     * Write a success response to stdout
     *
     * @param Response $response Response to write
     * @return bool True on success, false on failure
     */
    public function writeResponse(Response $response): bool
    {
        return $this->writeMessage($response);
    }

    /**
     * Check if stdin is at end of file
     *
     * @return bool
     */
    public function isEof(): bool
    {
        return feof($this->stdin);
    }

    /**
     * Close all streams
     *
     * Note: Does not close STDIN, STDOUT, or STDERR constants to avoid
     * closing standard streams. Only closes custom streams.
     */
    public function close(): void
    {
        // Only close if it's a resource and not one of the standard streams
        if (is_resource($this->stdin) && $this->stdin !== STDIN) {
            @fclose($this->stdin);
        }
        if (is_resource($this->stdout) && $this->stdout !== STDOUT) {
            @fclose($this->stdout);
        }
        if (is_resource($this->stderr) && $this->stderr !== STDERR) {
            @fclose($this->stderr);
        }
    }

    /**
     * Enable debug mode
     *
     * @param bool $debug
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }
}
