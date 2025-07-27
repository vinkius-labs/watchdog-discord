<?php

namespace VinkiusLabs\WatchdogDiscord\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ErrorTracking extends Model
{
    protected $table = 'watchdog_error_tracking';

    protected $fillable = [
        'error_hash',
        'exception_class',
        'message',
        'file',
        'line',
        'environment',
        'level',
        'severity_score',
        'context',
        'stack_trace',
        'url',
        'method',
        'ip',
        'user_id',
        'first_occurred_at',
        'last_occurred_at',
        'occurrence_count',
        'hourly_count',
        'daily_count',
        'is_resolved',
        'resolved_at',
        'notification_sent',
        'last_notification_at',
    ];

    protected $casts = [
        'context' => 'array',
        'stack_trace' => 'array',
        'first_occurred_at' => 'datetime',
        'last_occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_notification_at' => 'datetime',
        'is_resolved' => 'boolean',
        'notification_sent' => 'boolean',
    ];

    /**
     * Get the database connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return config('watchdog-discord.database.connection');
    }

    /**
     * Generate error hash for grouping similar errors
     */
    public static function generateErrorHash(\Throwable $exception, string $environment): string
    {
        $key = sprintf(
            '%s:%s:%s:%d:%s',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $environment
        );

        return hash('xxh3', $key);
    }

    /**
     * Generate error hash for log messages
     */
    public static function generateLogHash(string $level, string $message, string $environment): string
    {
        $key = sprintf(
            'log:%s:%s:%s',
            $level,
            $message,
            $environment
        );

        return hash('sha256', $key);
    }

    /**
     * Calculate severity score based on exception and frequency
     */
    public static function calculateSeverityScore(\Throwable $exception, string $level, int $occurrenceCount = 1): int
    {
        $baseScore = match ($level) {
            'emergency' => 10,
            'alert' => 9,
            'critical' => 8,
            'error' => 6,
            'warning' => 4,
            'notice' => 2,
            'info' => 1,
            'debug' => 1,
            default => 1,
        };

        // Increase score based on exception type
        $exceptionScore = match (true) {
            $exception instanceof \Error => 3,
            $exception instanceof \ParseError => 3,
            $exception instanceof \TypeError => 2,
            $exception instanceof \RuntimeException => 2,
            default => 0,
        };

        // Increase score based on frequency
        $frequencyScore = match (true) {
            $occurrenceCount >= 100 => 3,
            $occurrenceCount >= 50 => 2,
            $occurrenceCount >= 10 => 1,
            default => 0,
        };

        return min(10, $baseScore + $exceptionScore + $frequencyScore);
    }

    /**
     * Get recent errors (last 24 hours)
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->where('last_occurred_at', '>=', Carbon::now()->subDay());
    }

    /**
     * Get high severity errors
     */
    public function scopeHighSeverity(Builder $query): Builder
    {
        return $query->where('severity_score', '>=', 7);
    }

    /**
     * Get unresolved errors
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Get frequently occurring errors
     */
    public function scopeFrequent(Builder $query, int $minCount = 10): Builder
    {
        return $query->where('occurrence_count', '>=', $minCount);
    }

    /**
     * Get errors by environment
     */
    public function scopeForEnvironment(Builder $query, string $environment): Builder
    {
        return $query->where('environment', $environment);
    }

    /**
     * Get trending errors (increasing frequency)
     */
    public function scopeTrending(Builder $query): Builder
    {
        return $query->whereRaw('hourly_count > (occurrence_count / 24)')
            ->orderByDesc('hourly_count');
    }

    /**
     * Mark error as resolved
     */
    public function markAsResolved(): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => Carbon::now(),
        ]);
    }

    /**
     * Increment occurrence counters
     */
    public function incrementOccurrence(): void
    {
        $now = Carbon::now();
        $hourAgo = $now->subHour();
        $dayAgo = $now->subDay();

        // Reset hourly count if it's a new hour
        $hourlyCount = $this->last_occurred_at < $hourAgo ? 1 : $this->hourly_count + 1;

        // Reset daily count if it's a new day
        $dailyCount = $this->last_occurred_at < $dayAgo ? 1 : $this->daily_count + 1;

        $this->update([
            'last_occurred_at' => $now,
            'occurrence_count' => $this->occurrence_count + 1,
            'hourly_count' => $hourlyCount,
            'daily_count' => $dailyCount,
        ]);
    }

    /**
     * Check if error should trigger notification
     */
    public function shouldNotify(): bool
    {
        $config = config('watchdog-discord.error_tracking.notification_rules', []);

        // Always notify for high severity
        if ($this->severity_score >= ($config['min_severity'] ?? 7)) {
            return true;
        }

        // Notify for frequent errors
        if ($this->occurrence_count >= ($config['frequency_threshold'] ?? 10)) {
            return true;
        }

        // Notify for trending errors
        if ($this->hourly_count >= ($config['hourly_threshold'] ?? 5)) {
            return true;
        }

        // Don't spam - check last notification time
        if ($this->last_notification_at) {
            $cooldown = $config['notification_cooldown_minutes'] ?? 60;
            if ($this->last_notification_at->addMinutes($cooldown) > Carbon::now()) {
                return false;
            }
        }

        return false;
    }

    /**
     * Record notification sent
     */
    public function recordNotificationSent(): void
    {
        $this->update([
            'notification_sent' => true,
            'last_notification_at' => Carbon::now(),
        ]);
    }

    /**
     * Get frequency description for Discord message
     */
    public function getFrequencyDescription(): string
    {
        if ($this->occurrence_count === 1) {
            return 'First occurrence';
        }

        $parts = [];

        if ($this->hourly_count > 1) {
            $parts[] = "{$this->hourly_count}x in last hour";
        }

        if ($this->daily_count > $this->hourly_count) {
            $parts[] = "{$this->daily_count}x in last 24h";
        }

        if ($this->occurrence_count > $this->daily_count) {
            $parts[] = "{$this->occurrence_count}x total";
        }

        return empty($parts) ? "{$this->occurrence_count}x total" : implode(', ', $parts);
    }

    /**
     * Get severity emoji
     */
    public function getSeverityEmoji(): string
    {
        return match (true) {
            $this->severity_score >= 9 => '🚨',
            $this->severity_score >= 7 => '⚠️',
            $this->severity_score >= 5 => '⚡',
            $this->severity_score >= 3 => '🔍',
            default => 'ℹ️',
        };
    }
}
