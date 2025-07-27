<?php

namespace VinkiusLabs\WatchdogDiscord\Contracts;

use VinkiusLabs\WatchdogDiscord\Models\ErrorTracking;

interface ErrorTrackingServiceInterface
{
    public function trackException(\Throwable $exception, string $level, array $context = []): ?ErrorTracking;
    public function trackLog(string $level, string $message, array $context = []): ?ErrorTracking;
}
