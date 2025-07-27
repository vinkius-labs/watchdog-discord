<?php

namespace VinkiusLabs\WatchdogDiscord\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord;

class WatchdogDiscordMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $level = 'info')
    {
        $startTime = microtime(true);

        try {
            $response = $next($request);

            // Log successful requests if configured
            if (config('watchdog-discord.log_requests.enabled', false)) {
                $this->logRequest($request, $response, $startTime, $level);
            }

            return $response;
        } catch (\Throwable $exception) {
            // Log the exception (this will be handled by the global exception handler too)
            $this->logException($request, $exception, $startTime);

            throw $exception;
        }
    }

    /**
     * Log successful request
     *
     * @param  mixed  $response
     */
    protected function logRequest(Request $request, $response, float $startTime, string $level): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $statusCode = $response instanceof Response ? $response->getStatusCode() : 200;

        // Only log if it meets the criteria
        if (! $this->shouldLogRequest($request, $statusCode, $duration)) {
            return;
        }

        WatchdogDiscord::sendLog($level, 'Request completed', [
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Log exception from request
     */
    protected function logException(Request $request, \Throwable $exception, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        WatchdogDiscord::sendLog('error', 'Request failed with exception', [
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'duration_ms' => $duration,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Determine if request should be logged
     */
    protected function shouldLogRequest(Request $request, int $statusCode, float $duration): bool
    {
        $config = config('watchdog-discord.log_requests', []);

        // Check status code filters
        if (isset($config['status_codes']) && ! in_array($statusCode, $config['status_codes'])) {
            return false;
        }

        // Check minimum duration filter
        if (isset($config['min_duration_ms']) && $duration < $config['min_duration_ms']) {
            return false;
        }

        // Check route exclusions
        if (isset($config['exclude_routes']) && in_array($request->route()?->getName(), $config['exclude_routes'])) {
            return false;
        }

        // Check path exclusions
        if (isset($config['exclude_paths'])) {
            foreach ($config['exclude_paths'] as $pattern) {
                if (fnmatch($pattern, $request->path())) {
                    return false;
                }
            }
        }

        return true;
    }
}
