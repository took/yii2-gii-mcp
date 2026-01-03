<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Tests\Support\MockTool;

/**
 * Test AbstractTool base class functionality
 */
class AbstractToolTest extends Unit
{
    private MockTool $tool;

    public function testGetName(): void
    {
        $this->assertEquals('mock-tool', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertEquals('A mock tool for testing', $this->tool->getDescription());
    }

    public function testGetInputSchema(): void
    {
        $schema = $this->tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('testParam', $schema['properties']);
    }

    public function testExecuteWithValidInput(): void
    {
        $result = $this->tool->execute(['testParam' => 'value']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('text', $result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('value', $result['text']);
    }

    public function testExecuteWithInvalidInputThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input validation failed');

        // Missing required parameter
        $this->tool->execute([]);
    }

    public function testCreateResult(): void
    {
        $result = $this->tool->testCreateResult('Test output');

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertEquals('Test output', $result['text']);
    }

    public function testCreateDataResult(): void
    {
        $data = ['key' => 'value', 'number' => 123];
        $result = $this->tool->testCreateDataResult($data);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertJson($result['text']);

        $decoded = json_decode($result['text'], true);
        $this->assertEquals($data, $decoded);
    }

    public function testCreateError(): void
    {
        $result = $this->tool->testCreateError('Something went wrong', ['detail' => 'info']);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Error: Something went wrong', $result['text']);
        $this->assertStringContainsString('Details:', $result['text']);
    }

    public function testGetRequiredParamThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameter: missing');

        $this->tool->testGetRequiredParam(['other' => 'value'], 'missing');
    }

    public function testGetRequiredParam(): void
    {
        $value = $this->tool->testGetRequiredParam(['param' => 'value'], 'param');
        $this->assertEquals('value', $value);
    }

    public function testGetOptionalParam(): void
    {
        $value = $this->tool->testGetOptionalParam(['param' => 'value'], 'param', 'default');
        $this->assertEquals('value', $value);

        $default = $this->tool->testGetOptionalParam([], 'missing', 'default');
        $this->assertEquals('default', $default);
    }

    public function testFormatTableEmpty(): void
    {
        $table = $this->tool->testFormatTable(['Col1'], []);

        $this->assertEquals('No data available.', $table);
    }

    public function testFormatTable(): void
    {
        $headers = ['Name', 'Age', 'City'];
        $rows = [
            ['Alice', '30', 'New York'],
            ['Bob', '25', 'Los Angeles'],
        ];

        $table = $this->tool->testFormatTable($headers, $rows);

        $this->assertIsString($table);
        $this->assertStringContainsString('Name', $table);
        $this->assertStringContainsString('Alice', $table);
        $this->assertStringContainsString('Bob', $table);
        $this->assertStringContainsString('---', $table);
    }

    protected function setUp(): void
    {
        $this->tool = new MockTool();
    }
}
