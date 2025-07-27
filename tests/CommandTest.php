<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class CommandTest extends TestCase
{
    #[Test]
    public function it_can_run_test_command_for_exception()
    {
        Http::fake();

        $this->artisan('watchdog-discord:test', ['--exception' => true])
            ->expectsOutput(trans('watchdog-discord.commands.test.sending_exception', [], 'en'))
            ->expectsOutput(trans('watchdog-discord.commands.test.success', [], 'en'))
            ->assertExitCode(0);

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_can_run_test_command_for_log()
    {
        Http::fake();

        config(['watchdog-discord.report_errors.fatal' => true]);

        $this->artisan('watchdog-discord:test', ['--level' => 'error'])
            ->expectsOutput(trans('watchdog-discord.commands.test.sending_log', ['level' => 'error'], 'en'))
            ->expectsOutput(trans('watchdog-discord.commands.test.success', [], 'en'))
            ->assertExitCode(0);

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_fails_when_not_enabled()
    {
        config(['watchdog-discord.enabled' => false]);

        $this->artisan('watchdog-discord:test')
            ->expectsOutput(trans('watchdog-discord.commands.test.not_enabled', [], 'en'))
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_no_webhook_url()
    {
        config(['watchdog-discord.webhook_url' => '']);

        $this->artisan('watchdog-discord:test')
            ->expectsOutput(trans('watchdog-discord.commands.test.no_webhook', [], 'en'))
            ->assertExitCode(1);
    }

    #[Test]
    public function it_can_use_custom_message()
    {
        Http::fake();

        $this->artisan('watchdog-discord:test', [
            '--exception' => true,
            '--message' => 'Custom test message',
        ])->assertExitCode(0);

        Http::assertSentCount(1);
    }
}
