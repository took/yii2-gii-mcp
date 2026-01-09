<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\ReadLogs;

/**
 * Test ReadLogs Tool
 *
 * Tests focus on tool metadata and input schema validation.
 * Full execution tests require Yii2 integration testing.
 */
class ReadLogsTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $this->assertEquals('read-logs', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('log', strtolower($description));
        $this->assertStringContainsString('filter', strtolower($description));
        $this->assertStringContainsString('read-only', strtolower($description));
    }

    /**
     * Test input schema structure
     */
    public function testGetInputSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test input schema properties
     */
    public function testInputSchemaProperties()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        // Check all expected properties exist
        $expectedProps = ['application', 'source', 'level', 'category', 'since', 'until', 'search', 'limit'];
        foreach ($expectedProps as $prop) {
            $this->assertArrayHasKey($prop, $properties, "Property '{$prop}' should exist");
        }
    }

    /**
     * Test application property
     */
    public function testInputSchemaApplication()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();
        $appProp = $schema['properties']['application'];

        $this->assertEquals('string', $appProp['type']);
        $this->assertArrayHasKey('description', $appProp);
        $this->assertArrayHasKey('default', $appProp);
        $this->assertEquals('', $appProp['default']);
    }

    /**
     * Test source property with enum
     */
    public function testInputSchemaSource()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();
        $sourceProp = $schema['properties']['source'];

        $this->assertEquals('string', $sourceProp['type']);
        $this->assertArrayHasKey('enum', $sourceProp);
        $this->assertContains('file', $sourceProp['enum']);
        $this->assertContains('db', $sourceProp['enum']);
        $this->assertContains('both', $sourceProp['enum']);
        $this->assertEquals('both', $sourceProp['default']);
    }

    /**
     * Test level property with enum
     */
    public function testInputSchemaLevel()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();
        $levelProp = $schema['properties']['level'];

        $this->assertEquals('string', $levelProp['type']);
        $this->assertArrayHasKey('enum', $levelProp);
        $this->assertContains('error', $levelProp['enum']);
        $this->assertContains('warning', $levelProp['enum']);
        $this->assertContains('info', $levelProp['enum']);
        $this->assertContains('trace', $levelProp['enum']);
    }

    /**
     * Test category property
     */
    public function testInputSchemaCategory()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();
        $categoryProp = $schema['properties']['category'];

        $this->assertEquals('string', $categoryProp['type']);
        $this->assertArrayHasKey('description', $categoryProp);
        $this->assertStringContainsString('wildcard', strtolower($categoryProp['description']));
        $this->assertEquals('', $categoryProp['default']);
    }

    /**
     * Test time range properties
     */
    public function testInputSchemaTimeRange()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();

        // Check since property
        $sinceProp = $schema['properties']['since'];
        $this->assertEquals('string', $sinceProp['type']);
        $this->assertStringContainsString('ISO 8601', $sinceProp['description']);
        $this->assertEquals('', $sinceProp['default']);

        // Check until property
        $untilProp = $schema['properties']['until'];
        $this->assertEquals('string', $untilProp['type']);
        $this->assertStringContainsString('ISO 8601', $untilProp['description']);
        $this->assertEquals('', $untilProp['default']);
    }

    /**
     * Test search property
     */
    public function testInputSchemaSearch()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();
        $searchProp = $schema['properties']['search'];

        $this->assertEquals('string', $searchProp['type']);
        $this->assertArrayHasKey('description', $searchProp);
        $this->assertStringContainsString('full-text', strtolower($searchProp['description']));
        $this->assertEquals('', $searchProp['default']);
    }

    /**
     * Test limit property
     */
    public function testInputSchemaLimit()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();
        $limitProp = $schema['properties']['limit'];

        $this->assertEquals('integer', $limitProp['type']);
        $this->assertArrayHasKey('description', $limitProp);
        $this->assertEquals(100, $limitProp['default']);
    }

    /**
     * Test input schema has valid JSON Schema structure
     */
    public function testInputSchemaIsValidJsonSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();

        // Validate basic JSON Schema structure
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('properties', $schema);

        foreach ($schema['properties'] as $propName => $propSchema) {
            $this->assertArrayHasKey('type', $propSchema, "Property '{$propName}' should have 'type'");
            $this->assertArrayHasKey('description', $propSchema, "Property '{$propName}' should have 'description'");
            // Optional enum properties don't require defaults
            if ($propName !== 'level') {
                $this->assertArrayHasKey('default', $propSchema, "Property '{$propName}' should have 'default'");
            }
        }
    }

    /**
     * Test tool instantiation
     */
    public function testToolInstantiation()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $this->assertInstanceOf(ReadLogs::class, $tool);
        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\AbstractTool::class, $tool);
        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\ToolInterface::class, $tool);
    }

    /**
     * Test that description mentions key features
     */
    public function testDescriptionMentionsKeyFeatures()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $description = strtolower($tool->getDescription());

        $this->assertStringContainsString('filter', $description);
        $this->assertStringContainsString('level', $description);
        $this->assertStringContainsString('category', $description);
        $this->assertStringContainsString('search', $description);
        $this->assertStringContainsString('file', $description);
        $this->assertStringContainsString('database', $description);
    }

    /**
     * Test schema disallows additional properties
     */
    public function testSchemaDisallowsAdditionalProperties()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test tool name follows naming convention
     */
    public function testToolNameConvention()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $name = $tool->getName();

        // Tool names should be lowercase with hyphens
        $this->assertMatchesRegularExpression('/^[a-z]+(-[a-z]+)*$/', $name);
        $this->assertStringNotContainsString('_', $name);
        $this->assertStringNotContainsString(' ', $name);
    }

    /**
     * Test that all properties have proper defaults
     */
    public function testAllPropertiesHaveDefaults()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();

        foreach ($schema['properties'] as $propName => $propSchema) {
            // Optional enum properties (like level) don't require defaults
            if ($propName === 'level') {
                continue;
            }
            $this->assertArrayHasKey(
                'default',
                $propSchema,
                "Property '{$propName}' should have a default value"
            );
        }
    }

    /**
     * Test enum properties have valid values
     */
    public function testEnumPropertiesValid()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ReadLogs($bootstrap);

        $schema = $tool->getInputSchema();

        // Test source enum
        $sourceEnum = $schema['properties']['source']['enum'];
        $this->assertIsArray($sourceEnum);
        $this->assertNotEmpty($sourceEnum);
        $this->assertContains($schema['properties']['source']['default'], $sourceEnum);

        // Test level enum
        $levelEnum = $schema['properties']['level']['enum'];
        $this->assertIsArray($levelEnum);
        $this->assertNotEmpty($levelEnum);
    }

    /**
     * Note on Execution Tests:
     *
     * Testing doExecute() requires a real or well-mocked Yii2 application context
     * because the tool needs to:
     * - Initialize Yii2 application
     * - Access filesystem for log files
     * - Query database for DbTarget logs
     * - Use ProjectStructureHelper to find applications
     * - Parse actual log file formats
     *
     * Full execution tests would verify:
     * - Reading logs from file sources
     * - Reading logs from database sources
     * - Filtering by level, category, time range, search
     * - Application-specific log reading
     * - Multi-application log aggregation
     * - Statistics calculation
     * - Output formatting
     * - Error handling for missing log files
     * - Error handling for database connection issues
     * - Handling of malformed log entries
     *
     * These scenarios require integration tests with actual Yii2 project structures
     * and populated log files/database tables.
     */
}
