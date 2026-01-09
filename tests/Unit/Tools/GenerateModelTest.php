<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use ReflectionClass;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateModel;
use Took\Yii2GiiMCP\Tools\ToolInterface;

/**
 * Test GenerateModel Tool
 *
 * Note: These tests use mocks and Reflection to test without Yii2 dependencies.
 */
class GenerateModelTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $this->assertEquals('generate-model', $tool->getName());
    }

    /**
     * Test tool description
     */
    public function testGetDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('model', strtolower($description));
        $this->assertStringContainsString('preview', strtolower($description));
        $this->assertStringContainsString('ActiveRecord', $description);
    }

    /**
     * Test input schema structure
     */
    public function testGetInputSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        // Check required fields
        $this->assertContains('tableName', $schema['required']);

        // Check important properties
        $properties = $schema['properties'];
        $this->assertArrayHasKey('tableName', $properties);
        $this->assertArrayHasKey('preview', $properties);
        $this->assertArrayHasKey('namespace', $properties);
        $this->assertArrayHasKey('modelClass', $properties);
        $this->assertArrayHasKey('baseClass', $properties);
        $this->assertArrayHasKey('db', $properties);
        $this->assertArrayHasKey('generateRelations', $properties);
        $this->assertArrayHasKey('generateLabelsFromComments', $properties);
    }

    /**
     * Test preview mode is default
     */
    public function testPreviewModeIsDefault()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $schema = $tool->getInputSchema();
        $previewProperty = $schema['properties']['preview'];

        $this->assertEquals(true, $previewProperty['default']);
    }

    /**
     * Test input schema validation
     */
    public function testInputSchemaHasProperValidation()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $schema = $tool->getInputSchema();

        // Should not allow additional properties (for security)
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertEquals(false, $schema['additionalProperties']);
    }

    /**
     * Test schema has proper defaults
     */
    public function testSchemaDefaults()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        // Default namespace is 'common\models' for Advanced Template (used in most projects)
        // Default namespace is 'web\models' for Basic Template (default template)
        // The actual runtime default is determined by bootstrap->getDefaultModelNamespace()
        $this->assertContains($properties['namespace']['default'], ['common\\models', 'web\\models']);
        $this->assertEquals('yii\\db\\ActiveRecord', $properties['baseClass']['default']);
        $this->assertEquals('db', $properties['db']['default']);
        $this->assertEquals('all', $properties['generateRelations']['default']);
        $this->assertEquals(true, $properties['generateLabelsFromComments']['default']);
        $this->assertEquals(true, $properties['preview']['default']);
    }

    /**
     * Test generateRelations has valid enum values
     */
    public function testGenerateRelationsEnum()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $schema = $tool->getInputSchema();
        $relationsProperty = $schema['properties']['generateRelations'];

        $this->assertArrayHasKey('enum', $relationsProperty);
        $this->assertContains('all', $relationsProperty['enum']);
        $this->assertContains('none', $relationsProperty['enum']);
    }

    /**
     * Test all properties have descriptions
     */
    public function testAllPropertiesHaveDescriptions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        foreach ($properties as $name => $property) {
            $this->assertArrayHasKey('description', $property, "Property '{$name}' should have a description");
            $this->assertNotEmpty($property['description'], "Property '{$name}' description should not be empty");
        }
    }

    /**
     * Test tableName property description
     */
    public function testTableNameDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $schema = $tool->getInputSchema();
        $tableNameProperty = $schema['properties']['tableName'];

        $this->assertStringContainsString('table', strtolower($tableNameProperty['description']));
        $this->assertStringContainsString('database', strtolower($tableNameProperty['description']));
    }

    /**
     * Test formatGiiResult with success in preview mode
     */
    public function testFormatGiiResultWithSuccessInPreviewMode()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $result = [
            'success' => true,
            'fileCount' => 1,
            'files' => [
                [
                    'relativePath' => 'models/User.php',
                    'operation' => 'create',
                    'content' => '<?php class User {}',
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
        $this->assertStringContainsString('models/User.php', $text);
        $this->assertStringContainsString('<?php class User {}', $text);
        $this->assertStringContainsString('```php', $text);
    }

    /**
     * Test formatGiiResult with success in generation mode
     */
    public function testFormatGiiResultWithSuccessInGenerationMode()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $result = [
            'success' => true,
            'fileCount' => 1,
            'created' => 1,
            'skipped' => 0,
            'errors' => 0,
            'files' => [
                [
                    'relativePath' => 'models/User.php',
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
        $this->assertStringContainsString('Created: 1', $text);
        $this->assertStringContainsString('Skipped: 0', $text);
        $this->assertStringContainsString('[CREATED] models/User.php', $text);
    }

    /**
     * Test formatGiiResult with validation errors
     */
    public function testFormatGiiResultWithValidationErrors()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $result = [
            'success' => false,
            'error' => 'Validation failed',
            'validationErrors' => [
                'tableName' => ['Table name is invalid'],
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
        $this->assertEquals('text', $formatted['type']);

        $text = $formatted['text'];
        $this->assertStringContainsString('Error', $text);
        $this->assertStringContainsString('tableName', $text);
        $this->assertStringContainsString('namespace', $text);
    }

    /**
     * Test formatGiiResult with conflicts
     */
    public function testFormatGiiResultWithConflicts()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $result = [
            'success' => false,
            'error' => 'File conflicts detected',
            'conflicts' => [
                ['path' => 'models/User.php'],
                ['path' => 'models/Post.php'],
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
        $this->assertStringContainsString('models/User.php', $text);
        $this->assertStringContainsString('models/Post.php', $text);
        $this->assertStringContainsString('preview=false', $text);
    }

    /**
     * Test formatGiiResult with generic error
     */
    public function testFormatGiiResultWithGenericError()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $result = [
            'success' => false,
            'error' => 'Something went wrong',
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
        $this->assertStringContainsString('Something went wrong', $text);
    }

    /**
     * Test preview mode shows content
     */
    public function testPreviewModeShowsContent()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $result = [
            'success' => true,
            'fileCount' => 1,
            'files' => [
                [
                    'relativePath' => 'models/User.php',
                    'operation' => 'create',
                    'content' => '<?php namespace app\models; class User extends ActiveRecord {}',
                ],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, true);
        $text = $formatted['text'];

        $this->assertStringContainsString('Content:', $text);
        $this->assertStringContainsString('ActiveRecord', $text);
        $this->assertStringContainsString('Operation: create', $text);
    }

    /**
     * Test tool implements required interfaces
     */
    public function testToolImplementsRequiredInterfaces()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateModel($bootstrap);

        $this->assertInstanceOf(ToolInterface::class, $tool);
    }
}
