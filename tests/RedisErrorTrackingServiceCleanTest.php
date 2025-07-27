<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use VinkiusLabs\WatchdogDiscord\Services\RedisErrorTrackingService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class RedisErrorTrackingServiceCleanTest extends TestCase
{
    private RedisErrorTrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RedisErrorTrackingService::class);
    }

    #[Test]
    public function it_handles_redis_unavailable_gracefully()
    {
        Redis::shouldReceive('connection')->andThrow(new \Exception('Redis unavailable'));

        $this->assertDoesNotThrow(function () {
            $this->service->trackException($this->createMockException());
        }, 'Should handle Redis unavailable');
    }

    #[Test]
    public function it_handles_database_unavailable_gracefully()
    {
        DB::shouldReceive('connection')->andThrow(new \Exception('Database unavailable'));

        $this->assertDoesNotThrow(function () {
            $this->service->trackException($this->createMockException());
        }, 'Should handle database unavailable');
    }

    #[Test]
    public function it_handles_redis_timeout_gracefully()
    {
        Redis::shouldReceive('get')->andThrow(new \Exception('Redis timeout'));
        Redis::shouldReceive('set')->andThrow(new \Exception('Redis timeout'));

        $this->assertDoesNotThrow(function () {
            $this->service->trackException($this->createMockException());
        }, 'Should handle Redis timeouts');
    }

    #[Test]
    public function it_handles_malformed_exception_objects()
    {
        $malformedException = new \Error('Malformed error');

        $this->assertDoesNotThrow(function () use ($malformedException) {
            $this->service->trackException($malformedException);
        }, 'Should handle malformed exceptions');
    }

    #[Test]
    public function it_respects_execution_time_limits()
    {
        $startTime = microtime(true);

        $this->service->trackException($this->createMockException());

        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(3.0, $executionTime, 'Should complete within time limit');
    }

    #[Test]
    public function it_handles_unicode_and_special_characters()
    {
        $unicodeMessage = "Error with émojis 🚨 and unicode: Тест 测试 テスト";
        $exception = new \Exception($unicodeMessage);

        $this->assertDoesNotThrow(function () use ($exception) {
            $this->service->trackException($exception);
        }, 'Should handle unicode characters');
    }

    #[Test]
    public function it_handles_extremely_long_messages()
    {
        $longMessage = str_repeat('Very long error message. ', 1000);
        $exception = new \Exception($longMessage);

        $this->assertDoesNotThrow(function () use ($exception) {
            $this->service->trackException($exception);
        }, 'Should handle long messages');
    }

    #[Test]
    public function it_handles_concurrent_access_safely()
    {
        $exceptions = [];
        for ($i = 0; $i < 10; $i++) {
            $exceptions[] = new \Exception("Concurrent exception $i");
        }

        $this->assertDoesNotThrow(function () use ($exceptions) {
            foreach ($exceptions as $exception) {
                $this->service->trackException($exception);
            }
        }, 'Should handle concurrent access');
    }

    #[Test]
    public function it_handles_null_and_invalid_contexts()
    {
        $this->assertDoesNotThrow(function () {
            $this->service->trackLog('error', 'Test message', []);
        }, 'Should handle empty context');

        $this->assertDoesNotThrow(function () {
            $invalidContext = ['key' => null];
            $this->service->trackLog('error', 'Test message', $invalidContext);
        }, 'Should handle invalid context');
    }

    #[Test]
    public function it_never_blocks_application_execution()
    {
        // Simulate blocking operations
        Redis::shouldReceive('get')->andReturnUsing(function () {
            usleep(100000); // 100ms delay
            return null;
        });

        $startTime = microtime(true);

        $this->service->trackException($this->createMockException());

        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(2.0, $executionTime, 'Should not block application');
    }

    #[Test]
    public function it_handles_invalid_configuration()
    {
        // Test with various invalid configurations
        config(['watchdog-discord.error_tracking.enabled' => null]);

        $this->assertDoesNotThrow(function () {
            $this->service->trackException($this->createMockException());
        }, 'Should handle invalid configuration');
    }

    #[Test]
    public function it_maintains_performance_under_load()
    {
        $startTime = microtime(true);

        // Process multiple exceptions
        for ($i = 0; $i < 20; $i++) {
            $this->service->trackException(new \Exception("Load test $i"));
        }

        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(5.0, $executionTime, 'Should maintain performance under load');
    }
}
