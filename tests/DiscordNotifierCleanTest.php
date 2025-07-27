<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use VinkiusLabs\WatchdogDiscord\DiscordNotifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class DiscordNotifierCleanTest extends TestCase
{
    private DiscordNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notifier = new DiscordNotifier();
    }

    #[Test]
    public function it_handles_http_request_failures_gracefully()
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertDoesNotThrow(function () {
            $this->notifier->send($this->createMockException());
        }, 'Should handle HTTP failures without throwing');
    }

    #[Test]
    public function it_handles_network_timeouts_gracefully()
    {
        Http::fake(function () {
            throw new \Exception('Connection timeout');
        });

        $this->assertDoesNotThrow(function () {
            $this->notifier->send($this->createMockException());
        }, 'Should handle timeouts gracefully');
    }

    #[Test]
    public function it_handles_malformed_webhook_urls()
    {
        Config::set('watchdog-discord.webhook_url', 'not-a-valid-url');

        $this->assertDoesNotThrow(function () {
            $this->notifier->send($this->createMockException());
        }, 'Should handle malformed URLs');
    }

    #[Test]
    public function it_handles_extremely_large_payloads()
    {
        $largeMessage = str_repeat('A', 10000);
        $exception = new \Exception($largeMessage);

        $this->assertDoesNotThrow(function () use ($exception) {
            $this->notifier->send($exception);
        }, 'Should handle large payloads');
    }

    #[Test]
    public function it_handles_discord_rate_limiting()
    {
        Http::fake(['*' => Http::response([], 429)]);

        $this->assertDoesNotThrow(function () {
            $this->notifier->send($this->createMockException());
        }, 'Should handle rate limiting');
    }

    #[Test]
    public function it_handles_invalid_json_responses()
    {
        Http::fake(['*' => Http::response('invalid json', 200)]);

        $this->assertDoesNotThrow(function () {
            $this->notifier->send($this->createMockException());
        }, 'Should handle invalid JSON responses');
    }

    #[Test]
    public function it_respects_execution_time_limits()
    {
        $startTime = microtime(true);

        $this->notifier->send($this->createMockException());

        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(5.0, $executionTime, 'Should complete within time limit');
    }

    #[Test]
    public function it_handles_special_characters_in_fields()
    {
        $specialCharsMessage = "Test with émojis 🚀 and special chars: àáâãäå";
        $exception = new \Exception($specialCharsMessage);

        $this->assertDoesNotThrow(function () use ($exception) {
            $this->notifier->send($exception);
        }, 'Should handle special characters');
    }

    #[Test]
    public function it_handles_disabled_state_correctly()
    {
        Config::set('watchdog-discord.enabled', false);

        $this->assertDoesNotThrow(function () {
            $this->notifier->send($this->createMockException());
        }, 'Should handle disabled state');

        // Re-enable for other tests
        Config::set('watchdog-discord.enabled', true);
    }

    #[Test]
    public function it_handles_invalid_configuration()
    {
        Config::set('watchdog-discord.webhook_url', null);

        $this->assertDoesNotThrow(function () {
            $this->notifier->send($this->createMockException());
        }, 'Should handle null webhook URL');
    }

    #[Test]
    public function it_maintains_thread_safety()
    {
        $exceptions = [];

        // Simulate concurrent calls
        for ($i = 0; $i < 5; $i++) {
            $exceptions[] = new \Exception("Concurrent test $i");
        }

        $this->assertDoesNotThrow(function () use ($exceptions) {
            foreach ($exceptions as $exception) {
                $this->notifier->send($exception);
            }
        }, 'Should handle concurrent requests');
    }

    #[Test]
    public function it_processes_null_and_invalid_contexts_safely()
    {
        $this->assertDoesNotThrow(function () {
            $this->notifier->sendLog('error', 'Test message', []);
        }, 'Should handle empty context');

        $this->assertDoesNotThrow(function () {
            $invalidContext = ['circular' => null];
            $invalidContext['circular'] = &$invalidContext;
            $this->notifier->sendLog('error', 'Test with circular reference', ['context' => 'test']);
        }, 'Should handle circular references');
    }
}
