<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord;

class TestWatchdogDiscordJobCommandTest extends TestCase
{
    /** @test */
    public function it_requires_package_to_be_enabled()
    {
        config(['watchdog-discord.enabled' => false]);

        $this->artisan('watchdog-discord:test-job')
            ->expectsOutput('Watchdog Discord is not enabled. Please set WATCHDOG_DISCORD_ENABLED=true in your .env file.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_requires_webhook_url_to_be_configured()
    {
        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.webhook_url' => '',
        ]);

        $this->artisan('watchdog-discord:test-job')
            ->expectsOutput('Discord webhook URL is not configured. Please set WATCHDOG_DISCORD_WEBHOOK_URL in your .env file.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_requires_queue_monitoring_to_be_enabled()
    {
        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.webhook_url' => 'https://discord.com/api/webhooks/test',
            'watchdog-discord.queue_monitoring.enabled' => false,
        ]);

        $this->artisan('watchdog-discord:test-job')
            ->expectsOutput('Queue monitoring is not enabled. Please set WATCHDOG_DISCORD_QUEUE_MONITORING=true in your .env file.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_sends_test_job_failure_notification()
    {
        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.webhook_url' => 'https://discord.com/api/webhooks/test',
            'watchdog-discord.queue_monitoring.enabled' => true,
        ]);

        // Mock the WatchdogDiscord facade
        WatchdogDiscord::shouldReceive('sendJobFailure')
            ->once()
            ->with(
                \Mockery::type(\Exception::class),
                \Mockery::type('array')
            );

        $this->artisan('watchdog-discord:test-job --type=exception')
            ->expectsOutput('Sending test job failure notification...')
            ->expectsOutput('✅ Test notification sent successfully!')
            ->expectsOutput('Check your Discord channel to see if the message was received.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_sends_test_log_notification()
    {
        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.webhook_url' => 'https://discord.com/api/webhooks/test',
            'watchdog-discord.queue_monitoring.enabled' => true,
        ]);

        // Mock the WatchdogDiscord facade
        WatchdogDiscord::shouldReceive('sendLog')
            ->once()
            ->with('error', 'Test error message from job failure test command', \Mockery::type('array'));

        $this->artisan('watchdog-discord:test-job --type=log --level=error')
            ->expectsOutput('Sending test error log notification...')
            ->expectsOutput('✅ Test notification sent successfully!')
            ->expectsOutput('Check your Discord channel to see if the message was received.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_notification_failure_gracefully()
    {
        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.webhook_url' => 'https://discord.com/api/webhooks/test',
            'watchdog-discord.queue_monitoring.enabled' => true,
        ]);

        // Mock the WatchdogDiscord facade to throw an exception
        WatchdogDiscord::shouldReceive('sendJobFailure')
            ->once()
            ->andThrow(new \Exception('Webhook failed'));

        $this->artisan('watchdog-discord:test-job --type=exception')
            ->expectsOutput('Sending test job failure notification...')
            ->expectsOutput('❌ Failed to send test notification: Webhook failed')
            ->expectsOutput('Please check your Discord webhook URL and try again.')
            ->assertExitCode(1);
    }
}
