<?php

namespace Took\Yii2GiiMCP\Protocol;

use InvalidArgumentException;
use JsonException;

/**
 * JSON-RPC 2.0 Error Response message
 */
class ErrorResponse extends Message
{
    // JSON-RPC 2.0 standard error codes
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;

    // Server error codes (implementation defined)
    public const SERVER_ERROR_START = -32000;
    public const SERVER_ERROR_END = -32099;

    /**
     * @param string|int|null $id Request identifier (null if error occurred before ID could be read)
     * @param int $code Error code (should follow JSON-RPC 2.0 error code convention)
     * @param string $message Human-readable error message
     * @param mixed $data Additional error data (optional)
     */
    public function __construct(
        private readonly string|int|null $id,
        private readonly int             $code,
        private readonly string          $message,
        private readonly mixed           $data = null
    )
    {
    }

    /**
     * Create ErrorResponse from JSON string
     *
     * @param string $json JSON string
     * @return self
     * @throws JsonException If JSON is invalid
     * @throws InvalidArgumentException If message format is invalid
     */
    public static function fromJson(string $json): self
    {
        $data = self::parseJson($json);
        return self::fromArray($data);
    }

    /**
     * Create ErrorResponse from array
     *
     * @param array $data Error response data
     * @return self
     * @throws InvalidArgumentException If message format is invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateVersion($data);

        if (!isset($data['error']) || !is_array($data['error'])) {
            throw new InvalidArgumentException('Error response must have an "error" object');
        }

        $error = $data['error'];

        if (!isset($error['code']) || !is_int($error['code'])) {
            throw new InvalidArgumentException('Error must have an integer "code" field');
        }

        if (!isset($error['message']) || !is_string($error['message'])) {
            throw new InvalidArgumentException('Error must have a string "message" field');
        }

        $id = $data['id'] ?? null;
        $errorData = $error['data'] ?? null;

        return new self($id, $error['code'], $error['message'], $errorData);
    }

    /**
     * Create a parse error response
     *
     * @param string|null $details Additional error details
     * @return self
     */
    public static function parseError(?string $details = null): self
    {
        return new self(
            null,
            self::PARSE_ERROR,
            'Parse error',
            $details
        );
    }

    /**
     * Create an invalid request error response
     *
     * @param string|int|null $id Request ID if available
     * @param string|null $details Additional error details
     * @return self
     */
    public static function invalidRequest(string|int|null $id = null, ?string $details = null): self
    {
        return new self(
            $id,
            self::INVALID_REQUEST,
            'Invalid Request',
            $details
        );
    }

    /**
     * Create a method not found error response
     *
     * @param string|int $id Request ID
     * @param string $method Method name that was not found
     * @return self
     */
    public static function methodNotFound(string|int $id, string $method): self
    {
        return new self(
            $id,
            self::METHOD_NOT_FOUND,
            'Method not found',
            ['method' => $method]
        );
    }

    /**
     * Create an invalid params error response
     *
     * @param string|int $id Request ID
     * @param string|null $details Additional error details
     * @return self
     */
    public static function invalidParams(string|int $id, ?string $details = null): self
    {
        return new self(
            $id,
            self::INVALID_PARAMS,
            'Invalid params',
            $details
        );
    }

    /**
     * Create an internal error response
     *
     * @param string|int|null $id Request ID
     * @param string|null $details Additional error details
     * @return self
     */
    public static function internalError(string|int|null $id = null, ?string $details = null): self
    {
        return new self(
            $id,
            self::INTERNAL_ERROR,
            'Internal error',
            $details
        );
    }

    /**
     * Get error response ID
     *
     * @return string|int|null
     */
    public function getId(): string|int|null
    {
        return $this->id;
    }

    /**
     * Get error code
     *
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Get error message
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get additional error data
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $error = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $this->id,
            'error' => $error,
        ];
    }
}
