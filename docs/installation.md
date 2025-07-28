# Installation Guide

This guide provides detailed installation instructions for Watchdog Discord across different environments and configurations.

## System Requirements

### Minimum Requirements

- **PHP**: 8.1, 8.2, or 8.3
- **Laravel**: 9.x, 10.x, 11.x, or 12.x
- **Memory**: 128MB (minimum)
- **Disk Space**: 10MB for package files

### Recommended Requirements

- **PHP**: 8.3+ with OPcache enabled
- **Laravel**: 12.x (latest LTS)
- **Redis**: 6.0+ for caching and queues
- **Memory**: 512MB+ for optimal performance
- **Queue Worker**: Supervisor or similar process manager

### Dependencies

Watchdog Discord automatically installs these dependencies:

```json
{
    "guzzlehttp/guzzle": "^7.0",
    "illuminate/support": "^9.0|^10.0|^11.0|^12.0",
    "illuminate/http": "^9.0|^10.0|^11.0|^12.0",
    "illuminate/contracts": "^9.0|^10.0|^11.0|^12.0"
}
```

## Installation Methods

### 1. Composer Installation (Recommended)

```bash
composer require vinkius-labs/watchdog-discord
```

### 2. Development Installation

For development with the latest features:

```bash
composer require vinkius-labs/watchdog-discord:dev-main
```

### 3. Manual Installation

Download and install manually:

```bash
git clone https://github.com/vinkius-labs/watchdog-discord.git
cd laravel-watchdog-discord
composer install
```

## Service Provider Registration

Laravel's auto-discovery automatically registers the service provider. For manual registration:

```php
// config/app.php
'providers' => [
    // ...
    VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider::class,
],

'aliases' => [
    // ...
    'WatchdogDiscord' => VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord::class,
],
```

## Configuration Setup

### 1. Publish Configuration

```bash
php artisan vendor:publish --provider="VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider" --tag="watchdog-discord-config"
```

This creates `config/watchdog-discord.php` with all available options.

### 2. Publish All Assets (Optional)

```bash
# Publish all assets
php artisan vendor:publish --provider="VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider"

# Or publish specific assets
php artisan vendor:publish --tag="watchdog-discord-translations"
php artisan vendor:publish --tag="watchdog-discord-views"
```

### 3. Database Migration

Run the migration to create the error tracking table:

```bash
php artisan migrate
```

The migration creates the `watchdog_error_tracking` table with optimized indexes for performance.

## Environment Configuration

### Basic Configuration

Create or update your `.env` file:

```env
# Basic Configuration
WATCHDOG_DISCORD_ENABLED=true
WATCHDOG_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/your/webhook/url

# Optional Settings
WATCHDOG_DISCORD_USERNAME="Laravel Watchdog"
WATCHDOG_DISCORD_LOCALE=en
```

### Production Configuration

For production environments:

```env
# Core Settings
WATCHDOG_DISCORD_ENABLED=true
WATCHDOG_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/your/webhook/url

# Performance Settings (Critical for Production)
WATCHDOG_DISCORD_ASYNC_ENABLED=true
WATCHDOG_DISCORD_QUEUE_CONNECTION=redis
WATCHDOG_DISCORD_QUEUE_NAME=watchdog_notifications

# Redis Configuration
WATCHDOG_DISCORD_CACHE_PREFIX=watchdog_prod
WATCHDOG_DISCORD_CACHE_TTL=300
WATCHDOG_DISCORD_REDIS_CONNECTION=default

# Error Tracking
WATCHDOG_DISCORD_ERROR_TRACKING_ENABLED=true
WATCHDOG_DISCORD_MIN_SEVERITY=7
WATCHDOG_DISCORD_FREQUENCY_THRESHOLD=10

# Rate Limiting
WATCHDOG_DISCORD_RATE_LIMIT_ENABLED=true
WATCHDOG_DISCORD_RATE_LIMIT_MAX=20
WATCHDOG_DISCORD_RATE_LIMIT_WINDOW=5

# Environment Filtering
WATCHDOG_DISCORD_ENVIRONMENTS=production,staging

# Notification Targeting
WATCHDOG_DISCORD_MENTION_ROLES=123456789012345679
```

### Development Configuration

For development environments:

```env
# Core Settings
WATCHDOG_DISCORD_ENABLED=true
WATCHDOG_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/your/test/webhook

# Development Settings
WATCHDOG_DISCORD_ASYNC_ENABLED=false
WATCHDOG_DISCORD_QUEUE_CONNECTION=sync
WATCHDOG_DISCORD_ERROR_TRACKING_ENABLED=true
WATCHDOG_DISCORD_MIN_SEVERITY=1

# Verbose Logging
WATCHDOG_DISCORD_INCLUDE_REQUEST_DATA=true
WATCHDOG_DISCORD_MAX_STACK_TRACE_LINES=20
```

## Discord Setup

### 1. Create Discord Webhook

1. Open your Discord server
2. Navigate to **Server Settings** → **Integrations**
3. Click **Create Webhook** or **View Webhooks**
4. Click **New Webhook**
5. Configure the webhook:
   - **Name**: Laravel Watchdog
   - **Channel**: Select your monitoring channel
   - **Avatar**: Upload a custom avatar (optional)
6. Copy the **Webhook URL**
7. Save the webhook

### 2. Get User and Role IDs

For mentioning specific users or roles:

1. Enable **Developer Mode** in Discord:
   - Go to **User Settings** → **Advanced**
   - Enable **Developer Mode**
2. Get User ID:
   - Right-click on a user
   - Select **Copy User ID**
3. Get Role ID:
   - Right-click on a role in the members list
   - Select **Copy Role ID**

Add these to your environment:

```env
WATCHDOG_DISCORD_MENTION_USERS=123456789012345678,987654321098765432
WATCHDOG_DISCORD_MENTION_ROLES=123456789012345679
```

## Queue Configuration

### 1. Redis Setup (Recommended)

Install Redis and configure Laravel:

```bash
# Install Redis (Ubuntu/Debian)
sudo apt update
sudo apt install redis-server

# Start Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

Configure Laravel Redis connection:

```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],
    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
],
```

### 2. Queue Worker Setup

Configure Supervisor for production:

```ini
# /etc/supervisor/conf.d/laravel-watchdog-worker.conf
[program:laravel-watchdog-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work redis --queue=watchdog_notifications --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

Start the worker:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-watchdog-worker:*
```

## Verification

### 1. Test Configuration

```bash
# Test Discord notification
php artisan watchdog-discord:test --exception

# Test specific log level
php artisan watchdog-discord:test --level=error --message="Installation test"
```

### 2. Verify Database Connection

```bash
# Check migration status
php artisan migrate:status

# Verify table creation
php artisan tinker
>>> \VinkiusLabs\WatchdogDiscord\Models\ErrorTracking::count()
```

### 3. Check Queue Configuration

```bash
# Test queue connectivity
php artisan queue:failed
php artisan queue:monitor redis:watchdog_notifications

# Process queued jobs
php artisan queue:work redis --queue=watchdog_notifications --once
```

## Troubleshooting

### Common Issues

#### 1. Service Provider Not Registered

**Problem**: Package classes not found

**Solution**:
```bash
composer dump-autoload
php artisan config:clear
php artisan route:clear
```

#### 2. Migration Issues

**Problem**: Migration fails to run

**Solution**:
```bash
# Check database connection
php artisan migrate:status

# Force migration
php artisan migrate --force

# Use specific connection
DB_CONNECTION=mysql php artisan migrate
```

#### 3. Redis Connection Issues

**Problem**: Redis connection errors

**Solution**:
```bash
# Check Redis status
redis-cli ping

# Verify Laravel Redis config
php artisan tinker
>>> Redis::ping()
```

#### 4. Permission Issues

**Problem**: File permission errors

**Solution**:
```bash
# Fix Laravel permissions
sudo chown -R www-data:www-data storage
sudo chown -R www-data:www-data bootstrap/cache
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache
```

### Debug Mode

Enable debug mode for troubleshooting:

```env
WATCHDOG_DISCORD_DEBUG=true
WATCHDOG_DISCORD_LOG_REQUESTS=true
```

Check logs:

```bash
tail -f storage/logs/laravel.log
```

## Next Steps

After successful installation:

1. **[Configuration Reference](configuration.md)** - Detailed configuration options
2. **[Architecture Guide](architecture.md)** - Understanding the internal structure
3. **[Performance Guide](performance.md)** - Optimization for production
4. **[Troubleshooting](troubleshooting.md)** - Common issues and solutions

## Support

If you encounter issues during installation:

1. Check the [Troubleshooting Guide](troubleshooting.md)
2. Review the [GitHub Issues](https://github.com/vinkius-labs/watchdog-discord/issues)
4. Contact support at labs@vinkius.com
