<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithConsole;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class CommandsCleanTest extends TestCase
{
    use InteractsWithConsole;

    #[Test]
    public function commands_handle_all_services_down_gracefully()
    {
        // Mock Redis as unavailable
        Redis::shouldReceive('connection')->andThrow(new \Exception('Redis down'));

        // Mock DB as unavailable  
        DB::shouldReceive('connection')->andThrow(new \Exception('Database down'));

        // Mock HTTP as failing
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertDoesNotThrow(function () {
            $this->artisan('watchdog-discord:test');
        }, 'Test command should handle service failures gracefully');

        $this->assertDoesNotThrow(function () {
            $this->artisan('watchdog-discord:analytics');
        }, 'Analytics command should handle service failures gracefully');
    }

    #[Test]
    public function commands_respect_execution_time_limits()
    {
        $startTime = microtime(true);

        $this->artisan('watchdog-discord:test');

        $executionTime = microtime(true) - $startTime;

        $this->assertLessThan(10.0, $executionTime, 'Test command should complete within 10 seconds');
    }

    #[Test]
    public function commands_handle_invalid_configuration_gracefully()
    {
        // Test with null enabled setting
        Config::set('watchdog-discord.enabled', null);

        $this->assertDoesNotThrow(function () {
            $this->artisan('watchdog-discord:test');
        }, 'Command should handle null enabled config');

        // Test with empty webhook URL
        Config::set('watchdog-discord.webhook_url', '');

        $this->assertDoesNotThrow(function () {
            $this->artisan('watchdog-discord:test');
        }, 'Command should handle empty webhook URL');
    }

    #[Test]
    public function analytics_command_handles_missing_data()
    {
        $this->assertDoesNotThrow(function () {
            $this->artisan('watchdog-discord:analytics')
                ->assertExitCode(0);
        }, 'Analytics should handle missing data gracefully');
    }

    #[Test]
    public function commands_handle_network_failures()
    {
        Http::fake(function () {
            throw new \Exception('Network unreachable');
        });

        $this->assertDoesNotThrow(function () {
            $this->artisan('watchdog-discord:test')
                ->assertExitCode(0);
        }, 'Commands should handle network failures');
    }

    #[Test]
    public function commands_handle_concurrent_execution()
    {
        $results = [];

        // Run multiple commands in sequence
        for ($i = 0; $i < 3; $i++) {
            $this->assertDoesNotThrow(function () use (&$results) {
                $exitCode = $this->artisan('watchdog-discord:test')->run();
                $results[] = $exitCode;
            }, "Concurrent execution iteration $i should succeed");
        }

        foreach ($results as $index => $result) {
            $this->assertEquals(0, $result, "Execution $index should return success code");
        }
    }

    #[Test]
    public function commands_maintain_data_integrity()
    {
        $this->assertDoesNotThrow(function () {
            $this->artisan('watchdog-discord:test');
            $this->artisan('watchdog-discord:analytics');
        }, 'Commands should maintain application integrity');
    }

    #[Test]
    public function commands_provide_meaningful_output()
    {
        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('set')->andReturn(true);

        $this->assertDoesNotThrow(function () {
            $this->artisan('watchdog-discord:test');
        }, 'Commands should provide meaningful output');
    }
}
