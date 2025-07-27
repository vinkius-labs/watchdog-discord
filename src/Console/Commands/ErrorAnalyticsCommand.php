<?php

namespace VinkiusLabs\WatchdogDiscord\Console\Commands;

use Illuminate\Console\Command;
use VinkiusLabs\WatchdogDiscord\Models\ErrorTracking;
use VinkiusLabs\WatchdogDiscord\Services\ErrorAnalyticsService;

class ErrorAnalyticsCommand extends Command
{
    protected $signature = 'watchdog-discord:analytics
                            {--period=24h : Analysis period (1h, 24h, 7d, 30d)}
                            {--show-details : Show detailed error information}
                            {--cleanup : Clean up old resolved errors}
                            {--cleanup-days=30 : Days to keep resolved errors}';

    protected $description = 'Show error analytics and statistics from watchdog error tracking';

    public function handle(ErrorAnalyticsService $analyticsService): int
    {
        // Performance monitoring
        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);

        try {
            if ($this->option('cleanup')) {
                return $this->handleCleanup($analyticsService);
            }

            $this->showErrorAnalytics($analyticsService);

            // Show performance metrics
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $memoryUsed = round((memory_get_usage(true) - $memoryStart) / 1024 / 1024, 2);

            $this->newLine();
            $this->comment("Performance: {$executionTime}ms execution time, {$memoryUsed}MB memory used");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Analytics command failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function showErrorAnalytics(ErrorAnalyticsService $analyticsService): void
    {
        $this->info('📊 Watchdog Discord - Error Analytics');
        $this->newLine();

        // Get overall statistics
        $stats = $analyticsService->getErrorStatistics();

        $this->info('📈 Overall Statistics:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Errors', $stats['total_errors']],
                ['Unresolved Errors', $stats['unresolved_errors']],
                ['High Severity Errors', $stats['high_severity_errors']],
                ['Recent Errors (24h)', $stats['recent_errors']],
                ['Trending Errors', $stats['trending_errors']],
            ]
        );

        $this->newLine();

        // Show top errors if any exist
        if (!empty($stats['top_errors'])) {
            $this->info('🔥 Top Errors by Frequency:');
            $topErrors = array_slice($stats['top_errors'], 0, 10);
            $tableData = [];

            foreach ($topErrors as $error) {
                $tableData[] = [
                    substr($error['exception_class'], strrpos($error['exception_class'], '\\') + 1),
                    $this->truncate($error['message'], 50),
                    $error['occurrence_count'],
                    $error['severity_score'],
                ];
            }

            $this->table(
                ['Exception', 'Message', 'Count', 'Severity'],
                $tableData
            );
            $this->newLine();
        }

        // Show environment breakdown
        if (!empty($stats['by_environment'])) {
            $this->info('🌍 Errors by Environment:');
            $this->table(
                ['Environment', 'Count'],
                array_map(fn($env) => [$env['environment'], $env['count']], $stats['by_environment'])
            );
            $this->newLine();
        }

        // Show trending analysis
        $this->showTrendingAnalysis($analyticsService);

        if ($this->option('show-details')) {
            $this->showDetailedAnalysis($analyticsService);
        }
    }

    private function showTrendingAnalysis(ErrorAnalyticsService $analyticsService): void
    {
        $trending = $analyticsService->getTrendingAnalysis();

        if (empty($trending)) {
            $this->info('✅ No trending errors detected');
            return;
        }

        $this->warn('📈 Trending Errors (Increasing Frequency):');

        foreach (array_slice($trending, 0, 5) as $item) {
            $error = $item['error'];
            $this->line(sprintf(
                '  %s %s - %s (%s)',
                $error->getSeverityEmoji(),
                substr($error->exception_class, strrpos($error->exception_class, '\\') + 1),
                $this->truncate($error->message, 60),
                $item['description']
            ));
        }

        $this->newLine();
    }

    private function showDetailedAnalysis(ErrorAnalyticsService $analyticsService): void
    {
        $this->info('🔍 Detailed Analysis:');

        // Recent high severity errors
        $recentCritical = ErrorTracking::recent()
            ->highSeverity()
            ->unresolved()
            ->orderByDesc('last_occurred_at')
            ->limit(5)
            ->get();

        if ($recentCritical->isNotEmpty()) {
            $this->warn('🚨 Recent Critical Errors:');
            foreach ($recentCritical as $error) {
                $summary = $analyticsService->generateErrorSummary($error);

                $this->line(sprintf(
                    '  %s %s',
                    $error->getSeverityEmoji(),
                    $error->exception_class
                ));
                $this->line(sprintf('    Message: %s', $this->truncate($error->message, 80)));
                $this->line(sprintf('    Frequency: %s', $summary['frequency_analysis']));
                $this->line(sprintf('    Impact: %s', $summary['impact_assessment']));
                $this->line(sprintf('    Pattern: %s', $summary['pattern_analysis']));

                if (!empty($summary['recommendations'])) {
                    $this->line('    Recommendations:');
                    foreach ($summary['recommendations'] as $rec) {
                        $this->line(sprintf('      • %s', $rec));
                    }
                }

                $this->newLine();
            }
        }

        // Show frequently occurring errors
        $frequent = ErrorTracking::frequent(20)
            ->unresolved()
            ->orderByDesc('occurrence_count')
            ->limit(3)
            ->get();

        if ($frequent->isNotEmpty()) {
            $this->info('🔄 Most Frequent Errors:');
            foreach ($frequent as $error) {
                $this->line(sprintf(
                    '  %s %s - %s times',
                    $error->getSeverityEmoji(),
                    substr($error->exception_class, strrpos($error->exception_class, '\\') + 1),
                    $error->occurrence_count
                ));
                $this->line(sprintf('    %s', $this->truncate($error->message, 80)));
                $this->line(sprintf('    First seen: %s', $error->first_occurred_at->diffForHumans()));
                $this->line(sprintf('    Last seen: %s', $error->last_occurred_at->diffForHumans()));
                $this->newLine();
            }
        }
    }

    private function handleCleanup(ErrorAnalyticsService $analyticsService): int
    {
        $days = (int) $this->option('cleanup-days');

        if ($days <= 0) {
            $this->error('Cleanup days must be a positive integer');
            return Command::FAILURE;
        }

        $this->info("🧹 Cleaning up resolved errors older than {$days} days...");

        try {
            $startTime = microtime(true);

            // Use chunked processing for large datasets to prevent memory issues
            $deletedCount = 0;
            $chunkSize = 1000;

            ErrorTracking::resolved()
                ->where('last_occurred_at', '<', now()->subDays($days))
                ->chunk($chunkSize, function ($errors) use (&$deletedCount) {
                    $deletedCount += $errors->count();
                    $errors->each->delete();
                });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("✅ Cleaned up {$deletedCount} resolved errors in {$executionTime}ms");

            // Optimize database after cleanup
            if ($deletedCount > 0) {
                $this->comment('Optimizing database tables...');
                // This is database-specific, but most support this
                try {
                    \DB::statement('OPTIMIZE TABLE error_trackings');
                    $this->info('✅ Database optimized');
                } catch (\Exception $e) {
                    $this->comment('Database optimization skipped (not supported)');
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }
}
