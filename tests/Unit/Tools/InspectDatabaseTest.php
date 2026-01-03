<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\InspectDatabase;

/**
 * Test InspectDatabase Tool
 *
 * Note: These tests use mocks and do not require Yii2 installation.
 * Some tests may fail initially (TDD approach) until full implementation.
 */
class InspectDatabaseTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $this->assertEquals('inspect-database', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('inspect', strtolower($description));
        $this->assertStringContainsString('schema', strtolower($description));
        $this->assertStringContainsString('read-only', strtolower($description));
    }

    /**
     * Test input schema structure
     */
    public function testGetInputSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);

        $properties = $schema['properties'];
        $this->assertArrayHasKey('connection', $properties);
        $this->assertArrayHasKey('tablePattern', $properties);
        $this->assertArrayHasKey('includeViews', $properties);
    }

    /**
     * Test input schema has no required fields (all optional)
     */
    public function testInputSchemaHasNoRequiredFields()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $schema = $tool->getInputSchema();

        // InspectDatabase should work without any parameters
        $this->assertArrayNotHasKey('required', $schema);
    }

    /**
     * Test includeViews default value
     */
    public function testIncludeViewsHasDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals(false, $schema['properties']['includeViews']['default']);
    }

    /**
     * Test execute with database inspection (mock)
     *
     * @skip This test requires database mocking - to be implemented
     */
    public function testExecuteWithDatabaseInspection()
    {
        $this->markTestSkipped('Requires database mocking - TDD placeholder');
    }
}
