<?php

namespace VinkiusLabs\WatchdogDiscord\Commands;

use Illuminate\Console\Command;
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;

class TestWatchdogDiscordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watchdog-discord:test 
                            {--level=error : The log level to test (error, warning, info, debug, etc.)}
                            {--message= : Custom message to send}
                            {--exception : Send a test exception instead of a log message}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Discord notifications for Laravel Watchdog';

    /**
     * Get the configured locale for translations
     */
    protected function getLocale(): string
    {
        return config('watchdog-discord.locale', 'en');
    }

    /**
     * Get translated text
     */
    protected function trans(string $key, array $replace = []): string
    {
        $locale = $this->getLocale();
        $translation = trans("watchdog-discord.{$key}", $replace, $locale);

        // Fallback to English if translation is missing
        if ($translation === "watchdog-discord.{$key}") {
            $translation = trans("watchdog-discord.{$key}", $replace, 'en');
        }

        return $translation;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Performance check - ensure package won't slow down the application
        $startTime = microtime(true);

        if (! config('watchdog-discord.enabled', false)) {
            $this->error($this->trans('commands.test.not_enabled'));
            return Command::FAILURE;
        }

        $webhookUrl = config('watchdog-discord.webhook_url');
        if (empty($webhookUrl)) {
            $this->error($this->trans('commands.test.no_webhook'));
            return Command::FAILURE;
        }

        try {
            $notifier = app(DiscordNotifier::class);

            if ($this->option('exception')) {
                $this->info($this->trans('commands.test.sending_exception'));

                try {
                    throw new \Exception($this->option('message') ?: 'Test exception from Watchdog Discord');
                } catch (\Exception $e) {
                    $notifier->send($e, 'error', [
                        'command' => 'watchdog-discord:test',
                        'test_mode' => true,
                        'timestamp' => now()->toISOString(),
                    ]);
                }
            } else {
                $level = $this->option('level');
                $message = $this->option('message') ?: "Test {$level} message from Watchdog Discord";

                $this->info($this->trans('commands.test.sending_log', ['level' => $level]));

                $notifier->sendLog($level, $message, [
                    'command' => 'watchdog-discord:test',
                    'test_mode' => true,
                    'timestamp' => now()->toISOString(),
                    'user' => get_current_user() ?? 'console',
                ]);
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->info($this->trans('commands.test.success'));
            $this->line($this->trans('commands.test.check_discord'));
            $this->comment("Performance: {$executionTime}ms execution time");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to send test notification: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
