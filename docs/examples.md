# Examples and Usage Patterns

This document provides practical examples of how to use Watchdog Discord in different scenarios.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Advanced Configuration](#advanced-configuration)
- [Custom Error Handling](#custom-error-handling)
- [Performance Monitoring](#performance-monitoring)
- [Testing and Debugging](#testing-and-debugging)
- [Production Patterns](#production-patterns)

## Basic Usage

### Quick Setup (5 minutes)

```bash
# 1. Install package
composer require vinkiuslabs/watchdog-discord

# 2. Publish configuration
php artisan vendor:publish --provider="VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider"

# 3. Set up your webhook URL
echo "WATCHDOG_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/YOUR_ID/YOUR_TOKEN" >> .env

# 4. Test the integration
php artisan watchdog-discord:test
```

### Sending Manual Notifications

```php
use VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord;

// Simple error notification
WatchdogDiscord::error('Payment processing failed', [
    'user_id' => $user->id,
    'amount' => $payment->amount,
    'error_code' => 'PAYMENT_GATEWAY_ERROR'
]);

// Warning with context
WatchdogDiscord::warning('High memory usage detected', [
    'memory_usage' => memory_get_usage(true),
    'peak_memory' => memory_get_peak_usage(true),
    'server' => gethostname()
]);

// Info notification
WatchdogDiscord::info('New user registration', [
    'user_email' => $user->email,
    'registration_source' => 'mobile_app'
]);
```

### Log Levels Usage Examples

The package supports all standard PSR-3 log levels. Here are practical examples for each level:

```php
use VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord;
use Illuminate\Support\Facades\Log;

// Emergency (severity: 10) - System unusable
WatchdogDiscord::emergency('Database server down', [
    'server' => 'db-primary',
    'last_successful_connection' => now()->subMinutes(5),
    'affected_services' => ['api', 'website', 'admin']
]);

// Alert (severity: 9) - Immediate action required  
WatchdogDiscord::alert('Website unreachable', [
    'url' => 'https://example.com',
    'status_code' => null,
    'timeout' => 30,
    'monitoring_source' => 'pingdom'
]);

// Critical (severity: 8) - Critical conditions
WatchdogDiscord::critical('Payment gateway failure', [
    'gateway' => 'stripe',
    'error' => 'Connection timeout',
    'failed_transactions' => 15,
    'time_window' => '5 minutes'
]);

// Error (severity: 6) - Error conditions
WatchdogDiscord::error('User authentication failed', [
    'user_email' => 'user@example.com',
    'ip_address' => request()->ip(),
    'attempt_count' => 3,
    'locked_until' => now()->addMinutes(30)
]);

// Warning (severity: 4) - Warning conditions
WatchdogDiscord::warning('High memory usage', [
    'current_usage' => '85%',
    'threshold' => '80%',
    'server' => gethostname(),
    'process_count' => 45
]);

// Notice (severity: 2) - Significant events
WatchdogDiscord::notice('Admin user created', [
    'admin_email' => 'admin@example.com',
    'created_by' => auth()->user()->email,
    'permissions' => ['users', 'settings', 'reports']
]);

// Info (severity: 1) - General information
WatchdogDiscord::info('Scheduled backup completed', [
    'backup_size' => '2.5GB',
    'duration' => '45 minutes',
    'files_count' => 15420,
    'destination' => 's3://backups/daily/'
]);

// Debug (severity: 1) - Debug information
WatchdogDiscord::debug('Cache warming started', [
    'cache_store' => 'redis',
    'keys_to_warm' => 150,
    'estimated_time' => '2 minutes'
]);
```

### Severity-Based Configuration

Configure different log levels for different environments:

```php
// .env.production
WATCHDOG_DISCORD_LOG_LEVELS=emergency,alert,critical,error
WATCHDOG_DISCORD_MIN_SEVERITY=7

// .env.staging  
WATCHDOG_DISCORD_LOG_LEVELS=emergency,alert,critical,error,warning
WATCHDOG_DISCORD_MIN_SEVERITY=4

// .env.local
WATCHDOG_DISCORD_LOG_LEVELS=emergency,alert,critical,error,warning,notice,info,debug
WATCHDOG_DISCORD_MIN_SEVERITY=1
```

### Smart Severity Calculation Examples

The system automatically calculates severity scores based on multiple factors:

```php
// Example 1: Simple info log (severity = 1)
Log::info('User logged in', ['user_id' => 123]);
// Result: Base score (1) + No exception (0) + First occurrence (0) = 1

// Example 2: Error with high frequency (severity = 9)  
Log::error('API rate limit exceeded');
// After 50+ occurrences: Base score (6) + No exception (0) + High frequency (2) = 8
// After 100+ occurrences: Base score (6) + No exception (0) + Very high frequency (3) = 9

// Example 3: Critical error with runtime exception (severity = 10)
try {
    $result = $api->criticalOperation();
} catch (RuntimeException $e) {
    Log::critical('Critical operation failed', ['exception' => $e]);
}
// Result: Base score (8) + RuntimeException (2) + First occurrence (0) = 10
```

### Integration with Laravel's Log Facade

You can also use Laravel's standard Log facade, and the package will automatically track and analyze:

```php
use Illuminate\Support\Facades\Log;

// These will be automatically tracked if log level is configured
Log::emergency('System failure detected');
Log::alert('Immediate attention required');  
Log::critical('Critical system error');
Log::error('Application error occurred');
Log::warning('Performance degradation detected');
Log::notice('Significant event occurred');
Log::info('General information logged');
Log::debug('Debug information captured');
```

## Advanced Configuration

### Environment-Specific Settings

```php
// config/watchdog-discord.php

return [
    'enabled' => env('WATCHDOG_DISCORD_ENABLED', app()->environment(['production', 'staging'])),
    
    'environments' => [
        'production' => [
            'min_severity' => 8,
            'rate_limit_max' => 10,
            'include_stack_trace' => false,
        ],
        'staging' => [
            'min_severity' => 6,
            'rate_limit_max' => 20,
            'include_stack_trace' => true,
        ],
        'local' => [
            'min_severity' => 1,
            'rate_limit_enabled' => false,
            'async_enabled' => false,
        ]
    ]
];
```

### Multiple Webhook Configuration

```php
// config/watchdog-discord.php

return [
    'webhooks' => [
        'errors' => env('DISCORD_WEBHOOK_ERRORS'),
        'payments' => env('DISCORD_WEBHOOK_PAYMENTS'),
        'security' => env('DISCORD_WEBHOOK_SECURITY'),
    ],
    
    'routing' => [
        'payment' => 'payments',
        'authentication' => 'security',
        'authorization' => 'security',
        'default' => 'errors',
    ]
];
```

## Custom Error Handling

### Creating Custom Error Handlers

```php
use VinkiusLabs\WatchdogDiscord\Events\ErrorNotificationSent;

class PaymentErrorHandler
{
    public function handle($exception, $context = [])
    {
        if ($this->isPaymentRelated($exception)) {
            WatchdogDiscord::error('Payment System Error', [
                'error_type' => 'payment_processing',
                'gateway' => $context['gateway'] ?? 'unknown',
                'transaction_id' => $context['transaction_id'] ?? null,
                'severity' => $this->calculateSeverity($exception),
                'impact' => $this->assessImpact($exception)
            ]);
        }
    }
    
    private function isPaymentRelated($exception): bool
    {
        return str_contains($exception->getMessage(), 'payment') ||
               str_contains($exception->getFile(), 'Payment');
    }
    
    private function calculateSeverity($exception): int
    {
        // Business logic to calculate severity 1-10
        if (str_contains($exception->getMessage(), 'timeout')) {
            return 6; // Medium severity
        }
        
        if (str_contains($exception->getMessage(), 'declined')) {
            return 4; // Lower severity - common occurrence
        }
        
        return 8; // High severity for unknown payment errors
    }
}
```

### Event Listeners

```php
// EventServiceProvider.php

protected $listen = [
    ErrorNotificationSent::class => [
        UpdateErrorMetrics::class,
        LogErrorToAnalytics::class,
    ],
    
    LogNotificationSent::class => [
        UpdateLogMetrics::class,
    ],
];

// UpdateErrorMetrics.php
class UpdateErrorMetrics
{
    public function handle(ErrorNotificationSent $event)
    {
        // Update your monitoring dashboard
        Metrics::increment('discord_notifications.sent', [
            'severity' => $event->severity,
            'type' => $event->type,
            'environment' => app()->environment()
        ]);
    }
}
```

## Performance Monitoring

### Application Performance Impact

```php
// Monitor the impact of error tracking on your application

class PerformanceMonitoringMiddleware
{
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        
        $response = $next($request);
        
        $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds
        
        if ($duration > 1000) { // If request took longer than 1 second
            WatchdogDiscord::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'duration_ms' => round($duration, 2),
                'memory_peak' => memory_get_peak_usage(true),
                'user_id' => auth()->id()
            ]);
        }
        
        return $response;
    }
}
```

### Database Query Monitoring

```php
// In AppServiceProvider.php boot() method

if (app()->environment(['production', 'staging'])) {
    DB::listen(function ($query) {
        if ($query->time > 1000) { // Queries longer than 1 second
            WatchdogDiscord::warning('Slow database query detected', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
                'connection' => $query->connectionName
            ]);
        }
    });
}
```

## Testing and Debugging

### Unit Testing with Watchdog Discord

```php
use VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord;
use Illuminate\Support\Facades\Queue;

class WatchdogDiscordTest extends TestCase
{
    public function test_error_notification_is_queued()
    {
        Queue::fake();
        
        WatchdogDiscord::error('Test error', ['test' => true]);
        
        Queue::assertPushed(SendDiscordErrorNotification::class, function ($job) {
            return $job->message === 'Test error' && 
                   $job->context['test'] === true;
        });
    }
    
    public function test_rate_limiting_prevents_spam()
    {
        config(['watchdog-discord.rate_limit_enabled' => true]);
        config(['watchdog-discord.rate_limit_max' => 2]);
        
        WatchdogDiscord::error('Error 1');
        WatchdogDiscord::error('Error 2');
        WatchdogDiscord::error('Error 3'); // Should be rate limited
        
        Queue::assertPushed(SendDiscordErrorNotification::class, 2);
    }
}
```

### Local Development Testing

```php
// Create a test command for development
php artisan make:command TestWatchdogIntegration

class TestWatchdogIntegration extends Command
{
    protected $signature = 'test:watchdog {--type=error}';
    
    public function handle()
    {
        $type = $this->option('type');
        
        switch ($type) {
            case 'error':
                throw new \Exception('Test exception for Discord integration');
                
            case 'warning':
                WatchdogDiscord::warning('Test warning message', [
                    'timestamp' => now(),
                    'environment' => app()->environment()
                ]);
                break;
                
            case 'info':
                WatchdogDiscord::info('Test info message', [
                    'user' => 'developer',
                    'action' => 'integration_test'
                ]);
                break;
        }
        
        $this->info("Test {$type} notification sent!");
    }
}
```

## Production Patterns

### Error Categorization and Routing

```php
class ErrorCategorizer
{
    private const CRITICAL_PATTERNS = [
        'payment',
        'authentication',
        'database connection',
        'out of memory'
    ];
    
    private const WARNING_PATTERNS = [
        'deprecated',
        'slow query',
        'rate limit'
    ];
    
    public static function categorize(\Throwable $exception): array
    {
        $message = strtolower($exception->getMessage());
        
        foreach (self::CRITICAL_PATTERNS as $pattern) {
            if (str_contains($message, $pattern)) {
                return [
                    'severity' => 9,
                    'category' => 'critical',
                    'priority' => 'high'
                ];
            }
        }
        
        foreach (self::WARNING_PATTERNS as $pattern) {
            if (str_contains($message, $pattern)) {
                return [
                    'severity' => 5,
                    'category' => 'warning',
                    'priority' => 'medium'
                ];
            }
        }
        
        return [
            'severity' => 7,
            'category' => 'error',
            'priority' => 'high'
        ];
    }
}
```

### Health Check Integration

```php
// Create a health check endpoint
Route::get('/health/discord-integration', function () {
    try {
        // Test Redis connection
        Cache::store('redis')->put('watchdog_health_check', true, 60);
        
        // Test queue connection
        $queueStatus = Queue::size('watchdog_notifications') !== false;
        
        // Test Discord webhook (optional - be careful not to spam)
        // WatchdogDiscord::info('Health check', ['timestamp' => now()]);
        
        return response()->json([
            'status' => 'healthy',
            'redis' => true,
            'queue' => $queueStatus,
            'timestamp' => now()
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
            'timestamp' => now()
        ], 500);
    }
});
```

### Graceful Degradation

```php
// In your exception handler

class Handler extends ExceptionHandler
{
    public function report(Throwable $exception)
    {
        parent::report($exception);
        
        try {
            // Attempt to send Discord notification
            WatchdogDiscord::error(
                $exception->getMessage(),
                $this->buildContext($exception)
            );
        } catch (\Exception $e) {
            // If Discord notification fails, log it but don't break the application
            \Log::error('Failed to send Discord notification', [
                'original_exception' => $exception->getMessage(),
                'notification_error' => $e->getMessage()
            ]);
        }
    }
    
    private function buildContext(Throwable $exception): array
    {
        return [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace_preview' => array_slice($exception->getTrace(), 0, 3),
            'url' => request()->fullUrl(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];
    }
}
```

### Monitoring and Alerting

```bash
# Set up monitoring commands in your deployment pipeline

# Check queue health
php artisan queue:monitor redis:watchdog_notifications

# Test integration after deployment
php artisan watchdog-discord:test --quiet

# Monitor error rates (custom command)
php artisan watchdog:stats --hours=1
```

These examples demonstrate real-world usage patterns that will help you get the most out of Watchdog Discord in your production applications.
