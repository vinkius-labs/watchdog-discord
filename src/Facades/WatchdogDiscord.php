<?php

namespace VinkiusLabs\WatchdogDiscord\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void send(\Throwable $exception)
 * @method static void sendLog(string $level, string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void emergency(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 * @method static bool isEnabled()
 * @method static void disable()
 * @method static void enable()
 *
 * @see \VinkiusLabs\WatchdogDiscord\DiscordNotifier
 */
class WatchdogDiscord extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'watchdog-discord';
    }
}
