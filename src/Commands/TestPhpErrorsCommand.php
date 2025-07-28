<?php

namespace VinkiusLabs\WatchdogDiscord\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;
use Illuminate\Support\Facades\Log;

class TestPhpErrorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watchdog-discord:test-php-errors 
                            {--scenario=all : Specific scenario to test (all, undefined-property, method-not-exist, fatal-error, queue-job)}
                            {--force : Force sending notifications even if disabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test PHP error detection and Discord notifications with realistic scenarios';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing PHP error detection with realistic scenarios...');

        // Show current configuration
        $this->showConfiguration();

        $scenario = $this->option('scenario');

        switch ($scenario) {
            case 'undefined-property':
                $this->testUndefinedPropertyScenario();
                break;
            case 'method-not-exist':
                $this->testMethodNotExistScenario();
                break;
            case 'fatal-error':
                $this->testFatalErrorScenario();
                break;
            case 'queue-job':
                $this->testQueueJobFailureScenario();
                break;
            case 'all':
            default:
                $this->testAllScenarios();
                break;
        }

        $this->info('✅ Test completed. Check your Discord channel for notifications.');
        $this->info('📋 Also check your application logs for any issues.');

        return self::SUCCESS;
    }

    /**
     * Show current configuration
     */
    protected function showConfiguration(): void
    {
        $this->info('📋 Current Watchdog Configuration:');
        $this->line('   Enabled: ' . (config('watchdog-discord.enabled') ? '✅ Yes' : '❌ No'));
        $this->line('   Webhook URL: ' . (config('watchdog-discord.webhook_url') ? '✅ Configured' : '❌ Not set'));
        $this->line('   PHP Errors: ' . (config('watchdog-discord.php_errors.enabled', true) ? '✅ Enabled (default)' : '❌ Disabled'));
        $this->line('   Error Tracking: ' . (config('watchdog-discord.error_tracking.enabled', true) ? '✅ Enabled (default)' : '❌ Disabled'));
        $this->line('   Queue Enabled: ' . (config('watchdog-discord.queue.enabled') ? '✅ Yes' : '❌ No'));

        // Show a note about defaults
        if (!config('watchdog-discord.enabled')) {
            $this->warn('   ⚠️  Main watchdog is disabled. Enable with: WATCHDOG_DISCORD_ENABLED=true');
        }
        if (!config('watchdog-discord.webhook_url')) {
            $this->warn('   ⚠️  Webhook URL required. Set: WATCHDOG_DISCORD_WEBHOOK_URL=your-url');
        }

        $this->newLine();
    }

    /**
     * Test all scenarios
     */
    protected function testAllScenarios(): void
    {
        $this->info('🧪 Running all test scenarios...');
        $this->newLine();

        $this->testUndefinedPropertyScenario();
        sleep(1);

        $this->testMethodNotExistScenario();
        sleep(1);

        $this->testFatalErrorScenario();
        sleep(1);

        $this->testQueueJobFailureScenario();
        sleep(1);

        $this->testRawPhpErrorGeneration();
    }

    /**
     * Test scenario 1: Undefined property error (like in your logs)
     * Simulates: "Undefined property: Vincius\Services\TaskExecutionService::$errorRecoveryService"
     */
    protected function testUndefinedPropertyScenario(): void
    {
        $this->info('🔍 Test 1: Undefined Property Error');
        $this->line('   Simulating: Undefined property access');

        try {
            // Create a mock service similar to your TaskExecutionService
            $mockService = new class {
                public $existingProperty = 'I exist';

                public function triggerUndefinedPropertyError()
                {
                    // This will trigger the exact error from your logs
                    return $this->errorRecoveryService; // This property doesn't exist
                }
            };

            $result = $mockService->triggerUndefinedPropertyError();
            $this->error('   ❌ Error should have been triggered but wasn\'t');
        } catch (\Throwable $e) {
            $this->line('   ✅ Caught exception: ' . $e->getMessage());

            // Manually trigger the error handler to test if it's working
            $this->manuallyTriggerErrorHandler($e);
        }

        $this->newLine();
    }

    /**
     * Test scenario 2: Method does not exist error
     * Simulates: "Method toIso8661String does not exist"
     */
    protected function testMethodNotExistScenario(): void
    {
        $this->info('🔍 Test 2: Method Does Not Exist Error');
        $this->line('   Simulating: Carbon method error like in your logs');

        try {
            $carbon = Carbon::now();

            // This will trigger the exact error from your logs
            $result = $carbon->toIso8661String(); // Method doesn't exist
            $this->error('   ❌ Error should have been triggered but wasn\'t');
        } catch (\Throwable $e) {
            $this->line('   ✅ Caught exception: ' . $e->getMessage());
            $this->manuallyTriggerErrorHandler($e);
        }

        $this->newLine();
    }

    /**
     * Test scenario 3: Fatal error simulation
     */
    protected function testFatalErrorScenario(): void
    {
        $this->info('🔍 Test 3: Fatal Error Simulation');
        $this->line('   Simulating: Fatal error with proper stack trace');

        try {
            // Simulate a fatal error by calling a function that doesn't exist
            $this->simulateDeepStackTraceError();
        } catch (\Throwable $e) {
            $this->line('   ✅ Caught exception: ' . $e->getMessage());
            $this->manuallyTriggerErrorHandler($e);
        }

        $this->newLine();
    }

    /**
     * Test scenario 4: Queue job failure simulation
     */
    protected function testQueueJobFailureScenario(): void
    {
        $this->info('🔍 Test 4: Queue Job Failure Simulation');
        $this->line('   Simulating: Queue job failure like in your logs');

        try {
            // Create a mock job context similar to your logs
            $jobContext = [
                'job_name' => 'Vincius\Jobs\RunningWorkflowTasksJob',
                'queue' => 'workflows',
                'connection' => 'redis',
                'attempts' => 1,
                'payload' => ['id' => '76bee56e-4a1d-41b1-aeda-2958040792ea'],
            ];

            // Create an exception similar to your logs
            $exception = new \Exception(
                'Error processing orchestrator 76bee56e-4a1d-41b1-aeda-2958040792ea in workflow 01984e29-628f-719f-8611-178a00e96a3b: Method toIso8661String does not exist.'
            );

            // Test the job failure notification directly
            app(DiscordNotifier::class)->sendJobFailure($exception, $jobContext);

            $this->line('   ✅ Queue job failure notification sent');
        } catch (\Throwable $e) {
            $this->error('   ❌ Failed to send job failure notification: ' . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Test raw PHP error generation - this tests the error handlers directly
     */
    protected function testRawPhpErrorGeneration(): void
    {
        $this->info('🔍 Test 5: Raw PHP Error Generation');
        $this->line('   Triggering actual PHP errors to test error handlers');

        // Temporarily enable error reporting to ensure we catch them
        $oldErrorReporting = error_reporting(E_ALL);

        try {
            // Test 1: Undefined variable (E_WARNING in PHP 8+)
            $this->line('   🔸 Triggering undefined variable error...');
            $result = $undefinedVariable ?? 'fallback'; // This triggers E_WARNING

            // Test 2: Array access on non-array (E_WARNING)
            $this->line('   🔸 Triggering array access error...');
            $notArray = 'string';
            $value = @$notArray[0]; // Suppressed but still should be caught

            // Test 3: Division by zero
            $this->line('   🔸 Triggering division by zero...');
            $result = @(10 / 0); // This triggers E_WARNING

            $this->line('   ✅ Raw PHP errors triggered successfully');
        } catch (\Throwable $e) {
            $this->error('   ❌ Unexpected exception: ' . $e->getMessage());
        } finally {
            // Restore original error reporting
            error_reporting($oldErrorReporting);
        }

        $this->newLine();
    }

    /**
     * Simulate a deep stack trace error
     */
    protected function simulateDeepStackTraceError(): void
    {
        $this->level1();
    }

    protected function level1(): void
    {
        $this->level2();
    }

    protected function level2(): void
    {
        $this->level3();
    }

    protected function level3(): void
    {
        // This will create a realistic stack trace
        throw new \RuntimeException(
            'Simulated fatal error: Failed to process workflow task due to service dependency injection failure',
            500
        );
    }

    /**
     * Manually trigger the error handler to test if notifications work
     */
    protected function manuallyTriggerErrorHandler(\Throwable $exception): void
    {
        try {
            // Force the Discord notification to be sent
            app(DiscordNotifier::class)->send($exception);
            $this->line('   📤 Discord notification sent successfully');
        } catch (\Throwable $e) {
            $this->error('   ❌ Failed to send Discord notification: ' . $e->getMessage());

            // Also log this failure
            Log::error('Watchdog Discord notification failed in test', [
                'original_error' => $exception->getMessage(),
                'notification_error' => $e->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }
    }
}
