<?php

namespace VinkiusLabs\WatchdogDiscord\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ErrorNotificationSent
{
    use Dispatchable, SerializesModels;

    public \Throwable $exception;

    public array $payload;

    public bool $successful;

    /**
     * Create a new event instance.
     */
    public function __construct(\Throwable $exception, array $payload, bool $successful = true)
    {
        $this->exception = $exception;
        $this->payload = $payload;
        $this->successful = $successful;
    }
}
