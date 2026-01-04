<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateController;

/**
 * Test GenerateController Tool
 *
 * Tests focus on tool metadata, input schema validation, and validation methods.
 * Execution tests (doExecute with GiiHelper) require Yii2 dependencies.
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
        $this->assertStringContainsString('preview', strtolower($description));
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
        $this->assertArrayHasKey('baseClass', $properties);
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
     * Test namespace has default value
     */
    public function testNamespaceHasDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals('app\\controllers', $schema['properties']['namespace']['default']);
    }

    /**
     * Test baseClass has default value
     */
    public function testBaseClassHasDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals('yii\\web\\Controller', $schema['properties']['baseClass']['default']);
    }

    /**
     * Test schema structure
     */
    public function testInputSchemaStructure()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test controllerID property structure
     */
    public function testControllerIDPropertyStructure()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();
        $prop = $schema['properties']['controllerID'];

        $this->assertEquals('string', $prop['type']);
        $this->assertArrayHasKey('description', $prop);
        $this->assertStringContainsString('Controller ID', $prop['description']);
    }

    /**
     * Test actions property structure
     */
    public function testActionsPropertyStructure()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();
        $prop = $schema['properties']['actions'];

        $this->assertEquals('string', $prop['type']);
        $this->assertArrayHasKey('description', $prop);
        $this->assertArrayHasKey('default', $prop);
        $this->assertStringContainsString('action', strtolower($prop['description']));
    }

    /**
     * Test namespace property structure
     */
    public function testNamespacePropertyStructure()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();
        $prop = $schema['properties']['namespace'];

        $this->assertEquals('string', $prop['type']);
        $this->assertArrayHasKey('description', $prop);
        $this->assertArrayHasKey('default', $prop);
    }

    /**
     * Test preview property structure
     */
    public function testPreviewPropertyStructure()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();
        $prop = $schema['properties']['preview'];

        $this->assertEquals('boolean', $prop['type']);
        $this->assertArrayHasKey('description', $prop);
        $this->assertArrayHasKey('default', $prop);
        $this->assertTrue($prop['default']);
    }

    /**
     * Test validateControllerID with valid IDs
     */
    public function testValidateControllerIDWithValidIDs()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('validateControllerID');
        $method->setAccessible(true);

        // Valid simple controller IDs
        $this->assertTrue($method->invoke($tool, 'user'));
        $this->assertTrue($method->invoke($tool, 'post'));
        $this->assertTrue($method->invoke($tool, 'user-profile'));
        $this->assertTrue($method->invoke($tool, 'blog-post'));
        
        // Valid with path
        $this->assertTrue($method->invoke($tool, 'admin/user'));
        $this->assertTrue($method->invoke($tool, 'backend/post'));
        $this->assertTrue($method->invoke($tool, 'api/v1/user'));
    }

    /**
     * Test validateControllerID with invalid IDs
     */
    public function testValidateControllerIDWithInvalidIDs()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('validateControllerID');
        $method->setAccessible(true);

        // Invalid - starts with uppercase
        $this->assertFalse($method->invoke($tool, 'User'));
        $this->assertFalse($method->invoke($tool, 'Post'));
        
        // Invalid - starts with number
        $this->assertFalse($method->invoke($tool, '1user'));
        
        // Invalid - contains special characters
        $this->assertFalse($method->invoke($tool, 'user_profile'));
        $this->assertFalse($method->invoke($tool, 'user.profile'));
        $this->assertFalse($method->invoke($tool, 'user profile'));
        
        // Invalid - empty
        $this->assertFalse($method->invoke($tool, ''));
    }

    /**
     * Test validateActions with valid actions
     */
    public function testValidateActionsWithValidActions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('validateActions');
        $method->setAccessible(true);

        // Valid single actions
        $this->assertTrue($method->invoke($tool, 'index'));
        $this->assertTrue($method->invoke($tool, 'view'));
        $this->assertTrue($method->invoke($tool, 'create'));
        
        // Valid camelCase actions
        $this->assertTrue($method->invoke($tool, 'actionIndex'));
        $this->assertTrue($method->invoke($tool, 'myAction'));
        
        // Valid multiple actions
        $this->assertTrue($method->invoke($tool, 'index,view'));
        $this->assertTrue($method->invoke($tool, 'index,view,create,update,delete'));
        
        // Valid with spaces (trimmed)
        $this->assertTrue($method->invoke($tool, 'index, view, create'));
    }

    /**
     * Test validateActions with invalid actions
     */
    public function testValidateActionsWithInvalidActions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('validateActions');
        $method->setAccessible(true);

        // Invalid - starts with uppercase
        $this->assertFalse($method->invoke($tool, 'Index'));
        
        // Invalid - starts with number
        $this->assertFalse($method->invoke($tool, '1action'));
        
        // Invalid - contains hyphen
        $this->assertFalse($method->invoke($tool, 'my-action'));
        
        // Invalid - contains underscore
        $this->assertFalse($method->invoke($tool, 'my_action'));
        
        // Invalid - contains space
        $this->assertFalse($method->invoke($tool, 'my action'));
        
        // Invalid - one bad action in list
        $this->assertFalse($method->invoke($tool, 'index,My-Action,create'));
        
        // Invalid - empty
        $this->assertFalse($method->invoke($tool, ''));
    }

    /**
     * Test formatGiiResult with success in preview mode
     */
    public function testFormatGiiResultWithSuccessInPreviewMode()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => true,
            'fileCount' => 1,
            'files' => [
                [
                    'relativePath' => 'controllers/UserController.php',
                    'operation' => 'create',
                    'content' => '<?php class UserController {}',
                ],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, true);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Preview', $result['text']);
        $this->assertStringContainsString('1 file(s)', $result['text']);
        $this->assertStringContainsString('UserController.php', $result['text']);
        $this->assertStringContainsString('```php', $result['text']);
    }

    /**
     * Test formatGiiResult with success in generation mode
     */
    public function testFormatGiiResultWithSuccessInGenerationMode()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => true,
            'fileCount' => 1,
            'created' => 1,
            'skipped' => 0,
            'errors' => 0,
            'files' => [
                [
                    'relativePath' => 'controllers/UserController.php',
                    'status' => 'created',
                ],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, false);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Generated', $result['text']);
        $this->assertStringContainsString('1 file(s)', $result['text']);
        $this->assertStringContainsString('Created: 1', $result['text']);
        $this->assertStringContainsString('[CREATED]', $result['text']);
    }

    /**
     * Test formatGiiResult with validation errors
     */
    public function testFormatGiiResultWithValidationErrors()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => false,
            'error' => 'Validation failed',
            'validationErrors' => [
                'controllerID' => ['Controller ID cannot be empty'],
                'namespace' => ['Invalid namespace format'],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, true);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Error:', $result['text']);
        $this->assertStringContainsString('Validation failed', $result['text']);
        $this->assertStringContainsString('controllerID', $result['text']);
        $this->assertStringContainsString('namespace', $result['text']);
    }

    /**
     * Test formatGiiResult with file conflicts
     */
    public function testFormatGiiResultWithConflicts()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => false,
            'error' => 'File conflicts',
            'conflicts' => [
                ['path' => 'controllers/UserController.php'],
            ],
        ];

        $result = $method->invoke($tool, $giiResult, false);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Error:', $result['text']);
        $this->assertStringContainsString('File conflicts', $result['text']);
        $this->assertStringContainsString('UserController.php', $result['text']);
    }

    /**
     * Test formatGiiResult with generic error
     */
    public function testFormatGiiResultWithGenericError()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $giiResult = [
            'success' => false,
            'error' => 'Something went wrong',
        ];

        $result = $method->invoke($tool, $giiResult, true);

        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);
        $this->assertStringContainsString('Error:', $result['text']);
        $this->assertStringContainsString('Something went wrong', $result['text']);
    }

    /**
     * Test tool implements required interfaces
     */
    public function testToolImplementsRequiredInterfaces()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\ToolInterface::class, $tool);
        $this->assertInstanceOf(\Took\Yii2GiiMCP\Tools\AbstractTool::class, $tool);
    }

    /**
     * Test schema disallows additional properties
     */
    public function testSchemaDisallowsAdditionalProperties()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test all properties have descriptions
     */
    public function testAllPropertiesHaveDescriptions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();

        foreach ($schema['properties'] as $propName => $propSchema) {
            $this->assertArrayHasKey('description', $propSchema, 
                "Property '{$propName}' should have 'description'");
            $this->assertNotEmpty($propSchema['description'], 
                "Property '{$propName}' description should not be empty");
        }
    }

    /**
     * Test controllerID examples in description
     */
    public function testControllerIDExamplesInDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();
        $description = $schema['properties']['controllerID']['description'];

        $this->assertStringContainsString('user', $description);
        $this->assertStringContainsString('admin/user', $description);
    }

    /**
     * Test actions examples in description
     */
    public function testActionsExamplesInDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateController($bootstrap);

        $schema = $tool->getInputSchema();
        $description = $schema['properties']['actions']['description'];

        $this->assertStringContainsString('index', $description);
        $this->assertStringContainsString('comma-separated', strtolower($description));
    }
}
