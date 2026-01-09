<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use ReflectionClass;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\InspectDatabase;
use Took\Yii2GiiMCP\Tools\ToolInterface;

/**
 * Test InspectDatabase Tool
 *
 * Note: These tests use mocks and Reflection to test without Yii2 dependencies.
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
     * Test connection default value
     */
    public function testConnectionDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals('db', $schema['properties']['connection']['default']);
    }

    /**
     * Test schema disallows additional properties
     */
    public function testSchemaDisallowsAdditionalProperties()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test all properties have descriptions
     */
    public function testAllPropertiesHaveDescriptions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        foreach ($properties as $name => $property) {
            $this->assertArrayHasKey('description', $property, "Property '{$name}' should have a description");
            $this->assertNotEmpty($property['description'], "Property '{$name}' description should not be empty");
        }
    }

    /**
     * Test tablePattern description mentions patterns
     */
    public function testTablePatternDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $schema = $tool->getInputSchema();
        $tablePattern = $schema['properties']['tablePattern'];

        $this->assertStringContainsString('user*', $tablePattern['description']);
        $this->assertStringContainsString('*_log', $tablePattern['description']);
        $this->assertStringContainsString('glob', strtolower($tablePattern['description']));
    }

    /**
     * Test formatTableInfo method structure
     *
     * Note: Full testing requires yii\db\TableSchema which needs Yii2.
     * This test verifies the method exists and is accessible.
     */
    public function testFormatTableInfoMethodExists()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $reflection = new ReflectionClass($tool);

        $this->assertTrue($reflection->hasMethod('formatTableInfo'));

        $method = $reflection->getMethod('formatTableInfo');
        $this->assertTrue($method->isPrivate());

        // Verify the method signature expects yii\db\TableSchema
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('tableSchema', $params[0]->getName());
    }

    /**
     * Test formatOutput with no tables
     */
    public function testFormatOutputWithNoTables()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $result = $method->invoke($tool, [], 'mysql:host=localhost;dbname=test');

        $this->assertIsString($result);
        $this->assertStringContainsString('Database Schema Inspection', $result);
        $this->assertStringContainsString('mysql:host=localhost;dbname=test', $result);
        $this->assertStringContainsString('Tables: 0', $result);
        $this->assertStringContainsString('No tables found', $result);
    }

    /**
     * Test formatOutput with tables
     */
    public function testFormatOutputWithTables()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $tables = [
            [
                'name' => 'users',
                'schemaName' => 'public',
                'primaryKey' => ['id'],
                'sequenceName' => null,
                'columns' => [
                    ['name' => 'id', 'type' => 'integer'],
                    ['name' => 'name', 'type' => 'string'],
                ],
                'foreignKeys' => [],
                'indexes' => [],
            ],
            [
                'name' => 'posts',
                'schemaName' => 'public',
                'primaryKey' => ['id'],
                'sequenceName' => null,
                'columns' => [
                    ['name' => 'id', 'type' => 'integer'],
                    ['name' => 'user_id', 'type' => 'integer'],
                ],
                'foreignKeys' => [
                    ['name' => 'fk_post_user', 'referencedTable' => 'users', 'columns' => ['user_id' => 'id']],
                ],
                'indexes' => [],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $result = $method->invoke($tool, $tables, 'pgsql:host=localhost;dbname=test');

        $this->assertIsString($result);
        $this->assertStringContainsString('Database Schema Inspection', $result);
        $this->assertStringContainsString('pgsql:host=localhost;dbname=test', $result);
        $this->assertStringContainsString('Tables: 2', $result);
        $this->assertStringContainsString('```json', $result);
        $this->assertStringContainsString('users', $result);
        $this->assertStringContainsString('posts', $result);
        $this->assertStringContainsString('Summary:', $result);
    }

    /**
     * Test tool is read-only
     */
    public function testToolIsReadOnly()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $description = $tool->getDescription();

        $this->assertStringContainsString('read-only', strtolower($description));
        $this->assertStringContainsString('does not modify', strtolower($description));
    }

    /**
     * Test tool implements required interfaces
     */
    public function testToolImplementsRequiredInterfaces()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new InspectDatabase($bootstrap);

        $this->assertInstanceOf(ToolInterface::class, $tool);
    }
}
