<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\ListTables;

/**
 * Test ListTables Tool
 *
 * Tests focus on tool metadata and input schema validation.
 * Execution tests (doExecute) require Yii2 dependencies due to strict return typing
 * in Yii2Bootstrap::getDb(): yii\db\Connection, making mocking without Yii2 impossible.
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
        $this->assertStringContainsString('read-only', strtolower($description));
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

        // Verify connection property
        $this->assertEquals('string', $properties['connection']['type']);
        $this->assertEquals('db', $properties['connection']['default']);

        // Verify detailed property
        $this->assertEquals('boolean', $properties['detailed']['type']);
        $this->assertTrue($properties['detailed']['default']);
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
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);

        foreach ($schema['properties'] as $propName => $propSchema) {
            $this->assertArrayHasKey('type', $propSchema, "Property '{$propName}' should have 'type'");
            $this->assertArrayHasKey('description', $propSchema, "Property '{$propName}' should have 'description'");
            $this->assertArrayHasKey('default', $propSchema, "Property '{$propName}' should have 'default'");
        }
    }

    /**
     * Test connection property has correct default
     */
    public function testConnectionPropertyDefault()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListTables($bootstrap);

        $schema = $tool->getInputSchema();
        $connectionProp = $schema['properties']['connection'];

        $this->assertEquals('db', $connectionProp['default']);
        $this->assertStringContainsString('connection component', strtolower($connectionProp['description']));
    }

    /**
     * Test detailed property has correct default
     */
    public function testDetailedPropertyDefault()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListTables($bootstrap);

        $schema = $tool->getInputSchema();
        $detailedProp = $schema['properties']['detailed'];

        $this->assertTrue($detailedProp['default']);
        $this->assertStringContainsString('column', strtolower($detailedProp['description']));
    }

    /**
     * Test schema does not allow additional properties
     */
    public function testSchemaDisallowsAdditionalProperties()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListTables($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test tool instantiation
     */
    public function testToolInstantiation()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListTables($bootstrap);

        $this->assertInstanceOf(ListTables::class, $tool);
        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\AbstractTool::class, $tool);
        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\ToolInterface::class, $tool);
    }

    /**
     * Note on Execution Tests:
     * 
     * Testing doExecute() requires mocking yii\db\Connection which is not possible
     * without Yii2 dependencies due to PHP's strict return type checking in 
     * Yii2Bootstrap::getDb(): yii\db\Connection.
     * 
     * Full execution tests would verify:
     * - Listing tables with minimal parameters
     * - Detailed vs non-detailed output
     * - Custom connection IDs
     * - Empty database handling
     * - Foreign key detection
     * - Bootstrap initialization
     * - Exception handling
     * - Null schema handling
     * 
     * These scenarios require integration tests with actual Yii2/database setup.
     */
}
