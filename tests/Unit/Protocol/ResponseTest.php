<?php

namespace Tests\Unit\Protocol;

use Codeception\Test\Unit;
use InvalidArgumentException;
use JsonException;
use Took\Yii2GiiMCP\Protocol\Response;

/**
 * Test Response class
 */
class ResponseTest extends Unit
{
    /**
     * Test response creation
     */
    public function testConstructor(): void
    {
        $response = new Response(1, ['data' => 'value']);

        $this->assertEquals(1, $response->getId());
        $this->assertEquals(['data' => 'value'], $response->getResult());
    }

    /**
     * Test response with string ID
     */
    public function testConstructorWithStringId(): void
    {
        $response = new Response('abc-123', ['result' => 'success']);

        $this->assertEquals('abc-123', $response->getId());
        $this->assertEquals(['result' => 'success'], $response->getResult());
    }

    /**
     * Test response with null result
     */
    public function testConstructorWithNullResult(): void
    {
        $response = new Response(1, null);

        $this->assertEquals(1, $response->getId());
        $this->assertNull($response->getResult());
    }

    /**
     * Test response with scalar result
     */
    public function testConstructorWithScalarResult(): void
    {
        $response = new Response(1, 'success');

        $this->assertEquals('success', $response->getResult());
    }

    /**
     * Test response with boolean result
     */
    public function testConstructorWithBooleanResult(): void
    {
        $response = new Response(1, true);

        $this->assertTrue($response->getResult());
    }

    /**
     * Test response with numeric result
     */
    public function testConstructorWithNumericResult(): void
    {
        $response = new Response(1, 42);

        $this->assertEquals(42, $response->getResult());
    }

    /**
     * Test fromJson with valid JSON
     */
    public function testFromJsonWithValidResponse(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['data' => 'value'],
        ]);

        $response = Response::fromJson($json);

        $this->assertEquals(1, $response->getId());
        $this->assertEquals(['data' => 'value'], $response->getResult());
    }

    /**
     * Test fromJson with invalid JSON
     */
    public function testFromJsonWithInvalidJson(): void
    {
        $this->expectException(JsonException::class);
        Response::fromJson('{invalid json}');
    }

    /**
     * Test fromJson without ID
     */
    public function testFromJsonWithoutId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('id');

        $json = json_encode([
            'jsonrpc' => '2.0',
            'result' => ['data' => 'value'],
        ]);

        Response::fromJson($json);
    }

    /**
     * Test fromJson without result
     */
    public function testFromJsonWithoutResult(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('result');

        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
        ]);

        Response::fromJson($json);
    }

    /**
     * Test fromJson with null result is valid
     */
    public function testFromJsonWithNullResult(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => null,
        ]);

        $response = Response::fromJson($json);

        $this->assertNull($response->getResult());
    }

    /**
     * Test fromJson without jsonrpc version
     */
    public function testFromJsonWithoutVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON-RPC version');

        $json = json_encode([
            'id' => 1,
            'result' => 'success',
        ]);

        Response::fromJson($json);
    }

    /**
     * Test fromJson with wrong jsonrpc version
     */
    public function testFromJsonWithWrongVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('2.0');

        $json = json_encode([
            'jsonrpc' => '1.0',
            'id' => 1,
            'result' => 'success',
        ]);

        Response::fromJson($json);
    }

    /**
     * Test fromArray
     */
    public function testFromArray(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['key' => 'value'],
        ];

        $response = Response::fromArray($data);

        $this->assertEquals(1, $response->getId());
        $this->assertEquals(['key' => 'value'], $response->getResult());
    }

    /**
     * Test toJson
     */
    public function testToJson(): void
    {
        $response = new Response(1, ['data' => 'value']);
        $json = $response->toJson();

        $this->assertIsString($json);
        $data = json_decode($json, true);
        
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(1, $data['id']);
        $this->assertEquals(['data' => 'value'], $data['result']);
    }

    /**
     * Test toJson with null result
     */
    public function testToJsonWithNullResult(): void
    {
        $response = new Response(1, null);
        $json = $response->toJson();

        $data = json_decode($json, true);
        
        $this->assertArrayHasKey('result', $data);
        $this->assertNull($data['result']);
    }

    /**
     * Test toJson with complex nested result
     */
    public function testToJsonWithComplexResult(): void
    {
        $complexResult = [
            'users' => [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane'],
            ],
            'metadata' => [
                'total' => 2,
                'page' => 1,
            ],
        ];

        $response = new Response(1, $complexResult);
        $json = $response->toJson();

        $data = json_decode($json, true);
        $this->assertEquals($complexResult, $data['result']);
    }

    /**
     * Test toArray
     */
    public function testToArray(): void
    {
        $response = new Response(1, ['key' => 'value']);
        $array = $response->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('2.0', $array['jsonrpc']);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals(['key' => 'value'], $array['result']);
    }

    /**
     * Test toArray with string ID
     */
    public function testToArrayWithStringId(): void
    {
        $response = new Response('test-id', 'success');
        $array = $response->toArray();

        $this->assertEquals('test-id', $array['id']);
        $this->assertEquals('success', $array['result']);
    }

    /**
     * Test round-trip serialization
     */
    public function testRoundTripSerialization(): void
    {
        $original = new Response(123, [
            'status' => 'success',
            'data' => [
                'users' => ['alice', 'bob'],
                'count' => 2,
            ],
        ]);
        
        $json = $original->toJson();
        $restored = Response::fromJson($json);

        $this->assertEquals($original->getId(), $restored->getId());
        $this->assertEquals($original->getResult(), $restored->getResult());
    }

    /**
     * Test JSON encoding with special characters
     */
    public function testToJsonWithSpecialCharacters(): void
    {
        $response = new Response(1, [
            'path' => '/var/www/html',
            'message' => 'Test with "quotes" and \'apostrophes\'',
        ]);

        $json = $response->toJson();
        $decoded = json_decode($json, true);

        $this->assertEquals('/var/www/html', $decoded['result']['path']);
        $this->assertEquals('Test with "quotes" and \'apostrophes\'', $decoded['result']['message']);
    }

    /**
     * Test JSON doesn't escape slashes
     */
    public function testToJsonDoesNotEscapeSlashes(): void
    {
        $response = new Response(1, ['url' => 'https://example.com/path']);
        $json = $response->toJson();

        // JSON_UNESCAPED_SLASHES should prevent \/ escaping
        $this->assertStringContainsString('https://example.com/path', $json);
        $this->assertStringNotContainsString('\\/', $json);
    }
}
