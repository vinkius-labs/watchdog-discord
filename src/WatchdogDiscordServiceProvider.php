<?php

namespace VinkiusLabs\WatchdogDiscord;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use VinkiusLabs\WatchdogDiscord\Commands\TestWatchdogDiscordCommand;
use VinkiusLabs\WatchdogDiscord\Commands\TestWatchdogDiscordJobCommand;
use VinkiusLabs\WatchdogDiscord\Commands\TestPhpErrorsCommand;
use VinkiusLabs\WatchdogDiscord\Commands\DiagnoseErrorHandlingCommand;
use VinkiusLabs\WatchdogDiscord\Commands\SimulateRealErrorsCommand;
use VinkiusLabs\WatchdogDiscord\Commands\TestLogLevelsCommand;
use VinkiusLabs\WatchdogDiscord\Console\Commands\ErrorAnalyticsCommand;
use VinkiusLabs\WatchdogDiscord\Middleware\WatchdogDiscordMiddleware;
use VinkiusLabs\WatchdogDiscord\Contracts\ErrorTrackingServiceInterface;
use VinkiusLabs\WatchdogDiscord\Services\RedisErrorTrackingService;

class WatchdogDiscordServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->bootPublishing();
        $this->bootCommands();
        $this->bootExceptionHandler();
        $this->bootQueueFailedListener();
        $this->bootMiddleware();
        $this->bootViews();
        $this->bootTranslations();
        $this->bootMigrations();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/watchdog-discord.php',
            'watchdog-discord'
        );

        $this->app->singleton(DiscordNotifier::class, function ($app) {
            return new DiscordNotifier();
        });

        // Register additional service bindings
        $this->registerBindings();

        // Register the log listener in register method to ensure early registration
        $this->registerLogListener();
    }
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'watchdog-discord',
            DiscordNotifier::class,
        ];
    }

    /**
     * Boot publishing configuration
     */
    protected function bootPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config file
            $this->publishes([
                __DIR__ . '/../config/watchdog-discord.php' => config_path('watchdog-discord.php'),
            ], 'watchdog-discord-config');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/watchdog-discord'),
            ], 'watchdog-discord-views');

            // Publish translations
            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/watchdog-discord'),
            ], 'watchdog-discord-translations');

            // Publish all
            $this->publishes([
                __DIR__ . '/../config/watchdog-discord.php' => config_path('watchdog-discord.php'),
                __DIR__ . '/../resources/views' => resource_path('views/vendor/watchdog-discord'),
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/watchdog-discord'),
            ], 'watchdog-discord');
        }
    }

    /**
     * Boot commands
     */
    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestWatchdogDiscordCommand::class,
                TestWatchdogDiscordJobCommand::class,
                TestPhpErrorsCommand::class,
                DiagnoseErrorHandlingCommand::class,
                SimulateRealErrorsCommand::class,
                TestLogLevelsCommand::class,
                ErrorAnalyticsCommand::class,
            ]);
        }
    }

    /**
     * Boot exception handler integration
     */
    protected function bootExceptionHandler(): void
    {
        // Only register handlers if the watchdog is enabled
        if (! config('watchdog-discord.enabled', false)) {
            return;
        }

        // Register our exception handler in a way that doesn't interfere with existing handlers
        $this->app->resolving(ExceptionHandler::class, function (ExceptionHandler $handler, Application $app) {
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (\Throwable $e) {
                    $this->sendDiscordNotification($e);
                });
            }
        });

        // Register additional error handlers for PHP errors and warnings
        // Only if watchdog is enabled and PHP errors are not explicitly disabled
        if (config('watchdog-discord.php_errors.enabled', true)) {
            $this->registerErrorHandlers();
        }
    }

    /**
     * Register log listener to capture Laravel logs
     */
    protected function registerLogListener(): void
    {
        // Only register log listener if the watchdog is enabled
        if (! config('watchdog-discord.enabled', false)) {
            return;
        }

        // Register listener after the application has booted to ensure Log facade is ready
        $this->app->booted(function () {
            try {
                Log::listen(function ($logEntry) {
                    try {
                        // Prevent infinite loops - ignore our own log messages
                        if (isset($logEntry->context['watchdog_discord_internal'])) {
                            return;
                        }

                        // Send to Discord via the DiscordNotifier
                        // The DiscordNotifier will handle all filtering and validation
                        $notifier = app(DiscordNotifier::class);
                        $notifier->sendLog($logEntry->level, $logEntry->message, $logEntry->context ?? []);
                    } catch (\Throwable $e) {
                        // Silently fail to prevent breaking the logging system
                        // Log the error with our internal flag to prevent loops
                        Log::warning('Watchdog Discord log listener failed', [
                            'error' => $e->getMessage(),
                            'watchdog_discord_internal' => true,
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                // Silently fail if Log::listen registration fails
                // This could happen if Log facade is not ready
            }
        });
    }

    /**
     * Register additional error handlers for PHP errors, warnings, and notices
     */
    protected function registerErrorHandlers(): void
    {
        // PHP errors are always enabled by default since this is the main purpose of the package
        // Users can disable specifically if needed via config
        if (!config('watchdog-discord.php_errors.enabled', true)) {
            return;
        }

        // Store reference to previous handler to avoid conflicts
        $previousErrorHandler = set_error_handler(function ($severity, $message, $file, $line, $context = []) use (&$previousErrorHandler) {
            // Only process if we should report this error
            if ($this->shouldReportPhpError($severity) && $this->shouldReportErrorMessage($message)) {
                try {
                    $exception = new \ErrorException($message, 0, $severity, $file, $line);
                    $this->sendDiscordNotification($exception);
                } catch (\Throwable $e) {
                    // Log the failure but don't break the error handling chain
                    Log::warning('Watchdog Discord error handler failed', [
                        'error' => $e->getMessage(),
                        'original_error' => $message,
                    ]);
                }
            }

            // Call the previous error handler if it exists
            if ($previousErrorHandler && is_callable($previousErrorHandler)) {
                return call_user_func($previousErrorHandler, $severity, $message, $file, $line, $context);
            }

            // Return false to continue with normal error handling
            return false;
        }, E_ALL);

        // Register a fatal error handler that works with shutdown
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                if ($this->shouldReportErrorMessage($error['message'])) {
                    try {
                        $exception = new \ErrorException(
                            $error['message'],
                            0,
                            $error['type'],
                            $error['file'],
                            $error['line']
                        );
                        $this->sendDiscordNotification($exception);
                    } catch (\Throwable $e) {
                        // Log the failure
                        Log::warning('Watchdog Discord fatal error handler failed', [
                            'error' => $e->getMessage(),
                            'original_error' => $error['message'],
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Determine if a PHP error should be reported
     */
    protected function shouldReportPhpError(int $severity): bool
    {
        $config = config('watchdog-discord.php_errors', [
            'enabled' => true,
            'report_levels' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED, // All errors except deprecations by default
        ]);

        if (!$config['enabled']) {
            return false;
        }

        return ($severity & $config['report_levels']) !== 0;
    }

    /**
     * Check if error message should be reported (only exclude patterns matter)
     */
    protected function shouldReportErrorMessage(string $message): bool
    {
        $config = config('watchdog-discord.php_errors', []);

        // Only check exclude patterns - by default, capture EVERYTHING
        $excludePatterns = $config['exclude_patterns'] ?? [];
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return false;
            }
        }

        // Always return true by default - capture all errors unless explicitly excluded
        return true;
    }

    /**
     * Boot queue failed event listener
     */
    protected function bootQueueFailedListener(): void
    {
        if (! config('watchdog-discord.enabled', false)) {
            return;
        }

        if (! config('watchdog-discord.queue_monitoring.enabled', true)) {
            return;
        }

        // Official Laravel way to listen for queue job failures
        Queue::failing(function (JobFailed $event) {
            $this->sendQueueFailedNotification($event);
        });
    }

    /**
     * Boot middleware registration
     */
    protected function bootMiddleware(): void
    {
        // Register middleware alias
        $router = $this->app['router'];

        if (method_exists($router, 'aliasMiddleware')) {
            $router->aliasMiddleware('watchdog-discord', WatchdogDiscordMiddleware::class);
        }
    }

    /**
     * Boot views
     */
    protected function bootViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'watchdog-discord');
    }

    /**
     * Boot translations
     */
    protected function bootTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'watchdog-discord');
    }

    /**
     * Boot migrations
     */
    protected function bootMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register configuration
     */
    protected function registerConfig(): void
    {
        try {
            $configPath = __DIR__ . '/../config/watchdog-discord.php';
            if (file_exists($configPath)) {
                $this->mergeConfigFrom($configPath, 'watchdog-discord');
            }
        } catch (\Throwable $e) {
            // Gracefully handle config merge failures
            Log::warning('Failed to merge watchdog-discord config', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Register database connections
     */
    protected function registerDatabaseConnections(): void
    {
        // Register dedicated database connections if configured
        $connections = config('watchdog-discord.database.connections', []);

        foreach ($connections as $name => $config) {
            config(["database.connections.{$name}" => $config]);
        }
    }

    /**
     * Register service bindings
     */
    protected function registerBindings(): void
    {
        // Register the main notifier
        $this->app->singleton('watchdog-discord', function (Application $app) {
            return new DiscordNotifier;
        });

        $this->app->singleton(DiscordNotifier::class, function (Application $app) {
            return $app['watchdog-discord'];
        });

        // Register optimized error tracking service
        // Bind the error tracking service interface
        $this->app->singleton(ErrorTrackingServiceInterface::class, RedisErrorTrackingService::class);
    }

    /**
     * Register aliases
     */
    protected function registerAliases(): void
    {
        $this->app->alias('watchdog-discord', DiscordNotifier::class);
    }

    /**
     * Send exception notification to Discord
     */
    protected function sendDiscordNotification(\Throwable $exception): void
    {
        try {
            app(DiscordNotifier::class)->send($exception);
        } catch (\Exception $e) {
            // We don't want our error handler to throw errors
            // So we silently fail if something goes wrong with the notification
            Log::error('Failed to send Discord notification', [
                'error' => $e->getMessage(),
                'original_exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }
    }

    /**
     * Send queue job failure notification to Discord
     */
    protected function sendQueueFailedNotification(JobFailed $event): void
    {
        try {
            // Create a pseudo-exception from the job failure
            $exception = $event->exception ?? new \Exception(
                "Queue job failed: {$event->job->getName()}"
            );

            // Collect job context
            $jobContext = [
                'job_name' => $event->job->getName(),
                'queue' => $event->job->getQueue(),
                'connection' => $event->connectionName,
                'attempts' => $event->job->attempts(),
                'payload' => $event->job->payload(),
            ];

            app(DiscordNotifier::class)->sendJobFailure($exception, $jobContext);
        } catch (\Exception $e) {
            // We don't want our error handler to throw errors
            // So we silently fail if something goes wrong with the notification
            Log::error('Failed to send Discord notification for queue job failure', [
                'error' => $e->getMessage(),
                'job_name' => $event->job->getName() ?? 'Unknown job',
                'queue' => $event->job->getQueue() ?? 'Unknown queue',
                'connection' => $event->connectionName ?? 'Unknown connection',
            ]);
        }
    }
}
