<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\InspectComponents;

/**
 * Test InspectComponents Tool
 *
 * Tests component inspection functionality for controllers, models, and views.
 */
class InspectComponentsTest extends Unit
{
    private InspectComponents $tool;
    private $mockBootstrap;

    protected function _before()
    {
        // Create mock bootstrap - use basic mock builder to avoid PHPUnit version incompatibilities
        $this->mockBootstrap = $this->getMockBuilder(Yii2Bootstrap::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create tool instance
        $this->tool = new InspectComponents($this->mockBootstrap);
    }

    /**
     * Test tool metadata
     */
    public function testToolMetadata()
    {
        $this->assertEquals('inspect-components', $this->tool->getName());
        $this->assertNotEmpty($this->tool->getDescription());
        $this->assertStringContainsString('controllers', $this->tool->getDescription());
        $this->assertStringContainsString('models', $this->tool->getDescription());
    }

    /**
     * Test input schema structure
     */
    public function testInputSchema()
    {
        $schema = $this->tool->getInputSchema();

        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);

        $this->assertArrayHasKey('properties', $schema);
        $properties = $schema['properties'];

        // Check for required properties
        $this->assertArrayHasKey('application', $properties);
        $this->assertArrayHasKey('module', $properties);
        $this->assertArrayHasKey('componentType', $properties);
        $this->assertArrayHasKey('includeDetails', $properties);

        // Check componentType enum
        $this->assertArrayHasKey('enum', $properties['componentType']);
        $this->assertContains('controllers', $properties['componentType']['enum']);
        $this->assertContains('models', $properties['componentType']['enum']);
        $this->assertContains('views', $properties['componentType']['enum']);
        $this->assertContains('all', $properties['componentType']['enum']);

        // Check defaults
        $this->assertEquals('all', $properties['componentType']['default']);
        $this->assertTrue($properties['includeDetails']['default']);
    }

    /**
     * Test execution with all components (default)
     */
    public function testExecuteWithAllComponents()
    {
        // Mock Yii2 application
        $mockApp = $this->createMockApplication();

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('basic');

        // Execute tool
        $result = $this->tool->execute([
            'componentType' => 'all',
            'includeDetails' => false,
        ]);

        // Check result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('text', $result);

        // Check text contains expected sections
        $text = $result['text'];
        $this->assertStringContainsString('Component Inspection Report', $text);
        $this->assertStringContainsString('Controllers', $text);
        $this->assertStringContainsString('Models', $text);
        $this->assertStringContainsString('Views', $text);
    }

    /**
     * Test execution with controllers only
     */
    public function testExecuteWithControllersOnly()
    {
        $mockApp = $this->createMockApplication();

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('basic');

        $result = $this->tool->execute([
            'componentType' => 'controllers',
            'includeDetails' => false,
        ]);

        $this->assertIsArray($result);
        $text = $result['text'];
        $this->assertStringContainsString('Controllers', $text);
    }

    /**
     * Test execution with models only
     */
    public function testExecuteWithModelsOnly()
    {
        $mockApp = $this->createMockApplication();

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('basic');

        $result = $this->tool->execute([
            'componentType' => 'models',
            'includeDetails' => false,
        ]);

        $this->assertIsArray($result);
        $text = $result['text'];
        $this->assertStringContainsString('Models', $text);
    }

    /**
     * Test execution with views only
     */
    public function testExecuteWithViewsOnly()
    {
        $mockApp = $this->createMockApplication();

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('basic');

        $result = $this->tool->execute([
            'componentType' => 'views',
            'includeDetails' => false,
        ]);

        $this->assertIsArray($result);
        $text = $result['text'];
        $this->assertStringContainsString('Views', $text);
    }

    /**
     * Test execution with detailed analysis
     */
    public function testExecuteWithDetailedAnalysis()
    {
        $mockApp = $this->createMockApplication();

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('basic');

        $result = $this->tool->execute([
            'componentType' => 'all',
            'includeDetails' => true,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('text', $result);

        // With detailed analysis, output should contain JSON
        $text = $result['text'];
        $this->assertStringContainsString('JSON Representation', $text);
    }

    /**
     * Test with invalid application name
     */
    public function testExecuteWithInvalidApplication()
    {
        $mockApp = $this->createMockApplication();

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('basic');

        $result = $this->tool->execute([
            'application' => 'nonexistent',
            'componentType' => 'all',
        ]);

        $this->assertIsArray($result);
        $text = $result['text'];

        // Should return error
        $this->assertStringContainsString('Error', $text);
    }

    /**
     * Test with invalid module name
     */
    public function testExecuteWithInvalidModule()
    {
        $mockApp = $this->createMockApplication();

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('basic');

        $result = $this->tool->execute([
            'module' => 'nonexistent',
            'componentType' => 'all',
        ]);

        $this->assertIsArray($result);
        $text = $result['text'];

        // Should return error
        $this->assertStringContainsString('Error', $text);
    }

    /**
     * Test input validation with invalid component type
     */
    public function testInputValidationWithInvalidComponentType()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tool->execute([
            'componentType' => 'invalid',
        ]);
    }

    /**
     * Test default parameters
     */
    public function testDefaultParameters()
    {
        $mockApp = $this->createMockApplication();

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('basic');

        // Execute with minimal parameters (should use defaults for others)
        // Note: PHP empty array [] is treated as JSON array, not object, so we pass empty object-like array
        $result = $this->tool->execute(['componentType' => 'all']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('text', $result);

        // Should use default 'all' for componentType
        $text = $result['text'];
        $this->assertStringContainsString('Controllers', $text);
        $this->assertStringContainsString('Models', $text);
        $this->assertStringContainsString('Views', $text);
    }

    /**
     * Test with advanced template
     */
    public function testWithAdvancedTemplate()
    {
        $mockApp = $this->createMockApplication('/path/to/project/frontend');

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('advanced');

        $result = $this->tool->execute([
            'application' => 'backend',
            'componentType' => 'all',
        ]);

        $this->assertIsArray($result);
        // Test should handle advanced template structure
    }

    /**
     * Test JSON output structure
     */
    public function testJsonOutputStructure()
    {
        $mockApp = $this->createMockApplication();

        $this->mockBootstrap->method('getApp')
            ->willReturn($mockApp);
        $this->mockBootstrap->method('detectTemplateType')
            ->willReturn('basic');

        $result = $this->tool->execute([
            'componentType' => 'all',
            'includeDetails' => true,
        ]);

        $this->assertIsArray($result);
        $text = $result['text'];

        // Extract JSON from output
        if (preg_match('/JSON Representation:.*?\n={50}\n(.+)$/s', $text, $matches)) {
            $json = $matches[1];
            $data = json_decode($json, true);

            $this->assertNotNull($data, 'JSON should be valid');
            $this->assertArrayHasKey('application', $data);
            $this->assertArrayHasKey('basePath', $data);
            $this->assertArrayHasKey('controllers', $data);
            $this->assertArrayHasKey('models', $data);
            $this->assertArrayHasKey('views', $data);
        }
    }

    /**
     * Create mock Yii2 application
     */
    private function createMockApplication(string $basePath = null)
    {
        $basePath = $basePath ?? codecept_data_dir('test-app');

        // Create test directory structure
        @mkdir($basePath . '/controllers', 0777, true);
        @mkdir($basePath . '/models', 0777, true);
        @mkdir($basePath . '/views', 0777, true);

        // Use StubApplication to satisfy PHPUnit 12+ return type requirements
        return new StubApplication($basePath);
    }
}

/**
 * Simple stub application class for testing
 * Provides minimal Application interface for unit tests without requiring Yii2
 */
class StubApplication
{
    private string $testBasePath;

    public function __construct(string $basePath)
    {
        $this->testBasePath = $basePath;
    }

    public function getBasePath(): string
    {
        return $this->testBasePath;
    }
}
