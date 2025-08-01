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
        $hash = ErrorTracking::generateErrorHash($exception, app()->environment());

        if ($this->useAsync) {
            $this->enqueueForProcessing('exception', [
                'hash' => $hash,
                'exception' => $exception,
                'level' => $level,
                'context' => $context,
            ]);

            // Return a proper ErrorTracking record for async mode
            return $this->createOrGetErrorRecord($hash, [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'level' => $level,
                'context' => json_encode($context),
            ]);
        }

        // For synchronous mode, prepare complete data including request info
        $completeData = $this->prepareDatabaseData('exception', [
            'exception' => $exception,
            'level' => $level,
            'context' => $context,
        ]);

        return $this->processErrorTracking($hash, $completeData);
    }

    public function trackLog(string $level, string $message, array $context = []): ?ErrorTracking
    {
        $hash = ErrorTracking::generateLogHash($level, $message, app()->environment());

        if ($this->useAsync) {
            $this->enqueueForProcessing('log', [
                'hash' => $hash,
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ]);

            // Return a proper ErrorTracking record for async mode
            return $this->createOrGetErrorRecord($hash, [
                'exception_class' => 'Log',
                'message' => $message,
                'file' => null,
                'line' => null,
                'level' => $level,
                'context' => json_encode($context),
            ]);
        }

        // For synchronous mode, prepare complete data including request info
        $completeData = $this->prepareDatabaseData('log', [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ]);

        return $this->processErrorTracking($hash, $completeData);
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
            $existing->incrementOccurrence();
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
        $existing = $model->where('error_hash', $hash)->first();

        if ($existing) {
            $existing->incrementOccurrence();
        }
    }

    private function createCachedErrorMock(int $count): ErrorTracking
    {
        $mock = new ErrorTracking();
        $mock->occurrence_count = $count;
        $mock->setAttribute('occurrence_count', $count);

        return $mock;
    }

    private function enqueueForProcessing(string $type, array $data): void
    {
        if ($this->isRedisAvailable()) {
            try {
                $queueKey = "{$this->redisPrefix}:queue:{$type}:" . uniqid();
                Redis::setex($queueKey, 3600, json_encode($data));
            } catch (\Exception $e) {
                Log::warning('Redis queue failed, processing synchronously', [
                    'error' => $e->getMessage(),
                    'type' => $type
                ]);
            }
        }

        // ALWAYS process in database for historical tracking
        $databaseData = $this->prepareDatabaseData($type, $data);
        $this->processDatabaseOnly($data['hash'], $databaseData);
    }

    /**
     * Prepare data for database insertion based on type
     */
    private function prepareDatabaseData(string $type, array $data): array
    {
        $baseData = match ($type) {
            'exception' => [
                'exception_class' => get_class($data['exception']),
                'message' => $data['exception']->getMessage(),
                'file' => $data['exception']->getFile(),
                'line' => $data['exception']->getLine(),
                'level' => $data['level'],
                'context' => json_encode($this->enrichContext($data['context'], $data['exception'])),
                'stack_trace' => $this->formatStackTrace($data['exception']->getTrace()),
            ],
            'log' => [
                'exception_class' => 'Log',
                'message' => $data['message'],
                'file' => null,
                'line' => null,
                'level' => $data['level'],
                'context' => json_encode($this->enrichContext($data['context'])),
                'stack_trace' => null,
            ],
            default => throw new \InvalidArgumentException("Unsupported tracking type: {$type}")
        };

        // Add request data if available
        $requestData = $this->getRequestData();

        return array_merge($baseData, $requestData);
    }

    /**
     * Enrich context with additional debugging information
     */
    private function enrichContext(array $context, ?\Throwable $exception = null): array
    {
        // Add basic environment info
        $enrichedContext = array_merge($context, [
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ]);

        // Add memory and performance info
        $enrichedContext['memory_usage'] = memory_get_usage(true);
        $enrichedContext['memory_peak'] = memory_get_peak_usage(true);

        // Add exception-specific context
        if ($exception) {
            $enrichedContext['exception_type'] = get_class($exception);

            // For ErrorException (PHP errors), add severity information
            if ($exception instanceof \ErrorException) {
                $enrichedContext['error_severity'] = $this->getErrorSeverityName($exception->getSeverity());
                $enrichedContext['error_severity_code'] = $exception->getSeverity();
            }

            // Add previous exception chain
            $previous = $exception->getPrevious();
            $previousExceptions = [];
            while ($previous) {
                $previousExceptions[] = [
                    'class' => get_class($previous),
                    'message' => $previous->getMessage(),
                    'file' => $previous->getFile(),
                    'line' => $previous->getLine(),
                ];
                $previous = $previous->getPrevious();
            }
            if (!empty($previousExceptions)) {
                $enrichedContext['previous_exceptions'] = $previousExceptions;
            }
        }

        // Add server information
        if (isset($_SERVER)) {
            $enrichedContext['server_info'] = [
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'php_sapi' => php_sapi_name(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ];
        }

        return $enrichedContext;
    }

    /**
     * Get human-readable error severity name
     */
    private function getErrorSeverityName(int $severity): string
    {
        return match ($severity) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => "Unknown ({$severity})",
        };
    }

    /**
     * Get request data if available
     */
    private function getRequestData(): array
    {
        $requestData = [
            'url' => null,
            'method' => null,
            'ip' => null,
            'user_id' => null,
        ];

        try {
            if (app()->bound('request') && request() !== null) {
                $request = request();

                $requestData['url'] = $request->fullUrl();
                $requestData['method'] = $request->method();
                $requestData['ip'] = $request->ip();

                // Get user ID if authenticated
                if (auth()->check()) {
                    $requestData['user_id'] = auth()->id();
                }
            }
        } catch (\Exception $e) {
            // Silently fail if request data is not available (e.g., in console commands)
            Log::debug('Could not collect request data', ['error' => $e->getMessage()]);
        }

        return $requestData;
    }

    /**
     * Format stack trace for database storage
     */
    private function formatStackTrace(array $trace): ?string
    {
        try {
            if (empty($trace)) {
                return null;
            }

            // Limit stack trace to prevent database size issues
            $maxFrames = config('watchdog-discord.formatting.max_stack_trace_lines', 10);
            $limitedTrace = array_slice($trace, 0, $maxFrames);

            return json_encode($limitedTrace, JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            Log::warning('Failed to format stack trace', ['error' => $e->getMessage()]);
            return null;
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

    /**
     * Create or get existing error record for async mode
     */
    private function createOrGetErrorRecord(string $hash, array $data): ErrorTracking
    {
        $model = $this->createErrorTrackingModel();
        $existing = $model->where('error_hash', $hash)->first();

        if ($existing) {
            return $existing;
        }

        // Create new record for first-time errors
        return $this->createErrorRecord($hash, $data);
    }
}
