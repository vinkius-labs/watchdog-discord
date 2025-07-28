<?php

namespace VinkiusLabs\WatchdogDiscord\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Realistic Error Simulation Command
 * 
 * This command simulates the exact error conditions that are happening
 * in your application to test if Watchdog Discord can capture them.
 */
class SimulateRealErrorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watchdog-discord:simulate-real-errors 
                            {--disable-handlers : Disable error suppression to see raw errors}
                            {--force-notification : Force send notification even if disabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate the exact real-world errors from your logs to test Watchdog Discord';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🎯 Simulating Real-World Errors from Your Application Logs');
        $this->newLine();

        $disableHandlers = $this->option('disable-handlers');
        $forceNotification = $this->option('force-notification');

        if ($disableHandlers) {
            $this->warn('⚠️  Error suppression disabled - errors will be visible');
        }

        // Simulate the exact workflow from your logs
        $this->simulateWorkflowTaskProcessing();

        $this->newLine();
        $this->info('✅ Real error simulation completed. Check Discord and logs.');

        return self::SUCCESS;
    }

    /**
     * Simulate the exact workflow task processing that's failing in your logs
     */
    protected function simulateWorkflowTaskProcessing(): void
    {
        $this->info('🔄 Simulating: RunningWorkflowTasksJob processing');
        $this->line('   Workflow ID: 01984e29-628f-719f-8611-178a00e96a3b');
        $this->line('   Orchestrator ID: 76bee56e-4a1d-41b1-aeda-2958040792ea');
        $this->newLine();

        try {
            // Simulate the exact job that's failing
            $job = new MockRunningWorkflowTasksJob();
            $job->handle();
        } catch (\Throwable $e) {
            $this->error('💥 Job failed with error: ' . $e->getMessage());

            // Test notification manually
            $this->testNotificationForError($e);
        }
    }

    /**
     * Test notification for a specific error
     */
    protected function testNotificationForError(\Throwable $error): void
    {
        $this->line('📤 Testing notification for error...');

        try {
            $notifier = app(DiscordNotifier::class);
            $notifier->send($error);
            $this->line('   ✅ Notification sent successfully');
        } catch (\Throwable $e) {
            $this->error('   ❌ Notification failed: ' . $e->getMessage());

            // Log detailed error
            Log::error('Watchdog notification test failed', [
                'original_error' => $error->getMessage(),
                'notification_error' => $e->getMessage(),
                'original_file' => $error->getFile(),
                'original_line' => $error->getLine(),
            ]);
        }
    }
}

/**
 * Mock class that simulates your RunningWorkflowTasksJob
 */
class MockRunningWorkflowTasksJob
{
    protected $orchestratorId = '76bee56e-4a1d-41b1-aeda-2958040792ea';
    protected $workflowId = '01984e29-628f-719f-8611-178a00e96a3b';

    public function handle(): void
    {
        echo "   📋 Processing workflow tasks...\n";

        // Simulate the TaskExecutionService
        $taskExecutionService = new MockTaskExecutionService();
        $taskExecutionService->processWorkflowTasks($this->workflowId);
    }
}

/**
 * Mock class that simulates your TaskExecutionService with the exact error
 */
class MockTaskExecutionService
{
    // Simulate missing property that causes the error
    // public $errorRecoveryService; // This is commented out to cause the error

    public function processWorkflowTasks(string $workflowId): void
    {
        echo "   🔧 TaskExecutionService processing...\n";

        try {
            // This will cause the exact error from your logs
            $this->updateOrchestratorStatus();
        } catch (\Throwable $e) {
            echo "   💥 Error in processWorkflowTasks: " . $e->getMessage() . "\n";

            // This will cause the second error from your logs
            $this->logErrorWithBadDateFormat($e);

            throw $e;
        }
    }

    protected function updateOrchestratorStatus(): void
    {
        echo "   📊 Updating orchestrator status...\n";

        // This line will trigger: "Undefined property: MockTaskExecutionService::$errorRecoveryService"
        $this->errorRecoveryService->updateStatus($this->orchestratorId, false);
    }

    protected function logErrorWithBadDateFormat(\Throwable $error): void
    {
        echo "   📝 Logging error with timestamp...\n";

        try {
            // This will trigger: "Method toIso8661String does not exist"
            $timestamp = Carbon::now()->toIso8661String();

            Log::error('Orchestrator error', [
                'error' => $error->getMessage(),
                'timestamp' => $timestamp,
            ]);
        } catch (\Throwable $e) {
            echo "   💥 Timestamp formatting failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
