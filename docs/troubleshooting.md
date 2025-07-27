# Troubleshooting Guide

This guide provides solutions for common issues and problems you might encounter when using Watchdog Discord.

## Quick Diagnosis

### Health Check Command

First, verify your configuration with the built-in test command:

```bash
# Test Discord connectivity
php artisan watchdog-discord:test --exception

# Test specific log level
php artisan watchdog-discord:test --level=error --message="Troubleshooting test"
```

### Configuration Verification

```bash
# Check configuration values
php artisan tinker
>>> config('watchdog-discord.enabled')
>>> config('watchdog-discord.webhook_url')
>>> config('watchdog-discord.queue.enabled')
```

## Common Issues

### 1. Notifications Not Sending

#### Symptoms
- No Discord messages appearing
- Test command fails silently
- No error logs

#### Diagnosis Steps

1. **Check if package is enabled**:
   ```bash
   php artisan tinker
   >>> config('watchdog-discord.enabled')
   ```

2. **Verify webhook URL**:
   ```bash
   >>> config('watchdog-discord.webhook_url')
   ```

3. **Test webhook directly**:
   ```bash
   curl -X POST "YOUR_WEBHOOK_URL" \
     -H "Content-Type: application/json" \
     -d '{"content": "Test message"}'
   ```

#### Solutions

**Problem**: Package not enabled
```env
WATCHDOG_DISCORD_ENABLED=true
```

**Problem**: Missing webhook URL
```env
WATCHDOG_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/YOUR/WEBHOOK/URL
```

**Problem**: Invalid webhook URL format
- Ensure URL starts with `https://discord.com/api/webhooks/`
- Check for extra spaces or characters
- Verify webhook still exists in Discord

**Problem**: Environment filtering
```env
# Remove environment filtering or add current environment
WATCHDOG_DISCORD_ENVIRONMENTS=production,staging,local
```

### 2. Queue Jobs Not Processing

#### Symptoms
- Queue jobs accumulating
- Async notifications not working
- `php artisan queue:work` shows no activity

#### Diagnosis Steps

1. **Check queue configuration**:
   ```bash
   php artisan queue:monitor redis:default
   php artisan queue:failed
   ```

2. **Verify queue connection**:
   ```bash
   php artisan tinker
   >>> Queue::connection('redis')->size('default')
   ```

3. **Test queue manually**:
   ```bash
   php artisan queue:work --once
   ```

#### Solutions

**Problem**: Queue workers not running
```bash
# Start queue worker
php artisan queue:work redis --queue=default

# Or use Supervisor for production
sudo supervisorctl start laravel-worker:*
```

**Problem**: Wrong queue connection
```env
WATCHDOG_DISCORD_QUEUE_CONNECTION=redis
WATCHDOG_DISCORD_QUEUE_NAME=default
```

**Problem**: Failed jobs
```bash
# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

**Problem**: Redis connection issues
```bash
# Test Redis connectivity
redis-cli ping

# Check Laravel Redis config
php artisan tinker
>>> Redis::ping()
```

### 3. Database Migration Issues

#### Symptoms
- Migration fails to run
- Table not created
- Foreign key errors

#### Diagnosis Steps

1. **Check migration status**:
   ```bash
   php artisan migrate:status
   ```

2. **Test database connection**:
   ```bash
   php artisan tinker
   >>> DB::connection()->getPdo()
   ```

#### Solutions

**Problem**: Migration not found
```bash
# Re-publish and run migrations
php artisan vendor:publish --provider="VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider"
php artisan migrate
```

**Problem**: Database permission issues
```sql
-- Grant necessary permissions
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

**Problem**: Table already exists
```bash
# Force migration
php artisan migrate --force

# Or rollback and re-run
php artisan migrate:rollback
php artisan migrate
```

### 4. Redis Connection Problems

#### Symptoms
- "Connection refused" errors
- Performance degradation
- Cache-related failures

#### Diagnosis Steps

1. **Test Redis connectivity**:
   ```bash
   redis-cli ping
   redis-cli info
   ```

2. **Check Laravel Redis configuration**:
   ```php
   // config/database.php
   'redis' => [
       'client' => env('REDIS_CLIENT', 'phpredis'),
       'default' => [
           'host' => env('REDIS_HOST', '127.0.0.1'),
           'port' => env('REDIS_PORT', '6379'),
           // ...
       ],
   ]
   ```

#### Solutions

**Problem**: Redis not installed
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install redis-server

# Start Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

**Problem**: Connection configuration
```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
```

**Problem**: Redis extension missing
```bash
# Install phpredis extension
sudo apt install php-redis
# or
sudo pecl install redis
```

### 5. Performance Issues

#### Symptoms
- Application slowdown
- High memory usage
- Timeout errors

#### Diagnosis Steps

1. **Check processing mode**:
   ```bash
   php artisan tinker
   >>> config('watchdog-discord.queue.enabled')
   >>> config('watchdog-discord.performance.async_enabled')
   ```

2. **Monitor queue depth**:
   ```bash
   php artisan queue:monitor redis:default
   ```

3. **Check memory usage**:
   ```bash
   php artisan tinker
   >>> memory_get_usage(true) / 1024 / 1024 . ' MB'
   ```

#### Solutions

**Problem**: Synchronous processing in production
```env
# Enable async processing
WATCHDOG_DISCORD_ASYNC_ENABLED=true
WATCHDOG_DISCORD_QUEUE_ENABLED=true
WATCHDOG_DISCORD_QUEUE_CONNECTION=redis
```

**Problem**: Queue worker overload
```bash
# Scale queue workers
supervisorctl scale laravel-worker:4

# Or optimize worker configuration
php artisan queue:work redis --sleep=1 --tries=3 --timeout=60
```

**Problem**: Database performance
```sql
-- Add missing indexes
CREATE INDEX idx_error_hash ON watchdog_error_tracking(error_hash);
CREATE INDEX idx_environment_level ON watchdog_error_tracking(environment, level);
```

### 6. Discord API Rate Limiting

#### Symptoms
- HTTP 429 errors
- Notifications failing intermittently
- Rate limit exceeded messages

#### Diagnosis Steps

1. **Check Discord API responses**:
   ```bash
   # Enable debug logging
   php artisan tinker
   >>> config(['app.debug' => true])
   ```

2. **Monitor notification frequency**:
   ```bash
   # Check recent logs
   tail -f storage/logs/laravel.log | grep discord
   ```

#### Solutions

**Problem**: Too many notifications
```env
# Enable rate limiting
WATCHDOG_DISCORD_RATE_LIMIT_ENABLED=true
WATCHDOG_DISCORD_RATE_LIMIT_MAX=10
WATCHDOG_DISCORD_RATE_LIMIT_WINDOW=5
```

**Problem**: Error spam
```php
// config/watchdog-discord.php
'ignore_exceptions' => [
    \Illuminate\Validation\ValidationException::class,
    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    // Add more exceptions to ignore
],
```

**Problem**: High-frequency errors
```env
# Increase severity threshold
WATCHDOG_DISCORD_MIN_SEVERITY=8
WATCHDOG_DISCORD_FREQUENCY_THRESHOLD=50
```

### 7. Memory Leaks

#### Symptoms
- Memory usage constantly increasing
- Out of memory errors
- Performance degradation over time

#### Diagnosis Steps

1. **Profile memory usage**:
   ```php
   // Add to a test route
   Route::get('/memory-test', function () {
       $baseline = memory_get_usage(true);
       
       for ($i = 0; $i < 100; $i++) {
           $exception = new \Exception("Test {$i}");
           app(\VinkiusLabs\WatchdogDiscord\DiscordNotifier::class)->send($exception);
       }
       
       return [
           'baseline' => $baseline / 1024 / 1024,
           'current' => memory_get_usage(true) / 1024 / 1024,
           'peak' => memory_get_peak_usage(true) / 1024 / 1024,
       ];
   });
   ```

#### Solutions

**Problem**: Large stack traces
```env
WATCHDOG_DISCORD_INCLUDE_STACK_TRACE=false
WATCHDOG_DISCORD_MAX_STACK_TRACE_LINES=5
```

**Problem**: Excessive context data
```env
WATCHDOG_DISCORD_INCLUDE_REQUEST_DATA=false
WATCHDOG_DISCORD_MAX_FIELD_LENGTH=512
```

**Problem**: Object retention
```bash
# Restart queue workers periodically
php artisan queue:restart
```

## Debugging Tools

### 1. Debug Mode

Enable debug logging for troubleshooting:

```env
APP_DEBUG=true
WATCHDOG_DISCORD_DEBUG=true
```

### 2. Custom Debug Route

Create a debug route for testing:

```php
// routes/web.php (only for debugging)
Route::get('/debug/watchdog', function () {
    try {
        $notifier = app(\VinkiusLabs\WatchdogDiscord\DiscordNotifier::class);
        
        // Test configuration
        $config = [
            'enabled' => config('watchdog-discord.enabled'),
            'webhook_url' => config('watchdog-discord.webhook_url') ? 'SET' : 'NOT SET',
            'queue_enabled' => config('watchdog-discord.queue.enabled'),
            'redis_available' => class_exists('Redis'),
        ];
        
        // Test notification
        $testException = new \Exception('Debug test exception');
        $notifier->send($testException);
        
        return response()->json([
            'status' => 'success',
            'config' => $config,
            'message' => 'Test notification sent'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
```

### 3. Log Analysis

```bash
# Monitor logs in real-time
tail -f storage/logs/laravel.log | grep -i watchdog

# Search for specific errors
grep -r "watchdog" storage/logs/

# Check for Discord-related errors
grep -r "discord" storage/logs/ | tail -20
```

### 4. Database Debugging

```php
// Add to AppServiceProvider::boot()
use Illuminate\Support\Facades\DB;

if (app()->environment('local')) {
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'watchdog')) {
            logger()->info('Watchdog Query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time
            ]);
        }
    });
}
```

## Environment-Specific Issues

### Development Environment

**Common Issues**:
- Webhook URL not configured
- Redis not installed
- Queue workers not running

**Quick Fix**:
```env
# Minimal development configuration
WATCHDOG_DISCORD_ENABLED=true
WATCHDOG_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/your/webhook
WATCHDOG_DISCORD_ASYNC_ENABLED=false
WATCHDOG_DISCORD_QUEUE_CONNECTION=sync
```

### Testing Environment

**Common Issues**:
- Notifications sent during tests
- Database conflicts
- Queue interference

**Solutions**:
```php
// tests/TestCase.php
protected function setUp(): void
{
    parent::setUp();
    
    // Disable notifications during tests
    config(['watchdog-discord.enabled' => false]);
    
    // Use fake queue
    Queue::fake();
}
```

### Production Environment

**Common Issues**:
- Supervisor not starting workers
- Permission problems
- Environment variables not set

**Checklist**:
```bash
# Verify environment variables
env | grep WATCHDOG

# Check supervisor status
sudo supervisorctl status

# Verify file permissions
ls -la storage/logs/
ls -la bootstrap/cache/

# Test queue connectivity
php artisan queue:monitor redis:default
```

## Getting Help

### Collect Debug Information

Before seeking help, collect this information:

```bash
# System information
php --version
php -m | grep redis
composer show vinkius-labs/watchdog-discord

# Laravel information
php artisan --version
php artisan config:show watchdog-discord

# Queue information
php artisan queue:monitor redis:default
php artisan queue:failed

# Log information
tail -50 storage/logs/laravel.log
```

### Useful Commands

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan queue:clear

# Restart services
php artisan queue:restart
sudo supervisorctl restart laravel-worker:*

# Re-publish configuration
php artisan vendor:publish --provider="VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider" --force
```

### Support Channels

1. **GitHub Issues**: [Report bugs and issues](https://github.com/vinkius-labs/watchdog-discord/issues)
2. **Documentation**: [Complete documentation](../README.md)
3. **Community Discord**: [Join our Discord server](https://discord.gg/vinkiuslabs)
4. **Email Support**: labs@vinkius.com

### Creating a Bug Report

Include this information:

1. **Environment details**:
   - PHP version
   - Laravel version
   - Package version
   - Operating system

2. **Configuration**:
   - Relevant config values (redact sensitive data)
   - Environment variables

3. **Error details**:
   - Complete error messages
   - Stack traces
   - Log entries

4. **Steps to reproduce**:
   - Minimal code example
   - Expected vs actual behavior

5. **Additional context**:
   - Recent changes
   - Related packages
   - Server configuration

This troubleshooting guide should help you resolve most common issues with Watchdog Discord. If you encounter problems not covered here, please refer to the support channels above.
