<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateCrud;

/**
 * Test GenerateCrud Tool
 *
 * Note: These tests use mocks and do not require Yii2 installation.
 * Some tests may fail initially (TDD approach) until full implementation.
 */
class GenerateCrudTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateCrud($bootstrap);

        $this->assertEquals('generate-crud', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateCrud($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('crud', strtolower($description));
        $this->assertStringContainsString('controller', strtolower($description));
    }

    /**
     * Test input schema has required fields
     */
    public function testInputSchemaHasRequiredFields()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateCrud($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('modelClass', $schema['required']);

        $properties = $schema['properties'];
        $this->assertArrayHasKey('modelClass', $properties);
        $this->assertArrayHasKey('controllerClass', $properties);
        $this->assertArrayHasKey('viewPath', $properties);
        $this->assertArrayHasKey('searchModelClass', $properties);
        $this->assertArrayHasKey('preview', $properties);
    }

    /**
     * Test preview mode is default
     */
    public function testPreviewModeIsDefault()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateCrud($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals(true, $schema['properties']['preview']['default']);
    }

    /**
     * Test indexWidgetType has valid enum values
     */
    public function testIndexWidgetTypeHasValidEnum()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateCrud($bootstrap);

        $schema = $tool->getInputSchema();
        $indexWidgetType = $schema['properties']['indexWidgetType'];

        $this->assertArrayHasKey('enum', $indexWidgetType);
        $this->assertContains('grid', $indexWidgetType['enum']);
        $this->assertContains('list', $indexWidgetType['enum']);
    }
}
