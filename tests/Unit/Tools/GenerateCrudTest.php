<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateCrud;

/**
 * Enhanced Test for GenerateCrud Tool
 * Comprehensive tests without Yii2 dependencies including formatGiiResult()
 */
class GenerateCrudTest extends Unit
{
    public function testGetName()
    {
        $this->assertEquals('generate-crud', $this->getTool()->getName());
    }

    private function getTool()
    {
        return new GenerateCrud($this->createMock(Yii2Bootstrap::class));
    }

    public function testGetDescription()
    {
        $description = $this->getTool()->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('crud', strtolower($description));
        $this->assertStringContainsString('controller', strtolower($description));
        $this->assertStringContainsString('preview', strtolower($description));
    }

    public function testInputSchemaHasRequiredFields()
    {
        $schema = $this->getTool()->getInputSchema();

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('modelClass', $schema['required']);

        $properties = $schema['properties'];
        $this->assertArrayHasKey('modelClass', $properties);
        $this->assertArrayHasKey('controllerClass', $properties);
        $this->assertArrayHasKey('viewPath', $properties);
        $this->assertArrayHasKey('searchModelClass', $properties);
        $this->assertArrayHasKey('preview', $properties);
    }

    public function testPreviewModeIsDefault()
    {
        $schema = $this->getTool()->getInputSchema();
        $this->assertEquals(true, $schema['properties']['preview']['default']);
    }

    public function testIndexWidgetTypeHasValidEnum()
    {
        $schema = $this->getTool()->getInputSchema();
        $indexWidgetType = $schema['properties']['indexWidgetType'];

        $this->assertArrayHasKey('enum', $indexWidgetType);
        $this->assertContains('grid', $indexWidgetType['enum']);
        $this->assertContains('list', $indexWidgetType['enum']);
        $this->assertEquals('grid', $indexWidgetType['default']);
    }

    public function testSchemaStructure()
    {
        $schema = $this->getTool()->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertFalse($schema['additionalProperties']);
    }

    public function testBaseControllerClassHasDefault()
    {
        $schema = $this->getTool()->getInputSchema();
        $prop = $schema['properties']['baseControllerClass'];

        $this->assertEquals('yii\\web\\Controller', $prop['default']);
    }

    public function testEnableI18NHasDefault()
    {
        $schema = $this->getTool()->getInputSchema();
        $prop = $schema['properties']['enableI18N'];

        $this->assertFalse($prop['default']);
        $this->assertEquals('boolean', $prop['type']);
    }

    public function testAllPropertiesHaveDescriptions()
    {
        $schema = $this->getTool()->getInputSchema();

        foreach ($schema['properties'] as $name => $prop) {
            $this->assertArrayHasKey('description', $prop, "Property $name missing description");
            $this->assertNotEmpty($prop['description']);
        }
    }

    public function testModelClassDescription()
    {
        $schema = $this->getTool()->getInputSchema();
        $description = $schema['properties']['modelClass']['description'];

        $this->assertStringContainsString('app\\models\\User', $description);
    }

    /**
     * Test formatGiiResult with success in preview mode
     */
    public function testFormatGiiResultWithSuccessInPreviewMode()
    {
        $tool = $this->getTool();
        $method = (new \ReflectionClass($tool))->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => true,
            'fileCount' => 8,
            'files' => [
                [
                    'relativePath' => 'controllers/UserController.php',
                    'operation' => 'create',
                ],
                [
                    'relativePath' => 'models/UserSearch.php',
                    'operation' => 'create',
                ],
                [
                    'relativePath' => 'views/user/index.php',
                    'operation' => 'create',
                ],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, true);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Preview', $result['text']);
        $this->assertStringContainsString('8 file(s)', $result['text']);
        $this->assertStringContainsString('UserController.php', $result['text']);
        $this->assertStringContainsString('UserSearch.php', $result['text']);
        $this->assertStringContainsString('index.php', $result['text']);
    }

    /**
     * Test formatGiiResult with success in generation mode
     */
    public function testFormatGiiResultWithSuccessInGenerationMode()
    {
        $tool = $this->getTool();
        $method = (new \ReflectionClass($tool))->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => true,
            'fileCount' => 8,
            'created' => 8,
            'skipped' => 0,
            'errors' => 0,
            'files' => [
                [
                    'relativePath' => 'controllers/UserController.php',
                    'status' => 'created',
                ],
                [
                    'relativePath' => 'models/UserSearch.php',
                    'status' => 'created',
                ],
                [
                    'relativePath' => 'views/user/index.php',
                    'status' => 'created',
                ],
                [
                    'relativePath' => 'views/user/_form.php',
                    'status' => 'created',
                ],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, false);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Generated', $result['text']);
        $this->assertStringContainsString('8 file(s)', $result['text']);
        $this->assertStringContainsString('Created: 8', $result['text']);
        $this->assertStringContainsString('[CREATED]', $result['text']);
    }

    /**
     * Test formatGiiResult groups files by type
     */
    public function testFormatGiiResultGroupsByType()
    {
        $tool = $this->getTool();
        $method = (new \ReflectionClass($tool))->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => true,
            'fileCount' => 3,
            'created' => 3,
            'skipped' => 0,
            'errors' => 0,
            'files' => [
                ['relativePath' => 'controllers/PostController.php', 'status' => 'created'],
                ['relativePath' => 'models/PostSearch.php', 'status' => 'created'],
                ['relativePath' => 'views/post/index.php', 'status' => 'created'],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, false);

        $this->assertStringContainsString('Controllers:', $result['text']);
        $this->assertStringContainsString('Models:', $result['text']);
        $this->assertStringContainsString('Views:', $result['text']);
    }

    /**
     * Test formatGiiResult with validation errors
     */
    public function testFormatGiiResultWithValidationErrors()
    {
        $tool = $this->getTool();
        $method = (new \ReflectionClass($tool))->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => false,
            'error' => 'Validation failed',
            'validationErrors' => [
                'modelClass' => ['Model class is required'],
                'controllerClass' => ['Invalid controller class format'],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, true);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Error:', $result['text']);
        $this->assertStringContainsString('Validation failed', $result['text']);
        $this->assertStringContainsString('modelClass', $result['text']);
    }

    /**
     * Test formatGiiResult with file conflicts
     */
    public function testFormatGiiResultWithConflicts()
    {
        $tool = $this->getTool();
        $method = (new \ReflectionClass($tool))->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => false,
            'error' => 'File conflicts',
            'conflicts' => [
                ['path' => 'controllers/UserController.php'],
                ['path' => 'views/user/index.php'],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, false);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Error:', $result['text']);
        $this->assertStringContainsString('File conflicts', $result['text']);
        $this->assertStringContainsString('UserController.php', $result['text']);
        $this->assertStringContainsString('index.php', $result['text']);
    }

    /**
     * Test formatGiiResult with generic error
     */
    public function testFormatGiiResultWithGenericError()
    {
        $tool = $this->getTool();
        $method = (new \ReflectionClass($tool))->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => false,
            'error' => 'Model class not found',
        ];

        $result = $method->invoke($tool, $giiResult, true);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Error:', $result['text']);
        $this->assertStringContainsString('Model class not found', $result['text']);
    }

    /**
     * Test preview mode message includes helpful info
     */
    public function testPreviewModeIncludesHelpfulInfo()
    {
        $tool = $this->getTool();
        $method = (new \ReflectionClass($tool))->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => true,
            'fileCount' => 1,
            'files' => [
                ['relativePath' => 'test.php', 'operation' => 'create'],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, true);

        $this->assertStringContainsString('preview=false', $result['text']);
        $this->assertStringContainsString('controller', $result['text']);
        $this->assertStringContainsString('views', $result['text']);
    }

    /**
     * Test tool implements required interfaces
     */
    public function testToolImplementsRequiredInterfaces()
    {
        $tool = $this->getTool();

        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\ToolInterface::class, $tool);
        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\AbstractTool::class, $tool);
    }
}
