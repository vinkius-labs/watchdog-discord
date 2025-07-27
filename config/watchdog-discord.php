<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Watchdog
    |--------------------------------------------------------------------------
    |
    | Enable or disable the error monitoring.
    |
    */
    'enabled' => env('WATCHDOG_DISCORD_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Discord Webhook URL
    |--------------------------------------------------------------------------
    |
    | The webhook URL where error notifications will be sent.
    | You can create one in your Discord server settings.
    |
    */
    'webhook_url' => env('WATCHDOG_DISCORD_WEBHOOK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure whether notifications should be sent via queue for better
    | performance. When enabled, notifications will be queued and processed
    | asynchronously.
    |
    */
    'queue' => [
        'enabled' => env('WATCHDOG_DISCORD_QUEUE_ENABLED', false),
        'connection' => env('WATCHDOG_DISCORD_QUEUE_CONNECTION', 'default'),
        'name' => env('WATCHDOG_DISCORD_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HTTP timeout and retry settings for Discord webhook requests.
    |
    */
    'timeout' => env('WATCHDOG_DISCORD_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Message Settings
    |--------------------------------------------------------------------------
    |
    | Customize the message that will be sent to Discord.
    |
    */
    'message' => [
        'username' => env('WATCHDOG_DISCORD_USERNAME', 'Laravel Watchdog'),
        'avatar_url' => env('WATCHDOG_DISCORD_AVATAR_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mentions
    |--------------------------------------------------------------------------
    |
    | Users or roles to mention when an error occurs.
    | Add user IDs or role IDs to be notified when errors happen.
    |
    | To get user or role IDs in Discord:
    | 1. Enable Developer Mode in Discord (Settings > Advanced > Developer Mode)
    | 2. Right-click on a user or role and select "Copy ID"
    |
    | Example:
    | 'mentions' => [
    |     'users' => [123456789012345678, 987654321098765432],
    |     'roles' => [123456789012345679],
    | ],
    |
    */
    'mentions' => [
        // IDs of users or roles to mention in case of error
        'users' => array_filter(explode(',', env('WATCHDOG_DISCORD_MENTION_USERS', '')), 'strlen'),
        'roles' => array_filter(explode(',', env('WATCHDOG_DISCORD_MENTION_ROLES', '')), 'strlen'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Filtering
    |--------------------------------------------------------------------------
    |
    | Specify which error types should be reported.
    |
    */
    'report_errors' => [
        'fatal' => env('WATCHDOG_DISCORD_REPORT_FATAL', true),
        'warning' => env('WATCHDOG_DISCORD_REPORT_WARNING', false),
        'notice' => env('WATCHDOG_DISCORD_REPORT_NOTICE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Filtering
    |--------------------------------------------------------------------------
    |
    | Specify which environments should send notifications.
    | Leave empty to allow all environments.
    |
    */
    'environments' => array_filter(explode(',', env('WATCHDOG_DISCORD_ENVIRONMENTS', '')), 'strlen'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to prevent spam when many errors occur.
    | Set max_notifications to limit notifications per time window.
    |
    */
    'rate_limiting' => [
        'enabled' => env('WATCHDOG_DISCORD_RATE_LIMIT_ENABLED', true),
        'max_notifications' => env('WATCHDOG_DISCORD_RATE_LIMIT_MAX', 10),
        'time_window_minutes' => env('WATCHDOG_DISCORD_RATE_LIMIT_WINDOW', 5),
        'cache_key_prefix' => 'watchdog_discord_rate_limit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Configure automatic logging of HTTP requests via middleware.
    | This is disabled by default to prevent spam.
    |
    */
    'log_requests' => [
        'enabled' => env('WATCHDOG_DISCORD_LOG_REQUESTS', false),
        'status_codes' => [500, 502, 503, 504], // Only log these status codes
        'min_duration_ms' => env('WATCHDOG_DISCORD_MIN_DURATION', 1000), // Only log slow requests
        'exclude_routes' => [
            'debugbar.*',
            'horizon.*',
            'telescope.*',
        ],
        'exclude_paths' => [
            '_debugbar/*',
            'horizon/*',
            'telescope/*',
            'favicon.ico',
            '*.css',
            '*.js',
            '*.png',
            '*.jpg',
            '*.jpeg',
            '*.gif',
            '*.svg',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Filtering
    |--------------------------------------------------------------------------
    |
    | Configure which exceptions should be ignored.
    | Add exception class names to exclude them from notifications.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Message Formatting
    |--------------------------------------------------------------------------
    |
    | Configure how messages are formatted and displayed.
    |
    */
    'formatting' => [
        'include_stack_trace' => env('WATCHDOG_DISCORD_INCLUDE_STACK_TRACE', false),
        'max_stack_trace_lines' => env('WATCHDOG_DISCORD_MAX_STACK_TRACE_LINES', 10),
        'include_request_data' => env('WATCHDOG_DISCORD_INCLUDE_REQUEST_DATA', true),
        'max_field_length' => env('WATCHDOG_DISCORD_MAX_FIELD_LENGTH', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Tracking & Analytics
    |--------------------------------------------------------------------------
    |
    | Enable intelligent error tracking and analytics features.
    | This allows the package to group similar errors, detect patterns,
    | and provide frequency analysis.
    |
    */
    'error_tracking' => [
        'enabled' => env('WATCHDOG_DISCORD_ERROR_TRACKING_ENABLED', true),

        'notification_rules' => [
            // Minimum severity score to always trigger notification (1-10)
            'min_severity' => env('WATCHDOG_DISCORD_MIN_SEVERITY', 7),

            // Total occurrence threshold to trigger notification
            'frequency_threshold' => env('WATCHDOG_DISCORD_FREQUENCY_THRESHOLD', 10),

            // Hourly occurrence threshold to trigger notification
            'hourly_threshold' => env('WATCHDOG_DISCORD_HOURLY_THRESHOLD', 5),

            // Cooldown between notifications for same error (minutes)
            'notification_cooldown_minutes' => env('WATCHDOG_DISCORD_NOTIFICATION_COOLDOWN', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | Database connection to use for error tracking table.
    | Leave null to use the default connection.
    | For dedicated database, configure a separate connection.
    |
    */
    'database' => [
        'connection' => env('WATCHDOG_DISCORD_DB_CONNECTION'),

        // Dedicated database connection configuration
        'connections' => [
            'watchdog' => [
                'driver' => env('DB_DRIVER_WATCHDOG', 'mysql'),
                'host' => env('DB_HOST_WATCHDOG', '127.0.0.1'),
                'port' => env('DB_PORT_WATCHDOG', '3306'),
                'database' => env('DB_DATABASE_WATCHDOG', 'watchdog_errors'),
                'username' => env('DB_USERNAME_WATCHDOG', 'forge'),
                'password' => env('DB_PASSWORD_WATCHDOG', ''),
                'unix_socket' => env('DB_SOCKET_WATCHDOG', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance settings to ensure the package doesn't impact
    | application performance. Redis is used for high-speed caching.
    |
    */
    'performance' => [
        // Process error tracking asynchronously to avoid blocking
        'async_enabled' => env('WATCHDOG_DISCORD_ASYNC_ENABLED', true),

        // Maximum execution time for tracking operations (seconds)
        'max_execution_time' => env('WATCHDOG_DISCORD_MAX_EXECUTION_TIME', 2),

        // Skip tracking if database is slow (timeout in seconds)
        'db_timeout' => env('WATCHDOG_DISCORD_DB_TIMEOUT', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration (Redis)
    |--------------------------------------------------------------------------
    |
    | Redis configuration for ultra-fast error tracking and deduplication.
    | Redis provides sub-millisecond performance for error counting.
    |
    */
    'cache' => [
        // Redis cache prefix for error tracking
        'prefix' => env('WATCHDOG_DISCORD_CACHE_PREFIX', 'watchdog'),

        // Cache TTL for error counting (seconds)
        'ttl' => env('WATCHDOG_DISCORD_CACHE_TTL', 300), // 5 minutes

        // Redis connection to use (from config/database.php redis connections)
        'connection' => env('WATCHDOG_DISCORD_REDIS_CONNECTION', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the language/locale for notifications. Supported languages:
    | en (English), pt-BR (Portuguese), es (Spanish), fr (French),
    | zh-CN (Chinese), de (German), ja (Japanese), it (Italian), ru (Russian)
    |
    */
    'locale' => env('WATCHDOG_DISCORD_LOCALE', 'en'),
];
