<?php

namespace Took\Yii2GiiMCP\Protocol;

use InvalidArgumentException;
use JsonException;

/**
 * JSON-RPC 2.0 Success Response message
 */
class Response extends Message
{
    /**
     * @param string|int $id Request identifier (must match the request)
     * @param mixed $result Result of the method invocation
     */
    public function __construct(
        private readonly string|int $id,
        private readonly mixed      $result
    )
    {
    }

    /**
     * Create Response from JSON string
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
     * Create Response from array
     *
     * @param array $data Response data
     * @return self
     * @throws InvalidArgumentException If message format is invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateVersion($data);

        if (!isset($data['id'])) {
            throw new InvalidArgumentException('Response must have an "id" field');
        }

        if (!array_key_exists('result', $data)) {
            throw new InvalidArgumentException('Success response must have a "result" field');
        }

        return new self($data['id'], $data['result']);
    }

    /**
     * Get response ID
     *
     * @return string|int
     */
    public function getId(): string|int
    {
        return $this->id;
    }

    /**
     * Get result data
     *
     * @return mixed
     */
    public function getResult(): mixed
    {
        return $this->result;
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
        return [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $this->id,
            'result' => $this->result,
        ];
    }
}
