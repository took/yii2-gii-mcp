<?php

namespace Tests\Unit\Helpers;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\FileHelper;

/**
 * Test FileHelper class
 */
class FileHelperTest extends Unit
{
    private string $testDir;

    /**
     * Test checkConflicts with no conflicts
     */
    public function testCheckConflictsWithNoConflicts(): void
    {
        $files = [
            $this->testDir . '/nonexistent1.php',
            $this->testDir . '/nonexistent2.php',
        ];

        $conflicts = FileHelper::checkConflicts($files);

        $this->assertIsArray($conflicts);
        $this->assertEmpty($conflicts);
    }

    /**
     * Test checkConflicts with existing files
     */
    public function testCheckConflictsWithExistingFiles(): void
    {
        // Create test files
        $file1 = $this->testDir . '/existing1.php';
        $file2 = $this->testDir . '/existing2.php';
        file_put_contents($file1, '<?php echo "test1";');
        file_put_contents($file2, '<?php echo "test2";');

        $files = [$file1, $file2, $this->testDir . '/nonexistent.php'];

        $conflicts = FileHelper::checkConflicts($files);

        $this->assertIsArray($conflicts);
        $this->assertCount(2, $conflicts);
        $this->assertEquals($file1, $conflicts[0]['path']);
        $this->assertTrue($conflicts[0]['exists']);
        $this->assertTrue($conflicts[0]['readable']);
    }

    /**
     * Test getFileInfo with existing file
     */
    public function testGetFileInfoWithExistingFile(): void
    {
        $file = $this->testDir . '/test.php';
        file_put_contents($file, '<?php echo "test";');

        $info = FileHelper::getFileInfo($file);

        $this->assertIsArray($info);
        $this->assertEquals($file, $info['path']);
        $this->assertTrue($info['exists']);
        $this->assertIsInt($info['size']);
        $this->assertIsInt($info['modified']);
        $this->assertTrue($info['readable']);
        $this->assertTrue($info['writable']);
        $this->assertFalse($info['isDir']);
        $this->assertTrue($info['isFile']);
    }

    /**
     * Test getFileInfo with non-existing file
     */
    public function testGetFileInfoWithNonExistingFile(): void
    {
        $info = FileHelper::getFileInfo($this->testDir . '/nonexistent.php');

        $this->assertNull($info);
    }

    /**
     * Test getFileInfo with directory
     */
    public function testGetFileInfoWithDirectory(): void
    {
        $info = FileHelper::getFileInfo($this->testDir);

        $this->assertIsArray($info);
        $this->assertTrue($info['isDir']);
        $this->assertFalse($info['isFile']);
    }

    /**
     * Test checkWritable
     */
    public function testCheckWritable(): void
    {
        $file1 = $this->testDir . '/writable.php';
        $file2 = $this->testDir . '/nonexistent.php';
        file_put_contents($file1, 'test');

        $paths = [$file1, $file2];
        $results = FileHelper::checkWritable($paths);

        $this->assertIsArray($results);
        $this->assertTrue($results[$file1]);
        $this->assertTrue($results[$file2]); // Parent dir is writable
    }

    /**
     * Test canWrite with existing writable file
     */
    public function testCanWriteWithExistingFile(): void
    {
        $file = $this->testDir . '/test.php';
        file_put_contents($file, 'test');

        $this->assertTrue(FileHelper::canWrite($file));
    }

    /**
     * Test canWrite with non-existing file in writable directory
     */
    public function testCanWriteWithNonExistingFileInWritableDir(): void
    {
        $file = $this->testDir . '/new.php';

        $this->assertTrue(FileHelper::canWrite($file));
    }

    /**
     * Test canWrite with non-existing directory
     */
    public function testCanWriteWithNonExistingDirectory(): void
    {
        $file = $this->testDir . '/nonexistent_dir/file.php';

        $this->assertFalse(FileHelper::canWrite($file));
    }

    /**
     * Test getRelativePath
     */
    public function testGetRelativePath(): void
    {
        $basePath = '/var/www/app';
        $path = '/var/www/app/models/User.php';

        $relative = FileHelper::getRelativePath($path, $basePath);

        $this->assertEquals('models/User.php', $relative);
    }

    /**
     * Test getRelativePath with trailing slash
     */
    public function testGetRelativePathWithTrailingSlash(): void
    {
        $basePath = '/var/www/app/';
        $path = '/var/www/app/models/User.php';

        $relative = FileHelper::getRelativePath($path, $basePath);

        $this->assertEquals('models/User.php', $relative);
    }

    /**
     * Test getRelativePath with Windows paths
     */
    public function testGetRelativePathWithWindowsPaths(): void
    {
        $basePath = 'C:\\www\\app';
        $path = 'C:\\www\\app\\models\\User.php';

        $relative = FileHelper::getRelativePath($path, $basePath);

        $this->assertEquals('models/User.php', $relative);
    }

    /**
     * Test getRelativePath with path outside base
     */
    public function testGetRelativePathOutsideBase(): void
    {
        $basePath = '/var/www/app';
        $path = '/var/www/other/file.php';

        $relative = FileHelper::getRelativePath($path, $basePath);

        $this->assertEquals('/var/www/other/file.php', $relative);
    }

    /**
     * Test readFile with existing file
     */
    public function testReadFileWithExistingFile(): void
    {
        $file = $this->testDir . '/test.php';
        $content = '<?php echo "Hello World";';
        file_put_contents($file, $content);

        $read = FileHelper::readFile($file);

        $this->assertEquals($content, $read);
    }

    /**
     * Test readFile with non-existing file
     */
    public function testReadFileWithNonExistingFile(): void
    {
        $read = FileHelper::readFile($this->testDir . '/nonexistent.php');

        $this->assertNull($read);
    }

    /**
     * Test writeFile
     */
    public function testWriteFile(): void
    {
        $file = $this->testDir . '/newfile.php';
        $content = '<?php echo "test";';

        $result = FileHelper::writeFile($file, $content);

        $this->assertTrue($result);
        $this->assertFileExists($file);
        $this->assertEquals($content, file_get_contents($file));
    }

    /**
     * Test writeFile creates parent directory
     */
    public function testWriteFileCreatesParentDirectory(): void
    {
        $file = $this->testDir . '/subdir/newfile.php';
        $content = 'test';

        $result = FileHelper::writeFile($file, $content);

        $this->assertTrue($result);
        $this->assertFileExists($file);
        $this->assertDirectoryExists(dirname($file));
    }

    /**
     * Test ensureDirectory with non-existing directory
     */
    public function testEnsureDirectoryCreatesDirectory(): void
    {
        $dir = $this->testDir . '/newdir';

        $result = FileHelper::ensureDirectory($dir);

        $this->assertTrue($result);
        $this->assertDirectoryExists($dir);
    }

    /**
     * Test ensureDirectory with existing directory
     */
    public function testEnsureDirectoryWithExistingDirectory(): void
    {
        $result = FileHelper::ensureDirectory($this->testDir);

        $this->assertTrue($result);
    }

    /**
     * Test createBackup
     */
    public function testCreateBackup(): void
    {
        $file = $this->testDir . '/original.php';
        $content = 'original content';
        file_put_contents($file, $content);

        $backupPath = FileHelper::createBackup($file);

        $this->assertNotNull($backupPath);
        $this->assertFileExists($backupPath);
        $this->assertEquals($content, file_get_contents($backupPath));
        $this->assertStringEndsWith('.bak', $backupPath);
    }

    /**
     * Test createBackup with custom suffix
     */
    public function testCreateBackupWithCustomSuffix(): void
    {
        $file = $this->testDir . '/original.php';
        file_put_contents($file, 'test');

        $backupPath = FileHelper::createBackup($file, '.backup');

        $this->assertNotNull($backupPath);
        $this->assertStringEndsWith('.backup', $backupPath);
    }

    /**
     * Test createBackup with non-existing file
     */
    public function testCreateBackupWithNonExistingFile(): void
    {
        $backupPath = FileHelper::createBackup($this->testDir . '/nonexistent.php');

        $this->assertNull($backupPath);
    }

    /**
     * Test createBackup adds timestamp when backup exists
     */
    public function testCreateBackupAddsTimestampWhenExists(): void
    {
        $file = $this->testDir . '/original.php';
        file_put_contents($file, 'content');

        // Create first backup
        $backup1 = FileHelper::createBackup($file);
        $this->assertNotNull($backup1);

        // Create second backup
        $backup2 = FileHelper::createBackup($file);
        $this->assertNotNull($backup2);

        // Second backup should have timestamp
        $this->assertNotEquals($backup1, $backup2);
        $this->assertFileExists($backup1);
        $this->assertFileExists($backup2);
    }

    /**
     * Test getConflictSummary with no conflicts
     */
    public function testGetConflictSummaryWithNoConflicts(): void
    {
        $summary = FileHelper::getConflictSummary([]);

        $this->assertIsString($summary);
        $this->assertStringContainsString('No file conflicts', $summary);
    }

    /**
     * Test getConflictSummary with conflicts
     */
    public function testGetConflictSummaryWithConflicts(): void
    {
        $conflicts = [
            [
                'path' => '/path/to/file.php',
                'size' => 1024,
                'modified' => time(),
                'writable' => true,
            ],
        ];

        $summary = FileHelper::getConflictSummary($conflicts);

        $this->assertIsString($summary);
        $this->assertStringContainsString('1 file(s)', $summary);
        $this->assertStringContainsString('/path/to/file.php', $summary);
        $this->assertStringContainsString('writable', $summary);
    }

    /**
     * Test formatFileSize
     */
    public function testFormatFileSize(): void
    {
        $this->assertEquals('0 B', FileHelper::formatFileSize(0));
        $this->assertEquals('100 B', FileHelper::formatFileSize(100));
        $this->assertEquals('1 KB', FileHelper::formatFileSize(1024));
        $this->assertEquals('1.5 KB', FileHelper::formatFileSize(1536));
        $this->assertEquals('1 MB', FileHelper::formatFileSize(1024 * 1024));
        $this->assertEquals('1 GB', FileHelper::formatFileSize(1024 * 1024 * 1024));
    }

    /**
     * Test formatTimestamp
     */
    public function testFormatTimestamp(): void
    {
        $timestamp = strtotime('2024-01-01 12:00:00');
        $formatted = FileHelper::formatTimestamp($timestamp);

        $this->assertIsString($formatted);
        $this->assertStringContainsString('2024-01-01', $formatted);
        $this->assertStringContainsString('12:00:00', $formatted);
    }

    /**
     * Test validatePaths with safe paths
     */
    public function testValidatePathsWithSafePaths(): void
    {
        $paths = [
            '/var/www/app/models/User.php',
            '/var/www/app/controllers/UserController.php',
        ];

        $results = FileHelper::validatePaths($paths);

        $this->assertIsArray($results);
        $this->assertTrue($results[$paths[0]]);
        $this->assertTrue($results[$paths[1]]);
    }

    /**
     * Test validatePaths with path traversal
     */
    public function testValidatePathsWithPathTraversal(): void
    {
        $paths = [
            '../etc/passwd',
            '/var/www/app/../../../etc/passwd',
        ];

        $results = FileHelper::validatePaths($paths);

        $this->assertIsArray($results);
        $this->assertFalse($results[$paths[0]]);
        $this->assertFalse($results[$paths[1]]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/filehelper_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testDir)) {
            $this->rmdirRecursive($this->testDir);
        }
        parent::tearDown();
    }

    private function rmdirRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
