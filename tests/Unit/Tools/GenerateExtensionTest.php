<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use ReflectionClass;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\GenerateExtension;
use Took\Yii2GiiMCP\Tools\ToolInterface;

/**
 * Test GenerateExtension Tool
 *
 * Note: These tests use mocks and Reflection to test without Yii2 dependencies.
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
        $this->assertStringContainsString('composer', strtolower($description));
        $this->assertStringContainsString('README', $description);
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

    /**
     * Test schema structure
     */
    public function testSchemaStructure()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test type default value
     */
    public function testTypeDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $schema = $tool->getInputSchema();
        $type = $schema['properties']['type'];

        $this->assertEquals('yii2-extension', $type['default']);
    }

    /**
     * Test all properties have descriptions
     */
    public function testAllPropertiesHaveDescriptions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        foreach ($properties as $name => $property) {
            $this->assertArrayHasKey('description', $property, "Property '{$name}' should have a description");
            $this->assertNotEmpty($property['description'], "Property '{$name}' description should not be empty");
        }
    }

    /**
     * Test vendorName description has helpful examples
     */
    public function testVendorNameDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $schema = $tool->getInputSchema();
        $vendorName = $schema['properties']['vendorName'];

        $this->assertStringContainsString('yiisoft', $vendorName['description']);
        $this->assertStringContainsString('mycompany', $vendorName['description']);
    }

    /**
     * Test packageName description has helpful examples
     */
    public function testPackageNameDescription()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $schema = $tool->getInputSchema();
        $packageName = $schema['properties']['packageName'];

        $this->assertStringContainsString('yii2-widget', $packageName['description']);
        $this->assertStringContainsString('yii2-helper', $packageName['description']);
    }

    /**
     * Test validateName with valid names
     */
    public function testValidateNameWithValidNames()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $validNames = ['yiisoft', 'mycompany', 'my-company', 'test123', 'vendor-name-with-dashes'];

        foreach ($validNames as $name) {
            $result = $method->invoke($tool, $name);
            $this->assertTrue($result, "Name '{$name}' should be valid");
        }
    }

    /**
     * Test validateName with invalid names
     */
    public function testValidateNameWithInvalidNames()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $invalidNames = ['MyCompany', 'vendor_name', 'vendor.name', '123vendor', '-vendor', 'vendor name'];

        foreach ($invalidNames as $name) {
            $result = $method->invoke($tool, $name);
            $this->assertFalse($result, "Name '{$name}' should be invalid");
        }
    }

    /**
     * Test formatGiiResult with success in preview mode
     */
    public function testFormatGiiResultWithSuccessInPreviewMode()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $result = [
            'success' => true,
            'fileCount' => 3,
            'files' => [
                [
                    'relativePath' => 'composer.json',
                    'operation' => 'create',
                    'content' => '{"name": "vendor/package"}',
                ],
                [
                    'relativePath' => 'README.md',
                    'operation' => 'create',
                    'content' => '# Extension',
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
        $this->assertStringContainsString('composer.json', $text);
        $this->assertStringContainsString('README.md', $text);
    }

    /**
     * Test formatGiiResult with success in generation mode
     */
    public function testFormatGiiResultWithSuccessInGenerationMode()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $result = [
            'success' => true,
            'fileCount' => 3,
            'created' => 3,
            'skipped' => 0,
            'errors' => 0,
            'files' => [
                [
                    'relativePath' => 'composer.json',
                    'status' => 'created',
                ],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, false);

        $this->assertIsArray($formatted);
        $text = $formatted['text'];

        $this->assertStringContainsString('Generated', $text);
        $this->assertStringContainsString('Created: 3', $text);
        $this->assertStringContainsString('[CREATED]', $text);
    }

    /**
     * Test formatGiiResult with validation errors
     */
    public function testFormatGiiResultWithValidationErrors()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $result = [
            'success' => false,
            'error' => 'Validation failed',
            'validationErrors' => [
                'vendorName' => ['Vendor name is invalid'],
                'packageName' => ['Package name is invalid'],
            ],
        ];

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatGiiResult');
        $method->setAccessible(true);

        $formatted = $method->invoke($tool, $result, true);

        $this->assertIsArray($formatted);
        $text = $formatted['text'];

        $this->assertStringContainsString('Error', $text);
        $this->assertStringContainsString('vendorName', $text);
        $this->assertStringContainsString('packageName', $text);
    }

    /**
     * Test tool implements required interfaces
     */
    public function testToolImplementsRequiredInterfaces()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new GenerateExtension($bootstrap);

        $this->assertInstanceOf(ToolInterface::class, $tool);
    }
}
