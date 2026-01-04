<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use ReflectionClass;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateForm;
use Took\Yii2GiiMCP\Tools\ToolInterface;

/**
 * Test GenerateForm Tool
 *
 * Note: These tests use mocks and Reflection to test without Yii2 dependencies.
 */
class GenerateFormTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $this->assertEquals('generate-form', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('form', strtolower($description));
        $this->assertStringContainsString('preview', strtolower($description));
        $this->assertStringContainsString('data collection', strtolower($description));
    }

    /**
     * Test input schema has required fields
     */
    public function testInputSchemaHasRequiredFields()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('modelClass', $schema['required']);

        $properties = $schema['properties'];
        $this->assertArrayHasKey('modelClass', $properties);
        $this->assertArrayHasKey('namespace', $properties);
        $this->assertArrayHasKey('preview', $properties);
        $this->assertArrayHasKey('viewPath', $properties);
        $this->assertArrayHasKey('viewName', $properties);
        $this->assertArrayHasKey('scenarioName', $properties);
    }

    /**
     * Test preview mode is default
     */
    public function testPreviewModeIsDefault()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals(true, $schema['properties']['preview']['default']);
    }

    /**
     * Test schema structure
     */
    public function testSchemaStructure()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test schema defaults
     */
    public function testSchemaDefaults()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        $this->assertEquals('app\\models', $properties['namespace']['default']);
        $this->assertEquals('@app/views', $properties['viewPath']['default']);
        $this->assertEquals('default', $properties['scenarioName']['default']);
        $this->assertEquals(true, $properties['preview']['default']);
    }

    /**
     * Test all properties have descriptions
     */
    public function testAllPropertiesHaveDescriptions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        foreach ($properties as $name => $property) {
            $this->assertArrayHasKey('description', $property, "Property '{$name}' should have a description");
            $this->assertNotEmpty($property['description'], "Property '{$name}' description should not be empty");
        }
    }

    /**
     * Test modelClass property has helpful description
     */
    public function testModelClassDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $schema = $tool->getInputSchema();
        $modelClassProperty = $schema['properties']['modelClass'];

        $this->assertStringContainsString('ContactForm', $modelClassProperty['description']);
        $this->assertStringContainsString('LoginForm', $modelClassProperty['description']);
    }

    /**
     * Test formatGiiResult with success in preview mode
     */
    public function testFormatGiiResultWithSuccessInPreviewMode()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $result = [
            'success' => true,
            'fileCount' => 2,
            'files' => [
                [
                    'relativePath' => 'models/ContactForm.php',
                    'operation' => 'create',
                    'content' => '<?php class ContactForm extends Model {}',
                ],
                [
                    'relativePath' => 'views/contact.php',
                    'operation' => 'create',
                    'content' => '<?php /* View */ ?>',
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
        $text = $formatted['text'];

        $this->assertStringContainsString('Preview', $text);
        $this->assertStringContainsString('models/ContactForm.php', $text);
        $this->assertStringContainsString('views/contact.php', $text);
        $this->assertStringContainsString('ContactForm', $text);
    }

    /**
     * Test formatGiiResult with success in generation mode
     */
    public function testFormatGiiResultWithSuccessInGenerationMode()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $result = [
            'success' => true,
            'fileCount' => 2,
            'created' => 2,
            'skipped' => 0,
            'errors' => 0,
            'files' => [
                [
                    'relativePath' => 'models/ContactForm.php',
                    'status' => 'created',
                ],
                [
                    'relativePath' => 'views/contact.php',
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
        $this->assertStringContainsString('Created: 2', $text);
        $this->assertStringContainsString('[CREATED]', $text);
    }

    /**
     * Test formatGiiResult with validation errors
     */
    public function testFormatGiiResultWithValidationErrors()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $result = [
            'success' => false,
            'error' => 'Validation failed',
            'validationErrors' => [
                'modelClass' => ['Model class name is invalid'],
                'namespace' => ['Namespace must be valid'],
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
        $this->assertStringContainsString('modelClass', $text);
        $this->assertStringContainsString('namespace', $text);
    }

    /**
     * Test formatGiiResult with conflicts
     */
    public function testFormatGiiResultWithConflicts()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $result = [
            'success' => false,
            'error' => 'File conflicts detected',
            'conflicts' => [
                ['path' => 'models/ContactForm.php'],
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
        $this->assertStringContainsString('models/ContactForm.php', $text);
        $this->assertStringContainsString('preview=false', $text);
    }

    /**
     * Test formatGiiResult with generic error
     */
    public function testFormatGiiResultWithGenericError()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $result = [
            'success' => false,
            'error' => 'Unknown error occurred',
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
        $this->assertStringContainsString('Unknown error occurred', $text);
    }

    /**
     * Test tool implements required interfaces
     */
    public function testToolImplementsRequiredInterfaces()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateForm($bootstrap);

        $this->assertInstanceOf(ToolInterface::class, $tool);
    }
}
