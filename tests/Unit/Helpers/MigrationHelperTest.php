<?php

namespace Tests\Unit\Helpers;

use Codeception\Test\Unit;
use ReflectionClass;
use Took\Yii2GiiMCP\Helpers\MigrationHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Test MigrationHelper
 *
 * Note: These tests use mocks and Reflection to test without Yii2 dependencies.
 */
class MigrationHelperTest extends Unit
{
    /**
     * Test constructor
     */
    public function testConstructor()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $this->assertInstanceOf(MigrationHelper::class, $helper);
    }

    /**
     * Test extractTableNameFromMigrationName with create pattern
     */
    public function testExtractTableNameFromMigrationNameWithCreatePattern()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('extractTableNameFromMigrationName');
        $method->setAccessible(true);

        $tableName = $method->invoke($helper, 'create_users_table');
        $this->assertEquals('users', $tableName);
    }

    /**
     * Test extractTableNameFromMigrationName with different pattern
     */
    public function testExtractTableNameFromMigrationNameWithDifferentPattern()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('extractTableNameFromMigrationName');
        $method->setAccessible(true);

        $tableName = $method->invoke($helper, 'add_status_to_posts');
        $this->assertEquals('add_status_to_posts', $tableName);
    }

    /**
     * Test parseFieldDefinition with simple field
     */
    public function testParseFieldDefinitionSimple()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinition');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'name:string');
        $this->assertStringContainsString("'name'", $result);
        $this->assertStringContainsString('->string()', $result);
    }

    /**
     * Test parseFieldDefinition with modifiers
     */
    public function testParseFieldDefinitionWithModifiers()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinition');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'email:string:notNull:unique');
        $this->assertStringContainsString("'email'", $result);
        $this->assertStringContainsString('->string()', $result);
        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString('->unique()', $result);
    }

    /**
     * Test parseFieldDefinition with modifier arguments
     */
    public function testParseFieldDefinitionWithModifierArguments()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinition');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'status:integer:defaultValue(1)');
        $this->assertStringContainsString("'status'", $result);
        $this->assertStringContainsString('->integer()', $result);
        $this->assertStringContainsString('->defaultValue(1)', $result);
    }

    /**
     * Test parseFieldDefinition with size
     */
    public function testParseFieldDefinitionWithSize()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinition');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'name:string(255):notNull');
        $this->assertStringContainsString("'name'", $result);
        $this->assertStringContainsString('->string(255)', $result);
        $this->assertStringContainsString('->notNull()', $result);
    }

    /**
     * Test buildColumnsFromFields
     */
    public function testBuildColumnsFromFields()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('buildColumnsFromFields');
        $method->setAccessible(true);

        $fields = [
            'name:string:notNull',
            'email:string:notNull:unique',
            'status:integer:defaultValue(1)',
        ];

        $columns = $method->invoke($helper, $fields);

        $this->assertIsArray($columns);
        $this->assertCount(3, $columns);
        $this->assertStringContainsString('name', $columns[0]);
        $this->assertStringContainsString('email', $columns[1]);
        $this->assertStringContainsString('status', $columns[2]);
    }

    /**
     * Test generateMigrationContent with empty fields
     */
    public function testGenerateMigrationContentEmpty()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateMigrationContent');
        $method->setAccessible(true);

        $content = $method->invoke($helper, 'm123456_create_test', 'create_test', []);

        $this->assertStringContainsString('class m123456_create_test extends Migration', $content);
        $this->assertStringContainsString('public function safeUp()', $content);
        $this->assertStringContainsString('public function safeDown()', $content);
        $this->assertStringContainsString('// Add migration logic here', $content);
    }

    /**
     * Test generateMigrationContent with fields
     */
    public function testGenerateMigrationContentWithFields()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateMigrationContent');
        $method->setAccessible(true);

        $fields = ['name:string:notNull', 'email:string:notNull:unique'];
        $content = $method->invoke($helper, 'm123456_create_users_table', 'create_users_table', $fields);

        $this->assertStringContainsString('class m123456_create_users_table extends Migration', $content);
        $this->assertStringContainsString('$this->createTable(', $content);
        $this->assertStringContainsString('users', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('email', $content);
        $this->assertStringContainsString('$this->dropTable(', $content);
    }

    /**
     * Test generateMigrationContent structure
     */
    public function testGenerateMigrationContentStructure()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateMigrationContent');
        $method->setAccessible(true);

        $content = $method->invoke($helper, 'm123456_test', 'test', []);

        // Check PHP opening tag
        $this->assertStringStartsWith('<?php', $content);

        // Check use statement
        $this->assertStringContainsString('use yii\db\Migration;', $content);

        // Check class definition
        $this->assertStringContainsString('class m123456_test extends Migration', $content);

        // Check methods
        $this->assertStringContainsString('public function safeUp()', $content);
        $this->assertStringContainsString('public function safeDown()', $content);
    }

    /**
     * Test parseFieldDefinition with integer type
     */
    public function testParseFieldDefinitionInteger()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinition');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'age:integer');
        $this->assertStringContainsString("'age'", $result);
        $this->assertStringContainsString('->integer()', $result);
    }

    /**
     * Test parseFieldDefinition with text type
     */
    public function testParseFieldDefinitionText()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinition');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'description:text');
        $this->assertStringContainsString("'description'", $result);
        $this->assertStringContainsString('->text()', $result);
    }

    /**
     * Test parseFieldDefinition with timestamp type
     */
    public function testParseFieldDefinitionTimestamp()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinition');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'created_at:timestamp:notNull');
        $this->assertStringContainsString("'created_at'", $result);
        $this->assertStringContainsString('->timestamp()', $result);
        $this->assertStringContainsString('->notNull()', $result);
    }

    /**
     * Test parseFieldDefinition with boolean type
     */
    public function testParseFieldDefinitionBoolean()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinition');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'is_active:boolean:defaultValue(true)');
        $this->assertStringContainsString("'is_active'", $result);
        $this->assertStringContainsString('->boolean()', $result);
        $this->assertStringContainsString('->defaultValue(true)', $result);
    }

    /**
     * Test parseFieldDefinition with complex modifiers
     */
    public function testParseFieldDefinitionComplexModifiers()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinition');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'price:decimal(10,2):notNull:defaultValue(0.00)');
        $this->assertStringContainsString("'price'", $result);
        $this->assertStringContainsString('->decimal(10,2)', $result);
        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString('->defaultValue(0.00)', $result);
    }

    /**
     * Test buildColumnsFromFields with empty array
     */
    public function testBuildColumnsFromFieldsEmpty()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('buildColumnsFromFields');
        $method->setAccessible(true);

        $columns = $method->invoke($helper, []);

        $this->assertIsArray($columns);
        $this->assertEmpty($columns);
    }

    /**
     * Test extractTableNameFromMigrationName with underscore pattern
     */
    public function testExtractTableNameWithMultipleUnderscores()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('extractTableNameFromMigrationName');
        $method->setAccessible(true);

        $tableName = $method->invoke($helper, 'create_user_profiles_table');
        $this->assertEquals('user_profiles', $tableName);
    }

    /**
     * Test parseFieldDefinitionAdvanced with enum type - simple
     */
    public function testParseFieldDefinitionAdvancedEnumSimple()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinitionAdvanced');
        $method->setAccessible(true);

        $result = $method->invoke($helper, "status:enum('draft','published','archived')");

        $this->assertStringContainsString("'status'", $result);
        $this->assertStringContainsString('$this->string()', $result);
        $this->assertStringContainsString("->check(\"status IN ('draft','published','archived')\")", $result);
    }

    /**
     * Test parseFieldDefinitionAdvanced with enum and notNull
     */
    public function testParseFieldDefinitionAdvancedEnumWithNotNull()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinitionAdvanced');
        $method->setAccessible(true);

        $result = $method->invoke($helper, "status:enum('draft','published'):notNull");

        $this->assertStringContainsString("'status'", $result);
        $this->assertStringContainsString('$this->string()', $result);
        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString("->check(\"status IN ('draft','published')\")", $result);
    }

    /**
     * Test parseFieldDefinitionAdvanced with enum and default value
     */
    public function testParseFieldDefinitionAdvancedEnumWithDefault()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinitionAdvanced');
        $method->setAccessible(true);

        $result = $method->invoke($helper, "status:enum('draft','published'):defaultValue('draft')");

        $this->assertStringContainsString("'status'", $result);
        $this->assertStringContainsString('$this->string()', $result);
        $this->assertStringContainsString("->defaultValue('draft')", $result);
        $this->assertStringContainsString("->check(\"status IN ('draft','published')\")", $result);
    }

    /**
     * Test parseFieldDefinitionAdvanced with enum complex (multiple modifiers)
     */
    public function testParseFieldDefinitionAdvancedEnumComplex()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinitionAdvanced');
        $method->setAccessible(true);

        $result = $method->invoke($helper, "status:enum('draft','published','archived'):notNull:defaultValue('draft')");

        $this->assertStringContainsString("'status'", $result);
        $this->assertStringContainsString('$this->string()', $result);
        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString("->defaultValue('draft')", $result);
        $this->assertStringContainsString("->check(\"status IN ('draft','published','archived')\")", $result);
    }

    /**
     * Test parseFieldDefinitionAdvanced with enum with many values
     */
    public function testParseFieldDefinitionAdvancedEnumWithManyValues()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinitionAdvanced');
        $method->setAccessible(true);

        $result = $method->invoke($helper, "priority:enum('urgent','high','medium','low','trivial')");

        $this->assertStringContainsString("'priority'", $result);
        $this->assertStringContainsString('$this->string()', $result);
        $this->assertStringContainsString("->check(\"priority IN ('urgent','high','medium','low','trivial')\")", $result);
    }

    /**
     * Test parseFieldDefinitionAdvanced with enum single value
     */
    public function testParseFieldDefinitionAdvancedEnumSingleValue()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinitionAdvanced');
        $method->setAccessible(true);

        $result = $method->invoke($helper, "state:enum('active')");

        $this->assertStringContainsString("'state'", $result);
        $this->assertStringContainsString('$this->string()', $result);
        $this->assertStringContainsString("->check(\"state IN ('active')\")", $result);
    }

    /**
     * Test parseFieldDefinitionAdvanced without name parameter (enum)
     */
    public function testParseFieldDefinitionAdvancedEnumWithoutName()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('parseFieldDefinitionAdvanced');
        $method->setAccessible(true);

        $result = $method->invoke($helper, "status:enum('draft','published'):notNull", false);

        $this->assertStringNotContainsString("'status'", $result);
        $this->assertStringContainsString('$this->string()', $result);
        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString("->check(\"status IN ('draft','published')\")", $result);
    }

    /**
     * Test buildColumnsFromFields with enum fields
     */
    public function testBuildColumnsFromFieldsWithEnum()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('buildColumnsFromFields');
        $method->setAccessible(true);

        $fields = [
            'name:string:notNull',
            "status:enum('draft','published'):notNull:defaultValue('draft')",
            'email:string:notNull:unique',
        ];

        $columns = $method->invoke($helper, $fields);

        $this->assertIsArray($columns);
        $this->assertCount(3, $columns);
        $this->assertStringContainsString('name', $columns[0]);
        $this->assertStringContainsString('status', $columns[1]);
        $this->assertStringContainsString('check', $columns[1]);
        $this->assertStringContainsString("IN ('draft','published')", $columns[1]);
        $this->assertStringContainsString('email', $columns[2]);
    }

    /**
     * Test generateIndexes with single column
     */
    public function testGenerateIndexesSingleColumn()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateIndexes');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'users', ['email'], true, '        ');

        $this->assertStringContainsString('createIndex', $result);
        $this->assertStringContainsString('idx-users-email', $result);
        $this->assertStringContainsString("'{{%users}}'", $result);
        $this->assertStringContainsString("'email'", $result);
    }

    /**
     * Test generateIndexes with composite index
     */
    public function testGenerateIndexesCompositeIndex()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateIndexes');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'posts', ['user_id,created_at'], true, '        ');

        $this->assertStringContainsString('createIndex', $result);
        $this->assertStringContainsString('idx-posts-user_id-created_at', $result);
        $this->assertStringContainsString("'{{%posts}}'", $result);
        $this->assertStringContainsString("'user_id'", $result);
        $this->assertStringContainsString("'created_at'", $result);
    }

    /**
     * Test generateIndexes with multiple indexes
     */
    public function testGenerateIndexesMultiple()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateIndexes');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'users', ['email', 'username', 'status'], true, '        ');

        $this->assertStringContainsString('idx-users-email', $result);
        $this->assertStringContainsString('idx-users-username', $result);
        $this->assertStringContainsString('idx-users-status', $result);
        // Count occurrences of createIndex
        $this->assertEquals(3, substr_count($result, 'createIndex'));
    }

    /**
     * Test generateDropIndexes
     */
    public function testGenerateDropIndexes()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateDropIndexes');
        $method->setAccessible(true);

        $result = $method->invoke($helper, 'users', ['email', 'username'], '        ');

        $this->assertStringContainsString('dropIndex', $result);
        $this->assertStringContainsString('idx-users-email', $result);
        $this->assertStringContainsString('idx-users-username', $result);
        $this->assertEquals(2, substr_count($result, 'dropIndex'));
    }

    /**
     * Test generateForeignKeysExplicit with CASCADE
     */
    public function testGenerateForeignKeysExplicitCascade()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateForeignKeysExplicit');
        $method->setAccessible(true);

        $foreignKeys = [
            ['field' => 'user_id', 'table' => 'users', 'onDelete' => 'CASCADE', 'onUpdate' => 'RESTRICT'],
        ];

        $result = $method->invoke($helper, 'posts', $foreignKeys, true, '        ');

        $this->assertStringContainsString('addForeignKey', $result);
        $this->assertStringContainsString('fk-posts-user_id', $result);
        $this->assertStringContainsString("'{{%posts}}'", $result);
        $this->assertStringContainsString("'user_id'", $result);
        $this->assertStringContainsString("'{{%users}}'", $result);
        $this->assertStringContainsString("'CASCADE'", $result);
        $this->assertStringContainsString("'RESTRICT'", $result);
    }

    /**
     * Test generateForeignKeysExplicit with SET NULL
     */
    public function testGenerateForeignKeysExplicitSetNull()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateForeignKeysExplicit');
        $method->setAccessible(true);

        $foreignKeys = [
            ['field' => 'category_id', 'table' => 'categories', 'onDelete' => 'SET NULL'],
        ];

        $result = $method->invoke($helper, 'posts', $foreignKeys, true, '        ');

        $this->assertStringContainsString('addForeignKey', $result);
        $this->assertStringContainsString('fk-posts-category_id', $result);
        $this->assertStringContainsString("'SET NULL'", $result);
        $this->assertStringContainsString("'RESTRICT'", $result); // default onUpdate
    }

    /**
     * Test generateForeignKeysExplicit with invalid action (should default to RESTRICT)
     */
    public function testGenerateForeignKeysExplicitInvalidAction()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateForeignKeysExplicit');
        $method->setAccessible(true);

        $foreignKeys = [
            ['field' => 'user_id', 'table' => 'users', 'onDelete' => 'INVALID_ACTION'],
        ];

        $result = $method->invoke($helper, 'posts', $foreignKeys, true, '        ');

        // Invalid action should be converted to RESTRICT
        $this->assertStringContainsString("'RESTRICT'", $result);
        $this->assertStringNotContainsString("'INVALID_ACTION'", $result);
    }

    /**
     * Test generateForeignKeysExplicit with custom column
     */
    public function testGenerateForeignKeysExplicitCustomColumn()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateForeignKeysExplicit');
        $method->setAccessible(true);

        $foreignKeys = [
            ['field' => 'author_id', 'table' => 'users', 'column' => 'uuid', 'onDelete' => 'CASCADE'],
        ];

        $result = $method->invoke($helper, 'posts', $foreignKeys, true, '        ');

        $this->assertStringContainsString("'uuid'", $result);
        $this->assertStringNotContainsString("'id',", $result); // Should not contain default 'id'
    }

    /**
     * Test generateDropForeignKeysExplicit
     */
    public function testGenerateDropForeignKeysExplicit()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateDropForeignKeysExplicit');
        $method->setAccessible(true);

        $foreignKeys = [
            ['field' => 'user_id', 'table' => 'users'],
            ['field' => 'category_id', 'table' => 'categories'],
        ];

        $result = $method->invoke($helper, 'posts', $foreignKeys, '        ');

        $this->assertStringContainsString('dropForeignKey', $result);
        $this->assertStringContainsString('fk-posts-user_id', $result);
        $this->assertStringContainsString('fk-posts-category_id', $result);
        $this->assertEquals(2, substr_count($result, 'dropForeignKey'));
    }

    /**
     * Test generateForeignKeysExplicit with all valid actions
     */
    public function testGenerateForeignKeysExplicitAllValidActions()
    {
        $bootstrap = $this->createMock(Yii2Bootstrap::class);
        $helper = new MigrationHelper($bootstrap);

        $reflection = new ReflectionClass($helper);
        $method = $reflection->getMethod('generateForeignKeysExplicit');
        $method->setAccessible(true);

        $validActions = ['CASCADE', 'RESTRICT', 'SET NULL', 'SET DEFAULT', 'NO ACTION'];

        foreach ($validActions as $action) {
            $foreignKeys = [
                ['field' => 'test_id', 'table' => 'test', 'onDelete' => $action, 'onUpdate' => $action],
            ];

            $result = $method->invoke($helper, 'posts', $foreignKeys, true, '        ');

            $this->assertStringContainsString("'{$action}'", $result);
        }
    }
}
