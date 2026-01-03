<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\ListTables;

/**
 * Test ListTables Tool
 *
 * Note: These tests use mocks and do not require Yii2 installation.
 * Some tests may fail initially (TDD approach) until full implementation.
 */
class ListTablesTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListTables($bootstrap);

        $this->assertEquals('list-tables', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListTables($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('database', strtolower($description));
        $this->assertStringContainsString('table', strtolower($description));
    }

    /**
     * Test input schema structure
     */
    public function testGetInputSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListTables($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);

        // Check for expected properties
        $properties = $schema['properties'];
        $this->assertArrayHasKey('connection', $properties);
        $this->assertArrayHasKey('detailed', $properties);
    }

    /**
     * Test input schema has valid JSON Schema structure
     */
    public function testInputSchemaIsValidJsonSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListTables($bootstrap);

        $schema = $tool->getInputSchema();

        // Validate basic JSON Schema structure
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('properties', $schema);

        foreach ($schema['properties'] as $propName => $propSchema) {
            $this->assertArrayHasKey('type', $propSchema, "Property '{$propName}' should have 'type'");
            $this->assertArrayHasKey('description', $propSchema, "Property '{$propName}' should have 'description'");
        }
    }

    /**
     * Test execute with minimal parameters (mock)
     *
     * @skip This test requires Yii2 mocking infrastructure - to be implemented
     */
    public function testExecuteWithMinimalParameters()
    {
        $this->markTestSkipped('Requires Yii2 database mocking - TDD placeholder');

        // TODO: Implement with mock database connection
        // Expected behavior:
        // - Should initialize Yii2 if not initialized
        // - Should query database for table names
        // - Should return formatted table list
    }

    /**
     * Test execute with detailed parameter (mock)
     *
     * @skip This test requires Yii2 mocking infrastructure - to be implemented
     */
    public function testExecuteWithDetailedParameter()
    {
        $this->markTestSkipped('Requires Yii2 database mocking - TDD placeholder');

        // TODO: Implement with mock database connection
        // Expected behavior:
        // - Should return detailed table information including columns
        // - Should include column types, constraints, etc.
    }

    /**
     * Test execute with invalid connection
     *
     * @skip This test requires Yii2 mocking infrastructure - to be implemented
     */
    public function testExecuteWithInvalidConnection()
    {
        $this->markTestSkipped('Requires Yii2 database mocking - TDD placeholder');

        // TODO: Implement with mock bootstrap
        // Expected behavior:
        // - Should return error when connection not found
        // - Error should include connection name
    }
}
