<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use Illuminate\Support\Facades\Queue;
use VinkiusLabs\WatchdogDiscord\Jobs\ProcessErrorTracking;
use VinkiusLabs\WatchdogDiscord\Services\ErrorAnalyticsService;
use PHPUnit\Framework\Attributes\Test;

class ProcessErrorTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[Test]
    public function it_can_process_exception_data()
    {
        $exceptionData = [
            'exception_class' => \Exception::class,
            'message' => 'Test exception',
            'file' => '/path/to/file.php',
            'line' => 123,
            'level' => 'error',
            'context' => ['test' => 'data'],
        ];

        $job = new ProcessErrorTracking('exception', $exceptionData);

        $this->assertInstanceOf(ProcessErrorTracking::class, $job);
    }

    #[Test]
    public function it_can_process_log_data()
    {
        $logData = [
            'level' => 'error',
            'message' => 'Test log message',
            'context' => ['test' => 'data'],
        ];

        $job = new ProcessErrorTracking('log', $logData);

        $this->assertInstanceOf(ProcessErrorTracking::class, $job);
    }

    #[Test]
    public function it_has_correct_queue_configuration()
    {
        $job = new ProcessErrorTracking('log', []);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(30, $job->timeout);
        $this->assertEquals(3, $job->maxExceptions);
    }

    #[Test]
    public function it_handles_exception_processing()
    {
        $this->withoutMockingConsoleOutput();

        $analyticsService = $this->mock(ErrorAnalyticsService::class);
        $analyticsService->shouldReceive('trackException')
            ->once()
            ->with(
                \Mockery::type(\Exception::class),
                'error',
                ['test' => 'data']
            );

        $exceptionData = [
            'exception_class' => \Exception::class,
            'message' => 'Test exception',
            'file' => '/path/to/file.php',
            'line' => 123,
            'level' => 'error',
            'context' => ['test' => 'data'],
        ];

        $job = new ProcessErrorTracking('exception', $exceptionData);
        $job->handle($analyticsService);
    }

    #[Test]
    public function it_handles_log_processing()
    {
        $this->withoutMockingConsoleOutput();

        $analyticsService = $this->mock(ErrorAnalyticsService::class);
        $analyticsService->shouldReceive('trackLog')
            ->once()
            ->with('error', 'Test log message', ['test' => 'data']);

        $logData = [
            'level' => 'error',
            'message' => 'Test log message',
            'context' => ['test' => 'data'],
        ];

        $job = new ProcessErrorTracking('log', $logData);
        $job->handle($analyticsService);
    }

    #[Test]
    public function it_uses_configured_queue()
    {
        config(['watchdog-discord.queue.queue' => 'custom-queue']);

        $job = new ProcessErrorTracking('log', []);

        // Verify the job uses the configured queue
        $this->assertEquals('custom-queue', $job->queue);
    }
}
