<?php

namespace Tests\Unit\Helpers;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\ProjectStructureHelper;

/**
 * Test ProjectStructureHelper
 *
 * Tests project structure detection, environment analysis, and application discovery.
 */
class ProjectStructureHelperTest extends Unit
{
    /**
     * Test template type detection for Basic template
     */
    public function testDetectTemplateTypeBasic()
    {
        $testPath = codecept_data_dir('test-project-basic');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/app', 0777, true);
        @mkdir($testPath . '/config', 0777, true);

        $result = ProjectStructureHelper::detectTemplateType($testPath);

        $this->assertEquals('basic', $result);

        // Cleanup
        @rmdir($testPath . '/config');
        @rmdir($testPath . '/app');
        @rmdir($testPath);
    }

    /**
     * Test template type detection for Advanced template
     */
    public function testDetectTemplateTypeAdvanced()
    {
        $testPath = codecept_data_dir('test-project-advanced');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/common', 0777, true);
        @mkdir($testPath . '/console', 0777, true);

        $result = ProjectStructureHelper::detectTemplateType($testPath);

        $this->assertEquals('advanced', $result);

        // Cleanup
        @rmdir($testPath . '/console');
        @rmdir($testPath . '/common');
        @rmdir($testPath);
    }

    /**
     * Test template type detection for Advanced + API template
     */
    public function testDetectTemplateTypeAdvancedWithApi()
    {
        $testPath = codecept_data_dir('test-project-advanced-api');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/common', 0777, true);
        @mkdir($testPath . '/console', 0777, true);
        @mkdir($testPath . '/api', 0777, true);

        $result = ProjectStructureHelper::detectTemplateType($testPath);

        $this->assertEquals('advanced-api', $result);

        // Cleanup
        @rmdir($testPath . '/api');
        @rmdir($testPath . '/console');
        @rmdir($testPath . '/common');
        @rmdir($testPath);
    }

    /**
     * Test finding application directories in Basic template
     */
    public function testFindApplicationDirsBasic()
    {
        $testPath = codecept_data_dir('test-project-basic-apps');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/app', 0777, true);
        @mkdir($testPath . '/config', 0777, true);

        $result = ProjectStructureHelper::findApplicationDirs($testPath);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('app', $result);
        $this->assertEquals($testPath . '/app', $result['app']);

        // Cleanup
        @rmdir($testPath . '/config');
        @rmdir($testPath . '/app');
        @rmdir($testPath);
    }

    /**
     * Test finding application directories in Advanced template
     */
    public function testFindApplicationDirsAdvanced()
    {
        $testPath = codecept_data_dir('test-project-advanced-apps');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/common', 0777, true);
        @mkdir($testPath . '/console', 0777, true);
        @mkdir($testPath . '/frontend', 0777, true);
        @mkdir($testPath . '/backend', 0777, true);

        $result = ProjectStructureHelper::findApplicationDirs($testPath);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('frontend', $result);
        $this->assertArrayHasKey('backend', $result);
        $this->assertArrayHasKey('console', $result);
        $this->assertEquals($testPath . '/frontend', $result['frontend']);
        $this->assertEquals($testPath . '/backend', $result['backend']);

        // Cleanup
        @rmdir($testPath . '/backend');
        @rmdir($testPath . '/frontend');
        @rmdir($testPath . '/console');
        @rmdir($testPath . '/common');
        @rmdir($testPath);
    }

    /**
     * Test Yii2 application validation - positive case
     */
    public function testIsYii2ApplicationWithConfig()
    {
        $testPath = codecept_data_dir('test-yii2-app-config');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/config', 0777, true);

        $result = ProjectStructureHelper::isYii2Application($testPath);

        $this->assertTrue($result);

        // Cleanup
        @rmdir($testPath . '/config');
        @rmdir($testPath);
    }

    /**
     * Test Yii2 application validation - negative case
     */
    public function testIsYii2ApplicationInvalid()
    {
        $testPath = codecept_data_dir('test-not-yii2-app');
        @mkdir($testPath, 0777, true);

        $result = ProjectStructureHelper::isYii2Application($testPath);

        $this->assertFalse($result);

        // Cleanup
        @rmdir($testPath);
    }

    /**
     * Test application type detection for web app
     */
    public function testGetApplicationTypeWeb()
    {
        $testPath = codecept_data_dir('test-app-web');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/web', 0777, true);

        $result = ProjectStructureHelper::getApplicationType($testPath);

        $this->assertEquals('web', $result);

        // Cleanup
        @rmdir($testPath . '/web');
        @rmdir($testPath);
    }

    /**
     * Test application type detection for console app
     */
    public function testGetApplicationTypeConsole()
    {
        $testPath = codecept_data_dir('test-app-console');
        @mkdir($testPath, 0777, true);
        touch($testPath . '/yii');

        $result = ProjectStructureHelper::getApplicationType($testPath);

        $this->assertEquals('console', $result);

        // Cleanup
        @unlink($testPath . '/yii');
        @rmdir($testPath);
    }

    /**
     * Test application type detection for API app by directory name
     */
    public function testGetApplicationTypeApi()
    {
        $testPath = codecept_data_dir('test-apps/api');
        @mkdir($testPath, 0777, true);

        $result = ProjectStructureHelper::getApplicationType($testPath);

        $this->assertEquals('api', $result);

        // Cleanup
        @rmdir($testPath);
        @rmdir(dirname($testPath));
    }

    /**
     * Test parsing index.php file for environment constants
     */
    public function testParseIndexPhpFile()
    {
        $testPath = codecept_data_dir('test-index.php');
        $content = <<<'PHP'
<?php
defined('YII_ENV') or define('YII_ENV', 'dev');
defined('YII_DEBUG') or define('YII_DEBUG', true);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
PHP;

        file_put_contents($testPath, $content);

        $result = ProjectStructureHelper::parseIndexPhpFile($testPath);

        $this->assertArrayHasKey('YII_ENV', $result);
        $this->assertArrayHasKey('YII_DEBUG', $result);
        $this->assertEquals('dev', $result['YII_ENV']);
        $this->assertTrue($result['YII_DEBUG']);

        // Cleanup
        @unlink($testPath);
    }

    /**
     * Test parsing index.php file with prod environment
     */
    public function testParseIndexPhpFileProd()
    {
        $testPath = codecept_data_dir('test-index-prod.php');
        $content = <<<'PHP'
<?php
defined('YII_ENV') or define('YII_ENV', 'prod');
defined('YII_DEBUG') or define('YII_DEBUG', false);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
PHP;

        file_put_contents($testPath, $content);

        $result = ProjectStructureHelper::parseIndexPhpFile($testPath);

        $this->assertEquals('prod', $result['YII_ENV']);
        $this->assertFalse($result['YII_DEBUG']);

        // Cleanup
        @unlink($testPath);
    }

    /**
     * Test scanning environments folder
     */
    public function testScanEnvironmentsFolder()
    {
        $testPath = codecept_data_dir('test-project-env');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/environments', 0777, true);
        @mkdir($testPath . '/environments/dev', 0777, true);
        @mkdir($testPath . '/environments/prod', 0777, true);
        @mkdir($testPath . '/environments/test', 0777, true);

        $result = ProjectStructureHelper::scanEnvironmentsFolder($testPath);

        $this->assertIsArray($result);
        $this->assertContains('dev', $result);
        $this->assertContains('prod', $result);
        $this->assertContains('test', $result);

        // Cleanup
        @rmdir($testPath . '/environments/test');
        @rmdir($testPath . '/environments/prod');
        @rmdir($testPath . '/environments/dev');
        @rmdir($testPath . '/environments');
        @rmdir($testPath);
    }

    /**
     * Test detecting environments from config files
     */
    public function testDetectEnvironmentsFromConfigFiles()
    {
        $testPath = codecept_data_dir('test-project-config-env');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/config', 0777, true);
        touch($testPath . '/config/main-local.php');
        touch($testPath . '/config/main-prod.php');
        touch($testPath . '/config/web-dev.php');

        $result = ProjectStructureHelper::detectEnvironmentsFromConfigFiles($testPath);

        $this->assertIsArray($result);
        $this->assertContains('local', $result);
        $this->assertContains('prod', $result);
        $this->assertContains('dev', $result);

        // Cleanup
        @unlink($testPath . '/config/web-dev.php');
        @unlink($testPath . '/config/main-prod.php');
        @unlink($testPath . '/config/main-local.php');
        @rmdir($testPath . '/config');
        @rmdir($testPath);
    }

    /**
     * Test checking for .env files
     */
    public function testHasEnvFiles()
    {
        $testPath = codecept_data_dir('test-project-dotenv');
        @mkdir($testPath, 0777, true);
        touch($testPath . '/.env');
        touch($testPath . '/.env.local');

        $result = ProjectStructureHelper::hasEnvFiles($testPath);

        $this->assertTrue($result);

        // Cleanup
        @unlink($testPath . '/.env.local');
        @unlink($testPath . '/.env');
        @rmdir($testPath);
    }

    /**
     * Test checking for .env files - negative case
     */
    public function testHasEnvFilesNegative()
    {
        $testPath = codecept_data_dir('test-project-no-env');
        @mkdir($testPath, 0777, true);

        $result = ProjectStructureHelper::hasEnvFiles($testPath);

        $this->assertFalse($result);

        // Cleanup
        @rmdir($testPath);
    }

    /**
     * Test full environment detection
     */
    public function testDetectEnvironments()
    {
        $testPath = codecept_data_dir('test-project-full-env');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/environments', 0777, true);
        @mkdir($testPath . '/environments/dev', 0777, true);
        @mkdir($testPath . '/environments/prod', 0777, true);
        @mkdir($testPath . '/config', 0777, true);
        touch($testPath . '/config/main-local.php');

        $result = ProjectStructureHelper::detectEnvironments($testPath);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('currentDetails', $result);
        $this->assertArrayHasKey('sources', $result);
        $this->assertContains('environments-folder', $result['sources']);
        $this->assertContains('config-files', $result['sources']);

        // Cleanup
        @unlink($testPath . '/config/main-local.php');
        @rmdir($testPath . '/config');
        @rmdir($testPath . '/environments/prod');
        @rmdir($testPath . '/environments/dev');
        @rmdir($testPath . '/environments');
        @rmdir($testPath);
    }

    /**
     * Test finding modules in application
     */
    public function testFindModules()
    {
        $testPath = codecept_data_dir('test-app-modules');
        @mkdir($testPath, 0777, true);
        @mkdir($testPath . '/modules', 0777, true);
        @mkdir($testPath . '/modules/admin', 0777, true);
        @mkdir($testPath . '/modules/api', 0777, true);
        touch($testPath . '/modules/admin/Module.php');
        touch($testPath . '/modules/api/Module.php');

        $result = ProjectStructureHelper::findModules($testPath);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $moduleIds = array_column($result, 'id');
        $this->assertContains('admin', $moduleIds);
        $this->assertContains('api', $moduleIds);

        // Cleanup
        @unlink($testPath . '/modules/api/Module.php');
        @unlink($testPath . '/modules/admin/Module.php');
        @rmdir($testPath . '/modules/api');
        @rmdir($testPath . '/modules/admin');
        @rmdir($testPath . '/modules');
        @rmdir($testPath);
    }

    /**
     * Test default fallback for unknown template structure
     */
    public function testDetectTemplateTypeDefaultsToBasic()
    {
        $testPath = codecept_data_dir('test-project-unknown');
        @mkdir($testPath, 0777, true);

        $result = ProjectStructureHelper::detectTemplateType($testPath);

        $this->assertEquals('basic', $result);

        // Cleanup
        @rmdir($testPath);
    }
}
