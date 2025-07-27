<?php

namespace VinkiusLabs\WatchdogDiscord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VinkiusLabs\WatchdogDiscord\Services\ErrorAnalyticsService;

class ProcessErrorTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public int $maxExceptions = 3;

    protected string $type;
    protected array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(string $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;

        // Use low priority queue to avoid impacting application performance
        $this->onQueue(config('watchdog-discord.queue.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(ErrorAnalyticsService $analyticsService): void
    {
        try {
            if ($this->type === 'exception') {
                $this->processException($analyticsService);
            } elseif ($this->type === 'log') {
                $this->processLog($analyticsService);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the job completely
            \Log::warning('Error processing watchdog tracking', [
                'type' => $this->type,
                'error' => $e->getMessage(),
                'data' => $this->data,
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Process exception data
     */
    protected function processException(ErrorAnalyticsService $analyticsService): void
    {
        // Reconstruct exception from data
        $exceptionClass = $this->data['exception_class'];
        $message = $this->data['message'];
        $file = $this->data['file'];
        $line = $this->data['line'];

        // Create a mock exception for processing
        $exception = new \Exception($message);

        // Use reflection to set file and line
        $reflection = new \ReflectionClass($exception);
        $fileProperty = $reflection->getProperty('file');
        $fileProperty->setAccessible(true);
        $fileProperty->setValue($exception, $file);

        $lineProperty = $reflection->getProperty('line');
        $lineProperty->setAccessible(true);
        $lineProperty->setValue($exception, $line);

        $analyticsService->trackException(
            $exception,
            $this->data['level'],
            $this->data['context']
        );
    }

    /**
     * Process log data
     */
    protected function processLog(ErrorAnalyticsService $analyticsService): void
    {
        $analyticsService->trackLog(
            $this->data['level'],
            $this->data['message'],
            $this->data['context']
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Failed to process watchdog error tracking', [
            'type' => $this->type,
            'data' => $this->data,
            'error' => $exception->getMessage(),
        ]);
    }
}
