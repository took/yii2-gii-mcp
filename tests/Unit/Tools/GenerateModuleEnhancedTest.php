<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use ReflectionClass;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateModule;
use Took\Yii2GiiMCP\Tools\ToolInterface;

/**
 * Enhanced Test for GenerateModule Tool
 * Comprehensive tests without Yii2 dependencies
 */
class GenerateModuleEnhancedTest extends Unit
{
    public function testGetName()
    {
        $this->assertEquals('generate-module', $this->getTool()->getName());
    }

    private function getTool()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);

        return new GenerateModule($bootstrap);
    }

    public function testGetDescriptionContainsKeywords()
    {
        $description = $this->getTool()->getDescription();
        $this->assertStringContainsString('module', strtolower($description));
        $this->assertStringContainsString('preview', strtolower($description));
        $this->assertStringContainsString('Module.php', $description);
        $this->assertStringContainsString('controllers/', $description);
        $this->assertStringContainsString('models/', $description);
        $this->assertStringContainsString('views/', $description);
    }

    public function testInputSchemaStructure()
    {
        $schema = $this->getTool()->getInputSchema();
        $this->assertEquals('object', $schema['type']);
        $this->assertContains('moduleID', $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
    }

    public function testModuleIDProperty()
    {
        $schema = $this->getTool()->getInputSchema();
        $prop = $schema['properties']['moduleID'];
        $this->assertEquals('string', $prop['type']);
        $this->assertStringContainsString('admin', $prop['description']);
        $this->assertStringContainsString('api', $prop['description']);
        $this->assertStringContainsString('v1', $prop['description']);
    }

    public function testModuleClassProperty()
    {
        $schema = $this->getTool()->getInputSchema();
        $prop = $schema['properties']['moduleClass'];
        $this->assertEquals('string', $prop['type']);
        $this->assertStringContainsString('optional', $prop['description']);
        $this->assertStringContainsString('auto-generated', $prop['description']);
    }

    public function testPreviewPropertyDefaults()
    {
        $schema = $this->getTool()->getInputSchema();
        $this->assertTrue($schema['properties']['preview']['default']);
    }

    public function testAllPropertiesHaveDescriptions()
    {
        $schema = $this->getTool()->getInputSchema();
        foreach ($schema['properties'] as $name => $prop) {
            $this->assertArrayHasKey('description', $prop, "Property $name missing description");
            $this->assertNotEmpty($prop['description'], "Property $name has empty description");
        }
    }

    public function testValidateModuleIDWithValidIDs()
    {
        $tool = $this->getTool();
        $method = (new ReflectionClass($tool))->getMethod('validateModuleID');
        $method->setAccessible(true);

        $validIDs = ['admin', 'api', 'v1', 'admin-panel', 'user_module', 'my-admin-module', 'backend'];

        foreach ($validIDs as $id) {
            $this->assertTrue($method->invoke($tool, $id), "Module ID '{$id}' should be valid");
        }
    }

    public function testValidateModuleIDWithInvalidIDs()
    {
        $tool = $this->getTool();
        $method = (new ReflectionClass($tool))->getMethod('validateModuleID');
        $method->setAccessible(true);

        $invalidIDs = [
            'Admin',          // uppercase
            '1admin',         // starts with number
            'admin.module',   // dot
            '',               // empty
            'admin module',   // space
            'ADMIN',          // all uppercase
        ];

        foreach ($invalidIDs as $id) {
            $this->assertFalse($method->invoke($tool, $id), "Module ID '{$id}' should be invalid");
        }
    }

    /**
     * Test formatGiiResult with success in preview mode
     */
    public function testFormatGiiResultWithSuccessInPreviewMode()
    {
        $tool = $this->getTool();

        $result = [
            'success' => true,
            'fileCount' => 5,
            'files' => [
                [
                    'relativePath' => 'modules/admin/Module.php',
                    'operation' => 'create',
                    'content' => '<?php namespace app\modules\admin; class Module extends \yii\base\Module {}',
                ],
                [
                    'relativePath' => 'modules/admin/controllers/',
                    'operation' => 'create',
                    'content' => '',
                ],
                [
                    'relativePath' => 'modules/admin/models/',
                    'operation' => 'create',
                    'content' => '',
                ],
                [
                    'relativePath' => 'modules/admin/views/',
                    'operation' => 'create',
                    'content' => '',
                ],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, true);

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('type', $formatted);
        $this->assertArrayHasKey('text', $formatted);
        $this->assertEquals('text', $formatted['type']);

        $text = $formatted['text'];
        $this->assertStringContainsString('Preview', $text);
        $this->assertStringContainsString('5 file(s)', $text);
        $this->assertStringContainsString('Module Structure:', $text);
        $this->assertStringContainsString('Main Module Class:', $text);
        $this->assertStringContainsString('modules/admin/Module.php', $text);
        $this->assertStringContainsString('```php', $text);
        $this->assertStringContainsString('class Module', $text);
        $this->assertStringContainsString('preview=false', $text);
    }

    /**
     * Test formatGiiResult with success in generation mode
     */
    public function testFormatGiiResultWithSuccessInGenerationMode()
    {
        $tool = $this->getTool();

        $result = [
            'success' => true,
            'fileCount' => 5,
            'created' => 5,
            'skipped' => 0,
            'errors' => 0,
            'files' => [
                [
                    'relativePath' => 'modules/admin/Module.php',
                    'status' => 'created',
                ],
                [
                    'relativePath' => 'modules/admin/controllers/',
                    'status' => 'created',
                ],
                [
                    'relativePath' => 'modules/admin/models/',
                    'status' => 'created',
                ],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, false);

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('type', $formatted);
        $this->assertArrayHasKey('text', $formatted);

        $text = $formatted['text'];
        $this->assertStringContainsString('Generated', $text);
        $this->assertStringContainsString('Created: 5', $text);
        $this->assertStringContainsString('Skipped: 0', $text);
        $this->assertStringContainsString('[CREATED]', $text);
        $this->assertStringContainsString('Module Class:', $text);
        $this->assertStringContainsString('Directories:', $text);
        $this->assertStringContainsString('modules/admin/Module.php', $text);
    }

    /**
     * Test formatGiiResult groups files correctly in generation mode
     */
    public function testFormatGiiResultGroupsFilesByType()
    {
        $tool = $this->getTool();

        $result = [
            'success' => true,
            'fileCount' => 6,
            'created' => 6,
            'skipped' => 0,
            'errors' => 0,
            'files' => [
                [
                    'relativePath' => 'modules/admin/Module.php',
                    'status' => 'created',
                ],
                [
                    'relativePath' => 'modules/admin/controllers/',
                    'status' => 'created',
                ],
                [
                    'relativePath' => 'modules/admin/models/',
                    'status' => 'created',
                ],
                [
                    'relativePath' => 'modules/admin/views/default/index.php',
                    'status' => 'created',
                ],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, false);
        $text = $formatted['text'];

        // Should have separate sections
        $this->assertStringContainsString('Module Class:', $text);
        $this->assertStringContainsString('Directories:', $text);
        $this->assertStringContainsString('Other Files:', $text);
    }

    /**
     * Test formatGiiResult with validation errors
     */
    public function testFormatGiiResultWithValidationErrors()
    {
        $tool = $this->getTool();

        $result = [
            'success' => false,
            'error' => 'Validation failed',
            'validationErrors' => [
                'moduleID' => ['Module ID is invalid'],
                'moduleClass' => ['Module class name is not valid'],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, true);

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('type', $formatted);
        $this->assertArrayHasKey('text', $formatted);

        $text = $formatted['text'];
        $this->assertStringContainsString('Error', $text);
        $this->assertStringContainsString('moduleID', $text);
        $this->assertStringContainsString('moduleClass', $text);
        $this->assertStringContainsString('Module ID is invalid', $text);
    }

    /**
     * Test formatGiiResult with file conflicts
     */
    public function testFormatGiiResultWithConflicts()
    {
        $tool = $this->getTool();

        $result = [
            'success' => false,
            'error' => 'File conflicts detected',
            'conflicts' => [
                ['path' => 'modules/admin/Module.php'],
                ['path' => 'modules/admin/controllers/DefaultController.php'],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, true);

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('type', $formatted);
        $this->assertArrayHasKey('text', $formatted);

        $text = $formatted['text'];
        $this->assertStringContainsString('Error', $text);
        $this->assertStringContainsString('modules/admin/Module.php', $text);
        $this->assertStringContainsString('modules/admin/controllers/DefaultController.php', $text);
        $this->assertStringContainsString('preview=false', $text);
    }

    /**
     * Test formatGiiResult with generic error
     */
    public function testFormatGiiResultWithGenericError()
    {
        $tool = $this->getTool();

        $result = [
            'success' => false,
            'error' => 'Failed to create module structure',
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, true);

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('type', $formatted);
        $this->assertArrayHasKey('text', $formatted);

        $text = $formatted['text'];
        $this->assertStringContainsString('Error', $text);
        $this->assertStringContainsString('Failed to create module structure', $text);
    }

    /**
     * Test preview mode shows helpful information
     */
    public function testPreviewModeShowsHelpfulInfo()
    {
        $tool = $this->getTool();

        $result = [
            'success' => true,
            'fileCount' => 4,
            'files' => [
                [
                    'relativePath' => 'modules/admin/Module.php',
                    'operation' => 'create',
                    'content' => '<?php class Module {}',
                ],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, true);
        $text = $formatted['text'];

        $this->assertStringContainsString('Note:', $text);
        $this->assertStringContainsString('preview=false to generate', $text);
        $this->assertStringContainsString('Module.php class', $text);
        $this->assertStringContainsString('directory structure', $text);
    }

    /**
     * Test tool implements required interfaces
     */
    public function testToolImplementsRequiredInterfaces()
    {
        $tool = $this->getTool();
        $this->assertInstanceOf(ToolInterface::class, $tool);
    }
}
