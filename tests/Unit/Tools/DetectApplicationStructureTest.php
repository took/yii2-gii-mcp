<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\DetectApplicationStructure;

/**
 * Test DetectApplicationStructure Tool
 *
 * Tests focus on tool metadata and input schema validation.
 * Full execution tests require Yii2 integration testing.
 */
class DetectApplicationStructureTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $this->assertEquals('detect-application-structure', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('detect', strtolower($description));
        $this->assertStringContainsString('structure', strtolower($description));
        $this->assertStringContainsString('template', strtolower($description));
        $this->assertStringContainsString('read-only', strtolower($description));
    }

    /**
     * Test input schema structure
     */
    public function testGetInputSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test basePath property in schema
     */
    public function testInputSchemaBasePath()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        $this->assertArrayHasKey('basePath', $properties);
        $this->assertEquals('string', $properties['basePath']['type']);
        $this->assertArrayHasKey('description', $properties['basePath']);
        $this->assertArrayHasKey('default', $properties['basePath']);
        $this->assertEquals('', $properties['basePath']['default']);
    }

    /**
     * Test input schema has valid JSON Schema structure
     */
    public function testInputSchemaIsValidJsonSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $schema = $tool->getInputSchema();

        // Validate basic JSON Schema structure
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('properties', $schema);

        foreach ($schema['properties'] as $propName => $propSchema) {
            $this->assertArrayHasKey('type', $propSchema, "Property '{$propName}' should have 'type'");
            $this->assertArrayHasKey('description', $propSchema, "Property '{$propName}' should have 'description'");
            $this->assertArrayHasKey('default', $propSchema, "Property '{$propName}' should have 'default'");
        }
    }

    /**
     * Test tool instantiation
     */
    public function testToolInstantiation()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $this->assertInstanceOf(DetectApplicationStructure::class, $tool);
        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\AbstractTool::class, $tool);
        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\ToolInterface::class, $tool);
    }

    /**
     * Test that description mentions key features
     */
    public function testDescriptionMentionsKeyFeatures()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $description = strtolower($tool->getDescription());

        $this->assertStringContainsString('basic', $description);
        $this->assertStringContainsString('advanced', $description);
        $this->assertStringContainsString('environment', $description);
        $this->assertStringContainsString('application', $description);
        $this->assertStringContainsString('module', $description);
    }

    /**
     * Test schema disallows additional properties
     */
    public function testSchemaDisallowsAdditionalProperties()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test basePath property description is informative
     */
    public function testBasePathDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $schema = $tool->getInputSchema();
        $basePathProp = $schema['properties']['basePath'];

        $description = strtolower($basePathProp['description']);
        $this->assertStringContainsString('base', $description);
        $this->assertStringContainsString('path', $description);
    }

    /**
     * Test tool name follows naming convention
     */
    public function testToolNameConvention()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $name = $tool->getName();

        // Tool names should be lowercase with hyphens
        $this->assertMatchesRegularExpression('/^[a-z]+(-[a-z]+)*$/', $name);
        $this->assertStringNotContainsString('_', $name);
        $this->assertStringNotContainsString(' ', $name);
    }

    /**
     * Test that schema properties all have defaults
     */
    public function testSchemaPropertiesHaveDefaults()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new DetectApplicationStructure($bootstrap);

        $schema = $tool->getInputSchema();

        foreach ($schema['properties'] as $propName => $propSchema) {
            $this->assertArrayHasKey(
                'default',
                $propSchema,
                "Property '{$propName}' should have a default value"
            );
        }
    }

    /**
     * Note on Execution Tests:
     *
     * Testing doExecute() requires a real or well-mocked Yii2 application context
     * because the tool needs to:
     * - Get base path from Yii2Bootstrap
     * - Scan filesystem for project structure
     * - Parse actual Yii2 configuration files
     * - Analyze environment setup
     *
     * Full execution tests would verify:
     * - Basic template detection and structure analysis
     * - Advanced template detection with multiple applications
     * - Environment detection from init system
     * - Module discovery across applications
     * - Entry point detection and parsing
     * - JSON output format validation
     * - Error handling for invalid paths
     * - Graceful handling of missing directories
     *
     * These scenarios require integration tests with actual Yii2 project structures.
     */
}
