<?php

namespace Tests\Unit\Protocol;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Protocol\ErrorResponse;

/**
 * Test ErrorResponse class
 */
class ErrorResponseTest extends Unit
{
    public function testParseError(): void
    {
        $error = ErrorResponse::parseError('Invalid JSON syntax');

        $this->assertEquals(ErrorResponse::PARSE_ERROR, $error->getCode());
        $this->assertEquals('Parse error', $error->getMessage());
        $this->assertEquals('Invalid JSON syntax', $error->getData());
        $this->assertNull($error->getId());
    }

    public function testMethodNotFound(): void
    {
        $error = ErrorResponse::methodNotFound(123, 'unknown/method');

        $this->assertEquals(ErrorResponse::METHOD_NOT_FOUND, $error->getCode());
        $this->assertEquals('Method not found', $error->getMessage());
        $this->assertEquals(123, $error->getId());
        $this->assertIsArray($error->getData());
        $this->assertEquals('unknown/method', $error->getData()['method']);
    }

    public function testInvalidParams(): void
    {
        $error = ErrorResponse::invalidParams(456, 'Missing required parameter');

        $this->assertEquals(ErrorResponse::INVALID_PARAMS, $error->getCode());
        $this->assertEquals('Invalid params', $error->getMessage());
        $this->assertEquals(456, $error->getId());
        $this->assertEquals('Missing required parameter', $error->getData());
    }

    public function testInternalError(): void
    {
        $error = ErrorResponse::internalError(789, 'Database connection failed');

        $this->assertEquals(ErrorResponse::INTERNAL_ERROR, $error->getCode());
        $this->assertEquals('Internal error', $error->getMessage());
        $this->assertEquals(789, $error->getId());
        $this->assertEquals('Database connection failed', $error->getData());
    }

    public function testToArray(): void
    {
        $error = new ErrorResponse(1, -32600, 'Invalid Request', 'Test data');
        $array = $error->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('2.0', $array['jsonrpc']);
        $this->assertEquals(1, $array['id']);
        $this->assertArrayHasKey('error', $array);
        $this->assertEquals(-32600, $array['error']['code']);
        $this->assertEquals('Invalid Request', $array['error']['message']);
        $this->assertEquals('Test data', $array['error']['data']);
    }

    public function testToJson(): void
    {
        $error = ErrorResponse::parseError();
        $json = $error->toJson();

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertNull($decoded['id']);
        $this->assertEquals(ErrorResponse::PARSE_ERROR, $decoded['error']['code']);
    }

    public function testFromJson(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"error":{"code":-32600,"message":"Invalid Request"}}';
        $error = ErrorResponse::fromJson($json);

        $this->assertEquals(1, $error->getId());
        $this->assertEquals(-32600, $error->getCode());
        $this->assertEquals('Invalid Request', $error->getMessage());
    }

    public function testErrorCodesConstants(): void
    {
        $this->assertEquals(-32700, ErrorResponse::PARSE_ERROR);
        $this->assertEquals(-32600, ErrorResponse::INVALID_REQUEST);
        $this->assertEquals(-32601, ErrorResponse::METHOD_NOT_FOUND);
        $this->assertEquals(-32602, ErrorResponse::INVALID_PARAMS);
        $this->assertEquals(-32603, ErrorResponse::INTERNAL_ERROR);
        $this->assertEquals(-32000, ErrorResponse::SERVER_ERROR_START);
        $this->assertEquals(-32099, ErrorResponse::SERVER_ERROR_END);
    }
}
