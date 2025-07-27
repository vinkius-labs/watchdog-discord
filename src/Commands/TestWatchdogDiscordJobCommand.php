<?php

namespace VinkiusLabs\WatchdogDiscord\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\Job;
use VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord;

class TestWatchdogDiscordJobCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'watchdog-discord:test-job 
                            {--type=exception : Type of test notification (exception|log)}
                            {--level=error : Log level for log notifications}';

    /**
     * The console command description.
     */
    protected $description = 'Send a test job failure notification to Discord';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('watchdog-discord.enabled', false)) {
            $this->error('Watchdog Discord is not enabled. Please set WATCHDOG_DISCORD_ENABLED=true in your .env file.');
            return 1;
        }

        if (empty(config('watchdog-discord.webhook_url'))) {
            $this->error('Discord webhook URL is not configured. Please set WATCHDOG_DISCORD_WEBHOOK_URL in your .env file.');
            return 1;
        }

        if (! config('watchdog-discord.queue_monitoring.enabled', true)) {
            $this->error('Queue monitoring is not enabled. Please set WATCHDOG_DISCORD_QUEUE_MONITORING=true in your .env file.');
            return 1;
        }

        $type = $this->option('type');

        try {
            if ($type === 'exception') {
                $this->info('Sending test job failure notification...');
                $this->sendTestJobFailure();
            } else {
                $level = $this->option('level');
                $this->info("Sending test {$level} log notification...");
                WatchdogDiscord::sendLog($level, "Test {$level} message from job failure test command", [
                    'test' => true,
                    'command' => 'watchdog-discord:test-job',
                ]);
            }

            $this->info('✅ Test notification sent successfully!');
            $this->info('Check your Discord channel to see if the message was received.');
        } catch (\Exception $e) {
            $this->error('❌ Failed to send test notification: ' . $e->getMessage());
            $this->error('Please check your Discord webhook URL and try again.');
            return 1;
        }

        return 0;
    }

    /**
     * Send a test job failure notification
     */
    protected function sendTestJobFailure(): void
    {
        $exception = new \Exception('Test job failure exception from watchdog-discord:test-job command');

        $jobContext = [
            'job_name' => 'TestJob',
            'queue' => 'default',
            'connection' => 'redis',
            'attempts' => 3,
        ];

        WatchdogDiscord::sendJobFailure($exception, $jobContext);
    }
}
