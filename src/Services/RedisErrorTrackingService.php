<?php

namespace VinkiusLabs\WatchdogDiscord\Services;

use VinkiusLabs\WatchdogDiscord\Contracts\ErrorTrackingServiceInterface;
use VinkiusLabs\WatchdogDiscord\Models\ErrorTracking;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * High-performance error tracking service with Redis caching and MySQL persistence.
 * Implements SOLID principles with dependency inversion and single responsibility.
 */
class RedisErrorTrackingService implements ErrorTrackingServiceInterface
{
    private readonly bool $useAsync;
    private readonly string $redisPrefix;
    private readonly int $cacheTtl;

    public function __construct()
    {
        $this->useAsync = config('watchdog-discord.performance.async_enabled', true);
        $this->redisPrefix = config('watchdog-discord.cache.prefix', 'watchdog') ?: 'watchdog';
        $this->cacheTtl = max(1, config('watchdog-discord.cache.ttl', 300));
    }

    public function trackException(\Throwable $exception, string $level = 'error', array $context = []): ?ErrorTracking
    {
        $hash = $this->computeErrorHash($exception->getMessage(), $exception->getFile(), $exception->getLine());

        if ($this->useAsync) {
            $this->enqueueForProcessing('exception', [
                'hash' => $hash,
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'level' => $level,
                'context' => $context,
            ]);
            return null;
        }

        return $this->processErrorTracking($hash, [
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'level' => $level,
            'context' => json_encode($context),
        ]);
    }

    public function trackLog(string $level, string $message, array $context = []): ?ErrorTracking
    {
        $hash = $this->computeErrorHash($message, null, null);

        if ($this->useAsync) {
            $this->enqueueForProcessing('log', [
                'hash' => $hash,
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ]);
            return null;
        }

        return $this->processErrorTracking($hash, [
            'exception_class' => 'Log',
            'message' => $message,
            'file' => null,
            'line' => null,
            'level' => $level,
            'context' => json_encode($context),
        ]);
    }

    private function processErrorTracking(string $hash, array $data): ?ErrorTracking
    {
        if ($this->isRedisAvailable()) {
            try {
                $count = $this->incrementRedisCounter($hash);

                if ($count === 1) {
                    return $this->createErrorRecord($hash, $data);
                }

                $this->updateDatabaseCountInBackground($hash);
                return $this->createCachedErrorMock($count);
            } catch (\Exception $e) {
                Log::warning('Redis operation failed, falling back to database', [
                    'error' => $e->getMessage(),
                    'hash' => $hash
                ]);
            }
        }

        return $this->processDatabaseOnly($hash, $data);
    }

    private function incrementRedisCounter(string $hash): int
    {
        $key = "{$this->redisPrefix}:count:{$hash}";
        $count = Redis::incr($key);

        if ($count === 1) {
            Redis::expire($key, $this->cacheTtl);
        }

        return $count;
    }

    private function processDatabaseOnly(string $hash, array $data): ?ErrorTracking
    {
        $model = $this->createErrorTrackingModel();
        $existing = $model->where('error_hash', $hash)->first();

        if ($existing) {
            $existing->increment('occurrence_count');
            return $existing;
        }

        return $this->createErrorRecord($hash, $data);
    }

    private function createErrorRecord(string $hash, array $data): ErrorTracking
    {
        $model = $this->createErrorTrackingModel();
        $now = Carbon::now();

        return $model->create(array_merge($data, [
            'error_hash' => $hash,
            'environment' => app()->environment(),
            'severity_score' => $this->calculateSeverityScore($data['level']),
            'first_occurred_at' => $now,
            'last_occurred_at' => $now,
            'occurrence_count' => 1,
            'hourly_count' => 1,
            'daily_count' => 1,
            'is_resolved' => false,
            'notification_sent' => false,
        ]));
    }

    private function updateDatabaseCountInBackground(string $hash): void
    {
        $model = $this->createErrorTrackingModel();
        $model->where('error_hash', $hash)->increment('occurrence_count');
    }

    private function createCachedErrorMock(int $count): ErrorTracking
    {
        $mock = new ErrorTracking();
        $mock->occurrence_count = $count;
        $mock->should_notify = false;

        $mock->shouldNotify = fn() => false;
        $mock->recordNotificationSent = fn() => null;

        return $mock;
    }

    private function enqueueForProcessing(string $type, array $data): void
    {
        if ($this->isRedisAvailable()) {
            try {
                $queueKey = "{$this->redisPrefix}:queue:{$type}:" . uniqid();
                Redis::setex($queueKey, 3600, json_encode($data));
                return;
            } catch (\Exception $e) {
                Log::warning('Redis queue failed, processing synchronously', [
                    'error' => $e->getMessage(),
                    'type' => $type
                ]);
            }
        }

        // Fallback to synchronous processing
        if ($type === 'exception') {
            $this->processDatabaseOnly($data['hash'], [
                'exception_class' => $data['class'],
                'message' => $data['message'],
                'file' => $data['file'],
                'line' => $data['line'],
                'level' => $data['level'],
                'context' => json_encode($data['context']),
            ]);
        }
    }

    private function createErrorTrackingModel(): ErrorTracking
    {
        $model = new ErrorTracking();

        if ($connection = config('watchdog-discord.database.connection')) {
            $model->setConnection($connection);
        }

        return $model;
    }

    private function isRedisAvailable(): bool
    {
        try {
            if (!class_exists(\Redis::class) && !class_exists(\Predis\Client::class)) {
                return false;
            }
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function computeErrorHash(string $message, ?string $file, ?int $line): string
    {
        return hash('xxh3', $message . ($file ?? '') . ($line ?? ''));
    }

    private function calculateSeverityScore(string $level): int
    {
        return match ($level) {
            'emergency' => 10,
            'alert' => 9,
            'critical' => 8,
            'error' => 7,
            'warning' => 6,
            'notice' => 5,
            'info' => 4,
            'debug' => 3,
            default => 1
        };
    }
}
