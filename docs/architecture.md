# Architecture Guide

This guide provides an in-depth look at Watchdog Discord's technical architecture, design patterns, and implementation details.

## System Overview

Watchdog Discord implements a sophisticated, multi-layered architecture designed for enterprise-scale Laravel applications. The system prioritizes performance, reliability, and extensibility while maintaining zero-impact on application performance.

## Architectural Layers

```
┌─────────────────────────────────────────────────────────────┐
│                 Application Layer                           │
│  ┌─────────────┬──────────────┬─────────────┬─────────────┐  │
│  │ Exception   │  Middleware  │   Facade    │   Manual    │  │
│  │  Handler    │              │             │  Logging    │  │
│  └─────────────┴──────────────┴─────────────┴─────────────┘  │
├─────────────────────────────────────────────────────────────┤
│                 Service Layer                               │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │              Discord Notifier                          │  │
│  │    (Central orchestration and payload building)        │  │
│  └─────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────┤
│                Business Logic Layer                         │
│  ┌───────────────┬───────────────┬─────────────────────────┐  │
│  │ Error Tracking│  Rate Limiter │    Queue Manager        │  │
│  │   Service     │               │                         │  │
│  └───────────────┴───────────────┴─────────────────────────┘  │
├─────────────────────────────────────────────────────────────┤
│                Data Access Layer                            │
│  ┌──────────────┬───────────────┬─────────────────────────┐  │
│  │ Redis Cache  │   Database    │     Discord API         │  │
│  │              │   (MySQL)     │                         │  │
│  └──────────────┴───────────────┴─────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Core Components

### 1. Discord Notifier (Service Layer)

**File**: `src/DiscordNotifier.php`

The central orchestrator responsible for:
- Payload building and formatting
- Rate limiting enforcement
- Queue management decisions
- Event dispatching
- Localization handling

**Key Methods**:
```php
public function send(\Throwable $exception): void;
public function sendLog(string $level, string $message, array $context = []): void;
protected function buildErrorPayload(\Throwable $exception): array;
protected function checkRateLimit(): bool;
```

**Design Patterns**:
- **Facade Pattern**: Provides simple interface to complex subsystem
- **Template Method**: Common notification workflow with customizable steps
- **Strategy Pattern**: Different payload building strategies for errors vs logs

### 2. Error Tracking Service (Business Logic)

**Interface**: `src/Contracts/ErrorTrackingServiceInterface.php`  
**Implementation**: `src/Services/RedisErrorTrackingService.php`

High-performance error deduplication and analytics engine:

```php
interface ErrorTrackingServiceInterface
{
    public function trackException(\Throwable $exception, string $level, array $context = []): ?ErrorTracking;
    public function trackLog(string $level, string $message, array $context = []): ?ErrorTracking;
}
```

**Key Features**:
- **Error Deduplication**: Hash-based grouping of similar errors
- **Frequency Analysis**: Occurrence counting with time-based windows
- **Severity Scoring**: Dynamic scoring based on error type and frequency
- **Performance Optimization**: Redis-first with MySQL persistence

**Performance Architecture**:
```php
class RedisErrorTrackingService implements ErrorTrackingServiceInterface
{
    // Redis-first for speed
    private function processErrorTracking(string $hash, array $data): ?ErrorTracking
    {
        if ($this->isRedisAvailable()) {
            $count = $this->incrementRedisCounter($hash);
            
            // Background database sync
            if ($this->useAsync) {
                $this->updateDatabaseCountInBackground($hash);
                return $this->createCachedErrorMock($count);
            }
        }
        
        // Fallback to direct database
        return $this->processDatabaseOnly($hash, $data);
    }
}
```

### 3. Error Analytics Service

**File**: `src/Services/ErrorAnalyticsService.php`

Advanced analytics and pattern detection:

**Responsibilities**:
- Severity calculation algorithms
- Trend detection
- Frequency pattern analysis
- Notification threshold evaluation

**Severity Calculation Algorithm**:
```php
public static function calculateSeverityScore(\Throwable $exception, string $level, int $occurrenceCount = 1): int
{
    // Base score by log level
    $baseScore = match ($level) {
        'emergency' => 10,
        'alert' => 9,
        'critical' => 8,
        'error' => 6,
        'warning' => 4,
        'notice' => 2,
        'info' => 1,
        'debug' => 1,
        default => 5,
    };

    // Exception type multiplier
    $exceptionScore = match (true) {
        $exception instanceof \Error => 3,
        $exception instanceof \ParseError => 3,
        $exception instanceof \TypeError => 2,
        $exception instanceof \OutOfMemoryException => 3,
        str_contains($exception->getMessage(), 'database') => 2,
        str_contains($exception->getMessage(), 'connection') => 2,
        default => 0,
    };

    // Frequency multiplier
    $frequencyScore = match (true) {
        $occurrenceCount >= 100 => 3,
        $occurrenceCount >= 50 => 2,
        $occurrenceCount >= 10 => 1,
        default => 0,
    };

    return min(10, $baseScore + $exceptionScore + $frequencyScore);
}
```

### 4. Queue Jobs (Async Processing)

**Error Notifications**: `src/Jobs/SendDiscordErrorNotification.php`  
**Log Notifications**: `src/Jobs/SendDiscordLogNotification.php`  
**Error Tracking**: `src/Jobs/ProcessErrorTracking.php`

**Design Features**:
- **Retry Logic**: Configurable retry attempts with exponential backoff
- **Timeout Protection**: Prevents jobs from running indefinitely
- **Error Isolation**: Job failures don't affect application performance
- **Priority Queuing**: Critical errors get higher priority

**Job Configuration**:
```php
class SendDiscordErrorNotification implements ShouldQueue
{
    public int $tries = 3;
    public int $timeout = 30;
    public int $maxExceptions = 3;
    
    public function retryUntil(): DateTime
    {
        return now()->addMinutes(5);
    }
    
    public function backoff(): array
    {
        return [1, 5, 10]; // Seconds between retries
    }
}
```

### 5. Middleware Integration

**File**: `src/Middleware/WatchdogDiscordMiddleware.php`

HTTP request monitoring with performance tracking:

**Features**:
- **Request/Response Timing**: Microsecond precision
- **Exception Interception**: Captures and logs request failures
- **Conditional Logging**: Configurable filters for status codes, duration, paths
- **Zero-Impact Design**: Minimal overhead on request processing

**Request Flow**:
```php
public function handle(Request $request, Closure $next, ?string $level = 'info')
{
    $startTime = microtime(true);

    try {
        $response = $next($request);
        
        // Log successful requests if configured
        if (config('watchdog-discord.log_requests.enabled', false)) {
            $this->logRequest($request, $response, $startTime, $level);
        }
        
        return $response;
    } catch (\Throwable $exception) {
        // Log the exception with request context
        $this->logException($request, $exception, $startTime);
        throw $exception; // Re-throw to maintain normal flow
    }
}
```

## Data Models

### ErrorTracking Model

**File**: `src/Models/ErrorTracking.php`

Central data model for error analytics:

**Schema Design**:
```sql
CREATE TABLE watchdog_error_tracking (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    error_hash VARCHAR(64) NOT NULL,
    exception_class VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(500),
    line INT UNSIGNED,
    environment VARCHAR(50) NOT NULL,
    level VARCHAR(20) NOT NULL,
    severity_score TINYINT UNSIGNED NOT NULL,
    context JSON,
    stack_trace JSON,
    url VARCHAR(2048),
    method VARCHAR(10),
    ip VARCHAR(45),
    user_id BIGINT UNSIGNED,
    first_occurred_at TIMESTAMP NOT NULL,
    last_occurred_at TIMESTAMP NOT NULL,
    occurrence_count INT UNSIGNED DEFAULT 1,
    hourly_count INT UNSIGNED DEFAULT 1,
    daily_count INT UNSIGNED DEFAULT 1,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP NULL,
    notification_sent BOOLEAN DEFAULT FALSE,
    last_notification_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Performance indexes
    INDEX idx_error_hash (error_hash),
    INDEX idx_environment_level (environment, level),
    INDEX idx_severity_env (severity_score, environment),
    INDEX idx_occurrence_env (occurrence_count, environment),
    INDEX idx_last_occurred (last_occurred_at, environment)
);
```

**Key Methods**:
```php
// Error grouping
public static function generateErrorHash(\Throwable $exception, string $environment): string;

// Analytics
public static function calculateSeverityScore(\Throwable $exception, string $level, int $occurrenceCount = 1): int;
public function incrementOccurrence(): void;
public function shouldNotify(): bool;

// Query scopes
public function scopeRecent(Builder $query): Builder;
public function scopeHighSeverity(Builder $query): Builder;
public function scopeTrending(Builder $query): Builder;
```

## Performance Optimizations

### 1. Redis-First Architecture

**Strategy**: Use Redis for high-frequency operations, MySQL for persistence.

```php
private function processErrorTracking(string $hash, array $data): ?ErrorTracking
{
    if ($this->isRedisAvailable()) {
        // Ultra-fast Redis increment (~0.1ms)
        $count = Redis::incr("{$this->redisPrefix}:count:{$hash}");
        
        if ($this->useAsync) {
            // Background database sync
            ProcessErrorTracking::dispatch('exception', $data);
            return $this->createCachedErrorMock($count);
        }
    }
    
    // Fallback to database (~5ms)
    return $this->processDatabaseOnly($hash, $data);
}
```

### 2. Asynchronous Processing

**Pattern**: Immediate response with background processing.

```php
public function send(\Throwable $exception): void
{
    // Quick validation (~0.1ms)
    if (!$this->shouldSendNotification($exception)) {
        return;
    }
    
    // Fast payload building (~1ms)
    $payload = $this->buildErrorPayload($exception);
    
    if (config('watchdog-discord.queue.enabled')) {
        // Queue job (~0.1ms)
        SendDiscordErrorNotification::dispatch($webhookUrl, $payload, $exception);
        return;
    }
    
    // Synchronous fallback (~150ms)
    $this->sendSynchronously($webhookUrl, $payload, $exception);
}
```

### 3. Intelligent Caching

**Multilevel Caching Strategy**:

1. **L1 Cache**: Application memory (object caching)
2. **L2 Cache**: Redis (distributed caching)
3. **L3 Cache**: Database (persistent storage)

```php
// Rate limiting with efficient caching
protected function checkRateLimit(string $type = 'error'): bool
{
    $key = "rate_limit:{$type}:" . now()->format('Y-m-d-H-i');
    
    // Memory cache check
    if (isset($this->memoryCache[$key])) {
        return $this->memoryCache[$key] < $this->maxNotifications;
    }
    
    // Redis cache check
    $count = Cache::remember($key, 60, fn() => 0);
    $this->memoryCache[$key] = $count;
    
    return $count < $this->maxNotifications;
}
```

## Error Handling & Resilience

### 1. Graceful Degradation

**Principle**: Never break the application, even when monitoring fails.

```php
protected function sendDiscordNotification(\Throwable $exception): void
{
    try {
        app(DiscordNotifier::class)->send($exception);
    } catch (\Exception $e) {
        // Silent failure - log but don't re-throw
        Log::error('Failed to send Discord notification', [
            'error' => $e->getMessage(),
            'original_exception' => $exception->getMessage(),
        ]);
    }
}
```

### 2. Circuit Breaker Pattern

**Implementation**: Automatic failure detection and recovery.

```php
private function isRedisAvailable(): bool
{
    static $lastCheck = null;
    static $isAvailable = true;
    
    // Check every 60 seconds
    if ($lastCheck && now()->diffInSeconds($lastCheck) < 60) {
        return $isAvailable;
    }
    
    try {
        Redis::ping();
        $isAvailable = true;
        $lastCheck = now();
        return true;
    } catch (\Exception $e) {
        $isAvailable = false;
        $lastCheck = now();
        return false;
    }
}
```

### 3. Timeout Protection

**Strategy**: Prevent operations from blocking application.

```php
private function processDatabaseOnly(string $hash, array $data): ?ErrorTracking
{
    // Set query timeout
    DB::connection()->getPdo()->setAttribute(PDO::ATTR_TIMEOUT, $this->dbTimeout);
    
    try {
        return DB::transaction(function () use ($hash, $data) {
            // Database operations with timeout protection
            return $this->createOrUpdateErrorRecord($hash, $data);
        });
    } catch (QueryException $e) {
        if (str_contains($e->getMessage(), 'timeout')) {
            Log::warning('Database timeout in error tracking', ['hash' => $hash]);
            return null;
        }
        throw $e;
    }
}
```

## Security Considerations

### 1. Data Sanitization

**Input Sanitization**:
```php
protected function truncateField(?string $value): string
{
    if (!$value) return '';
    
    // Sanitize sensitive data
    $value = $this->sanitizeSensitiveData($value);
    
    // Truncate to Discord limits
    return mb_substr($value, 0, 1024);
}

private function sanitizeSensitiveData(string $data): string
{
    // Remove potential passwords, tokens, etc.
    $patterns = [
        '/password["\s]*[:=]["\s]*[^,}\s]*/i',
        '/token["\s]*[:=]["\s]*[^,}\s]*/i',
        '/key["\s]*[:=]["\s]*[^,}\s]*/i',
    ];
    
    return preg_replace($patterns, '[REDACTED]', $data);
}
```

### 2. Rate Limiting

**DDoS Protection**:
```php
protected function checkRateLimit(string $type = 'error'): bool
{
    $config = config('watchdog-discord.rate_limiting');
    
    if (!$config['enabled']) {
        return true;
    }
    
    $key = $config['cache_key_prefix'] . ":{$type}:" . now()->format('Y-m-d-H-i');
    $current = Cache::get($key, 0);
    
    if ($current >= $config['max_notifications']) {
        Log::warning('Rate limit exceeded for Discord notifications', [
            'type' => $type,
            'current' => $current,
            'max' => $config['max_notifications']
        ]);
        return false;
    }
    
    Cache::put($key, $current + 1, $config['time_window_minutes'] * 60);
    return true;
}
```

## Event System

### 1. Event Dispatching

**Events**:
- `ErrorNotificationSent`: Fired after error notification
- `LogNotificationSent`: Fired after log notification

```php
// In DiscordNotifier
protected function sendSynchronously(string $webhookUrl, array $payload, \Throwable $exception): void
{
    try {
        $response = Http::timeout($this->timeout)->post($webhookUrl, $payload);
        $successful = $response->successful();
        
        event(new ErrorNotificationSent($exception, $payload, $successful));
    } catch (\Exception $e) {
        event(new ErrorNotificationSent($exception, $payload, false));
        throw $e;
    }
}
```

### 2. Event Listeners

**Application Integration**:
```php
// In EventServiceProvider
use VinkiusLabs\WatchdogDiscord\Events\ErrorNotificationSent;

Event::listen(ErrorNotificationSent::class, function ($event) {
    if (!$event->successful) {
        // Handle failed notifications
        Mail::to('admin@example.com')->send(new NotificationFailedMail($event));
    }
    
    // Log notification metrics
    Metrics::increment('discord_notifications_sent', [
        'success' => $event->successful ? 'true' : 'false',
        'exception_type' => get_class($event->exception)
    ]);
});
```

## Testing Architecture

### 1. Unit Testing Strategy

**Service Testing**:
```php
class DiscordNotifierTest extends TestCase
{
    public function testSendsNotificationWhenEnabled()
    {
        Http::fake();
        config(['watchdog-discord.enabled' => true]);
        
        $notifier = new DiscordNotifier();
        $exception = new \Exception('Test exception');
        
        $notifier->send($exception);
        
        Http::assertSentCount(1);
    }
}
```

**Integration Testing**:
```php
class ErrorTrackingIntegrationTest extends TestCase
{
    public function testErrorTrackingWithRedis()
    {
        Redis::flushall();
        
        $service = app(ErrorTrackingServiceInterface::class);
        $exception = new \Exception('Test error');
        
        $result = $service->trackException($exception);
        
        $this->assertNotNull($result);
        $this->assertEquals(1, $result->occurrence_count);
    }
}
```

## Deployment Considerations

### 1. Database Optimization

**Index Strategy**:
```sql
-- Primary lookup index
CREATE INDEX idx_error_hash ON watchdog_error_tracking(error_hash);

-- Analytics queries
CREATE INDEX idx_environment_severity ON watchdog_error_tracking(environment, severity_score, last_occurred_at);

-- Cleanup queries
CREATE INDEX idx_resolved_old ON watchdog_error_tracking(is_resolved, created_at);
```

### 2. Queue Configuration

**Supervisor Configuration**:
```ini
[program:watchdog-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/app/artisan queue:work redis --queue=watchdog_notifications
autostart=true
autorestart=true
numprocs=2
stopwaitsecs=3600
```

### 3. Monitoring & Alerting

**Health Checks**:
```php
// Health check endpoint
Route::get('/health/watchdog', function () {
    return [
        'status' => 'ok',
        'redis_available' => app(RedisErrorTrackingService::class)->isRedisAvailable(),
        'queue_size' => Queue::size('watchdog_notifications'),
        'failed_jobs' => Queue::connection('redis')->getFailedJobs()->count(),
    ];
});
```

## Future Architecture Enhancements

### 1. Microservice Architecture

**Planned**: Separate service for error analytics
- Independent scaling
- Dedicated database
- API-based communication

### 2. Machine Learning Integration

**Roadmap**: Intelligent error classification
- Automatic severity scoring
- Anomaly detection
- Predictive alerting

### 3. Multi-Channel Support

**Extension**: Support for additional notification channels
- Slack integration
- Email notifications
- SMS alerts
- Webhook endpoints

This architecture provides a solid foundation for enterprise error monitoring while maintaining flexibility for future enhancements and integrations.
