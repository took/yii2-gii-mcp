<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateModule;

/**
 * Test GenerateModule Tool
 *
 * Note: These tests use mocks and do not require Yii2 installation.
 * Tests cover the production implementation of the module generator.
 */
class GenerateModuleTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModule($bootstrap);

        $this->assertEquals('generate-module', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModule($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('module', strtolower($description));
    }

    /**
     * Test input schema has required fields
     */
    public function testInputSchemaHasRequiredFields()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModule($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('moduleID', $schema['required']);

        $properties = $schema['properties'];
        $this->assertArrayHasKey('moduleID', $properties);
        $this->assertArrayHasKey('moduleClass', $properties);
        $this->assertArrayHasKey('preview', $properties);
    }

    /**
     * Test preview mode is default
     */
    public function testPreviewModeIsDefault()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModule($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals(true, $schema['properties']['preview']['default']);
    }
}
