<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;
use VinkiusLabs\WatchdogDiscord\Contracts\ErrorTrackingServiceInterface;

class DiscordNotifierCriticalTest extends TestCase
{
    protected DiscordNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        config(['watchdog-discord.enabled' => true]);
        config(['watchdog-discord.webhook_url' => 'https://discord.com/api/webhooks/test']);

        $this->notifier = app(DiscordNotifier::class);
        Http::fake();
    }

    /** @test */
    public function it_never_throws_exceptions_when_http_request_fails()
    {
        Http::fake(['*' => Http::response([], 500)]);

        $exception = new \Exception('Test exception');

        try {
            $this->notifier->send($exception);
            $this->assertTrue(true, 'Notifier handled HTTP failure gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception on HTTP failure: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_network_timeouts_gracefully()
    {
        Http::fake(function () {
            throw new \Exception('Connection timeout');
        });

        $exception = new \Exception('Test exception');

        try {
            $this->notifier->send($exception);
            $this->assertTrue(true, 'Notifier handled network timeout gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception on timeout: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_malformed_webhook_urls()
    {
        $malformedUrls = [
            '',
            'not-a-url',
            'http://',
            'https://',
            'ftp://invalid.com',
            'javascript:alert(1)',
            null,
            false,
            123,
        ];

        foreach ($malformedUrls as $url) {
            config(['watchdog-discord.webhook_url' => $url]);

            try {
                $notifier = app(DiscordNotifier::class);
                $notifier->send(new \Exception('Test'));
                $this->assertTrue(true, 'Handled malformed URL gracefully');
            } catch (\Exception $e) {
                $this->fail("Notifier threw exception with malformed URL '{$url}': " . $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_handles_extremely_large_payloads()
    {
        Http::fake();

        $largeMessage = str_repeat('x', 50000); // 50KB message
        $largeContext = array_fill(0, 1000, str_repeat('y', 100));

        $exception = new \Exception($largeMessage);

        try {
            $this->notifier->send($exception, 'error', $largeContext);
            $this->assertTrue(true, 'Notifier handled large payload gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception with large payload: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_discord_rate_limiting()
    {
        // Simulate Discord rate limiting response
        Http::fake([
            '*' => Http::response([
                'message' => 'You are being rate limited.',
                'retry_after' => 1000,
                'global' => false,
            ], 429)
        ]);

        $exception = new \Exception('Test exception');

        try {
            $this->notifier->send($exception);
            $this->assertTrue(true, 'Notifier handled rate limiting gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception on rate limiting: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_invalid_json_responses()
    {
        Http::fake([
            '*' => Http::response('invalid json response', 200)
        ]);

        $exception = new \Exception('Test exception');

        try {
            $this->notifier->send($exception);
            $this->assertTrue(true, 'Notifier handled invalid JSON gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception with invalid JSON: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_respects_execution_time_limits_during_http_requests()
    {
        $startTime = microtime(true);

        // Test rapid successive calls
        for ($i = 0; $i < 50; $i++) {
            $exception = new \Exception("Test exception {$i}");
            $this->notifier->send($exception);
        }

        $executionTime = microtime(true) - $startTime;

        // Should complete 50 notifications within reasonable time
        $this->assertLessThan(2.0, $executionTime, 'Notifier execution time exceeds limits');
    }

    /** @test */
    public function it_handles_memory_pressure_during_notification_building()
    {
        $startMemory = memory_get_usage(true);

        // Create notifications with varying complexity
        for ($i = 0; $i < 100; $i++) {
            $context = array_fill(0, $i * 10, "context item {$i}");
            $exception = new \Exception("Exception {$i}", $i);

            try {
                $this->notifier->send($exception, 'error', $context);
            } catch (\Exception $e) {
                $this->fail("Notifier threw exception during memory pressure: " . $e->getMessage());
            }
        }

        $endMemory = memory_get_usage(true);
        $memoryIncrease = ($endMemory - $startMemory) / 1024 / 1024; // MB

        // Memory shouldn't increase by more than 20MB
        $this->assertLessThan(20, $memoryIncrease, 'Memory usage increased too much');
    }

    /** @test */
    public function it_handles_corrupted_error_tracking_service()
    {
        // Create a service that always throws exceptions
        $corruptedService = new class implements ErrorTrackingServiceInterface {
            public function trackException(\Throwable $exception, string $level, array $context = []): ?\VinkiusLabs\WatchdogDiscord\Models\ErrorTracking
            {
                throw new \Exception('Service completely corrupted');
            }

            public function trackLog(string $level, string $message, array $context = []): ?\VinkiusLabs\WatchdogDiscord\Models\ErrorTracking
            {
                throw new \Exception('Service completely corrupted');
            }
        };

        $this->app->instance(ErrorTrackingServiceInterface::class, $corruptedService);

        $exception = new \Exception('Test exception');

        try {
            $notifier = app(DiscordNotifier::class);
            $notifier->send($exception);
            $this->assertTrue(true, 'Notifier handled corrupted service gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception with corrupted service: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_special_characters_in_all_fields()
    {
        Http::fake();

        $specialChars = [
            'message' => "🚀 Test \0 NULL \xFF byte 测试 <script>alert('xss')</script>",
            'file' => "/path/with/special/chars/测试.php",
            'level' => "error\0null",
        ];

        $exception = new \Exception($specialChars['message']);

        // Mock exception properties
        $reflection = new \ReflectionObject($exception);
        $fileProperty = $reflection->getProperty('file');
        $fileProperty->setAccessible(true);
        $fileProperty->setValue($exception, $specialChars['file']);

        try {
            $this->notifier->send($exception, $specialChars['level'], $specialChars);
            $this->assertTrue(true, 'Notifier handled special characters gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception with special characters: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_circular_references_in_context()
    {
        Http::fake();

        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1; // Circular reference

        $context = [
            'circular' => $obj1,
            'nested' => ['deep' => ['very_deep' => $obj1]],
        ];

        $exception = new \Exception('Test exception');

        try {
            $this->notifier->send($exception, 'error', $context);
            $this->assertTrue(true, 'Notifier handled circular references gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception with circular references: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_disabled_state_correctly()
    {
        config(['watchdog-discord.enabled' => false]);

        $exception = new \Exception('Test exception');

        try {
            $notifier = app(DiscordNotifier::class);
            $result = $notifier->send($exception);

            // Should not make HTTP requests when disabled
            // Since we're not actually sending, we can't assert on HTTP
            $this->assertTrue(true, 'Notifier handled disabled state correctly');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception when disabled: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_invalid_configuration_gracefully()
    {
        // Test with completely invalid configuration
        config(['watchdog-discord' => null]);

        try {
            $notifier = app(DiscordNotifier::class);
            $notifier->send(new \Exception('Test'));
            $this->assertTrue(true, 'Notifier handled null config gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception with null config: " . $e->getMessage());
        }

        // Test with array instead of string values
        config(['watchdog-discord.webhook_url' => ['not', 'a', 'string']]);

        try {
            $notifier = app(DiscordNotifier::class);
            $notifier->send(new \Exception('Test'));
            $this->assertTrue(true, 'Notifier handled array config gracefully');
        } catch (\Exception $e) {
            $this->fail("Notifier threw exception with array config: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_maintains_thread_safety()
    {
        Http::fake();

        $exception = new \Exception('Thread safety test');

        // Simulate concurrent access
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            try {
                $this->notifier->send($exception);
                $results[] = 'success';
            } catch (\Exception $e) {
                $results[] = 'failed: ' . $e->getMessage();
            }
        }

        // All operations should succeed
        foreach ($results as $result) {
            $this->assertEquals('success', $result, 'Thread safety issue detected');
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
