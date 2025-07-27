<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use Illuminate\Support\Facades\Http;
use VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord;
use PHPUnit\Framework\Attributes\Test;

class FacadeTest extends TestCase
{
    #[Test]
    public function it_can_access_notifier_via_facade()
    {
        $this->assertTrue(WatchdogDiscord::isEnabled());
    }

    #[Test]
    public function it_can_send_logs_via_facade()
    {
        Http::fake();

        config([
            'watchdog-discord.report_errors.fatal' => true,
        ]);

        WatchdogDiscord::error('Test error via facade', ['test' => true]);

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_can_control_notifier_via_facade()
    {
        WatchdogDiscord::disable();
        $this->assertFalse(WatchdogDiscord::isEnabled());

        WatchdogDiscord::enable();
        $this->assertTrue(WatchdogDiscord::isEnabled());
    }

    #[Test]
    public function it_has_all_log_level_methods()
    {
        Http::fake();

        config([
            'watchdog-discord.log_levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
        ]);

        WatchdogDiscord::emergency('Emergency');
        WatchdogDiscord::alert('Alert');
        WatchdogDiscord::critical('Critical');
        WatchdogDiscord::error('Error');
        WatchdogDiscord::warning('Warning');
        WatchdogDiscord::notice('Notice');
        WatchdogDiscord::info('Info');
        WatchdogDiscord::debug('Debug');

        // Should send 8 notifications (all levels are enabled in config)
        Http::assertSentCount(8);
    }
}
