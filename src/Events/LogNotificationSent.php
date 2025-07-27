<?php

namespace VinkiusLabs\WatchdogDiscord\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LogNotificationSent
{
    use Dispatchable, SerializesModels;

    public string $level;

    public string $message;

    public array $context;

    public array $payload;

    public bool $successful;

    /**
     * Create a new event instance.
     */
    public function __construct(string $level, string $message, array $context, array $payload, bool $successful = true)
    {
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
        $this->payload = $payload;
        $this->successful = $successful;
    }
}
