<?php

namespace VinkiusLabs\WatchdogDiscord\Services;

use VinkiusLabs\WatchdogDiscord\Models\ErrorTracking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ErrorAnalyticsService
{
    /**
     * Track an exception error
     */
    public function trackException(\Throwable $exception, string $level, array $context = []): ErrorTracking
    {
        $environment = app()->environment();
        $errorHash = ErrorTracking::generateErrorHash($exception, $environment);

        // Find existing error or create new one
        $errorRecord = ErrorTracking::where('error_hash', $errorHash)->first();

        if ($errorRecord) {
            // Update existing record
            $errorRecord->incrementOccurrence();

            // Recalculate severity based on new frequency
            $newSeverity = ErrorTracking::calculateSeverityScore(
                $exception,
                $level,
                $errorRecord->occurrence_count
            );

            $errorRecord->update([
                'severity_score' => $newSeverity,
                'context' => array_merge($errorRecord->context ?? [], $context),
            ]);
        } else {
            // Create new error record
            $errorRecord = ErrorTracking::create([
                'error_hash' => $errorHash,
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'environment' => $environment,
                'level' => $level,
                'severity_score' => ErrorTracking::calculateSeverityScore($exception, $level),
                'context' => $context,
                'stack_trace' => $this->formatStackTrace($exception),
                'url' => request()->fullUrl() ?? null,
                'method' => request()->method() ?? null,
                'ip' => request()->ip() ?? null,
                'user_id' => auth()->id() ?? null,
                'first_occurred_at' => Carbon::now(),
                'last_occurred_at' => Carbon::now(),
                'occurrence_count' => 1,
                'hourly_count' => 1,
                'daily_count' => 1,
                'is_resolved' => false,
                'notification_sent' => false,
            ]);
        }

        return $errorRecord;
    }

    /**
     * Track a log message
     */
    public function trackLog(string $level, string $message, array $context = []): ErrorTracking
    {
        $environment = app()->environment();
        $errorHash = ErrorTracking::generateLogHash($level, $message, $environment);

        // Find existing log or create new one
        $logRecord = ErrorTracking::where('error_hash', $errorHash)->first();

        if ($logRecord) {
            $logRecord->incrementOccurrence();

            $logRecord->update([
                'context' => array_merge($logRecord->context ?? [], $context),
            ]);
        } else {
            // Create fake exception for severity calculation
            $fakeException = new \Exception($message);

            $logRecord = ErrorTracking::create([
                'error_hash' => $errorHash,
                'exception_class' => 'Log',
                'message' => $message,
                'file' => null,
                'line' => null,
                'environment' => $environment,
                'level' => $level,
                'severity_score' => ErrorTracking::calculateSeverityScore($fakeException, $level),
                'context' => $context,
                'stack_trace' => null,
                'url' => request()->fullUrl() ?? null,
                'method' => request()->method() ?? null,
                'ip' => request()->ip() ?? null,
                'user_id' => auth()->id() ?? null,
                'first_occurred_at' => Carbon::now(),
                'last_occurred_at' => Carbon::now(),
                'occurrence_count' => 1,
                'hourly_count' => 1,
                'daily_count' => 1,
                'is_resolved' => false,
                'notification_sent' => false,
            ]);
        }

        return $logRecord;
    }

    /**
     * Get error statistics
     */
    public function getErrorStatistics(): array
    {
        $stats = [
            'total_errors' => ErrorTracking::count(),
            'unresolved_errors' => ErrorTracking::unresolved()->count(),
            'high_severity_errors' => ErrorTracking::highSeverity()->count(),
            'recent_errors' => ErrorTracking::recent()->count(),
            'trending_errors' => ErrorTracking::trending()->count(),
        ];

        // Top errors by frequency
        $stats['top_errors'] = ErrorTracking::unresolved()
            ->orderByDesc('occurrence_count')
            ->limit(10)
            ->get(['error_hash', 'exception_class', 'message', 'occurrence_count', 'severity_score'])
            ->toArray();

        // Errors by environment
        $stats['by_environment'] = ErrorTracking::select('environment', DB::raw('count(*) as count'))
            ->groupBy('environment')
            ->orderByDesc('count')
            ->get()
            ->toArray();

        // Errors by severity
        $stats['by_severity'] = ErrorTracking::select('severity_score', DB::raw('count(*) as count'))
            ->groupBy('severity_score')
            ->orderByDesc('severity_score')
            ->get()
            ->toArray();

        // Hourly trend (last 24 hours) - Database agnostic
        $connection = config('watchdog-discord.database.connection') ?? config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        $hourSelection = match ($driver) {
            'sqlite' => "strftime('%H', last_occurred_at)",
            'mysql', 'mariadb' => "HOUR(last_occurred_at)",
            'pgsql' => "EXTRACT(HOUR FROM last_occurred_at)",
            default => "HOUR(last_occurred_at)" // Default to MySQL syntax
        };

        $stats['hourly_trend'] = ErrorTracking::select(
            DB::raw("{$hourSelection} as hour"),
            DB::raw('count(*) as count')
        )
            ->where('last_occurred_at', '>=', Carbon::now()->subDay())
            ->groupBy(DB::raw($hourSelection))
            ->orderBy('hour')
            ->get()
            ->toArray();

        return $stats;
    }

    /**
     * Get similar errors
     */
    public function getSimilarErrors(ErrorTracking $error, int $limit = 5): array
    {
        // Find errors with similar exception class and message
        $similar = ErrorTracking::where('id', '!=', $error->id)
            ->where('exception_class', $error->exception_class)
            ->where('environment', $error->environment)
            ->where(function ($query) use ($error) {
                // Similar message (basic similarity)
                $words = explode(' ', $error->message);
                foreach (array_slice($words, 0, 3) as $word) { // First 3 words
                    if (strlen($word) > 3) {
                        $query->orWhere('message', 'like', '%' . $word . '%');
                    }
                }
            })
            ->orderByDesc('occurrence_count')
            ->limit($limit)
            ->get();

        return $similar->toArray();
    }

    /**
     * Clean up old resolved errors
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);

        return ErrorTracking::where('is_resolved', true)
            ->where('resolved_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get trending analysis
     */
    public function getTrendingAnalysis(): array
    {
        $trending = ErrorTracking::trending()
            ->unresolved()
            ->limit(10)
            ->get();

        $analysis = [];
        foreach ($trending as $error) {
            $hourlyRate = $error->hourly_count;
            $averageRate = $error->occurrence_count / max(1, $error->first_occurred_at->diffInHours(Carbon::now()));

            $analysis[] = [
                'error' => $error,
                'trend_factor' => $hourlyRate / max(1, $averageRate),
                'description' => $this->getTrendDescription($error),
            ];
        }

        return $analysis;
    }

    /**
     * Generate intelligent error summary
     */
    public function generateErrorSummary(ErrorTracking $error): array
    {
        $summary = [
            'frequency_analysis' => $error->getFrequencyDescription(),
            'severity_indicator' => $error->getSeverityEmoji(),
            'pattern_analysis' => $this->analyzePattern($error),
            'impact_assessment' => $this->assessImpact($error),
            'recommendations' => $this->getRecommendations($error),
        ];

        return $summary;
    }

    /**
     * Format stack trace for storage
     */
    private function formatStackTrace(\Throwable $exception): array
    {
        $trace = [];
        foreach ($exception->getTrace() as $item) {
            $trace[] = [
                'file' => $item['file'] ?? 'unknown',
                'line' => $item['line'] ?? 0,
                'function' => $item['function'] ?? 'unknown',
                'class' => $item['class'] ?? null,
            ];
        }
        return $trace;
    }

    /**
     * Analyze error pattern
     */
    private function analyzePattern(ErrorTracking $error): string
    {
        if ($error->hourly_count >= 10) {
            return 'High frequency - occurring multiple times per hour';
        }

        if ($error->daily_count >= 20) {
            return 'Frequent - multiple occurrences today';
        }

        if ($error->occurrence_count >= 50) {
            return 'Persistent - recurring over time';
        }

        return 'Sporadic - infrequent occurrences';
    }

    /**
     * Assess error impact
     */
    private function assessImpact(ErrorTracking $error): string
    {
        if ($error->severity_score >= 9) {
            return 'Critical - immediate attention required';
        }

        if ($error->severity_score >= 7) {
            return 'High - should be addressed soon';
        }

        if ($error->severity_score >= 5) {
            return 'Medium - monitor and plan fix';
        }

        return 'Low - can be addressed in regular maintenance';
    }

    /**
     * Get recommendations for error
     */
    private function getRecommendations(ErrorTracking $error): array
    {
        $recommendations = [];

        if ($error->occurrence_count >= 10) {
            $recommendations[] = 'Consider implementing error prevention measures';
        }

        if ($error->hourly_count >= 5) {
            $recommendations[] = 'Investigate root cause - high frequency detected';
        }

        if ($error->severity_score >= 8) {
            $recommendations[] = 'Escalate to development team immediately';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Monitor for pattern changes';
        }

        return $recommendations;
    }

    /**
     * Get trend description
     */
    private function getTrendDescription(ErrorTracking $error): string
    {
        $hourlyRate = $error->hourly_count;
        $totalHours = max(1, $error->first_occurred_at->diffInHours(Carbon::now()));
        $averageRate = $error->occurrence_count / $totalHours;

        if ($hourlyRate > $averageRate * 3) {
            return 'Rapidly increasing';
        }

        if ($hourlyRate > $averageRate * 2) {
            return 'Increasing';
        }

        return 'Stable frequency';
    }
}
