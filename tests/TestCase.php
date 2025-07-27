<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Force service provider to boot by accessing the notifier
        $this->app->make(\VinkiusLabs\WatchdogDiscord\DiscordNotifier::class);

        // Run migrations silently
        try {
            $this->artisan('migrate', ['--database' => 'sqlite'])->run();
        } catch (\Exception $e) {
            // Ignore migration errors during testing
        }

        // Clear cache before each test
        if (isset($this->app['cache'])) {
            $this->app['cache']->flush();
        }
    }

    /**
     * Define which service providers to load for the test.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            WatchdogDiscordServiceProvider::class,
        ];
    }

    /**
     * Define which aliases to load for the test.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app): array
    {
        return [
            'WatchdogDiscord' => \VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {

        // Database configuration
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Cache configuration
        $app['config']->set('cache.default', 'array');

        // Queue configuration
        $app['config']->set('queue.default', 'sync');

        // Session configuration
        $app['config']->set('session.driver', 'array');

        // Watchdog Discord configuration
        $app['config']->set('watchdog-discord', [
            'enabled' => true,
            'webhook_url' => 'https://discord.com/api/webhooks/test/webhook/url',
            'username' => 'WatchdogBot',
            'avatar_url' => null,
            'timeout' => 2,
            'rate_limit' => 60,
            'queue' => 'default',
            'async' => false,
            'levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            'ignored_exceptions' => [],
            'include_request_data' => true,
            'mentions' => [],
            'error_tracking' => [
                'enabled' => true,
                'connection' => 'sqlite',
                'table' => 'watchdog_errors',
                'min_severity' => 1,
                'frequency_threshold' => 1,
                'hourly_threshold' => 1,
                'notification_cooldown' => 0,
                'cleanup_days' => 30,
            ],
        ]);

        // Legacy configuration support
        $app['config']->set('watchdog-discord.queue.enabled', false);
        $app['config']->set('watchdog-discord.rate_limiting.enabled', false);
        $app['config']->set('watchdog-discord.locale', 'en');
        $app['config']->set('watchdog-discord.error_tracking.notification_rules.min_severity', 1);
        $app['config']->set('watchdog-discord.error_tracking.notification_rules.frequency_threshold', 1);
        $app['config']->set('watchdog-discord.error_tracking.notification_rules.hourly_threshold', 1);
        $app['config']->set('watchdog-discord.error_tracking.notification_rules.notification_cooldown_minutes', 0);
    }

    /**
     * Create a mock exception for testing.
     */
    protected function createMockException(string $message = 'Test exception', int $code = 0): \Exception
    {
        return new \Exception($message, $code);
    }

    /**
     * Create a mock error context for testing.
     */
    protected function createMockContext(): array
    {
        return [
            'user_id' => 123,
            'action' => 'test_action',
            'timestamp' => now()->toISOString(),
            'environment' => 'testing',
        ];
    }

    /**
     * Safely execute code that might fail.
     */
    protected function executeWithoutErrors(callable $callback): bool
    {
        try {
            $callback();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Assert that a closure doesn't throw any exceptions.
     */
    protected function assertDoesNotThrow(callable $callback, string $message = ''): void
    {
        try {
            $callback();
            $this->assertTrue(true, $message ?: 'Code executed without throwing exceptions');
        } catch (\Throwable $e) {
            $this->fail(($message ?: 'Code threw an unexpected exception') . ': ' . $e->getMessage());
        }
    }
}
