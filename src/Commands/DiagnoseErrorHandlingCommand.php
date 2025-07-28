<?php

namespace VinkiusLabs\WatchdogDiscord\Commands;

use Illuminate\Console\Command;
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;
use Illuminate\Support\Facades\Log;

class DiagnoseErrorHandlingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watchdog-discord:diagnose 
                            {--test-direct : Test direct notification without error handlers}
                            {--test-handlers : Test if error handlers are working}
                            {--verbose : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose Watchdog Discord error handling and configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Diagnosing Watchdog Discord Error Handling...');
        $this->newLine();

        // 1. Check basic configuration
        $this->checkBasicConfiguration();

        // 2. Test direct notification
        if ($this->option('test-direct')) {
            $this->testDirectNotification();
        }

        // 3. Check error handlers
        if ($this->option('test-handlers')) {
            $this->testErrorHandlers();
        }

        // 4. Test specific error scenarios that match your logs
        $this->testSpecificScenarios();

        $this->newLine();
        $this->info('✅ Diagnosis completed. Check your logs and Discord for notifications.');

        return self::SUCCESS;
    }

    /**
     * Check basic configuration
     */
    protected function checkBasicConfiguration(): void
    {
        $this->info('📋 1. Basic Configuration Check');

        $enabled = config('watchdog-discord.enabled');
        $webhookUrl = config('watchdog-discord.webhook_url');
        $phpErrorsEnabled = config('watchdog-discord.php_errors.enabled', true);

        $this->line("   Watchdog Enabled: " . ($enabled ? '✅ Yes' : '❌ No'));
        $this->line("   Webhook URL: " . ($webhookUrl ? '✅ Configured' : '❌ Missing'));
        $this->line("   PHP Errors: " . ($phpErrorsEnabled ? '✅ Enabled (default)' : '❌ Disabled'));

        if (!$enabled) {
            $this->warn('   ⚠️  Watchdog is disabled. Set WATCHDOG_DISCORD_ENABLED=true in your .env');
        }

        if (!$webhookUrl) {
            $this->warn('   ⚠️  No webhook URL configured. Set WATCHDOG_DISCORD_WEBHOOK_URL in your .env');
        }

        $this->info('   ℹ️  PHP errors are captured by default - no additional config needed');

        $this->newLine();
    }

    /**
     * Test direct notification without error handlers
     */
    protected function testDirectNotification(): void
    {
        $this->info('📤 2. Testing Direct Notification');

        try {
            $testException = new \Exception('Direct test notification from Watchdog Discord diagnostic');

            $notifier = app(DiscordNotifier::class);
            $notifier->send($testException);

            $this->line('   ✅ Direct notification sent successfully');
        } catch (\Throwable $e) {
            $this->error('   ❌ Failed to send direct notification: ' . $e->getMessage());

            if ($this->option('verbose')) {
                $this->line('   Stack trace:');
                $this->line('   ' . $e->getTraceAsString());
            }
        }

        $this->newLine();
    }

    /**
     * Test if error handlers are properly registered
     */
    protected function testErrorHandlers(): void
    {
        $this->info('🔧 3. Testing Error Handlers');

        // Check if our error handler is active
        $this->line('   Current error handler: ' . $this->getCurrentErrorHandler());

        // Test custom error handler
        $handlerWorking = false;
        $originalHandler = set_error_handler(function ($severity, $message, $file, $line) use (&$handlerWorking) {
            $handlerWorking = true;
            $this->line("   🎯 Custom handler caught: {$message}");

            // Let the error continue normally
            return false;
        });

        // Trigger a PHP error
        $nonExistentVariable = null;
        @$undefined = $nonExistentVariable;

        // Restore original handler
        restore_error_handler();

        $this->line('   Error handler test: ' . ($handlerWorking ? '✅ Working' : '❌ Not working'));

        $this->newLine();
    }

    /**
     * Test specific scenarios from your logs
     */
    protected function testSpecificScenarios(): void
    {
        $this->info('🎯 4. Testing Specific Error Scenarios from Logs');

        // Scenario 1: Test undefined property (exact match to your error)
        $this->testUndefinedPropertyError();

        // Scenario 2: Test method does not exist (exact match to Carbon error)
        $this->testMethodNotExistError();

        // Scenario 3: Test if exceptions are being caught by Laravel's handler
        $this->testLaravelExceptionHandler();
    }

    /**
     * Test undefined property error exactly like in your logs
     */
    protected function testUndefinedPropertyError(): void
    {
        $this->line('   🔸 Testing: Undefined property error');

        try {
            // Create a class similar to your TaskExecutionService
            $mockService = new class {
                public function processWorkflowTasks()
                {
                    // This simulates the exact error from your logs
                    return $this->errorRecoveryService->handleError();
                }
            };

            $result = $mockService->processWorkflowTasks();
        } catch (\Throwable $e) {
            $this->line('   ✅ Caught: ' . $e->getMessage());

            // Try to send notification manually
            try {
                app(DiscordNotifier::class)->send($e);
                $this->line('   📤 Notification sent');
            } catch (\Throwable $notifError) {
                $this->error('   ❌ Notification failed: ' . $notifError->getMessage());
            }
        }
    }

    /**
     * Test method does not exist error exactly like in your logs
     */
    protected function testMethodNotExistError(): void
    {
        $this->line('   🔸 Testing: Method does not exist error');

        try {
            $carbon = \Carbon\Carbon::now();

            // This is the exact error from your logs
            $result = $carbon->toIso8661String();
        } catch (\Throwable $e) {
            $this->line('   ✅ Caught: ' . $e->getMessage());

            // Try to send notification manually
            try {
                app(DiscordNotifier::class)->send($e);
                $this->line('   📤 Notification sent');
            } catch (\Throwable $notifError) {
                $this->error('   ❌ Notification failed: ' . $notifError->getMessage());
            }
        }
    }

    /**
     * Test Laravel exception handler integration
     */
    protected function testLaravelExceptionHandler(): void
    {
        $this->line('   🔸 Testing: Laravel Exception Handler Integration');

        try {
            // Test if our reportable handler is registered
            $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

            if (method_exists($handler, 'reportable')) {
                $this->line('   ✅ Laravel exception handler supports reportable()');

                // Create a test exception and report it
                $testException = new \RuntimeException('Test exception for Laravel handler integration');

                // This should trigger our Discord notification if everything is working
                $handler->report($testException);

                $this->line('   📤 Exception reported through Laravel handler');
            } else {
                $this->warn('   ⚠️  Laravel exception handler does not support reportable()');
            }
        } catch (\Throwable $e) {
            $this->error('   ❌ Laravel handler test failed: ' . $e->getMessage());
        }
    }

    /**
     * Get information about current error handler
     */
    protected function getCurrentErrorHandler(): string
    {
        $handler = set_error_handler(function () {});
        restore_error_handler();

        if ($handler === null) {
            return 'Default PHP handler';
        }

        if (is_array($handler)) {
            return get_class($handler[0]) . '::' . $handler[1];
        }

        if (is_string($handler)) {
            return $handler;
        }

        return 'Custom closure';
    }
}
