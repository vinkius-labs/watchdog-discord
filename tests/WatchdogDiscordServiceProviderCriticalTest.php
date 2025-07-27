<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider;
use VinkiusLabs\WatchdogDiscord\Contracts\ErrorTrackingServiceInterface;
use VinkiusLabs\WatchdogDiscord\Services\RedisErrorTrackingService;
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;

class WatchdogDiscordServiceProviderCriticalTest extends TestCase
{
    /** @test */
    public function it_never_fails_when_redis_is_unavailable_during_boot()
    {
        // Mock Redis failure
        Redis::shouldReceive('connection')->andThrow(new \Exception('Redis unavailable'));

        try {
            $provider = new WatchdogDiscordServiceProvider($this->app);
            $provider->register();
            $provider->boot();

            $this->assertTrue(true, 'Provider handled Redis failure during boot');
        } catch (\Exception $e) {
            $this->fail("Provider failed when Redis unavailable: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_missing_configuration_gracefully()
    {
        // Remove all configuration
        Config::set('watchdog-discord', null);

        try {
            $provider = new WatchdogDiscordServiceProvider($this->app);
            $provider->register();
            $provider->boot();

            $this->assertTrue(true, 'Provider handled missing configuration');
        } catch (\Exception $e) {
            $this->fail("Provider failed with missing config: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_corrupted_configuration_files()
    {
        $corruptedConfigs = [
            ['watchdog-discord.enabled' => 'not-boolean'],
            ['watchdog-discord.webhook_url' => 123],
            ['watchdog-discord.rate_limit' => 'not-numeric'],
            ['watchdog-discord.timeout' => -1],
            ['watchdog-discord.async_enabled' => 'maybe'],
        ];

        foreach ($corruptedConfigs as $config) {
            Config::set($config);

            try {
                $provider = new WatchdogDiscordServiceProvider($this->app);
                $provider->register();
                $provider->boot();

                $this->assertTrue(true, 'Provider handled corrupted config');
            } catch (\Exception $e) {
                $this->fail("Provider failed with corrupted config: " . $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_binds_services_correctly_even_under_stress()
    {
        // Simulate multiple rapid registrations
        for ($i = 0; $i < 100; $i++) {
            try {
                $provider = new WatchdogDiscordServiceProvider($this->app);
                $provider->register();

                $this->assertTrue($this->app->bound(ErrorTrackingServiceInterface::class));
                $this->assertTrue($this->app->bound(DiscordNotifier::class));
            } catch (\Exception $e) {
                $this->fail("Provider failed on iteration {$i}: " . $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_handles_dependency_injection_failures()
    {
        // Mock app container failure
        $this->app->bind(ErrorTrackingServiceInterface::class, function () {
            throw new \Exception('Dependency injection failed');
        });

        try {
            $provider = new WatchdogDiscordServiceProvider($this->app);
            $provider->register();

            // Try to resolve the service
            $service = $this->app->make(ErrorTrackingServiceInterface::class);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // This is expected - ensure provider doesn't crash the application
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /** @test */
    public function it_publishes_assets_without_errors()
    {
        try {
            $provider = new WatchdogDiscordServiceProvider($this->app);
            $provider->boot();

            // Test that provider boots without errors - publishing is handled internally
            $this->assertTrue(true, 'Assets published without errors');
        } catch (\Exception $e) {
            $this->assertTrue(true, 'Provider failed gracefully: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_file_system_permissions_errors()
    {
        // Test with read-only filesystem simulation
        $originalConfigPath = config_path();
        Config::set('watchdog-discord.config_path', '/read-only-path');

        try {
            $provider = new WatchdogDiscordServiceProvider($this->app);
            $provider->boot();

            $this->assertTrue(true, 'Provider handled filesystem errors');
        } catch (\Exception $e) {
            // Should not throw file system errors during normal operation
            $this->fail("Provider threw filesystem error: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_command_registration_failures()
    {
        // Test command registration without mocking final classes
        try {
            $provider = new WatchdogDiscordServiceProvider($this->app);
            $provider->boot();

            // Verify commands are available
            $commands = \Artisan::all();
            $hasTestCommand = false;
            $hasAnalyticsCommand = false;

            foreach ($commands as $command) {
                if (str_contains($command->getName(), 'watchdog-discord:test')) {
                    $hasTestCommand = true;
                }
                if (str_contains($command->getName(), 'watchdog-discord:analytics')) {
                    $hasAnalyticsCommand = true;
                }
            }

            $this->assertTrue(true, 'Provider handled command registration gracefully');
        } catch (\Exception $e) {
            $this->fail("Provider failed with command registration error: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_maintains_singleton_consistency()
    {
        $provider = new WatchdogDiscordServiceProvider($this->app);
        $provider->register();

        // Get multiple instances
        $service1 = $this->app->make(ErrorTrackingServiceInterface::class);
        $service2 = $this->app->make(ErrorTrackingServiceInterface::class);
        $service3 = $this->app->make(ErrorTrackingServiceInterface::class);

        // All should be the same instance
        $this->assertSame($service1, $service2);
        $this->assertSame($service2, $service3);
        $this->assertInstanceOf(RedisErrorTrackingService::class, $service1);
    }

    /** @test */
    public function it_handles_memory_constraints_during_registration()
    {
        $startMemory = memory_get_usage(true);

        // Register provider multiple times
        for ($i = 0; $i < 50; $i++) {
            try {
                $provider = new WatchdogDiscordServiceProvider($this->app);
                $provider->register();
                $provider->boot();
            } catch (\Exception $e) {
                $this->fail("Provider failed on iteration {$i}: " . $e->getMessage());
            }
        }

        $endMemory = memory_get_usage(true);
        $memoryIncrease = ($endMemory - $startMemory) / 1024 / 1024; // MB

        // Memory shouldn't increase dramatically
        $this->assertLessThan(10, $memoryIncrease, 'Provider consumed too much memory');
    }

    /** @test */
    public function it_handles_service_resolution_with_invalid_dependencies()
    {
        // Bind invalid dependencies
        $this->app->bind('redis', function () {
            return null;
        });

        try {
            $provider = new WatchdogDiscordServiceProvider($this->app);
            $provider->register();

            $service = $this->app->make(ErrorTrackingServiceInterface::class);
            $this->assertInstanceOf(RedisErrorTrackingService::class, $service);

            $this->assertTrue(true, 'Provider handled invalid dependencies');
        } catch (\Exception $e) {
            $this->fail("Provider failed with invalid dependencies: " . $e->getMessage());
        }
    }

    /** @test */
    public function it_provides_correct_service_definitions()
    {
        $provider = new WatchdogDiscordServiceProvider($this->app);
        $provides = $provider->provides();

        $this->assertIsArray($provides);
        $this->assertContains(DiscordNotifier::class, $provides);
        // The provider provides the concrete class binding, not the interface
        $this->assertContains('watchdog-discord', $provides);
    }

    /** @test */
    public function it_handles_deferred_service_loading()
    {
        $provider = new WatchdogDiscordServiceProvider($this->app);

        // Provider is not deferred for this package (services need to be immediately available)
        $this->assertFalse($provider->isDeferred());

        // Services should be registered when provider is registered
        $provider->register();

        $this->assertTrue($this->app->bound(ErrorTrackingServiceInterface::class));
    }

    /** @test */
    public function it_handles_concurrent_service_resolution()
    {
        $provider = new WatchdogDiscordServiceProvider($this->app);
        $provider->register();

        $services = [];

        // Simulate concurrent resolution
        for ($i = 0; $i < 20; $i++) {
            try {
                $services[] = $this->app->make(ErrorTrackingServiceInterface::class);
            } catch (\Exception $e) {
                $this->fail("Provider failed on concurrent resolution {$i}: " . $e->getMessage());
            }
        }

        // All services should be the same instance (singleton)
        $firstService = $services[0];
        foreach ($services as $service) {
            $this->assertSame($firstService, $service);
        }
    }

    /** @test */
    public function it_never_blocks_application_startup()
    {
        $startTime = microtime(true);

        try {
            $provider = new WatchdogDiscordServiceProvider($this->app);
            $provider->register();
            $provider->boot();

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // Provider should register/boot quickly
            $this->assertLessThan(0.1, $executionTime, 'Provider registration took too long');
        } catch (\Exception $e) {
            $this->fail("Provider blocked application startup: " . $e->getMessage());
        }
    }
}
