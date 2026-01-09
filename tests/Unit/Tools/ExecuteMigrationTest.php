<?php

namespace Tests\Unit\Tools;

use Codeception\Test\Unit;
use ReflectionClass;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;
use Took\Yii2GiiMCP\Tools\ExecuteMigration;
use Took\Yii2GiiMCP\Tools\ToolInterface;

/**
 * Test ExecuteMigration Tool
 *
 * Note: These tests use mocks and Reflection to test without Yii2 dependencies.
 * Focus on safety validation logic.
 */
class ExecuteMigrationTest extends Unit
{
    /**
     * Test tool name
     */
    public function testGetName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $this->assertEquals('execute-migration', $tool->getName());
    }

    /**
     * Test tool description mentions safety
     */
    public function testGetDescriptionMentionsSafety()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('confirmation', strtolower($description));
        $this->assertStringContainsString('warning', strtolower($description));
    }

    /**
     * Test implements ToolInterface
     */
    public function testImplementsToolInterface()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $this->assertInstanceOf(ToolInterface::class, $tool);
    }

    /**
     * Test input schema structure
     */
    public function testGetInputSchema()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
    }

    /**
     * Test operation is required
     */
    public function testOperationIsRequired()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertContains('operation', $schema['required']);
    }

    /**
     * Test confirmation is required
     */
    public function testConfirmationIsRequired()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertContains('confirmation', $schema['required']);
    }

    /**
     * Test operation enum values
     */
    public function testOperationEnumValues()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();
        $operationProperty = $schema['properties']['operation'];

        $this->assertArrayHasKey('enum', $operationProperty);
        $this->assertContains('up', $operationProperty['enum']);
        $this->assertContains('down', $operationProperty['enum']);
        $this->assertContains('create', $operationProperty['enum']);
        $this->assertContains('redo', $operationProperty['enum']);
        $this->assertContains('fresh', $operationProperty['enum']);
    }

    /**
     * Test preview default is true
     */
    public function testPreviewDefaultIsTrue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();
        $previewProperty = $schema['properties']['preview'];

        $this->assertEquals(true, $previewProperty['default']);
    }

    /**
     * Test input schema has all properties
     */
    public function testInputSchemaHasAllProperties()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        $this->assertArrayHasKey('operation', $properties);
        $this->assertArrayHasKey('migrationName', $properties);
        $this->assertArrayHasKey('migrationCount', $properties);
        $this->assertArrayHasKey('fields', $properties);
        $this->assertArrayHasKey('confirmation', $properties);
        $this->assertArrayHasKey('destructiveConfirmation', $properties);
        $this->assertArrayHasKey('preview', $properties);
    }

    /**
     * Test fields is array type
     */
    public function testFieldsIsArrayType()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();
        $fieldsProperty = $schema['properties']['fields'];

        $this->assertEquals('array', $fieldsProperty['type']);
        $this->assertArrayHasKey('items', $fieldsProperty);
        $this->assertEquals('string', $fieldsProperty['items']['type']);
    }

    /**
     * Test migrationCount default value
     */
    public function testMigrationCountDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();
        $countProperty = $schema['properties']['migrationCount'];

        $this->assertEquals(1, $countProperty['default']);
    }

    /**
     * Test no additional properties allowed
     */
    public function testNoAdditionalProperties()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Test validateConfirmations accepts valid confirmation
     */
    public function testValidateConfirmationsValid()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateConfirmations');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'up', 'yes', null);

        $this->assertNull($result);
    }

    /**
     * Test validateConfirmations rejects invalid confirmation
     */
    public function testValidateConfirmationsInvalid()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateConfirmations');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'up', 'YES', null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('text', $result);
    }

    /**
     * Test validateConfirmations requires destructive confirmation for down
     */
    public function testValidateConfirmationsRequiresDestructiveForDown()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateConfirmations');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'down', 'yes', null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('text', $result);
    }

    /**
     * Test validateConfirmations accepts valid destructive confirmation
     */
    public function testValidateConfirmationsValidDestructive()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateConfirmations');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'down', 'yes', 'I understand this will modify the database');

        $this->assertNull($result);
    }

    /**
     * Test validateConfirmations requires destructive confirmation for fresh
     */
    public function testValidateConfirmationsRequiresDestructiveForFresh()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateConfirmations');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'fresh', 'yes', null);

        $this->assertIsArray($result);
    }

    /**
     * Test validateConfirmations requires destructive confirmation for redo
     */
    public function testValidateConfirmationsRequiresDestructiveForRedo()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateConfirmations');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'redo', 'yes', null);

        $this->assertIsArray($result);
    }

    /**
     * Test validateConfirmations does not require destructive for up
     */
    public function testValidateConfirmationsNoDestructiveForUp()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateConfirmations');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'up', 'yes', null);

        $this->assertNull($result);
    }

    /**
     * Test validateConfirmations does not require destructive for create
     */
    public function testValidateConfirmationsNoDestructiveForCreate()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateConfirmations');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'create', 'yes', null);

        $this->assertNull($result);
    }

    /**
     * Test validateOperationRequirements for create without name
     */
    public function testValidateOperationRequirementsCreateWithoutName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateOperationRequirements');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'create', null, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('text', $result);
    }

    /**
     * Test validateOperationRequirements for create with name
     */
    public function testValidateOperationRequirementsCreateWithName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('validateOperationRequirements');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'create', 'create_users_table', []);

        $this->assertNull($result);
    }

    /**
     * Test createPreviewResult structure
     */
    public function testCreatePreviewResult()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('createPreviewResult');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'up', null, 1, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('text', $result);
        $text = $result['text'];
        $this->assertStringContainsString('Preview', $text);
        $this->assertStringContainsString('up', $text);
    }

    /**
     * Test createPreviewResult includes operation
     */
    public function testCreatePreviewResultIncludesOperation()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('createPreviewResult');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'down', 'test_migration', 1, []);

        $text = $result['text'];
        $this->assertStringContainsString('down', $text);
    }

    /**
     * Test createPreviewResult shows warning for destructive operations
     */
    public function testCreatePreviewResultShowsWarningForDestructive()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('createPreviewResult');
        $method->setAccessible(true);

        $result = $method->invoke($tool, 'fresh', null, 1, []);

        $text = $result['text'];
        $this->assertStringContainsString('DANGER', $text);
    }

    /**
     * Test createPreviewResult includes fields for create
     */
    public function testCreatePreviewResultIncludesFields()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('createPreviewResult');
        $method->setAccessible(true);

        $fields = ['name:string', 'email:string'];
        $result = $method->invoke($tool, 'create', 'test', 1, $fields);

        $text = $result['text'];
        $this->assertStringContainsString('name:string', $text);
        $this->assertStringContainsString('email:string', $text);
    }

    /**
     * Test formatExecutionResult structure
     */
    public function testFormatExecutionResult()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatExecutionResult');
        $method->setAccessible(true);

        $result = ['output' => 'Migration applied'];
        $output = $method->invoke($tool, 'up', $result);

        $this->assertStringContainsString('Execution Result', $output);
        $this->assertStringContainsString('up', $output);
        $this->assertStringContainsString('Completed', $output);
    }

    /**
     * Test input schema property descriptions
     */
    public function testInputSchemaPropertyDescriptions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('description', $schema['properties']['operation']);
        $this->assertArrayHasKey('description', $schema['properties']['confirmation']);
        $this->assertArrayHasKey('description', $schema['properties']['destructiveConfirmation']);

        $this->assertIsString($schema['properties']['operation']['description']);
        $this->assertIsString($schema['properties']['confirmation']['description']);
    }

    /**
     * Test input schema has direction property
     */
    public function testInputSchemaHasDirectionProperty()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();
        $properties = $schema['properties'];

        $this->assertArrayHasKey('direction', $properties);
        $this->assertEquals('string', $properties['direction']['type']);
    }

    /**
     * Test direction enum values
     */
    public function testDirectionEnumValues()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();
        $directionProperty = $schema['properties']['direction'];

        $this->assertArrayHasKey('enum', $directionProperty);
        $this->assertContains('up', $directionProperty['enum']);
        $this->assertContains('down', $directionProperty['enum']);
        $this->assertCount(2, $directionProperty['enum']);
    }

    /**
     * Test direction default value
     */
    public function testDirectionDefaultValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $schema = $tool->getInputSchema();
        $directionProperty = $schema['properties']['direction'];

        $this->assertEquals('up', $directionProperty['default']);
    }

    /**
     * Test formatSqlPreview structure
     */
    public function testFormatSqlPreviewStructure()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatSqlPreview');
        $method->setAccessible(true);

        $output = $method->invoke($tool, 'm123456_create_users', 'up', 'CREATE TABLE users');

        $this->assertStringContainsString('SQL Preview', $output);
        $this->assertStringContainsString('m123456_create_users', $output);
        $this->assertStringContainsString('up', $output);
        $this->assertStringContainsString('CREATE TABLE users', $output);
    }

    /**
     * Test formatSqlPreview includes migration name
     */
    public function testFormatSqlPreviewIncludesMigrationName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatSqlPreview');
        $method->setAccessible(true);

        $output = $method->invoke($tool, 'test_migration', 'up', 'SELECT 1');

        $this->assertStringContainsString('test_migration', $output);
    }

    /**
     * Test formatSqlPreview includes direction
     */
    public function testFormatSqlPreviewIncludesDirection()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatSqlPreview');
        $method->setAccessible(true);

        $output = $method->invoke($tool, 'test_migration', 'down', 'DROP TABLE users');

        $this->assertStringContainsString('down', $output);
    }

    /**
     * Test formatSqlPreview includes SQL
     */
    public function testFormatSqlPreviewIncludesSql()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatSqlPreview');
        $method->setAccessible(true);

        $sql = "CREATE TABLE users (id INT, name VARCHAR(255))";
        $output = $method->invoke($tool, 'test', 'up', $sql);

        $this->assertStringContainsString($sql, $output);
    }

    /**
     * Test formatSqlPreview includes safety notice
     */
    public function testFormatSqlPreviewIncludesNotice()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $tool = new ExecuteMigration($bootstrap);

        $reflection = new ReflectionClass($tool);
        $method = $reflection->getMethod('formatSqlPreview');
        $method->setAccessible(true);

        $output = $method->invoke($tool, 'test', 'up', 'SELECT 1');

        $this->assertStringContainsString('preview', strtolower($output));
        $this->assertStringContainsString('no database changes', strtolower($output));
    }
}
