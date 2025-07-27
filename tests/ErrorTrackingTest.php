<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use VinkiusLabs\WatchdogDiscord\Models\ErrorTracking;
use VinkiusLabs\WatchdogDiscord\Services\ErrorAnalyticsService;
use Exception;

class ErrorTrackingTest extends TestCase
{
    public function test_can_track_exception()
    {
        $analytics = app(ErrorAnalyticsService::class);
        $exception = new Exception('Test error message');

        $errorRecord = $analytics->trackException($exception, 'error');

        $this->assertInstanceOf(ErrorTracking::class, $errorRecord);
        $this->assertEquals('Test error message', $errorRecord->message);
        $this->assertEquals(Exception::class, $errorRecord->exception_class);
        $this->assertEquals('error', $errorRecord->level);
        $this->assertEquals(1, $errorRecord->occurrence_count);
    }

    public function test_can_track_duplicate_exceptions()
    {
        $analytics = app(ErrorAnalyticsService::class);
        $exception = new Exception('Duplicate error');

        // Track the same exception twice
        $first = $analytics->trackException($exception, 'error');
        $second = $analytics->trackException($exception, 'error');

        // Should return the same record with incremented count
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(2, $second->occurrence_count);
    }

    public function test_can_track_log_messages()
    {
        $analytics = app(ErrorAnalyticsService::class);

        $logRecord = $analytics->trackLog('warning', 'Test log message');

        $this->assertInstanceOf(ErrorTracking::class, $logRecord);
        $this->assertEquals('Test log message', $logRecord->message);
        $this->assertEquals('Log', $logRecord->exception_class);
        $this->assertEquals('warning', $logRecord->level);
    }

    public function test_error_hash_generation()
    {
        // Create exceptions with same message and metadata for consistency
        $file = '/test/file.php';
        $line = 100;

        // Mock exceptions to have same file/line
        $exception1 = $this->createMockExceptionWithLocation('Same message', $file, $line);
        $exception2 = $this->createMockExceptionWithLocation('Same message', $file, $line);
        $exception3 = $this->createMockExceptionWithLocation('Different message', $file, $line);

        $hash1 = ErrorTracking::generateErrorHash($exception1, 'testing');
        $hash2 = ErrorTracking::generateErrorHash($exception2, 'testing');
        $hash3 = ErrorTracking::generateErrorHash($exception3, 'testing');

        // Same exceptions should generate same hash
        $this->assertEquals($hash1, $hash2);
        // Different exceptions should generate different hash
        $this->assertNotEquals($hash1, $hash3);
        // All hashes should be 16 character strings (xxh3)
        $this->assertEquals(16, strlen($hash1));
        $this->assertEquals(16, strlen($hash2));
        $this->assertEquals(16, strlen($hash3));
        // Hashes should be hexadecimal
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $hash1);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $hash2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $hash3);
    }

    public function test_severity_calculation()
    {
        $exception = new Exception('Test');

        $criticalSeverity = ErrorTracking::calculateSeverityScore($exception, 'critical');
        $infoSeverity = ErrorTracking::calculateSeverityScore($exception, 'info');

        $this->assertGreaterThan($infoSeverity, $criticalSeverity);
        $this->assertGreaterThanOrEqual(1, $infoSeverity);
        $this->assertLessThanOrEqual(10, $criticalSeverity);
    }

    public function test_frequency_description()
    {
        $error = new ErrorTracking([
            'occurrence_count' => 1,
            'hourly_count' => 1,
            'daily_count' => 1,
        ]);

        $description = $error->getFrequencyDescription();
        $this->assertEquals('First occurrence', $description);

        $error->occurrence_count = 5;
        $error->hourly_count = 3;
        $error->daily_count = 5;

        $description = $error->getFrequencyDescription();
        $this->assertStringContainsString('3x in last hour', $description);
        $this->assertStringContainsString('5x in last 24h', $description);
    }

    public function test_should_notify_logic()
    {
        // Temporarily override config for this test
        config(['watchdog-discord.error_tracking.notification_rules.min_severity' => 7]);
        config(['watchdog-discord.error_tracking.notification_rules.frequency_threshold' => 10]);
        config(['watchdog-discord.error_tracking.notification_rules.hourly_threshold' => 5]);

        // High severity error should always notify
        $highSeverityError = new ErrorTracking([
            'severity_score' => 9,
            'occurrence_count' => 1,
            'hourly_count' => 1,
            'last_notification_at' => null,
        ]);

        $this->assertTrue($highSeverityError->shouldNotify());

        // Low severity, low frequency should not notify
        $lowError = new ErrorTracking([
            'severity_score' => 2,
            'occurrence_count' => 1,
            'hourly_count' => 1,
            'last_notification_at' => null,
        ]);

        $this->assertFalse($lowError->shouldNotify());

        // High frequency should notify regardless of severity
        $frequentError = new ErrorTracking([
            'severity_score' => 3,
            'occurrence_count' => 15,
            'hourly_count' => 1,
            'last_notification_at' => null,
        ]);

        $this->assertTrue($frequentError->shouldNotify());
    }

    public function test_error_analytics_statistics()
    {
        $analytics = app(ErrorAnalyticsService::class);

        // Create some test errors
        $analytics->trackException(new Exception('Error 1'), 'error');
        $analytics->trackException(new Exception('Error 2'), 'critical');
        $analytics->trackLog('warning', 'Warning log');

        $stats = $analytics->getErrorStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_errors', $stats);
        $this->assertArrayHasKey('unresolved_errors', $stats);
        $this->assertArrayHasKey('high_severity_errors', $stats);
        $this->assertEquals(3, $stats['total_errors']);
    }

    public function test_error_scopes()
    {
        $analytics = app(ErrorAnalyticsService::class);

        // Create errors with different properties
        $recentError = $analytics->trackException(new Exception('Recent'), 'critical');
        $oldError = ErrorTracking::create([
            'error_hash' => 'old_hash',
            'exception_class' => Exception::class,
            'message' => 'Old error',
            'environment' => 'testing',
            'level' => 'error',
            'severity_score' => 5,
            'first_occurred_at' => now()->subDays(2),
            'last_occurred_at' => now()->subDays(2),
            'occurrence_count' => 1,
            'hourly_count' => 0,
            'daily_count' => 0,
            'is_resolved' => false,
        ]);

        $recentErrors = ErrorTracking::recent()->get();
        $highSeverityErrors = ErrorTracking::highSeverity()->get();
        $unresolvedErrors = ErrorTracking::unresolved()->get();

        $this->assertCount(1, $recentErrors);
        $this->assertCount(1, $highSeverityErrors);
        $this->assertCount(2, $unresolvedErrors);
    }

    public function test_error_resolution()
    {
        $analytics = app(ErrorAnalyticsService::class);
        $error = $analytics->trackException(new Exception('Test'), 'error');

        $this->assertFalse($error->is_resolved);
        $this->assertNull($error->resolved_at);

        $error->markAsResolved();

        $this->assertTrue($error->is_resolved);
        $this->assertNotNull($error->resolved_at);
    }

    public function test_occurrence_increment()
    {
        $error = ErrorTracking::create([
            'error_hash' => 'test_hash',
            'exception_class' => Exception::class,
            'message' => 'Test error',
            'environment' => 'testing',
            'level' => 'error',
            'severity_score' => 5,
            'first_occurred_at' => now()->subHour(),
            'last_occurred_at' => now()->subHour(),
            'occurrence_count' => 1,
            'hourly_count' => 1,
            'daily_count' => 1,
            'is_resolved' => false,
        ]);

        $originalCount = $error->occurrence_count;
        $error->incrementOccurrence();

        $this->assertEquals($originalCount + 1, $error->occurrence_count);
        $this->assertEquals(2, $error->hourly_count);
        $this->assertEquals(2, $error->daily_count);
    }

    protected function createMockExceptionWithLocation(string $message, string $file, int $line): \Exception
    {
        $exception = new \Exception($message);

        // Use reflection to set file and line
        $reflection = new \ReflectionObject($exception);
        $fileProperty = $reflection->getProperty('file');
        $fileProperty->setAccessible(true);
        $fileProperty->setValue($exception, $file);

        $lineProperty = $reflection->getProperty('line');
        $lineProperty->setAccessible(true);
        $lineProperty->setValue($exception, $line);

        return $exception;
    }
}
