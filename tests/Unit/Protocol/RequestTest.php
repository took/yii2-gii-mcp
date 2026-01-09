<?php

namespace Tests\Unit\Protocol;

use Codeception\Test\Unit;
use InvalidArgumentException;
use JsonException;
use Took\Yii2GiiMCP\Protocol\Request;

/**
 * Test Request class
 */
class RequestTest extends Unit
{
    /**
     * Test request creation with all parameters
     */
    public function testConstructorWithAllParams(): void
    {
        $request = new Request(1, 'test/method', ['key' => 'value']);

        $this->assertEquals(1, $request->getId());
        $this->assertEquals('test/method', $request->getMethod());
        $this->assertEquals(['key' => 'value'], $request->getParams());
        $this->assertFalse($request->isNotification());
    }

    /**
     * Test request creation without params
     */
    public function testConstructorWithoutParams(): void
    {
        $request = new Request(1, 'test/method');

        $this->assertEquals(1, $request->getId());
        $this->assertEquals('test/method', $request->getMethod());
        $this->assertNull($request->getParams());
    }

    /**
     * Test notification (no ID)
     */
    public function testNotificationRequest(): void
    {
        $request = new Request(null, 'test/notification', ['data' => 'value']);

        $this->assertNull($request->getId());
        $this->assertTrue($request->isNotification());
        $this->assertEquals('test/notification', $request->getMethod());
    }

    /**
     * Test request with string ID
     */
    public function testRequestWithStringId(): void
    {
        $request = new Request('abc-123', 'test/method');

        $this->assertEquals('abc-123', $request->getId());
        $this->assertFalse($request->isNotification());
    }

    /**
     * Test fromJson with valid JSON
     */
    public function testFromJsonWithValidRequest(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test/method',
            'params' => ['key' => 'value'],
        ]);

        $request = Request::fromJson($json);

        $this->assertEquals(1, $request->getId());
        $this->assertEquals('test/method', $request->getMethod());
        $this->assertEquals(['key' => 'value'], $request->getParams());
    }

    /**
     * Test fromJson with notification
     */
    public function testFromJsonWithNotification(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'test/notification',
        ]);

        $request = Request::fromJson($json);

        $this->assertNull($request->getId());
        $this->assertTrue($request->isNotification());
    }

    /**
     * Test fromJson with invalid JSON
     */
    public function testFromJsonWithInvalidJson(): void
    {
        $this->expectException(JsonException::class);
        Request::fromJson('{invalid json}');
    }

    /**
     * Test fromJson without method
     */
    public function testFromJsonWithoutMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('method');

        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
        ]);

        Request::fromJson($json);
    }

    /**
     * Test fromJson with invalid method type
     */
    public function testFromJsonWithInvalidMethodType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 123, // Should be string
        ]);

        Request::fromJson($json);
    }

    /**
     * Test fromJson with invalid params type
     */
    public function testFromJsonWithInvalidParamsType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('params must be an array');

        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test',
            'params' => 'invalid', // Should be array or null
        ]);

        Request::fromJson($json);
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
            'method' => 'test',
        ]);

        Request::fromJson($json);
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
            'method' => 'test',
        ]);

        Request::fromJson($json);
    }

    /**
     * Test fromArray
     */
    public function testFromArray(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test/method',
            'params' => ['key' => 'value'],
        ];

        $request = Request::fromArray($data);

        $this->assertEquals(1, $request->getId());
        $this->assertEquals('test/method', $request->getMethod());
        $this->assertEquals(['key' => 'value'], $request->getParams());
    }

    /**
     * Test toJson
     */
    public function testToJson(): void
    {
        $request = new Request(1, 'test/method', ['key' => 'value']);
        $json = $request->toJson();

        $this->assertIsString($json);
        $data = json_decode($json, true);

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('test/method', $data['method']);
        $this->assertEquals(['key' => 'value'], $data['params']);
    }

    /**
     * Test toJson without params
     */
    public function testToJsonWithoutParams(): void
    {
        $request = new Request(1, 'test/method');
        $json = $request->toJson();

        $data = json_decode($json, true);

        $this->assertArrayNotHasKey('params', $data);
    }

    /**
     * Test toJson for notification
     */
    public function testToJsonForNotification(): void
    {
        $request = new Request(null, 'test/notification');
        $json = $request->toJson();

        $data = json_decode($json, true);

        $this->assertArrayNotHasKey('id', $data);
        $this->assertEquals('test/notification', $data['method']);
    }

    /**
     * Test toArray
     */
    public function testToArray(): void
    {
        $request = new Request(1, 'test/method', ['key' => 'value']);
        $array = $request->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('2.0', $array['jsonrpc']);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('test/method', $array['method']);
        $this->assertEquals(['key' => 'value'], $array['params']);
    }

    /**
     * Test toArray for notification
     */
    public function testToArrayForNotification(): void
    {
        $request = new Request(null, 'test/notification', ['data' => 'test']);
        $array = $request->toArray();

        $this->assertArrayNotHasKey('id', $array);
        $this->assertEquals('2.0', $array['jsonrpc']);
        $this->assertEquals('test/notification', $array['method']);
    }

    /**
     * Test round-trip serialization
     */
    public function testRoundTripSerialization(): void
    {
        $original = new Request(123, 'test/method', ['key' => 'value', 'nested' => ['data' => true]]);

        $json = $original->toJson();
        $restored = Request::fromJson($json);

        $this->assertEquals($original->getId(), $restored->getId());
        $this->assertEquals($original->getMethod(), $restored->getMethod());
        $this->assertEquals($original->getParams(), $restored->getParams());
    }
}
