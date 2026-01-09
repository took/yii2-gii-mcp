<?php

namespace Tests\Unit\Config;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Config\ServerConfig;

/**
 * Test ServerConfig class
 */
class ServerConfigTest extends Unit
{
    /**
     * Test configuration from environment variables
     */
    public function testConstructorWithEnvironmentVariables(): void
    {
        $env = [
            'YII2_CONFIG_PATH' => '/path/to/config.php',
            'YII2_APP_PATH' => '/path/to/app',
            'GII_ENABLED' => 'true',
            'DB_CONNECTION' => 'testdb',
            'DEBUG' => 'true',
        ];

        $config = new ServerConfig($env);

        $this->assertEquals('/path/to/config.php', $config->getYii2ConfigPath());
        $this->assertEquals('/path/to/app', $config->getYii2AppPath());
        $this->assertTrue($config->isGiiEnabled());
        $this->assertEquals('testdb', $config->getDbConnection());
        $this->assertTrue($config->isDebugMode());
    }

    /**
     * Test default values when environment is empty
     */
    public function testConstructorWithDefaults(): void
    {
        $config = new ServerConfig([]);

        $this->assertNull($config->getYii2ConfigPath());
        $this->assertNull($config->getYii2AppPath());
        $this->assertTrue($config->isGiiEnabled()); // default true
        $this->assertEquals('db', $config->getDbConnection()); // default 'db'
        $this->assertFalse($config->isDebugMode()); // default false
    }

    /**
     * Test boolean parsing from strings
     */
    public function testBooleanParsing(): void
    {
        // Test truthy values for DEBUG (defaults to false)
        $env = ['DEBUG' => '1'];
        $this->assertTrue((new ServerConfig($env))->isDebugMode());

        $env = ['DEBUG' => 'true'];
        $this->assertTrue((new ServerConfig($env))->isDebugMode());

        $env = ['DEBUG' => 'TRUE'];
        $this->assertTrue((new ServerConfig($env))->isDebugMode());

        $env = ['DEBUG' => 'yes'];
        $this->assertTrue((new ServerConfig($env))->isDebugMode());

        $env = ['DEBUG' => 'on'];
        $this->assertTrue((new ServerConfig($env))->isDebugMode());

        // Test falsy values for DEBUG
        $env = ['DEBUG' => '0'];
        $this->assertFalse((new ServerConfig($env))->isDebugMode());

        $env = ['DEBUG' => 'false'];
        $this->assertFalse((new ServerConfig($env))->isDebugMode());

        $env = ['DEBUG' => 'no'];
        $this->assertFalse((new ServerConfig($env))->isDebugMode());

        // Test GII_ENABLED
        $env = ['GII_ENABLED' => 'false'];
        $this->assertFalse((new ServerConfig($env))->isGiiEnabled());
    }

    /**
     * Test config path inference from app path
     */
    public function testConfigPathInferenceFromAppPath(): void
    {
        $env = [
            'YII2_APP_PATH' => '/path/to/app',
        ];

        $config = new ServerConfig($env);

        $this->assertEquals('/path/to/app/config/web.php', $config->getYii2ConfigPath());
    }

    /**
     * Test app path inference from config path
     */
    public function testAppPathInferenceFromConfigPath(): void
    {
        $env = [
            'YII2_CONFIG_PATH' => '/path/to/app/config/web.php',
        ];

        $config = new ServerConfig($env);

        $this->assertEquals('/path/to/app', $config->getYii2AppPath());
    }

    /**
     * Test setters and fluent interface
     */
    public function testSettersAndFluentInterface(): void
    {
        $config = new ServerConfig([]);

        $result = $config
            ->setYii2ConfigPath('/new/config.php')
            ->setYii2AppPath('/new/app')
            ->setGiiEnabled(false)
            ->setDbConnection('customdb')
            ->setDebugMode(true);

        // Test fluent interface returns self
        $this->assertSame($config, $result);

        // Test values were set
        $this->assertEquals('/new/config.php', $config->getYii2ConfigPath());
        $this->assertEquals('/new/app', $config->getYii2AppPath());
        $this->assertFalse($config->isGiiEnabled());
        $this->assertEquals('customdb', $config->getDbConnection());
        $this->assertTrue($config->isDebugMode());
    }

    /**
     * Test toArray serialization
     */
    public function testToArray(): void
    {
        $config = new ServerConfig([
            'YII2_CONFIG_PATH' => '/path/to/config.php',
            'YII2_APP_PATH' => '/path/to/app',
            'DEBUG' => 'true',
        ]);

        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('yii2ConfigPath', $array);
        $this->assertArrayHasKey('yii2AppPath', $array);
        $this->assertArrayHasKey('giiEnabled', $array);
        $this->assertArrayHasKey('dbConnection', $array);
        $this->assertArrayHasKey('debugMode', $array);
        $this->assertArrayHasKey('isValid', $array);

        $this->assertEquals('/path/to/config.php', $array['yii2ConfigPath']);
        $this->assertEquals('/path/to/app', $array['yii2AppPath']);
        $this->assertTrue($array['debugMode']);
    }

    /**
     * Test validation with missing config path
     */
    public function testValidateWithMissingConfigPath(): void
    {
        $config = new ServerConfig([]);

        $errors = $config->validate();

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('config path not set', strtolower(implode(' ', $errors)));
    }

    /**
     * Test validation with non-existent config file
     */
    public function testValidateWithNonExistentConfigFile(): void
    {
        $config = new ServerConfig([
            'YII2_CONFIG_PATH' => '/nonexistent/path/config.php',
            'YII2_APP_PATH' => '/nonexistent/path',
        ]);

        $errors = $config->validate();

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $errors)));
    }

    /**
     * Test validation with existing valid paths
     */
    public function testValidateWithValidPaths(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_config_' . uniqid();
        $configPath = $tempDir . '/config.php';

        // Create test directory and file
        mkdir($tempDir, 0755, true);
        file_put_contents($configPath, '<?php return [];');

        try {
            $config = new ServerConfig([
                'YII2_CONFIG_PATH' => $configPath,
                'YII2_APP_PATH' => $tempDir,
            ]);

            $errors = $config->validate();

            $this->assertIsArray($errors);
            $this->assertEmpty($errors, 'Should have no errors with valid paths');
            $this->assertTrue($config->isValid());
        } finally {
            // Cleanup
            unlink($configPath);
            rmdir($tempDir);
        }
    }

    /**
     * Test isValid method
     */
    public function testIsValid(): void
    {
        // Invalid config (missing paths)
        $config = new ServerConfig([]);
        $this->assertFalse($config->isValid());

        // Create valid config
        $tempDir = sys_get_temp_dir() . '/test_config_valid_' . uniqid();
        $configPath = $tempDir . '/config.php';

        mkdir($tempDir, 0755, true);
        file_put_contents($configPath, '<?php return [];');

        try {
            $config = new ServerConfig([
                'YII2_CONFIG_PATH' => $configPath,
                'YII2_APP_PATH' => $tempDir,
            ]);

            $this->assertTrue($config->isValid());
        } finally {
            unlink($configPath);
            rmdir($tempDir);
        }
    }

    /**
     * Test getErrorMessage method
     */
    public function testGetErrorMessage(): void
    {
        $config = new ServerConfig([]);

        $errorMessage = $config->getErrorMessage();

        $this->assertIsString($errorMessage);
        $this->assertNotEmpty($errorMessage);
        $this->assertStringContainsString('Configuration Error', $errorMessage);
    }

    /**
     * Test getErrorMessage returns empty for valid config
     */
    public function testGetErrorMessageWithValidConfig(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_config_err_' . uniqid();
        $configPath = $tempDir . '/config.php';

        mkdir($tempDir, 0755, true);
        file_put_contents($configPath, '<?php return [];');

        try {
            $config = new ServerConfig([
                'YII2_CONFIG_PATH' => $configPath,
                'YII2_APP_PATH' => $tempDir,
            ]);

            $errorMessage = $config->getErrorMessage();

            $this->assertIsString($errorMessage);
            $this->assertEmpty($errorMessage);
        } finally {
            unlink($configPath);
            rmdir($tempDir);
        }
    }

    /**
     * Test validation with non-readable config file
     */
    public function testValidateWithNonReadableConfigFile(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('File permission tests not reliable on Windows');
        }

        $tempDir = sys_get_temp_dir() . '/test_config_perms_' . uniqid();
        $configPath = $tempDir . '/config.php';

        mkdir($tempDir, 0755, true);
        file_put_contents($configPath, '<?php return [];');
        chmod($configPath, 0000); // Make unreadable

        try {
            $config = new ServerConfig([
                'YII2_CONFIG_PATH' => $configPath,
                'YII2_APP_PATH' => $tempDir,
            ]);

            $errors = $config->validate();

            $this->assertIsArray($errors);
            $this->assertNotEmpty($errors);
            $this->assertStringContainsString('not readable', strtolower(implode(' ', $errors)));
        } finally {
            chmod($configPath, 0644); // Restore permissions for cleanup
            unlink($configPath);
            rmdir($tempDir);
        }
    }

    /**
     * Test validation with non-directory app path
     */
    public function testValidateWithNonDirectoryAppPath(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_file_' . uniqid();
        file_put_contents($tempFile, 'test');

        try {
            $config = new ServerConfig([
                'YII2_CONFIG_PATH' => $tempFile,
                'YII2_APP_PATH' => $tempFile, // File instead of directory
            ]);

            $errors = $config->validate();

            $this->assertIsArray($errors);
            $this->assertNotEmpty($errors);
            $this->assertStringContainsString('not a directory', strtolower(implode(' ', $errors)));
        } finally {
            unlink($tempFile);
        }
    }
}
