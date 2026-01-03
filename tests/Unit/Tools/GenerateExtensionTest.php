<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateExtension;

/**
 * Test GenerateExtension Tool
 *
 * Note: These tests use mocks and do not require Yii2 installation.
 * Tests cover the production implementation of the extension generator.
 */
class GenerateExtensionTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $this->assertEquals('generate-extension', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('extension', strtolower($description));
    }

    /**
     * Test input schema has required fields
     */
    public function testInputSchemaHasRequiredFields()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('vendorName', $schema['required']);
        $this->assertContains('packageName', $schema['required']);

        $properties = $schema['properties'];
        $this->assertArrayHasKey('vendorName', $properties);
        $this->assertArrayHasKey('packageName', $properties);
        $this->assertArrayHasKey('namespace', $properties);
        $this->assertArrayHasKey('type', $properties);
        $this->assertArrayHasKey('preview', $properties);
    }

    /**
     * Test preview mode is default
     */
    public function testPreviewModeIsDefault()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals(true, $schema['properties']['preview']['default']);
    }

    /**
     * Test type has valid enum values
     */
    public function testTypeHasValidEnum()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $schema = $tool->getInputSchema();
        $type = $schema['properties']['type'];

        $this->assertArrayHasKey('enum', $type);
        $this->assertContains('yii2-extension', $type['enum']);
        $this->assertContains('library', $type['enum']);
    }
}
