<?php

namespace Tests\Unit\Protocol;

use Codeception\Test\Unit;
use InvalidArgumentException;
use JsonException;
use Took\Yii2GiiMCP\Protocol\Request;
use Took\Yii2GiiMCP\Protocol\Response;

/**
 * Test Message base class functionality
 *
 * Tests are performed through concrete implementations (Request/Response)
 * since Message is an abstract class.
 */
class MessageTest extends Unit
{
    /**
     * Test JSON-RPC version constant
     */
    public function testJsonRpcVersionConstant(): void
    {
        $reflection = new \ReflectionClass(Request::class);
        $constant = $reflection->getConstant('JSON_RPC_VERSION');

        $this->assertEquals('2.0', $constant);
    }

    /**
     * Test parseJson with valid JSON
     */
    public function testParseJsonWithValidJson(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test',
        ]);

        // Test through Request::fromJson which uses Message::parseJson
        $request = Request::fromJson($json);

        $this->assertInstanceOf(Request::class, $request);
    }

    /**
     * Test parseJson with invalid JSON syntax
     */
    public function testParseJsonWithInvalidSyntax(): void
    {
        $this->expectException(JsonException::class);

        Request::fromJson('{invalid: json}');
    }

    /**
     * Test parseJson with non-array JSON
     */
    public function testParseJsonWithNonArrayJson(): void
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Invalid JSON-RPC message format');

        Request::fromJson('"just a string"');
    }

    /**
     * Test parseJson with JSON array (not object)
     *
     * JSON arrays decode successfully but fail version validation
     */
    public function testParseJsonWithJsonArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON-RPC version');

        Request::fromJson('[1, 2, 3]');
    }

    /**
     * Test validateVersion with correct version
     */
    public function testValidateVersionWithCorrectVersion(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test',
        ]);

        $request = Request::fromJson($json);

        $this->assertInstanceOf(Request::class, $request);
    }

    /**
     * Test validateVersion with missing version
     */
    public function testValidateVersionWithMissingVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON-RPC version');

        $json = json_encode([
            'id' => 1,
            'method' => 'test',
        ]);

        Request::fromJson($json);
    }

    /**
     * Test validateVersion with wrong version
     */
    public function testValidateVersionWithWrongVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON-RPC version');

        $json = json_encode([
            'jsonrpc' => '1.0',
            'id' => 1,
            'method' => 'test',
        ]);

        Request::fromJson($json);
    }

    /**
     * Test validateVersion with numeric version
     */
    public function testValidateVersionWithNumericVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $json = json_encode([
            'jsonrpc' => 2.0, // numeric instead of string
            'id' => 1,
            'method' => 'test',
        ]);

        Request::fromJson($json);
    }

    /**
     * Test toJson implementation in Request
     */
    public function testToJsonInRequest(): void
    {
        $request = new Request(1, 'test/method', ['key' => 'value']);
        $json = $request->toJson();

        $this->assertIsString($json);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals('2.0', $decoded['jsonrpc']);
    }

    /**
     * Test toJson implementation in Response
     */
    public function testToJsonInResponse(): void
    {
        $response = new Response(1, ['result' => 'success']);
        $json = $response->toJson();

        $this->assertIsString($json);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals('2.0', $decoded['jsonrpc']);
    }

    /**
     * Test toArray implementation in Request
     */
    public function testToArrayInRequest(): void
    {
        $request = new Request(1, 'test/method');
        $array = $request->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('jsonrpc', $array);
        $this->assertEquals('2.0', $array['jsonrpc']);
    }

    /**
     * Test toArray implementation in Response
     */
    public function testToArrayInResponse(): void
    {
        $response = new Response(1, 'success');
        $array = $response->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('jsonrpc', $array);
        $this->assertEquals('2.0', $array['jsonrpc']);
    }

    /**
     * Test JSON encoding handles UTF-8 properly
     */
    public function testJsonEncodingHandlesUtf8(): void
    {
        $request = new Request(1, 'test', ['text' => 'Hello 世界 🌍']);
        $json = $request->toJson();

        $decoded = json_decode($json, true);
        $this->assertEquals('Hello 世界 🌍', $decoded['params']['text']);
    }

    /**
     * Test JSON encoding preserves data types
     */
    public function testJsonEncodingPreservesTypes(): void
    {
        $response = new Response(1, [
            'string' => 'text',
            'integer' => 42,
            'float' => 3.14,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'object' => ['key' => 'value'],
        ]);

        $json = $response->toJson();
        $decoded = json_decode($json, true);
        $result = $decoded['result'];

        $this->assertIsString($result['string']);
        $this->assertIsInt($result['integer']);
        $this->assertIsFloat($result['float']);
        $this->assertIsBool($result['boolean']);
        $this->assertNull($result['null']);
        $this->assertIsArray($result['array']);
        $this->assertIsArray($result['object']);
    }

    /**
     * Test deeply nested data structures
     */
    public function testDeeplyNestedStructures(): void
    {
        $deepData = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'data' => 'deep value',
                        ],
                    ],
                ],
            ],
        ];

        $response = new Response(1, $deepData);
        $json = $response->toJson();
        $decoded = json_decode($json, true);

        $this->assertEquals('deep value', $decoded['result']['level1']['level2']['level3']['level4']['data']);
    }
}
