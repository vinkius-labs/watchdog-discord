<?php

namespace VinkiusLabs\WatchdogDiscord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VinkiusLabs\WatchdogDiscord\Events\ErrorNotificationSent;

class SendDiscordErrorNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $webhookUrl;

    protected array $payload;

    protected \Throwable $exception;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(string $webhookUrl, array $payload, \Throwable $exception)
    {
        $this->webhookUrl = $webhookUrl;
        $this->payload = $payload;
        $this->exception = $exception;

        // Set queue connection if configured
        $this->onQueue(config('watchdog-discord.queue.name', 'default'));
        $this->onConnection(config('watchdog-discord.queue.connection', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $response = Http::timeout(config('watchdog-discord.timeout', 30))
                ->post($this->webhookUrl, $this->payload);

            $successful = $response->successful();

            if (! $successful) {
                Log::error('Discord webhook failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'exception' => $this->exception->getMessage(),
                ]);
            }

            // Dispatch event
            event(new ErrorNotificationSent($this->exception, $this->payload, $successful));
        } catch (\Exception $e) {
            Log::error('Failed to send Discord notification', [
                'error' => $e->getMessage(),
                'exception' => $this->exception->getMessage(),
            ]);

            // Dispatch failed event
            event(new ErrorNotificationSent($this->exception, $this->payload, false));

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Discord notification job permanently failed', [
            'error' => $exception->getMessage(),
            'original_exception' => $this->exception->getMessage(),
            'payload' => $this->payload,
        ]);

        // Dispatch final failed event
        event(new ErrorNotificationSent($this->exception, $this->payload, false));
    }
}
