<?php

namespace Took\Yii2GiiMCP\Protocol;

use InvalidArgumentException;
use JsonException;

/**
 * JSON-RPC 2.0 Request message
 */
class Request extends Message
{
    /**
     * @param string|int $id Request identifier (can be string, number, or null)
     * @param string $method Method name to invoke
     * @param array|null $params Optional parameters for the method
     */
    public function __construct(
        private readonly string|int|null $id,
        private readonly string          $method,
        private readonly ?array          $params = null
    )
    {
    }

    /**
     * Create Request from JSON string
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
     * Create Request from array
     *
     * @param array $data Request data
     * @return self
     * @throws InvalidArgumentException If message format is invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateVersion($data);

        if (!isset($data['method']) || !is_string($data['method'])) {
            throw new InvalidArgumentException('Request must have a "method" field');
        }

        $id = $data['id'] ?? null;
        $method = $data['method'];
        $params = $data['params'] ?? null;

        if ($params !== null && !is_array($params)) {
            throw new InvalidArgumentException('Request params must be an array or null');
        }

        return new self($id, $method, $params);
    }

    /**
     * Get request ID
     *
     * @return string|int|null
     */
    public function getId(): string|int|null
    {
        return $this->id;
    }

    /**
     * Get method name
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get parameters
     *
     * @return array|null
     */
    public function getParams(): ?array
    {
        return $this->params;
    }

    /**
     * Check if this is a notification (no response expected)
     *
     * @return bool
     */
    public function isNotification(): bool
    {
        return $this->id === null;
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
        $data = [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'method' => $this->method,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        if ($this->params !== null) {
            $data['params'] = $this->params;
        }

        return $data;
    }
}
