<?php

namespace VinkiusLabs\WatchdogDiscord;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VinkiusLabs\WatchdogDiscord\Events\ErrorNotificationSent;
use VinkiusLabs\WatchdogDiscord\Events\LogNotificationSent;
use VinkiusLabs\WatchdogDiscord\Jobs\SendDiscordErrorNotification;
use VinkiusLabs\WatchdogDiscord\Jobs\SendDiscordLogNotification;
use VinkiusLabs\WatchdogDiscord\Models\ErrorTracking;
use VinkiusLabs\WatchdogDiscord\Contracts\ErrorTrackingServiceInterface;

class DiscordNotifier
{
    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('watchdog-discord.enabled', false);
    }

    /**
     * Get the configured locale for translations
     */
    protected function getLocale(): string
    {
        return config('watchdog-discord.locale', 'en');
    }

    /**
     * Get translated text
     */
    protected function trans(string $key, array $replace = []): string
    {
        $translation = __("watchdog-discord.{$key}", $replace);

        // If translation failed, try to load it manually from file
        if ($translation === "watchdog-discord.{$key}") {
            $langFile = __DIR__ . '/../resources/lang/' . $this->getLocale() . '/watchdog-discord.php';

            if (file_exists($langFile)) {
                $translations = include $langFile;
                $value = $this->getNestedArrayValue($translations, $key);

                if ($value !== null) {
                    // Replace placeholders
                    foreach ($replace as $placeholder => $replacement) {
                        $value = str_replace(":{$placeholder}", $replacement, $value);
                    }
                    return $value;
                }
            }
        }

        return $translation;
    }

    /**
     * Get nested array value using dot notation
     */
    protected function getNestedArrayValue(array $array, string $key)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Send error notification to Discord via webhook
     */
    public function send(\Throwable $exception): void
    {
        if (! $this->shouldSendNotification($exception)) {
            return;
        }

        $webhookUrl = config('watchdog-discord.webhook_url');
        if (!is_string($webhookUrl) || empty($webhookUrl)) {
            return;
        }

        // Track error in analytics system if enabled
        $errorRecord = null;
        if (config('watchdog-discord.error_tracking.enabled', true)) {
            try {
                $analytics = app(ErrorTrackingServiceInterface::class);
                $errorRecord = $analytics->trackException($exception, 'error');

                // Check if this error should trigger notification based on intelligence
                if ($errorRecord && !$errorRecord->shouldNotify()) {
                    return;
                }
            } catch (\Throwable $trackingError) {
                // Never let tracking errors prevent notifications
                // Log the tracking error but continue with notification
                try {
                    Log::warning('Error tracking service failed', [
                        'error' => $trackingError->getMessage(),
                        'original_exception' => $exception->getMessage()
                    ]);
                } catch (\Throwable $logError) {
                    // Even logging failed - just continue silently
                }
            }
        }

        // Check rate limiting
        if (! $this->checkRateLimit()) {
            return;
        }

        $payload = $this->buildErrorPayload($exception, $errorRecord);

        // Send via queue if enabled
        if (config('watchdog-discord.queue.enabled', false)) {
            SendDiscordErrorNotification::dispatch($webhookUrl, $payload, $exception)
                ->onQueue(config('watchdog-discord.queue.queue'))
                ->delay(config('watchdog-discord.queue.delay', 0));

            if ($errorRecord) {
                $errorRecord->recordNotificationSent();
            }
            return;
        }

        $this->sendSynchronously($webhookUrl, $payload, $exception);

        if ($errorRecord) {
            $errorRecord->recordNotificationSent();
        }
    }

    /**
     * Send queue job failure notification to Discord
     */
    public function sendJobFailure(\Throwable $exception, array $jobContext = []): void
    {
        if (! $this->shouldSendNotification($exception)) {
            return;
        }

        $webhookUrl = config('watchdog-discord.webhook_url');
        if (!is_string($webhookUrl) || empty($webhookUrl)) {
            return;
        }

        // Check rate limiting
        if (! $this->checkRateLimit('job_failure')) {
            return;
        }

        // Create enhanced error data with job context
        $errorData = $this->formatJobFailureError($exception, $jobContext);

        // Send via queue if enabled
        if (config('watchdog-discord.queue.enabled', false)) {
            SendDiscordErrorNotification::dispatch($errorData)
                ->onConnection(config('watchdog-discord.queue.connection'))
                ->onQueue(config('watchdog-discord.queue.queue'))
                ->delay(config('watchdog-discord.queue.delay', 0));
        } else {
            $this->sendSynchronously($webhookUrl, $errorData, $exception);
        }

        // Dispatch event
        event(new ErrorNotificationSent($exception, $jobContext));
    }

    /**
     * Send log notification to Discord
     */
    public function sendLog(string $level, string $message, array $context = []): void
    {
        if (! $this->enabled || ! $this->shouldReportLogLevel($level)) {
            return;
        }

        $webhookUrl = config('watchdog-discord.webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        // Track log in analytics system if enabled
        $logRecord = null;
        if (config('watchdog-discord.error_tracking.enabled', true)) {
            $analytics = app(ErrorTrackingServiceInterface::class);
            $logRecord = $analytics->trackLog($level, $message, $context);

            // Check if this log should trigger notification based on intelligence
            if ($logRecord && !$logRecord->shouldNotify()) {
                return;
            }
        }

        // Check rate limiting for logs
        if (! $this->checkRateLimit('log')) {
            return;
        }

        $payload = $this->buildLogPayload($level, $message, $context, $logRecord);

        // Send via queue if enabled
        if (config('watchdog-discord.queue.enabled', false)) {
            SendDiscordLogNotification::dispatch($webhookUrl, $payload, $level, $message, $context)
                ->onQueue(config('watchdog-discord.queue.queue'))
                ->delay(config('watchdog-discord.queue.delay', 0));

            if ($logRecord) {
                $logRecord->recordNotificationSent();
            }
            return;
        }

        $this->sendLogSynchronously($webhookUrl, $payload, $level, $message, $context);

        if ($logRecord) {
            $logRecord->recordNotificationSent();
        }
    }

    /**
     * Send info level log
     */
    public function info(string $message, array $context = []): void
    {
        $this->sendLog('info', $message, $context);
    }

    /**
     * Send warning level log
     */
    public function warning(string $message, array $context = []): void
    {
        $this->sendLog('warning', $message, $context);
    }

    /**
     * Send error level log
     */
    public function error(string $message, array $context = []): void
    {
        $this->sendLog('error', $message, $context);
    }

    /**
     * Send critical level log
     */
    public function critical(string $message, array $context = []): void
    {
        $this->sendLog('critical', $message, $context);
    }

    /**
     * Send emergency level log
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->sendLog('emergency', $message, $context);
    }

    /**
     * Send alert level log
     */
    public function alert(string $message, array $context = []): void
    {
        $this->sendLog('alert', $message, $context);
    }

    /**
     * Send notice level log
     */
    public function notice(string $message, array $context = []): void
    {
        $this->sendLog('notice', $message, $context);
    }

    /**
     * Send debug level log
     */
    public function debug(string $message, array $context = []): void
    {
        $this->sendLog('debug', $message, $context);
    }

    /**
     * Check if the notifier is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Disable the notifier
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Enable the notifier
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Send error notification synchronously
     */
    protected function sendSynchronously(string $webhookUrl, array $payload, \Throwable $exception): void
    {
        // Validate webhook URL
        if (!is_string($webhookUrl) || empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            Log::warning('Invalid webhook URL provided', ['webhook_url' => $webhookUrl]);
            return;
        }

        try {
            $response = Http::timeout(10)->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::debug('Discord error notification sent successfully', [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);

                event(new ErrorNotificationSent($exception, $payload, true));
            } else {
                Log::error('Failed to send Discord error notification', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'exception' => get_class($exception),
                ]);

                event(new ErrorNotificationSent($exception, $payload, false));
            }
        } catch (\Exception $e) {
            Log::error('Exception occurred while sending Discord error notification', [
                'error' => $e->getMessage(),
                'exception' => get_class($exception),
            ]);

            event(new ErrorNotificationSent($exception, $payload, false));
        }
    }

    /**
     * Send log notification synchronously
     */
    protected function sendLogSynchronously(string $webhookUrl, array $payload, string $level, string $message, array $context): void
    {
        try {
            $response = Http::timeout(10)->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::debug('Discord log notification sent successfully', [
                    'level' => $level,
                    'message' => $message,
                ]);

                event(new LogNotificationSent($level, $message, $context, $payload, true));
            } else {
                Log::error('Failed to send Discord log notification', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'level' => $level,
                ]);

                event(new LogNotificationSent($level, $message, $context, $payload, false));
            }
        } catch (\Exception $e) {
            Log::error('Exception occurred while sending Discord log notification', [
                'error' => $e->getMessage(),
                'level' => $level,
            ]);

            event(new LogNotificationSent($level, $message, $context, $payload, false));
        }
    }

    /**
     * Check if notification should be sent
     */
    protected function shouldSendNotification(\Throwable $exception): bool
    {
        if (! $this->enabled) {
            return false;
        }

        // Check environment filtering
        $allowedEnvironments = config('watchdog-discord.environments', []);
        if (! empty($allowedEnvironments) && ! in_array(app()->environment(), $allowedEnvironments)) {
            return false;
        }

        // Check if exception should be ignored
        $ignoredExceptions = config('watchdog-discord.ignore_exceptions', []);
        foreach ($ignoredExceptions as $ignoredException) {
            if ($exception instanceof $ignoredException) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if log level should be reported
     */
    protected function shouldReportLogLevel(string $level): bool
    {
        $reportedLevels = config('watchdog-discord.log_levels', ['error', 'critical', 'emergency']);

        return in_array($level, $reportedLevels);
    }

    /**
     * Check rate limiting
     */
    protected function checkRateLimit(string $type = 'error'): bool
    {
        if (! config('watchdog-discord.rate_limiting.enabled', true)) {
            return true;
        }

        $maxNotifications = config('watchdog-discord.rate_limiting.max_notifications', 10);
        $timeWindow = config('watchdog-discord.rate_limiting.time_window', 60);

        $cacheKey = "watchdog_discord_rate_limit_{$type}_" . now()->format('YmdHi');
        $cacheKey = substr($cacheKey, 0, -1) . '0'; // Round to nearest 10 minutes for bucketing

        $count = Cache::get($cacheKey, 0);

        if ($count >= $maxNotifications) {
            return false;
        }

        Cache::put($cacheKey, $count + 1, $timeWindow * 60);

        return true;
    }

    /**
     * Build error payload for Discord
     */
    protected function buildErrorPayload(\Throwable $exception, ?ErrorTracking $errorRecord = null): array
    {
        $appName = config('app.name', 'Laravel App');
        $environment = app()->environment();
        $mentions = $this->getMentions();

        $fields = [
            [
                'name' => $this->trans('notifications.fields.environment'),
                'value' => ucfirst($environment),
                'inline' => true,
            ],
            [
                'name' => $this->trans('notifications.fields.application'),
                'value' => $this->truncateField($appName),
                'inline' => true,
            ],
            [
                'name' => $this->trans('notifications.fields.error_type'),
                'value' => $this->truncateField($this->getErrorTypeDescription($exception)),
                'inline' => true,
            ],
            [
                'name' => $this->trans('notifications.fields.file'),
                'value' => $this->truncateField($exception->getFile()),
                'inline' => false,
            ],
            [
                'name' => $this->trans('notifications.fields.line'),
                'value' => (string) $exception->getLine(),
                'inline' => true,
            ],
        ];

        // Add PHP error specific information
        if ($exception instanceof \ErrorException) {
            $fields[] = [
                'name' => '🔍 PHP Error Level',
                'value' => $this->getPhpErrorLevelName($exception->getSeverity()),
                'inline' => true,
            ];
        }

        // Add frequency analysis if error tracking is enabled
        if ($errorRecord) {
            $fields[] = [
                'name' => '📊 ' . $this->trans('notifications.fields.frequency'),
                'value' => $errorRecord->getFrequencyDescription(),
                'inline' => true,
            ];

            $fields[] = [
                'name' => '⚠️ ' . $this->trans('notifications.fields.severity'),
                'value' => $errorRecord->getSeverityEmoji() . ' ' . $errorRecord->severity_score . '/10',
                'inline' => true,
            ];
        }

        // Add URL if available
        if (function_exists('request')) {
            $request = request();
            if ($request) {
                $fields[] = [
                    'name' => $this->trans('notifications.fields.url'),
                    'value' => $this->truncateField($request->fullUrl()),
                    'inline' => false,
                ];
            }
        }

        // Add request data if enabled
        if (config('watchdog-discord.formatting.include_request_data', true)) {
            $fields = array_merge($fields, $this->getRequestFields());
        }

        // Always include stack trace for better debugging
        $fields[] = $this->getStackTraceField($exception, $errorRecord);

        // Get title and color based on error type
        $title = $this->getErrorTitle($exception);
        $color = $this->getErrorColor($exception);

        return [
            'username' => config('watchdog-discord.message.username', 'Laravel Watchdog'),
            'avatar_url' => config('watchdog-discord.message.avatar_url'),
            'content' => $mentions,
            'embeds' => [
                [
                    'title' => $title,
                    'description' => $this->truncateField($this->formatErrorMessage($exception)),
                    'color' => $color,
                    'fields' => $fields,
                    'timestamp' => now()->toISOString(),
                    'footer' => [
                        'text' => 'Vinkius - Watchdog Discord',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get error type description with better formatting for PHP errors
     */
    protected function getErrorTypeDescription(\Throwable $exception): string
    {
        $class = get_class($exception);

        if ($exception instanceof \ErrorException) {
            $severity = $this->getPhpErrorLevelName($exception->getSeverity());
            return "PHP {$severity}";
        }

        return $class;
    }

    /**
     * Get PHP error level name
     */
    protected function getPhpErrorLevelName(int $severity): string
    {
        return match ($severity) {
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
            default => "Unknown Error ({$severity})",
        };
    }

    /**
     * Get appropriate title for the error
     */
    protected function getErrorTitle(\Throwable $exception): string
    {
        if ($exception instanceof \ErrorException) {
            $severity = $this->getPhpErrorLevelName($exception->getSeverity());
            return "🐛 PHP {$severity} Detected";
        }

        return '🚨 ' . $this->trans('notifications.error_title');
    }

    /**
     * Get appropriate color for the error
     */
    protected function getErrorColor(\Throwable $exception): int
    {
        if ($exception instanceof \ErrorException) {
            return match ($exception->getSeverity()) {
                E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 0xFF0000, // Red
                E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 0xFF8000, // Orange
                E_NOTICE, E_USER_NOTICE, E_STRICT => 0xFFFF00, // Yellow
                E_DEPRECATED, E_USER_DEPRECATED => 0x808080, // Gray
                default => 0xFF0000, // Red for unknown
            };
        }

        return $this->getColorForLevel('error');
    }

    /**
     * Format error message with better context for PHP errors
     */
    protected function formatErrorMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        // Add helpful context for common PHP errors
        if ($exception instanceof \ErrorException) {
            if (str_contains($message, 'Undefined property:')) {
                $message .= "\n💡 **Tip:** This property might not be initialized or could be a typo.";
            } elseif (str_contains($message, 'does not exist')) {
                $message .= "\n💡 **Tip:** Check the method name for typos or verify the class has this method.";
            } elseif (str_contains($message, 'Call to undefined')) {
                $message .= "\n💡 **Tip:** Verify the function/method exists and is properly imported.";
            }
        }

        return $message;
    }

    /**
     * Format job failure error for Discord notification
     */
    protected function formatJobFailureError(\Throwable $exception, array $jobContext = []): array
    {
        // Use the existing buildErrorPayload method as base
        $payload = $this->buildErrorPayload($exception);

        // Enhance with job-specific information
        if (!empty($jobContext)) {
            $jobFields = [];

            if (isset($jobContext['job_name'])) {
                $jobFields[] = [
                    'name' => '🔧 Job Name',
                    'value' => $this->truncateField($jobContext['job_name']),
                    'inline' => true,
                ];
            }

            if (isset($jobContext['queue'])) {
                $jobFields[] = [
                    'name' => '📋 Queue',
                    'value' => $this->truncateField($jobContext['queue']),
                    'inline' => true,
                ];
            }

            if (isset($jobContext['connection'])) {
                $jobFields[] = [
                    'name' => '🔗 Connection',
                    'value' => $this->truncateField($jobContext['connection']),
                    'inline' => true,
                ];
            }

            if (isset($jobContext['attempts'])) {
                $jobFields[] = [
                    'name' => '🔄 Attempts',
                    'value' => (string) $jobContext['attempts'],
                    'inline' => true,
                ];
            }

            // Insert job fields at the beginning of the fields array
            if (isset($payload['embeds'][0]['fields'])) {
                $payload['embeds'][0]['fields'] = array_merge($jobFields, $payload['embeds'][0]['fields']);
            }

            // Update the title to indicate it's a job failure
            $payload['embeds'][0]['title'] = '🚨 ' . $this->trans('notifications.job_error_title');
            $payload['embeds'][0]['color'] = 0xFF0000; // Red color for job failures
        }

        return $payload;
    }

    /**
     * Build log payload for Discord
     */
    protected function buildLogPayload(string $level, string $message, array $context = [], ?ErrorTracking $logRecord = null): array
    {
        $appName = config('app.name', 'Laravel App');
        $environment = app()->environment();
        $mentions = $this->getMentions();

        $fields = [
            [
                'name' => $this->trans('notifications.fields.environment'),
                'value' => ucfirst($environment),
                'inline' => true,
            ],
            [
                'name' => $this->trans('notifications.fields.application'),
                'value' => $this->truncateField($appName),
                'inline' => true,
            ],
        ];

        // Add frequency analysis if log tracking is enabled
        if ($logRecord) {
            $fields[] = [
                'name' => '📊 ' . $this->trans('notifications.fields.frequency'),
                'value' => $logRecord->getFrequencyDescription(),
                'inline' => true,
            ];

            if ($logRecord->severity_score >= 5) {
                $fields[] = [
                    'name' => '⚠️ ' . $this->trans('notifications.fields.severity'),
                    'value' => $logRecord->getSeverityEmoji() . ' ' . $logRecord->severity_score . '/10',
                    'inline' => true,
                ];
            }
        }

        // Add URL if available
        if (function_exists('request')) {
            $request = request();
            if ($request) {
                $fields[] = [
                    'name' => $this->trans('notifications.fields.url'),
                    'value' => $this->truncateField($request->fullUrl()),
                    'inline' => false,
                ];
            }
        }

        // Add context data if provided
        if (! empty($context)) {
            foreach ($context as $key => $value) {
                if (count($fields) >= 25) { // Discord limit is 25 fields
                    break;
                }

                $fields[] = [
                    'name' => ucfirst($key),
                    'value' => $this->truncateField(is_string($value) ? $value : json_encode($value)),
                    'inline' => true,
                ];
            }
        }

        return [
            'username' => config('watchdog-discord.message.username', 'Laravel Watchdog'),
            'avatar_url' => config('watchdog-discord.message.avatar_url'),
            'content' => $mentions,
            'embeds' => [
                [
                    'title' => $this->getLogLevelEmoji($level) . ' ' . $this->trans('notifications.log_title', ['level' => ucfirst($level)]),
                    'description' => $this->truncateField($message),
                    'color' => $this->getColorForLevel($level),
                    'fields' => $fields,
                    'timestamp' => now()->toISOString(),
                    'footer' => [
                        'text' => 'Vinkius - Watchdog Discord',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get request fields for Discord embed
     */
    protected function getRequestFields(): array
    {
        $fields = [];

        if (! function_exists('request')) {
            return $fields;
        }

        $request = request();
        if (! $request) {
            return $fields;
        }

        if ($request->method()) {
            $fields[] = [
                'name' => $this->trans('notifications.fields.method'),
                'value' => $request->method(),
                'inline' => true,
            ];
        }

        if ($request->ip()) {
            $fields[] = [
                'name' => $this->trans('notifications.fields.ip'),
                'value' => $request->ip(),
                'inline' => true,
            ];
        }

        if (function_exists('auth') && auth()->check()) {
            $fields[] = [
                'name' => $this->trans('notifications.fields.user_id'),
                'value' => (string) auth()->id(),
                'inline' => true,
            ];
        }

        if ($request->userAgent()) {
            $fields[] = [
                'name' => $this->trans('notifications.fields.user_agent'),
                'value' => $this->truncateField($request->userAgent()),
                'inline' => false,
            ];
        }

        return $fields;
    }

    /**
     * Get stack trace field for Discord embed
     */
    protected function getStackTraceField(\Throwable $exception, ?ErrorTracking $errorRecord = null): array
    {
        // Use stored stack trace from ErrorTracking if available and more detailed
        $stackTrace = null;

        if ($errorRecord && $errorRecord->stack_trace && is_array($errorRecord->stack_trace)) {
            $stackTrace = $this->formatStructuredStackTrace($errorRecord->stack_trace);
        } else {
            // Fallback to exception's getTraceAsString
            $stackTrace = $exception->getTraceAsString();
        }

        $lines = explode("\n", $stackTrace);
        $maxLines = config('watchdog-discord.formatting.max_stack_trace_lines', 15);

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[] = '... (truncated)';
        }

        return [
            'name' => '🔍 ' . $this->trans('notifications.fields.stack_trace'),
            'value' => $this->truncateField('```' . implode("\n", $lines) . '```'),
            'inline' => false,
        ];
    }

    /**
     * Format structured stack trace from ErrorTracking
     */
    protected function formatStructuredStackTrace(array $stackTrace): string
    {
        $formatted = [];

        foreach ($stackTrace as $index => $frame) {
            $line = "#{$index} ";

            if (!empty($frame['file'])) {
                $line .= $frame['file'];
                if (!empty($frame['line'])) {
                    $line .= "({$frame['line']})";
                }
                $line .= ": ";
            }

            if (!empty($frame['class'])) {
                $line .= $frame['class'];
                if (!empty($frame['type'])) {
                    $line .= $frame['type'];
                }
            }

            $line .= $frame['function'] ?? 'unknown';
            $line .= "()";

            $formatted[] = $line;
        }

        return implode("\n", $formatted);
    }

    /**
     * Truncate field value to Discord limits
     */
    protected function truncateField(?string $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        $maxLength = config('watchdog-discord.formatting.max_field_length', 1024);

        if (strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength - 3) . '...';
        }

        return $value;
    }

    /**
     * Get color for log level
     */
    protected function getColorForLevel(string $level): int
    {
        $colors = config('watchdog-discord.colors', [
            'emergency' => 0xFF0000, // Red
            'alert' => 0xFF4500,     // Orange Red
            'critical' => 0xFF6347,  // Tomato
            'error' => 0xFF0000,     // Red
            'warning' => 0xFFA500,   // Orange
            'notice' => 0x0000FF,    // Blue
            'info' => 0x00BFFF,      // Deep Sky Blue
            'debug' => 0x808080,     // Gray
        ]);

        return $colors[$level] ?? 0xFF0000;
    }

    /**
     * Get emoji for log level
     */
    protected function getLogLevelEmoji(string $level): string
    {
        $emojis = [
            'emergency' => '🚨',
            'alert' => '🔔',
            'critical' => '❌',
            'error' => '🚨',
            'warning' => '⚠️',
            'notice' => 'ℹ️',
            'info' => 'ℹ️',
            'debug' => '🐛',
        ];

        return $emojis[$level] ?? '📝';
    }

    /**
     * Get mentions from config
     */
    protected function getMentions(): string
    {
        $mentions = config('watchdog-discord.message.mentions', []);

        if (empty($mentions)) {
            return '';
        }

        $mentionStrings = [];
        foreach ($mentions as $mention) {
            if (str_starts_with($mention, '@&')) {
                // Role mention
                $mentionStrings[] = $mention;
            } elseif (str_starts_with($mention, '@')) {
                // User mention
                $mentionStrings[] = $mention;
            } else {
                // Assume it's a user ID
                $mentionStrings[] = "<@{$mention}>";
            }
        }

        return implode(' ', $mentionStrings) . ' ';
    }
}
