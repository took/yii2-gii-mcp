<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateController;

/**
 * Test GenerateController Tool
 *
 * Note: These tests use mocks and do not require Yii2 installation.
 * Some tests may fail initially (TDD approach) until full implementation.
 */
class GenerateControllerTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $this->assertEquals('generate-controller', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('controller', strtolower($description));
        $this->assertStringContainsString('action', strtolower($description));
    }

    /**
     * Test input schema has required fields
     */
    public function testInputSchemaHasRequiredFields()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('controllerID', $schema['required']);

        // Check properties
        $properties = $schema['properties'];
        $this->assertArrayHasKey('controllerID', $properties);
        $this->assertArrayHasKey('actions', $properties);
        $this->assertArrayHasKey('namespace', $properties);
        $this->assertArrayHasKey('preview', $properties);
    }

    /**
     * Test preview mode is default
     */
    public function testPreviewModeIsDefault()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals(true, $schema['properties']['preview']['default']);
    }

    /**
     * Test actions default value
     */
    public function testActionsHasDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals('index', $schema['properties']['actions']['default']);
    }

    /**
     * Test execute with controller generation (mock)
     *
     * @skip This test requires Gii mocking infrastructure - to be implemented
     */
    public function testExecuteWithControllerGeneration()
    {
        $this->markTestSkipped('Requires Gii generator mocking - TDD placeholder');
    }
}
