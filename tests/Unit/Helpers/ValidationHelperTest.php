<?php

namespace Tests\Unit\Helpers;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\ValidationHelper;

/**
 * Test ValidationHelper class
 */
class ValidationHelperTest extends Unit
{
    /**
     * Test valid class names
     */
    public function testValidateClassNameWithValidNames(): void
    {
        $this->assertTrue(ValidationHelper::validateClassName('User'));
        $this->assertTrue(ValidationHelper::validateClassName('UserModel'));
        $this->assertTrue(ValidationHelper::validateClassName('User_Model'));
        $this->assertTrue(ValidationHelper::validateClassName('_User'));
        $this->assertTrue(ValidationHelper::validateClassName('app\\models\\User'));
        $this->assertTrue(ValidationHelper::validateClassName('App\\Models\\User'));
    }

    /**
     * Test invalid class names
     */
    public function testValidateClassNameWithInvalidNames(): void
    {
        $this->assertFalse(ValidationHelper::validateClassName('123User')); // starts with number
        $this->assertFalse(ValidationHelper::validateClassName('User-Model')); // contains hyphen
        $this->assertFalse(ValidationHelper::validateClassName('User Model')); // contains space
        $this->assertFalse(ValidationHelper::validateClassName('User.Model')); // contains dot
        $this->assertFalse(ValidationHelper::validateClassName('User@Model')); // contains @
        $this->assertFalse(ValidationHelper::validateClassName('')); // empty string
    }

    /**
     * Test valid namespaces
     */
    public function testValidateNamespaceWithValidNames(): void
    {
        $this->assertTrue(ValidationHelper::validateNamespace(''));
        $this->assertTrue(ValidationHelper::validateNamespace('app'));
        $this->assertTrue(ValidationHelper::validateNamespace('app\\models'));
        $this->assertTrue(ValidationHelper::validateNamespace('App\\Models\\User'));
        $this->assertTrue(ValidationHelper::validateNamespace('_app\\models'));
    }

    /**
     * Test invalid namespaces
     */
    public function testValidateNamespaceWithInvalidNames(): void
    {
        $this->assertFalse(ValidationHelper::validateNamespace('app\\')); // trailing backslash
        $this->assertFalse(ValidationHelper::validateNamespace('\\app')); // leading backslash
        $this->assertFalse(ValidationHelper::validateNamespace('app\\\\models')); // double backslash
        $this->assertFalse(ValidationHelper::validateNamespace('app\\123models')); // segment starts with number
        $this->assertFalse(ValidationHelper::validateNamespace('app.models')); // contains dot
        $this->assertFalse(ValidationHelper::validateNamespace('app-models')); // contains hyphen
    }

    /**
     * Test valid paths
     */
    public function testValidatePathWithValidPaths(): void
    {
        $basePath = sys_get_temp_dir();
        $testDir = $basePath . '/test_validation_' . uniqid();

        // Create test directory
        mkdir($testDir, 0755, true);

        try {
            // Test with existing path
            $this->assertTrue(ValidationHelper::validatePath($testDir, $basePath));

            // Test with non-existing subdirectory (parent exists)
            $subDir = $testDir . '/subdir';
            $this->assertTrue(ValidationHelper::validatePath($subDir, $basePath));
        } finally {
            // Cleanup
            rmdir($testDir);
        }
    }

    /**
     * Test invalid paths (path traversal)
     */
    public function testValidatePathWithTraversalAttempt(): void
    {
        $basePath = sys_get_temp_dir();

        // Attempt to access parent directory
        $this->assertFalse(ValidationHelper::validatePath('/etc/passwd', $basePath));
        $this->assertFalse(ValidationHelper::validatePath('/root', $basePath));
    }

    /**
     * Test valid controller IDs
     */
    public function testValidateControllerIdWithValidIds(): void
    {
        $this->assertTrue(ValidationHelper::validateControllerId('user'));
        $this->assertTrue(ValidationHelper::validateControllerId('user-profile'));
        $this->assertTrue(ValidationHelper::validateControllerId('admin-user'));
        $this->assertTrue(ValidationHelper::validateControllerId('post123'));
    }

    /**
     * Test invalid controller IDs
     */
    public function testValidateControllerIdWithInvalidIds(): void
    {
        $this->assertFalse(ValidationHelper::validateControllerId('User')); // uppercase
        $this->assertFalse(ValidationHelper::validateControllerId('user_profile')); // underscore
        $this->assertFalse(ValidationHelper::validateControllerId('123user')); // starts with number
        $this->assertFalse(ValidationHelper::validateControllerId('user profile')); // space
        $this->assertFalse(ValidationHelper::validateControllerId('')); // empty
    }

    /**
     * Test valid attribute names
     */
    public function testValidateAttributeNameWithValidNames(): void
    {
        $this->assertTrue(ValidationHelper::validateAttributeName('username'));
        $this->assertTrue(ValidationHelper::validateAttributeName('user_name'));
        $this->assertTrue(ValidationHelper::validateAttributeName('_username'));
        $this->assertTrue(ValidationHelper::validateAttributeName('userName'));
        $this->assertTrue(ValidationHelper::validateAttributeName('user123'));
    }

    /**
     * Test invalid attribute names
     */
    public function testValidateAttributeNameWithInvalidNames(): void
    {
        $this->assertFalse(ValidationHelper::validateAttributeName('123user')); // starts with number
        $this->assertFalse(ValidationHelper::validateAttributeName('user-name')); // hyphen
        $this->assertFalse(ValidationHelper::validateAttributeName('user name')); // space
        $this->assertFalse(ValidationHelper::validateAttributeName('user.name')); // dot
        $this->assertFalse(ValidationHelper::validateAttributeName('')); // empty
    }

    /**
     * Test table name sanitization
     */
    public function testSanitizeTableName(): void
    {
        $this->assertEquals('users', ValidationHelper::sanitizeTableName('users'));
        $this->assertEquals('user_profiles', ValidationHelper::sanitizeTableName('user_profiles'));
        $this->assertEquals('user123', ValidationHelper::sanitizeTableName('user123'));
        $this->assertEquals('schema.table_name', ValidationHelper::sanitizeTableName('schema.table_name')); // Dots are allowed

        // Remove dangerous characters - they're removed but letters/numbers remain
        $this->assertEquals('usersDROPTABLEusers', ValidationHelper::sanitizeTableName('users; DROP TABLE users;'));
        $this->assertEquals('users', ValidationHelper::sanitizeTableName('users--'));
        $this->assertEquals('users', ValidationHelper::sanitizeTableName('users/*'));
    }

    /**
     * Test class name sanitization
     */
    public function testSanitizeClassName(): void
    {
        $this->assertEquals('User', ValidationHelper::sanitizeClassName('User'));
        $this->assertEquals('UserModel', ValidationHelper::sanitizeClassName('UserModel'));
        $this->assertEquals('app\\models\\User', ValidationHelper::sanitizeClassName('app\\models\\User')); // Backslashes are allowed

        // Remove dangerous characters - they're removed but letters/numbers remain
        $this->assertEquals('UserDROPTABLE', ValidationHelper::sanitizeClassName('User; DROP TABLE'));
        $this->assertEquals('Usercomment', ValidationHelper::sanitizeClassName('User--comment'));
        $this->assertEquals('User123', ValidationHelper::sanitizeClassName('User123!@#$'));
    }

    /**
     * Test path traversal detection
     */
    public function testHasPathTraversal(): void
    {
        // Test various traversal patterns
        $this->assertTrue(ValidationHelper::hasPathTraversal('../etc/passwd'));
        $this->assertTrue(ValidationHelper::hasPathTraversal('..\\windows\\system32'));
        $this->assertTrue(ValidationHelper::hasPathTraversal('/tmp/../etc/passwd'));
        $this->assertTrue(ValidationHelper::hasPathTraversal('C:\\temp\\..\\windows'));

        // Test safe paths
        $this->assertFalse(ValidationHelper::hasPathTraversal('/var/www/html'));
        $this->assertFalse(ValidationHelper::hasPathTraversal('models/User.php'));
        $this->assertFalse(ValidationHelper::hasPathTraversal('app/controllers'));
    }

    /**
     * Test multiple table name validation
     */
    public function testValidateTableNames(): void
    {
        $names = ['users', 'posts', 'user_profiles', 'invalid-table'];
        $results = ValidationHelper::validateTableNames($names);

        $this->assertIsArray($results);
        $this->assertCount(4, $results);
        $this->assertTrue($results['users']);
        $this->assertTrue($results['posts']);
        $this->assertTrue($results['user_profiles']);
        $this->assertFalse($results['invalid-table']);
    }

    /**
     * Test valid table names
     */
    public function testValidateTableNameWithValidNames(): void
    {
        $this->assertTrue(ValidationHelper::validateTableName('users'));
        $this->assertTrue(ValidationHelper::validateTableName('user_profiles'));
        $this->assertTrue(ValidationHelper::validateTableName('users123'));
        $this->assertTrue(ValidationHelper::validateTableName('schema.table_name'));
        $this->assertTrue(ValidationHelper::validateTableName('_users'));
    }

    /**
     * Test invalid table names
     */
    public function testValidateTableNameWithInvalidNames(): void
    {
        $this->assertFalse(ValidationHelper::validateTableName('users; DROP TABLE'));
        $this->assertFalse(ValidationHelper::validateTableName('users--'));
        $this->assertFalse(ValidationHelper::validateTableName('users/*'));
        $this->assertFalse(ValidationHelper::validateTableName('users-profiles')); // hyphen not allowed
        $this->assertFalse(ValidationHelper::validateTableName('')); // empty
    }

    /**
     * Test error message for table names
     */
    public function testGetTableNameError(): void
    {
        $error = ValidationHelper::getTableNameError('invalid-table');

        $this->assertIsString($error);
        $this->assertStringContainsString('invalid-table', $error);
        $this->assertStringContainsString('alphanumeric', strtolower($error));
    }

    /**
     * Test error message for class names
     */
    public function testGetClassNameError(): void
    {
        $error = ValidationHelper::getClassNameError('123Invalid');

        $this->assertIsString($error);
        $this->assertStringContainsString('123Invalid', $error);
        $this->assertStringContainsString('letter', strtolower($error));
    }

    /**
     * Test error message for namespaces
     */
    public function testGetNamespaceError(): void
    {
        $error = ValidationHelper::getNamespaceError('invalid\\\\namespace');

        $this->assertIsString($error);
        $this->assertStringContainsString('invalid\\\\namespace', $error);
        $this->assertStringContainsString('namespace', strtolower($error));
    }

    /**
     * Test error message for paths
     */
    public function testGetPathError(): void
    {
        $error = ValidationHelper::getPathError('/etc/passwd', '/var/www');

        $this->assertIsString($error);
        $this->assertStringContainsString('/etc/passwd', $error);
        $this->assertStringContainsString('/var/www', $error);
        $this->assertStringContainsString('boundaries', strtolower($error));
    }
}
