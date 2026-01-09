<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use ReflectionClass;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\ListMigrations;
use Took\Yii2GiiMCP\Tools\ToolInterface;

/**
 * Test ListMigrations Tool
 *
 * Note: These tests use mocks and Reflection to test without Yii2 dependencies.
 */
class ListMigrationsTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $this->assertEquals('list-migrations', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('migration', strtolower($description));
        $this->assertStringContainsString('status', strtolower($description));
        $this->assertStringContainsString('read-only', strtolower($description));
    }

    /**
     * Test implements ToolInterface
     */
    public function testImplementsToolInterface()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $this->assertInstanceOf(ToolInterface::class, $tool);
    }

    /**
     * Test input schema structure
     */
    public function testGetInputSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
    }

    /**
     * Test input schema has status property
     */
    public function testInputSchemaHasStatusProperty()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        $this->assertArrayHasKey('status', $properties);
        $this->assertEquals('string', $properties['status']['type']);
    }

    /**
     * Test status enum values
     */
    public function testStatusEnumValues()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $schema = $tool->getInputSchema();
        $statusProperty = $schema['properties']['status'];

        $this->assertArrayHasKey('enum', $statusProperty);
        $this->assertContains('all', $statusProperty['enum']);
        $this->assertContains('applied', $statusProperty['enum']);
        $this->assertContains('pending', $statusProperty['enum']);
    }

    /**
     * Test status default value
     */
    public function testStatusDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $schema = $tool->getInputSchema();
        $statusProperty = $schema['properties']['status'];

        $this->assertEquals('all', $statusProperty['default']);
    }

    /**
     * Test input schema has limit property
     */
    public function testInputSchemaHasLimitProperty()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        $this->assertArrayHasKey('limit', $properties);
        $this->assertEquals('integer', $properties['limit']['type']);
    }

    /**
     * Test limit default value
     */
    public function testLimitDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $schema = $tool->getInputSchema();
        $limitProperty = $schema['properties']['limit'];

        $this->assertEquals(10, $limitProperty['default']);
    }

    /**
     * Test no additional properties allowed
     */
    public function testNoAdditionalProperties()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test formatOutput with empty migrations
     */
    public function testFormatOutputEmpty()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $output = $method->invoke($tool, [], 'all', 10);

        $this->assertStringContainsString('No migrations found', $output);
    }

    /**
     * Test formatOutput with migrations
     */
    public function testFormatOutputWithMigrations()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $migrations = [
            ['name' => 'm123456_create_users', 'status' => 'applied', 'applied_time' => '2024-01-01 12:00:00'],
            ['name' => 'm123457_create_posts', 'status' => 'pending', 'applied_time' => null],
        ];

        $output = $method->invoke($tool, $migrations, 'all', 10);

        $this->assertStringContainsString('Found 2 migration', $output);
        $this->assertStringContainsString('m123456_create_users', $output);
        $this->assertStringContainsString('m123457_create_posts', $output);
        $this->assertStringContainsString('Summary', $output);
    }

    /**
     * Test formatOutput shows summary
     */
    public function testFormatOutputShowsSummary()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $migrations = [
            ['name' => 'm1', 'status' => 'applied', 'applied_time' => '2024-01-01'],
            ['name' => 'm2', 'status' => 'applied', 'applied_time' => '2024-01-02'],
            ['name' => 'm3', 'status' => 'pending', 'applied_time' => null],
        ];

        $output = $method->invoke($tool, $migrations, 'all', 10);

        $this->assertStringContainsString('Applied: 2', $output);
        $this->assertStringContainsString('Pending: 1', $output);
        $this->assertStringContainsString('Total: 3', $output);
    }

    /**
     * Test formatOutput with limit
     */
    public function testFormatOutputWithLimit()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $migrations = [
            ['name' => 'm1', 'status' => 'applied', 'applied_time' => '2024-01-01'],
            ['name' => 'm2', 'status' => 'applied', 'applied_time' => '2024-01-02'],
        ];

        $output = $method->invoke($tool, $migrations, 'all', 1);

        // Note: formatOutput doesn't actually slice, that's done in doExecute
        $this->assertStringContainsString('Found 2 migration', $output);
    }

    /**
     * Test input schema property descriptions
     */
    public function testInputSchemaPropertyDescriptions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ListMigrations($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('description', $schema['properties']['status']);
        $this->assertArrayHasKey('description', $schema['properties']['limit']);

        $this->assertIsString($schema['properties']['status']['description']);
        $this->assertIsString($schema['properties']['limit']['description']);
    }
}
