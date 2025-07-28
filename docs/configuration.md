# Configuration Reference

Complete reference for all Watchdog Discord configuration options with detailed explanations and examples.

## Configuration File Structure

The main configuration file is located at `config/watchdog-discord.php`. Here's the complete structure:

```php
return [
    'enabled' => env('WATCHDOG_DISCORD_ENABLED', false),
    'webhook_url' => env('WATCHDOG_DISCORD_WEBHOOK_URL', ''),
    'queue' => [...],
    'timeout' => env('WATCHDOG_DISCORD_TIMEOUT', 30),
    'message' => [...],
    'mentions' => [...],
    'report_errors' => [...],
    'environments' => [...],
    'rate_limiting' => [...],
    'log_requests' => [...],
    'ignore_exceptions' => [...],
    'formatting' => [...],
    'error_tracking' => [...],
    'database' => [...],
    'performance' => [...],
    'cache' => [...],
];
```

## Core Configuration

### Enable/Disable Package

```php
'enabled' => env('WATCHDOG_DISCORD_ENABLED', false),
```

**Environment Variable**: `WATCHDOG_DISCORD_ENABLED`  
**Type**: `boolean`  
**Default**: `false`  
**Description**: Master switch to enable or disable all Discord notifications.

**Examples**:
```env
# Enable in production
WATCHDOG_DISCORD_ENABLED=true

# Disable for maintenance
WATCHDOG_DISCORD_ENABLED=false
```

### Discord Webhook URL

```php
'webhook_url' => env('WATCHDOG_DISCORD_WEBHOOK_URL', ''),
```

**Environment Variable**: `WATCHDOG_DISCORD_WEBHOOK_URL`  
**Type**: `string`  
**Required**: Yes (when enabled)  
**Description**: Discord webhook URL where notifications will be sent.

**Examples**:
```env
WATCHDOG_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/123456789/AbCdEfGhIjKlMnOpQrStUvWxYz
```

### HTTP Timeout

```php
'timeout' => env('WATCHDOG_DISCORD_TIMEOUT', 30),
```

**Environment Variable**: `WATCHDOG_DISCORD_TIMEOUT`  
**Type**: `integer`  
**Default**: `30`  
**Unit**: seconds  
**Description**: HTTP request timeout for Discord API calls.

## Queue Configuration

```php
'queue' => [
    'enabled' => env('WATCHDOG_DISCORD_QUEUE_ENABLED', false),
    'connection' => env('WATCHDOG_DISCORD_QUEUE_CONNECTION', 'default'),
    'name' => env('WATCHDOG_DISCORD_QUEUE_NAME', 'default'),
    'delay' => env('WATCHDOG_DISCORD_QUEUE_DELAY', 0),
],
```

### Queue Enabled

**Environment Variable**: `WATCHDOG_DISCORD_QUEUE_ENABLED`  
**Type**: `boolean`  
**Default**: `false`  
**Description**: Enable asynchronous notification processing via Laravel queues.

**Production Recommendation**: `true`

### Queue Connection

**Environment Variable**: `WATCHDOG_DISCORD_QUEUE_CONNECTION`  
**Type**: `string`  
**Default**: `default`  
**Description**: Laravel queue connection to use for processing notifications.

**Recommended Values**:
- `redis` - Best performance
- `database` - Reliable fallback
- `sync` - Development only

### Queue Name

**Environment Variable**: `WATCHDOG_DISCORD_QUEUE_NAME`  
**Type**: `string`  
**Default**: `default`  
**Description**: Specific queue name for Watchdog notifications.

**Examples**:
```env
WATCHDOG_DISCORD_QUEUE_NAME=notifications
WATCHDOG_DISCORD_QUEUE_NAME=watchdog_errors
WATCHDOG_DISCORD_QUEUE_NAME=monitoring
```

## Message Configuration

```php
'message' => [
    'username' => env('WATCHDOG_DISCORD_USERNAME', 'Laravel Watchdog'),
    'avatar_url' => env('WATCHDOG_DISCORD_AVATAR_URL'),
],
```

### Bot Username

**Environment Variable**: `WATCHDOG_DISCORD_USERNAME`  
**Type**: `string`  
**Default**: `Laravel Watchdog`  
**Description**: Display name for the webhook bot in Discord.

### Avatar URL

**Environment Variable**: `WATCHDOG_DISCORD_AVATAR_URL`  
**Type**: `string|null`  
**Default**: `null`  
**Description**: URL to an image for the webhook bot avatar.

**Examples**:
```env
WATCHDOG_DISCORD_AVATAR_URL=https://example.com/bot-avatar.png
WATCHDOG_DISCORD_AVATAR_URL=https://cdn.discord.com/avatars/bot.png
```

## Mention Configuration

```php
'mentions' => [
    'users' => array_filter(explode(',', env('WATCHDOG_DISCORD_MENTION_USERS', '')), 'strlen'),
    'roles' => array_filter(explode(',', env('WATCHDOG_DISCORD_MENTION_ROLES', '')), 'strlen'),
],
```

### User Mentions

**Environment Variable**: `WATCHDOG_DISCORD_MENTION_USERS`  
**Type**: `string` (comma-separated Discord user IDs)  
**Default**: empty  
**Description**: Discord user IDs to mention when errors occur.

### Role Mentions

**Environment Variable**: `WATCHDOG_DISCORD_MENTION_ROLES`  
**Type**: `string` (comma-separated Discord role IDs)  
**Default**: empty  
**Description**: Discord role IDs to mention when errors occur.

**Examples**:
```env
# Mention specific users
WATCHDOG_DISCORD_MENTION_USERS=123456789012345678,987654321098765432

# Mention a role
WATCHDOG_DISCORD_MENTION_ROLES=123456789012345679

# Mention both users and roles
WATCHDOG_DISCORD_MENTION_USERS=123456789012345678
WATCHDOG_DISCORD_MENTION_ROLES=123456789012345679,987654321098765432
```

## Error Reporting Configuration

```php
'report_errors' => [
    'fatal' => env('WATCHDOG_DISCORD_REPORT_FATAL', true),
    'warning' => env('WATCHDOG_DISCORD_REPORT_WARNING', false),
    'notice' => env('WATCHDOG_DISCORD_REPORT_NOTICE', false),
],
```

Configure which error types should trigger notifications:

- **Fatal Errors**: Always recommended `true`
- **Warnings**: Usually `false` for production
- **Notices**: Usually `false` for production

## Log Levels Configuration

```php
'log_levels' => explode(',', env('WATCHDOG_DISCORD_LOG_LEVELS', 'error,critical,emergency')),
```

**Environment Variable**: `WATCHDOG_DISCORD_LOG_LEVELS`  
**Type**: `string` (comma-separated log levels)  
**Default**: `error,critical,emergency`  
**Description**: Specify which log levels should be sent to Discord when using `Log::info()`, `Log::error()`, etc.

### Available Log Levels

The following log levels are supported (ordered from highest to lowest severity):

| Level | Severity Score | Description | Example Use Case |
|-------|----------------|-------------|------------------|
| `emergency` | 10 | System is unusable | Database completely down |
| `alert` | 9 | Action must be taken immediately | Website unreachable |
| `critical` | 8 | Critical conditions | Application crashes |
| `error` | 6 | Error conditions | Exception thrown |
| `warning` | 4 | Warning conditions | Deprecated API usage |
| `notice` | 2 | Normal but significant | User login events |
| `info` | 1 | Informational messages | General information |
| `debug` | 1 | Debug-level messages | Development debugging |

### Configuration Examples

```env
# Only critical issues (recommended for production)
WATCHDOG_DISCORD_LOG_LEVELS=emergency,alert,critical,error

# Include warnings for staging environment
WATCHDOG_DISCORD_LOG_LEVELS=emergency,alert,critical,error,warning

# All levels for development/debugging
WATCHDOG_DISCORD_LOG_LEVELS=emergency,alert,critical,error,warning,notice,info,debug

# Only info and above (useful for tracking specific events)
WATCHDOG_DISCORD_LOG_LEVELS=emergency,alert,critical,error,warning,notice,info
```

### Severity Score Calculation

The system automatically calculates a severity score (1-10) for each log entry using an intelligent algorithm:

#### Base Score (by Log Level)
- `emergency` = 10
- `alert` = 9  
- `critical` = 8
- `error` = 6
- `warning` = 4
- `notice` = 2
- `info` = 1
- `debug` = 1

#### Exception Type Bonus (+0 to +3)
- `Error` and `ParseError` = +3
- `TypeError` and `RuntimeException` = +2
- Other exceptions = +0

#### Frequency Bonus (+0 to +3)
- ≥100 occurrences = +3
- ≥50 occurrences = +2
- ≥10 occurrences = +1
- <10 occurrences = +0

#### Final Calculation
```
Final Score = min(10, baseScore + exceptionBonus + frequencyBonus)
```

#### Examples
```php
// Critical error that occurred 150 times
// critical (8) + RuntimeException (2) + frequency ≥100 (3) = 10

// Info log first time
// info (1) + no exception (0) + frequency <10 (0) = 1

// Warning with type error that happened 15 times  
// warning (4) + TypeError (2) + frequency ≥10 (1) = 7
```

### Notification Rules

Logs will be sent to Discord if they meet **any** of these criteria:

1. **High Severity**: `severity_score >= min_severity` (default: 7)
2. **High Frequency**: `occurrence_count >= frequency_threshold` (default: 10)
3. **Trending**: `hourly_count >= hourly_threshold` (default: 5)

Configure these thresholds in the Error Tracking section.

## Environment Filtering

```php
'environments' => array_filter(explode(',', env('WATCHDOG_DISCORD_ENVIRONMENTS', '')), 'strlen'),
```

**Environment Variable**: `WATCHDOG_DISCORD_ENVIRONMENTS`  
**Type**: `string` (comma-separated environment names)  
**Default**: empty (all environments)  
**Description**: Limit notifications to specific environments.

**Examples**:
```env
# Production only
WATCHDOG_DISCORD_ENVIRONMENTS=production

# Production and staging
WATCHDOG_DISCORD_ENVIRONMENTS=production,staging

# All environments except local
WATCHDOG_DISCORD_ENVIRONMENTS=production,staging,testing
```

## Rate Limiting

```php
'rate_limiting' => [
    'enabled' => env('WATCHDOG_DISCORD_RATE_LIMIT_ENABLED', true),
    'max_notifications' => env('WATCHDOG_DISCORD_RATE_LIMIT_MAX', 10),
    'time_window_minutes' => env('WATCHDOG_DISCORD_RATE_LIMIT_WINDOW', 5),
    'cache_key_prefix' => 'watchdog_discord_rate_limit',
],
```

### Rate Limit Enabled

**Environment Variable**: `WATCHDOG_DISCORD_RATE_LIMIT_ENABLED`  
**Type**: `boolean`  
**Default**: `true`  
**Description**: Enable rate limiting to prevent notification spam.

### Max Notifications

**Environment Variable**: `WATCHDOG_DISCORD_RATE_LIMIT_MAX`  
**Type**: `integer`  
**Default**: `10`  
**Description**: Maximum number of notifications allowed per time window.

### Time Window

**Environment Variable**: `WATCHDOG_DISCORD_RATE_LIMIT_WINDOW`  
**Type**: `integer`  
**Default**: `5`  
**Unit**: minutes  
**Description**: Time window for rate limiting.

**Calculation**: Max 10 notifications per 5 minutes = 2 notifications per minute average.

## Request Logging

```php
'log_requests' => [
    'enabled' => env('WATCHDOG_DISCORD_LOG_REQUESTS', false),
    'status_codes' => [500, 502, 503, 504],
    'min_duration_ms' => env('WATCHDOG_DISCORD_MIN_DURATION', 1000),
    'exclude_routes' => ['debugbar.*', 'horizon.*', 'telescope.*'],
    'exclude_paths' => ['_debugbar/*', 'horizon/*', 'telescope/*', 'favicon.ico', '*.css', '*.js', '*.png', '*.jpg', '*.jpeg', '*.gif', '*.svg'],
],
```

### Request Logging Enabled

**Environment Variable**: `WATCHDOG_DISCORD_LOG_REQUESTS`  
**Type**: `boolean`  
**Default**: `false`  
**Description**: Enable automatic logging of HTTP requests via middleware.

### Status Codes

**Type**: `array`  
**Default**: `[500, 502, 503, 504]`  
**Description**: HTTP status codes that should trigger notifications.

### Minimum Duration

**Environment Variable**: `WATCHDOG_DISCORD_MIN_DURATION`  
**Type**: `integer`  
**Default**: `1000`  
**Unit**: milliseconds  
**Description**: Only log requests that take longer than this duration.

## Exception Filtering

```php
'ignore_exceptions' => [
    \Illuminate\Auth\AuthenticationException::class,
    \Illuminate\Auth\Access\AuthorizationException::class,
    \Illuminate\Database\Eloquent\ModelNotFoundException::class,
    \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
    \Illuminate\Session\TokenMismatchException::class,
    \Illuminate\Validation\ValidationException::class,
    \Symfony\Component\HttpKernel\Exception\HttpException::class,
    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
],
```

**Type**: `array`  
**Description**: List of exception classes that should not trigger notifications.

**Common Exceptions to Ignore**:
- Authentication failures
- Validation errors
- 404 Not Found errors
- Authorization failures
- CSRF token mismatches

## Message Formatting

```php
'formatting' => [
    'max_stack_trace_lines' => env('WATCHDOG_DISCORD_MAX_STACK_TRACE_LINES', 15),
    'include_request_data' => env('WATCHDOG_DISCORD_INCLUDE_REQUEST_DATA', true),
    'max_field_length' => env('WATCHDOG_DISCORD_MAX_FIELD_LENGTH', 1024),
],
```

**Note**: Stack trace is always included in notifications for better debugging capabilities.

### Max Stack Trace Lines

**Environment Variable**: `WATCHDOG_DISCORD_MAX_STACK_TRACE_LINES`  
**Type**: `integer`  
**Default**: `15`  
**Description**: Maximum number of stack trace lines to include.

### Include Request Data

**Environment Variable**: `WATCHDOG_DISCORD_INCLUDE_REQUEST_DATA`  
**Type**: `boolean`  
**Default**: `true`  
**Description**: Include HTTP request information in notifications.

### Max Field Length

**Environment Variable**: `WATCHDOG_DISCORD_MAX_FIELD_LENGTH`  
**Type**: `integer`  
**Default**: `1024`  
**Unit**: characters  
**Description**: Maximum length for Discord embed fields (Discord limit is 1024).

## Error Tracking & Analytics

```php
'error_tracking' => [
    'enabled' => env('WATCHDOG_DISCORD_ERROR_TRACKING_ENABLED', true),
    'notification_rules' => [
        'min_severity' => env('WATCHDOG_DISCORD_MIN_SEVERITY', 7),
        'frequency_threshold' => env('WATCHDOG_DISCORD_FREQUENCY_THRESHOLD', 10),
        'hourly_threshold' => env('WATCHDOG_DISCORD_HOURLY_THRESHOLD', 5),
        'notification_cooldown_minutes' => env('WATCHDOG_DISCORD_NOTIFICATION_COOLDOWN', 60),
    ],
],
```

### Error Tracking Enabled

**Environment Variable**: `WATCHDOG_DISCORD_ERROR_TRACKING_ENABLED`  
**Type**: `boolean`  
**Default**: `true`  
**Description**: Enable intelligent error tracking and analytics.

### Minimum Severity

**Environment Variable**: `WATCHDOG_DISCORD_MIN_SEVERITY`  
**Type**: `integer`  
**Range**: 1-10  
**Default**: `7`  
**Description**: Minimum severity score to trigger immediate notification.

**Severity Scale**:
- 1-3: Low (debug, info, notice)
- 4-6: Medium (warning, error with low frequency)
- 7-8: High (error, critical)
- 9-10: Critical (alert, emergency, high-frequency errors)

> **Note**: Severity scores are calculated automatically using an intelligent algorithm that considers log level, exception type, and frequency. See the [Log Levels Configuration](#log-levels-configuration) section for detailed calculation rules.

**Examples**:
```env
# Only high-severity issues (recommended for production)
WATCHDOG_DISCORD_MIN_SEVERITY=7

# Include medium-severity warnings
WATCHDOG_DISCORD_MIN_SEVERITY=4

# Track all logs (useful for debugging)
WATCHDOG_DISCORD_MIN_SEVERITY=1
```

### Frequency Threshold

**Environment Variable**: `WATCHDOG_DISCORD_FREQUENCY_THRESHOLD`  
**Type**: `integer`  
**Default**: `10`  
**Description**: Total occurrence count to trigger notification for lower-severity errors.

### Hourly Threshold

**Environment Variable**: `WATCHDOG_DISCORD_HOURLY_THRESHOLD`  
**Type**: `integer`  
**Default**: `5`  
**Description**: Hourly occurrence count to trigger notification.

### Notification Cooldown

**Environment Variable**: `WATCHDOG_DISCORD_NOTIFICATION_COOLDOWN`  
**Type**: `integer`  
**Default**: `60`  
**Unit**: minutes  
**Description**: Cooldown period between notifications for the same error.

## Database Configuration

```php
'database' => [
    'connection' => env('WATCHDOG_DISCORD_DB_CONNECTION'),
    'connections' => [
        'watchdog' => [
            'driver' => env('DB_DRIVER_WATCHDOG', 'mysql'),
            'host' => env('DB_HOST_WATCHDOG', '127.0.0.1'),
            'port' => env('DB_PORT_WATCHDOG', '3306'),
            'database' => env('DB_DATABASE_WATCHDOG', 'watchdog_errors'),
            'username' => env('DB_USERNAME_WATCHDOG', 'forge'),
            'password' => env('DB_PASSWORD_WATCHDOG', ''),
            // ... additional connection config
        ],
    ],
],
```

### Database Connection

**Environment Variable**: `WATCHDOG_DISCORD_DB_CONNECTION`  
**Type**: `string|null`  
**Default**: `null` (uses default Laravel connection)  
**Description**: Specific database connection for error tracking table.

### Dedicated Database Connection

For high-traffic applications, configure a dedicated database:

```env
WATCHDOG_DISCORD_DB_CONNECTION=watchdog
DB_HOST_WATCHDOG=dedicated-db-server.com
DB_DATABASE_WATCHDOG=error_tracking
DB_USERNAME_WATCHDOG=watchdog_user
DB_PASSWORD_WATCHDOG=secure_password
```

## Performance Configuration

```php
'performance' => [
    'async_enabled' => env('WATCHDOG_DISCORD_ASYNC_ENABLED', true),
    'max_execution_time' => env('WATCHDOG_DISCORD_MAX_EXECUTION_TIME', 2),
    'db_timeout' => env('WATCHDOG_DISCORD_DB_TIMEOUT', 1),
],
```

### Async Processing

**Environment Variable**: `WATCHDOG_DISCORD_ASYNC_ENABLED`  
**Type**: `boolean`  
**Default**: `true`  
**Description**: Process error tracking asynchronously to avoid blocking application.

**Critical**: Always `true` in production.

### Max Execution Time

**Environment Variable**: `WATCHDOG_DISCORD_MAX_EXECUTION_TIME`  
**Type**: `integer`  
**Default**: `2`  
**Unit**: seconds  
**Description**: Maximum time allowed for synchronous tracking operations.

### Database Timeout

**Environment Variable**: `WATCHDOG_DISCORD_DB_TIMEOUT`  
**Type**: `integer`  
**Default**: `1`  
**Unit**: seconds  
**Description**: Database operation timeout to prevent application blocking.

## Cache Configuration

```php
'cache' => [
    'prefix' => env('WATCHDOG_DISCORD_CACHE_PREFIX', 'watchdog'),
    'ttl' => env('WATCHDOG_DISCORD_CACHE_TTL', 300),
    'connection' => env('WATCHDOG_DISCORD_REDIS_CONNECTION', 'default'),
],
```

### Cache Prefix

**Environment Variable**: `WATCHDOG_DISCORD_CACHE_PREFIX`  
**Type**: `string`  
**Default**: `watchdog`  
**Description**: Redis key prefix for error tracking cache.

### Cache TTL

**Environment Variable**: `WATCHDOG_DISCORD_CACHE_TTL`  
**Type**: `integer`  
**Default**: `300`  
**Unit**: seconds  
**Description**: Time-to-live for cached error counts.

### Redis Connection

**Environment Variable**: `WATCHDOG_DISCORD_REDIS_CONNECTION`  
**Type**: `string`  
**Default**: `default`  
**Description**: Redis connection name for caching.

## Internationalization

```php
'locale' => env('WATCHDOG_DISCORD_LOCALE', 'en'),
```

**Environment Variable**: `WATCHDOG_DISCORD_LOCALE`  
**Type**: `string`  
**Default**: `en`  
**Options**: `en`, `pt-BR`, `es`, `fr`, `zh-CN`, `de`, `ja`, `it`, `ru`  
**Description**: Language for Discord notification messages.

## Configuration Validation

Add this to your application's health checks:

```php
// Validate configuration
public function validateWatchdogConfig()
{
    $required = [
        'watchdog-discord.enabled',
        'watchdog-discord.webhook_url',
    ];
    
    foreach ($required as $key) {
        if (empty(config($key))) {
            throw new \Exception("Missing required config: {$key}");
        }
    }
    
    // Validate webhook URL format
    $webhookUrl = config('watchdog-discord.webhook_url');
    if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        throw new \Exception("Invalid webhook URL format");
    }
    
    // Test Discord connectivity
    $response = Http::timeout(5)->post($webhookUrl, [
        'content' => 'Configuration test - please ignore',
    ]);
    
    if (!$response->successful()) {
        throw new \Exception("Cannot connect to Discord webhook");
    }
}
```

## Environment-Specific Configurations

### Development

```env
WATCHDOG_DISCORD_ENABLED=true
WATCHDOG_DISCORD_ASYNC_ENABLED=false
WATCHDOG_DISCORD_QUEUE_CONNECTION=sync
WATCHDOG_DISCORD_ERROR_TRACKING_ENABLED=true
WATCHDOG_DISCORD_MIN_SEVERITY=1
WATCHDOG_DISCORD_INCLUDE_REQUEST_DATA=true
```

### Testing

```env
WATCHDOG_DISCORD_ENABLED=false
WATCHDOG_DISCORD_ASYNC_ENABLED=false
WATCHDOG_DISCORD_QUEUE_CONNECTION=sync
```

### Staging

```env
WATCHDOG_DISCORD_ENABLED=true
WATCHDOG_DISCORD_ASYNC_ENABLED=true
WATCHDOG_DISCORD_QUEUE_CONNECTION=redis
WATCHDOG_DISCORD_MIN_SEVERITY=6
WATCHDOG_DISCORD_ENVIRONMENTS=staging
```

### Production

```env
WATCHDOG_DISCORD_ENABLED=true
WATCHDOG_DISCORD_ASYNC_ENABLED=true
WATCHDOG_DISCORD_QUEUE_CONNECTION=redis
WATCHDOG_DISCORD_MIN_SEVERITY=7
WATCHDOG_DISCORD_ENVIRONMENTS=production
WATCHDOG_DISCORD_RATE_LIMIT_ENABLED=true
```

## Best Practices

1. **Always enable async processing in production**
2. **Use Redis for optimal performance**
3. **Configure appropriate rate limits**
4. **Filter environments to avoid spam**
5. **Set severity thresholds appropriately**
6. **Monitor queue workers**
7. **Use dedicated database for high-traffic apps**
8. **Test configuration after deployment**

## Next Steps

- **[Architecture Guide](architecture.md)** - Understanding the technical implementation
- **[Performance Guide](performance.md)** - Optimization strategies
- **[Troubleshooting](troubleshooting.md)** - Common configuration issues
