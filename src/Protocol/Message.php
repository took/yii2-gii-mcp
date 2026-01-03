<?php

namespace Took\Yii2GiiMCP\Protocol;

use InvalidArgumentException;
use JsonException;

/**
 * Base class for JSON-RPC 2.0 messages
 */
abstract class Message
{
    public const JSON_RPC_VERSION = '2.0';

    /**
     * Parse JSON string to message object
     *
     * @param string $json JSON string to parse
     * @return array Decoded JSON data
     * @throws JsonException If JSON is invalid
     */
    protected static function parseJson(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new JsonException('Invalid JSON-RPC message format');
        }

        return $data;
    }

    /**
     * Validate JSON-RPC version
     *
     * @param array $data Message data
     * @throws InvalidArgumentException If version is invalid
     */
    protected static function validateVersion(array $data): void
    {
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== self::JSON_RPC_VERSION) {
            throw new InvalidArgumentException('Invalid JSON-RPC version. Expected "2.0"');
        }
    }

    /**
     * Convert message to JSON string
     *
     * @return string
     */
    abstract public function toJson(): string;

    /**
     * Convert message to array representation
     *
     * @return array
     */
    abstract public function toArray(): array;
}
