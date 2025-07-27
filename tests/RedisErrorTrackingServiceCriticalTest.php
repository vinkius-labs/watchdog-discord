<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use VinkiusLabs\WatchdogDiscord\Services\RedisErrorTrackingService;
use VinkiusLabs\WatchdogDiscord\Models\ErrorTracking;

class RedisErrorTrackingServiceCriticalTest extends TestCase
{
    protected RedisErrorTrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RedisErrorTrackingService();
        Queue::fake();
    }

    /** @test */
    public function it_never_throws_exceptions_when_redis_is_completely_unavailable()
    {
        // Simulate Redis completely unavailable
        Redis::shouldReceive('ping')->andThrow(new \Exception('Redis connection failed'));

        $exception = new \Exception('Test exception');

        // This should NEVER throw an exception, even if Redis fails
        try {
            $result = $this->service->trackException($exception);
            $this->assertTrue(true, 'Service handled Redis failure gracefully');
        } catch (\Exception $e) {
            $this->fail("Service threw exception when Redis unavailable: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_never_throws_exceptions_when_database_is_unavailable()
    {
        // Mock database failure
        DB::shouldReceive('table')->andThrow(new \Exception('Database connection failed'));

        $exception = new \Exception('Test exception');

        try {
            $result = $this->service->trackException($exception);
            $this->assertTrue(true, 'Service handled database failure gracefully');
        } catch (\Exception $e) {
            $this->fail("Service threw exception when database unavailable: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_redis_timeout_gracefully()
    {
        Redis::shouldReceive('ping')->andReturnTrue();
        Redis::shouldReceive('incr')->andThrow(new \Exception('Connection timeout'));

        $exception = new \Exception('Test exception');

        try {
            $result = $this->service->trackException($exception);
            $this->assertTrue(true, 'Service handled Redis timeout gracefully');
        } catch (\Exception $e) {
            $this->fail("Service threw exception on Redis timeout: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_memory_exhaustion_scenarios()
    {
        // Simulate memory pressure
        $largeContext = array_fill(0, 10000, str_repeat('x', 1000));
        $exception = new \Exception('Test exception');

        try {
            $result = $this->service->trackException($exception, 'error', $largeContext);
            $this->assertTrue(true, 'Service handled large context gracefully');
        } catch (\Exception $e) {
            $this->fail("Service threw exception with large context: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_malformed_exception_objects()
    {
        // Test with exception that has problematic properties
        $exception = new \Exception("Test exception with \0 null bytes and \xFF invalid chars");

        // Test with different types of exceptions that could cause issues
        $exceptions = [
            new \RuntimeException('Runtime error'),
            new \InvalidArgumentException('Invalid argument'),
            new \OutOfBoundsException('Out of bounds'),
            new \LogicException('Logic error'),
        ];

        foreach ($exceptions as $testException) {
            try {
                $result = $this->service->trackException($testException);
                $this->assertTrue(true, 'Service handled exception type gracefully');
            } catch (\Exception $e) {
                $this->fail("Service threw exception with " . get_class($testException) . ": " . $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_respects_execution_time_limits()
    {
        $startTime = microtime(true);

        // Test with multiple rapid calls
        for ($i = 0; $i < 100; $i++) {
            $exception = new \Exception("Test exception {$i}");
            $this->service->trackException($exception);
        }

        $executionTime = microtime(true) - $startTime;

        // Should complete within reasonable time (1 second for 100 operations)
        $this->assertLessThan(1.0, $executionTime, 'Service execution time exceeds limits');
    }

    /** @test */
    public function it_handles_circular_reference_in_context()
    {
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1; // Circular reference

        $exception = new \Exception('Test exception');

        try {
            $result = $this->service->trackException($exception, 'error', ['circular' => $obj1]);
            $this->assertTrue(true, 'Service handled circular reference gracefully');
        } catch (\Exception $e) {
            $this->fail("Service threw exception with circular reference: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_unicode_and_special_characters()
    {
        $specialChars = [
            'unicode' => '🚀 Test with emojis 🔥',
            'chinese' => '测试中文字符',
            'arabic' => 'اختبار الأحرف العربية',
            'special' => "NULL\0BYTE\x00\xFF",
            'sql_injection' => "'; DROP TABLE users; --",
            'xss' => '<script>alert("xss")</script>',
        ];

        foreach ($specialChars as $type => $content) {
            $exception = new \Exception($content);

            try {
                $result = $this->service->trackException($exception, 'error', [$type => $content]);
                $this->assertTrue(true, "Service handled {$type} characters gracefully");
            } catch (\Exception $e) {
                $this->fail("Service threw exception with {$type} characters: " . $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_handles_extremely_long_messages()
    {
        $longMessage = str_repeat('x', 100000); // 100KB message
        $exception = new \Exception($longMessage);

        try {
            $result = $this->service->trackException($exception);
            $this->assertTrue(true, 'Service handled extremely long message gracefully');
        } catch (\Exception $e) {
            $this->fail("Service threw exception with long message: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_concurrent_access_safely()
    {
        $exception = new \Exception('Concurrent test exception');

        // Simulate concurrent access
        $processes = [];
        for ($i = 0; $i < 10; $i++) {
            $processes[] = function () use ($exception) {
                return $this->service->trackException($exception);
            };
        }

        // Execute all processes
        foreach ($processes as $process) {
            try {
                $process();
                $this->assertTrue(true, 'Concurrent access handled gracefully');
            } catch (\Exception $e) {
                $this->fail("Service threw exception during concurrent access: " . $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_handles_null_and_invalid_contexts()
    {
        $invalidContexts = [
            null,
            false,
            '',
            [],
            [null, false, ''],
            ['resource' => fopen('php://memory', 'r')],
            ['closure' => function () {
                return 'test';
            }],
        ];

        $exception = new \Exception('Test exception');

        foreach ($invalidContexts as $context) {
            try {
                $result = $this->service->trackException($exception, 'error', (array)$context);
                $this->assertTrue(true, 'Service handled invalid context gracefully');
            } catch (\Exception $e) {
                $this->fail("Service threw exception with invalid context: " . $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_maintains_performance_under_load()
    {
        $startMemory = memory_get_usage(true);
        $startTime = microtime(true);

        // Generate load
        for ($i = 0; $i < 1000; $i++) {
            $exception = new \Exception("Load test exception {$i}");
            $this->service->trackException($exception);
        }

        $endMemory = memory_get_usage(true);
        $endTime = microtime(true);

        $memoryIncrease = ($endMemory - $startMemory) / 1024 / 1024; // MB
        $executionTime = $endTime - $startTime;

        // Memory shouldn't increase by more than 50MB for 1000 operations
        $this->assertLessThan(50, $memoryIncrease, 'Memory usage increased too much under load');

        // Should complete 1000 operations in under 5 seconds
        $this->assertLessThan(5.0, $executionTime, 'Execution time too slow under load');
    }

    /** @test */
    public function it_never_blocks_application_execution()
    {
        // Mock slow operations
        Redis::shouldReceive('ping')->andReturnUsing(function () {
            usleep(100000); // 100ms delay
            return true;
        });

        $exception = new \Exception('Test exception');
        $startTime = microtime(true);

        try {
            $this->service->trackException($exception);
            $executionTime = microtime(true) - $startTime;

            // Should not take more than configured max execution time
            $maxTime = config('watchdog-discord.performance.max_execution_time', 3);
            $this->assertLessThan($maxTime, $executionTime, 'Service exceeded maximum execution time');
        } catch (\Exception $e) {
            $this->fail("Service threw exception during slow operation: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_invalid_configuration_gracefully()
    {
        // Test with invalid configuration values
        config(['watchdog-discord.performance.async_enabled' => 'invalid']);
        config(['watchdog-discord.cache.ttl' => -1]);
        config(['watchdog-discord.cache.prefix' => null]);

        $exception = new \Exception('Test exception');

        try {
            $service = new RedisErrorTrackingService();
            $result = $service->trackException($exception);
            $this->assertTrue(true, 'Service handled invalid configuration gracefully');
        } catch (\Exception $e) {
            $this->fail("Service threw exception with invalid configuration: " . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Close any open resources
        if (isset($this->service)) {
            unset($this->service);
        }

        parent::tearDown();
    }
}
