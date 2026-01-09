<?php

namespace Tests\Unit\Helpers;

use Codeception\Test\Unit;
use Took\Yii2GiiMCP\Helpers\LogReaderHelper;

/**
 * Test LogReaderHelper
 *
 * Tests log parsing, filtering, and aggregation functionality.
 */
class LogReaderHelperTest extends Unit
{
    /**
     * Test parsing valid log line
     */
    public function testParseLogLine()
    {
        $line = "2024-01-09 12:34:56 [127.0.0.1][-][-][error][yii\base\ErrorException] Test error message";

        $result = LogReaderHelper::parseLogLine($line);

        $this->assertIsArray($result);
        $this->assertEquals('2024-01-09 12:34:56', $result['timestamp']);
        $this->assertEquals('127.0.0.1', $result['ip']);
        $this->assertEquals('-', $result['userId']);
        $this->assertEquals('-', $result['sessionId']);
        $this->assertEquals('error', $result['level']);
        $this->assertEquals('yii\base\ErrorException', $result['category']);
        $this->assertEquals('Test error message', $result['message']);
        $this->assertEquals('', $result['trace']);
    }

    /**
     * Test parsing log line with user ID
     */
    public function testParseLogLineWithUserId()
    {
        $line = "2024-01-09 12:34:56 [192.168.1.1][123][abc123][warning][application] Warning message";

        $result = LogReaderHelper::parseLogLine($line);

        $this->assertEquals('123', $result['userId']);
        $this->assertEquals('abc123', $result['sessionId']);
        $this->assertEquals('warning', $result['level']);
        $this->assertEquals('application', $result['category']);
    }

    /**
     * Test parsing invalid log line
     */
    public function testParseLogLineInvalid()
    {
        $line = "This is not a valid log line";

        $result = LogReaderHelper::parseLogLine($line);

        $this->assertNull($result);
    }

    /**
     * Test parsing log line with different levels
     */
    public function testParseLogLineDifferentLevels()
    {
        $levels = ['error', 'warning', 'info', 'trace'];

        foreach ($levels as $level) {
            $line = "2024-01-09 12:34:56 [127.0.0.1][-][-][{$level}][test] Message";
            $result = LogReaderHelper::parseLogLine($line);

            $this->assertEquals($level, $result['level']);
        }
    }

    /**
     * Test log level mapping from numeric to string
     */
    public function testMapLogLevel()
    {
        $this->assertEquals('error', LogReaderHelper::mapLogLevel(1));
        $this->assertEquals('warning', LogReaderHelper::mapLogLevel(2));
        $this->assertEquals('info', LogReaderHelper::mapLogLevel(4));
        $this->assertEquals('trace', LogReaderHelper::mapLogLevel(8));
        $this->assertEquals('unknown', LogReaderHelper::mapLogLevel(999));
    }

    /**
     * Test log level mapping from string
     */
    public function testMapLogLevelString()
    {
        $this->assertEquals('error', LogReaderHelper::mapLogLevel('error'));
        $this->assertEquals('warning', LogReaderHelper::mapLogLevel('warning'));
        $this->assertEquals('info', LogReaderHelper::mapLogLevel('info'));
        $this->assertEquals('trace', LogReaderHelper::mapLogLevel('trace'));
    }

    /**
     * Test log level to number conversion
     */
    public function testLogLevelToNumber()
    {
        $this->assertEquals(1, LogReaderHelper::logLevelToNumber('error'));
        $this->assertEquals(2, LogReaderHelper::logLevelToNumber('warning'));
        $this->assertEquals(4, LogReaderHelper::logLevelToNumber('info'));
        $this->assertEquals(8, LogReaderHelper::logLevelToNumber('trace'));
        $this->assertEquals(4, LogReaderHelper::logLevelToNumber('unknown'));
    }

    /**
     * Test filtering logs by level
     */
    public function testApplyFiltersLevel()
    {
        $logs = [
            ['level' => 'error', 'category' => 'app', 'message' => 'Error 1', 'timestamp' => '2024-01-09 12:00:00', 'trace' => ''],
            ['level' => 'warning', 'category' => 'app', 'message' => 'Warning 1', 'timestamp' => '2024-01-09 12:01:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'app', 'message' => 'Error 2', 'timestamp' => '2024-01-09 12:02:00', 'trace' => ''],
        ];

        $filtered = LogReaderHelper::applyFilters($logs, ['level' => 'error']);

        $this->assertCount(2, $filtered);
        $this->assertEquals('Error 1', $filtered[0]['message']);
        $this->assertEquals('Error 2', $filtered[1]['message']);
    }

    /**
     * Test filtering logs by exact category
     */
    public function testApplyFiltersExactCategory()
    {
        $logs = [
            ['level' => 'error', 'category' => 'application', 'message' => 'Msg 1', 'timestamp' => '2024-01-09 12:00:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'yii\db\Connection', 'message' => 'Msg 2', 'timestamp' => '2024-01-09 12:01:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'application', 'message' => 'Msg 3', 'timestamp' => '2024-01-09 12:02:00', 'trace' => ''],
        ];

        $filtered = LogReaderHelper::applyFilters($logs, ['category' => 'application']);

        $this->assertCount(2, $filtered);
        $this->assertEquals('Msg 1', $filtered[0]['message']);
        $this->assertEquals('Msg 3', $filtered[1]['message']);
    }

    /**
     * Test filtering logs by category wildcard
     */
    public function testApplyFiltersWildcardCategory()
    {
        $logs = [
            ['level' => 'error', 'category' => 'application.module', 'message' => 'Msg 1', 'timestamp' => '2024-01-09 12:00:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'yii\db\Connection', 'message' => 'Msg 2', 'timestamp' => '2024-01-09 12:01:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'application.controller', 'message' => 'Msg 3', 'timestamp' => '2024-01-09 12:02:00', 'trace' => ''],
        ];

        $filtered = LogReaderHelper::applyFilters($logs, ['category' => 'application.*']);

        $this->assertCount(2, $filtered);
        $this->assertEquals('Msg 1', $filtered[0]['message']);
        $this->assertEquals('Msg 3', $filtered[1]['message']);
    }

    /**
     * Test filtering logs by time range (since)
     */
    public function testApplyFiltersTimeSince()
    {
        $logs = [
            ['level' => 'error', 'category' => 'app', 'message' => 'Msg 1', 'timestamp' => '2024-01-09 10:00:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'app', 'message' => 'Msg 2', 'timestamp' => '2024-01-09 12:00:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'app', 'message' => 'Msg 3', 'timestamp' => '2024-01-09 14:00:00', 'trace' => ''],
        ];

        $filtered = LogReaderHelper::applyFilters($logs, ['since' => '2024-01-09 12:00:00']);

        $this->assertCount(2, $filtered);
        $this->assertEquals('Msg 2', $filtered[0]['message']);
        $this->assertEquals('Msg 3', $filtered[1]['message']);
    }

    /**
     * Test filtering logs by time range (until)
     */
    public function testApplyFiltersTimeUntil()
    {
        $logs = [
            ['level' => 'error', 'category' => 'app', 'message' => 'Msg 1', 'timestamp' => '2024-01-09 10:00:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'app', 'message' => 'Msg 2', 'timestamp' => '2024-01-09 12:00:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'app', 'message' => 'Msg 3', 'timestamp' => '2024-01-09 14:00:00', 'trace' => ''],
        ];

        $filtered = LogReaderHelper::applyFilters($logs, ['until' => '2024-01-09 12:00:00']);

        $this->assertCount(2, $filtered);
        $this->assertEquals('Msg 1', $filtered[0]['message']);
        $this->assertEquals('Msg 2', $filtered[1]['message']);
    }

    /**
     * Test filtering logs by search term in message
     */
    public function testApplyFiltersSearchMessage()
    {
        $logs = [
            ['level' => 'error', 'category' => 'app', 'message' => 'Database connection failed', 'timestamp' => '2024-01-09 12:00:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'app', 'message' => 'File not found', 'timestamp' => '2024-01-09 12:01:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'app', 'message' => 'Database query error', 'timestamp' => '2024-01-09 12:02:00', 'trace' => ''],
        ];

        $filtered = LogReaderHelper::applyFilters($logs, ['search' => 'database']);

        $this->assertCount(2, $filtered);
        $this->assertStringContainsString('Database', $filtered[0]['message']);
        $this->assertStringContainsString('Database', $filtered[1]['message']);
    }

    /**
     * Test filtering logs by search term in trace
     */
    public function testApplyFiltersSearchTrace()
    {
        $logs = [
            ['level' => 'error', 'category' => 'app', 'message' => 'Error 1', 'timestamp' => '2024-01-09 12:00:00', 'trace' => 'Stack trace with PDOException'],
            ['level' => 'error', 'category' => 'app', 'message' => 'Error 2', 'timestamp' => '2024-01-09 12:01:00', 'trace' => 'Stack trace with HttpException'],
            ['level' => 'error', 'category' => 'app', 'message' => 'Error 3', 'timestamp' => '2024-01-09 12:02:00', 'trace' => 'Stack trace with PDOException'],
        ];

        $filtered = LogReaderHelper::applyFilters($logs, ['search' => 'PDOException']);

        $this->assertCount(2, $filtered);
        $this->assertStringContainsString('PDOException', $filtered[0]['trace']);
        $this->assertStringContainsString('PDOException', $filtered[1]['trace']);
    }

    /**
     * Test limiting log results
     */
    public function testApplyFiltersLimit()
    {
        $logs = [];
        for ($i = 1; $i <= 10; $i++) {
            $logs[] = [
                'level' => 'error',
                'category' => 'app',
                'message' => "Message {$i}",
                'timestamp' => '2024-01-09 12:00:00',
                'trace' => '',
            ];
        }

        $filtered = LogReaderHelper::applyFilters($logs, ['limit' => 5]);

        $this->assertCount(5, $filtered);
    }

    /**
     * Test log aggregation from multiple applications
     */
    public function testAggregateLogs()
    {
        $logsByApp = [
            'frontend' => [
                ['level' => 'error', 'message' => 'Frontend error', 'timestamp' => '2024-01-09 12:00:00', 'category' => 'app', 'trace' => ''],
                ['level' => 'warning', 'message' => 'Frontend warning', 'timestamp' => '2024-01-09 12:02:00', 'category' => 'app', 'trace' => ''],
            ],
            'backend' => [
                ['level' => 'error', 'message' => 'Backend error', 'timestamp' => '2024-01-09 12:01:00', 'category' => 'app', 'trace' => ''],
            ],
        ];

        $aggregated = LogReaderHelper::aggregateLogs($logsByApp, ['limit' => 100]);

        $this->assertCount(3, $aggregated);
        
        // Should be sorted by timestamp (newest first)
        $this->assertEquals('Frontend warning', $aggregated[0]['message']);
        $this->assertEquals('frontend', $aggregated[0]['application']);
        $this->assertEquals('Backend error', $aggregated[1]['message']);
        $this->assertEquals('backend', $aggregated[1]['application']);
        $this->assertEquals('Frontend error', $aggregated[2]['message']);
        $this->assertEquals('frontend', $aggregated[2]['application']);
    }

    /**
     * Test log aggregation with limit
     */
    public function testAggregateLogsWithLimit()
    {
        $logsByApp = [
            'app1' => [
                ['level' => 'error', 'message' => 'Msg 1', 'timestamp' => '2024-01-09 12:00:00', 'category' => 'app', 'trace' => ''],
                ['level' => 'error', 'message' => 'Msg 2', 'timestamp' => '2024-01-09 12:01:00', 'category' => 'app', 'trace' => ''],
            ],
            'app2' => [
                ['level' => 'error', 'message' => 'Msg 3', 'timestamp' => '2024-01-09 12:02:00', 'category' => 'app', 'trace' => ''],
                ['level' => 'error', 'message' => 'Msg 4', 'timestamp' => '2024-01-09 12:03:00', 'category' => 'app', 'trace' => ''],
            ],
        ];

        $aggregated = LogReaderHelper::aggregateLogs($logsByApp, ['limit' => 2]);

        $this->assertCount(2, $aggregated);
    }

    /**
     * Test reading non-existent log file
     */
    public function testReadLogFileNonExistent()
    {
        $result = LogReaderHelper::readLogFile('/nonexistent/path/to/app.log');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test combined filters
     */
    public function testApplyFiltersCombined()
    {
        $logs = [
            ['level' => 'error', 'category' => 'application', 'message' => 'Database error', 'timestamp' => '2024-01-09 10:00:00', 'trace' => ''],
            ['level' => 'warning', 'category' => 'application', 'message' => 'Database warning', 'timestamp' => '2024-01-09 12:00:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'yii\db', 'message' => 'Database error', 'timestamp' => '2024-01-09 14:00:00', 'trace' => ''],
            ['level' => 'error', 'category' => 'application', 'message' => 'File error', 'timestamp' => '2024-01-09 16:00:00', 'trace' => ''],
        ];

        $filtered = LogReaderHelper::applyFilters($logs, [
            'level' => 'error',
            'category' => 'application',
            'search' => 'database',
            'since' => '2024-01-09 09:00:00',
        ]);

        $this->assertCount(1, $filtered);
        $this->assertEquals('Database error', $filtered[0]['message']);
        $this->assertEquals('application', $filtered[0]['category']);
        $this->assertEquals('error', $filtered[0]['level']);
    }
}
