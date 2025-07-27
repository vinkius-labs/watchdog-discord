# Watchdog Discord

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vinkius-labs/watchdog-discord.svg?style=flat-square)](https://packagist.org/packages/vinkius-labs/watchdog-discord)
[![Total Downloads](https://img.shields.io/packagist/dt/vinkius-labs/watchdog-discord.svg?style=flat-square)](https://packagist.org/packages/vinkius-labs/watchdog-discord)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/vinkius-labs/watchdog-discord/run-tests.yml?branch=main&label=tests)](https://github.com/vinkius-labs/watchdog-discord/actions)

**Enterprise-grade real-time error monitoring and alerting system for Laravel applications via Discord webhooks.**

## Overview

Watchdog Discord is a high-performance monitoring solution that provides intelligent error tracking, analytics, and instant Discord notifications for Laravel applications. Built with enterprise scalability in mind, it offers Redis-powered error deduplication, severity scoring, and asynchronous processing to ensure zero impact on application performance.

## Key Features

- **🚀 High Performance**: Redis-powered error tracking with sub-millisecond performance
- **🔄 Asynchronous Processing**: Queue-based notifications to prevent application slowdown  
- **🎯 Smart Deduplication**: Hash-based error grouping and frequency analysis
- **📊 Error Analytics**: Severity scoring, trend detection, and analytics dashboard
- **🛡️ Rate Limiting**: Configurable thresholds to prevent notification spam
- **🌍 Multi-language Support**: Built-in translations for 9 languages
- **🐳 Docker Ready**: Full containerization support for modern deployments

## Architecture

```
Application Layer
├── Exception Handler    ├── Middleware    ├── Facade    ├── Manual Logging
│
Service Layer  
├── Discord Notifier (Central orchestration and payload building)
│
Business Logic Layer
├── Error Tracking Service    ├── Rate Limiter    ├── Queue Manager
│
Data Access Layer
├── Redis Cache    ├── Database (MySQL)    ├── Discord API
```

The system implements a layered architecture with Redis-first caching, database persistence, and configurable async processing for optimal performance in production environments.

## Installation

```bash
composer require vinkius-labs/watchdog-discord
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider" --tag="watchdog-discord-config"
```

### Run Migrations

```bash
php artisan migrate
```

## Basic Configuration

### Environment Setup

```env
# Required
WATCHDOG_DISCORD_ENABLED=true
WATCHDOG_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/your/webhook/url

# Performance (Production)
WATCHDOG_DISCORD_ASYNC_ENABLED=true
WATCHDOG_DISCORD_QUEUE_CONNECTION=redis
WATCHDOG_DISCORD_CACHE_PREFIX=watchdog_prod

# Error Tracking
WATCHDOG_DISCORD_ERROR_TRACKING_ENABLED=true
WATCHDOG_DISCORD_MIN_SEVERITY=7
WATCHDOG_DISCORD_FREQUENCY_THRESHOLD=10

# Rate Limiting
WATCHDOG_DISCORD_RATE_LIMIT_ENABLED=true
WATCHDOG_DISCORD_RATE_LIMIT_MAX=20
WATCHDOG_DISCORD_RATE_LIMIT_WINDOW=5
```

### Discord Webhook Setup

1. Navigate to your Discord server settings
2. Go to **Integrations** → **Webhooks**
3. Create a new webhook and copy the URL
4. Add the URL to your `.env` file

## Usage

### Automatic Error Handling

The package automatically captures and reports Laravel exceptions through the exception handler integration.

### Manual Logging

```php
use VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord;

// Log exceptions with context
try {
    $result = $riskyOperation->execute();
} catch (\Exception $e) {
    WatchdogDiscord::send($e, 'error', [
        'operation' => 'payment_processing',
        'user_id' => auth()->id()
    ]);
    throw $e;
}

// Log custom messages
WatchdogDiscord::sendLog('warning', 'High memory usage detected', [
    'memory_usage' => memory_get_usage(true),
    'peak_memory' => memory_get_peak_usage(true)
]);
```

### Middleware Integration

```php
// Apply to specific routes
Route::middleware('watchdog-discord:error')->group(function () {
    Route::post('/api/payments', [PaymentController::class, 'process']);
});

// Global middleware (app/Http/Kernel.php)
protected $middleware = [
    \VinkiusLabs\WatchdogDiscord\Middleware\WatchdogDiscordMiddleware::class,
];
```

### Dependency Injection

```php
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;

class PaymentService
{
    public function __construct(
        private DiscordNotifier $notifier
    ) {}
    
    public function processPayment($data)
    {
        try {
            return $this->gateway->charge($data);
        } catch (\Exception $e) {
            $this->notifier->send($e, 'critical', [
                'payment_data' => $data,
                'gateway' => $this->gateway->getName()
            ]);
            throw $e;
        }
    }
}
```

## Performance Optimization

### Redis Configuration

For optimal performance, configure Redis connection:

```php
// config/database.php
'redis' => [
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],
],
```

### Queue Workers

Configure dedicated queue workers for notifications:

```bash
# Supervisor configuration
php artisan queue:work redis --queue=watchdog_notifications --sleep=3 --tries=3
```

### Production Settings

```env
# Optimal production configuration
WATCHDOG_DISCORD_ASYNC_ENABLED=true
WATCHDOG_DISCORD_QUEUE_CONNECTION=redis
WATCHDOG_DISCORD_CACHE_TTL=300
WATCHDOG_DISCORD_ERROR_TRACKING_ENABLED=true
WATCHDOG_DISCORD_RATE_LIMIT_ENABLED=true
```

## Testing

```bash
# Test Discord notifications
php artisan watchdog-discord:test --exception

# Test with custom message
php artisan watchdog-discord:test --level=error --message="Test notification"

# Run test suite
composer test

# Code analysis
composer analyse
```

## Documentation

- **[Installation Guide](docs/installation.md)** - Detailed installation and setup
- **[Configuration Reference](docs/configuration.md)** - Complete configuration options
- **[Architecture Guide](docs/architecture.md)** - Technical architecture details
- **[Performance Guide](docs/performance.md)** - Production optimization
- **[Examples](docs/examples.md)** - Usage examples and patterns
- **[Troubleshooting](docs/troubleshooting.md)** - Common issues and solutions

## Requirements

- **PHP**: 8.1, 8.2, or 8.3
- **Laravel**: 9.x, 10.x, 11.x, or 12.x
- **Redis**: 6.0+ (recommended for production)
- **MySQL**: 5.7+ or 8.0+

## Security

If you discover a security vulnerability, please send an email to VinkiusLabs at labs@vinkius.com. All security vulnerabilities will be promptly addressed.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- **[Vinkius Labs](https://labs.vinkius.com)** - Package development and maintenance
- **[Contributors](https://github.com/vinkius-labs/watchdog-discord/contributors)** - Community contributions

---

<div align="center">
Built with ❤️ by <a href="https://labs.vinkius.com">Vinkius Labs</a>
</div>