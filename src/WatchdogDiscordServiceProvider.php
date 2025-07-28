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
     * Register the application services.
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerDatabaseConnections();
        $this->registerBindings();
        $this->registerAliases();
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
                ErrorAnalyticsCommand::class,
            ]);
        }
    }

    /**
     * Boot exception handler integration
     */
    protected function bootExceptionHandler(): void
    {
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
