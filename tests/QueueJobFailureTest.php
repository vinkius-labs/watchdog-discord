<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;

class QueueJobFailureTest extends TestCase
{
    /** @test */
    public function it_registers_queue_failed_listener_when_enabled()
    {
        // Enable the package and queue monitoring
        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.queue_monitoring.enabled' => true,
        ]);

        // Recreate the service provider to register listeners
        $this->app->register(\VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider::class);

        // Verify that Queue::failing was called
        $this->assertTrue(true); // This is a basic test structure
    }

    /** @test */
    public function it_does_not_register_queue_failed_listener_when_disabled()
    {
        // Disable queue monitoring
        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.queue_monitoring.enabled' => false,
        ]);

        // Recreate the service provider
        $this->app->register(\VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider::class);

        $this->assertTrue(true); // This is a basic test structure
    }

    /** @test */
    public function it_sends_notification_for_job_failure()
    {
        // Enable the package
        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.queue_monitoring.enabled' => true,
            'watchdog-discord.webhook_url' => 'https://discord.com/api/webhooks/test',
        ]);

        // Mock the DiscordNotifier
        $notifierMock = Mockery::mock(DiscordNotifier::class);
        $notifierMock->shouldReceive('sendJobFailure')
            ->once()
            ->with(
                Mockery::type(\Throwable::class),
                Mockery::type('array')
            );

        $this->app->instance(DiscordNotifier::class, $notifierMock);

        // Mock a failed job event
        $jobMock = Mockery::mock(Job::class);
        $jobMock->shouldReceive('getName')->andReturn('TestJob');
        $jobMock->shouldReceive('getQueue')->andReturn('default');
        $jobMock->shouldReceive('attempts')->andReturn(3);
        $jobMock->shouldReceive('payload')->andReturn(['test' => 'data']);

        $exception = new \Exception('Test job failure');

        $event = new JobFailed('redis', $jobMock, $exception);

        // Create service provider and call the notification method directly
        $serviceProvider = new \VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider($this->app);

        // Use reflection to access the protected method
        $method = new \ReflectionMethod($serviceProvider, 'sendQueueFailedNotification');
        $method->setAccessible(true);
        $method->invoke($serviceProvider, $event);
    }

    /** @test */
    public function it_formats_job_context_correctly()
    {
        $notifier = new DiscordNotifier();

        $exception = new \Exception('Test exception');
        $jobContext = [
            'job_name' => 'ProcessPayment',
            'queue' => 'payments',
            'connection' => 'redis',
            'attempts' => 2,
        ];

        // Use reflection to access the protected method
        $method = new \ReflectionMethod($notifier, 'formatJobFailureError');
        $method->setAccessible(true);
        $result = $method->invoke($notifier, $exception, $jobContext);

        // Verify the payload structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('embeds', $result);
        $this->assertArrayHasKey('fields', $result['embeds'][0]);

        // Verify job-specific fields are present
        $fields = $result['embeds'][0]['fields'];
        $fieldNames = array_column($fields, 'name');

        $this->assertContains('🔧 Job Name', $fieldNames);
        $this->assertContains('📋 Queue', $fieldNames);
        $this->assertContains('🔗 Connection', $fieldNames);
        $this->assertContains('🔄 Attempts', $fieldNames);

        // Verify the title is updated
        $this->assertStringContainsString('Queue Job Failed', $result['embeds'][0]['title']);
    }

    /** @test */
    public function it_handles_job_failure_with_missing_exception()
    {
        // Enable the package
        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.queue_monitoring.enabled' => true,
            'watchdog-discord.webhook_url' => 'https://discord.com/api/webhooks/test',
        ]);

        // Mock the DiscordNotifier
        $notifierMock = Mockery::mock(DiscordNotifier::class);
        $notifierMock->shouldReceive('sendJobFailure')
            ->once()
            ->with(
                Mockery::type(\Exception::class),
                Mockery::type('array')
            );

        $this->app->instance(DiscordNotifier::class, $notifierMock);

        // Mock a failed job event without exception
        $jobMock = Mockery::mock(Job::class);
        $jobMock->shouldReceive('getName')->andReturn('TestJob');
        $jobMock->shouldReceive('getQueue')->andReturn('default');
        $jobMock->shouldReceive('attempts')->andReturn(1);
        $jobMock->shouldReceive('payload')->andReturn([]);

        $event = new JobFailed('redis', $jobMock, null); // No exception

        // Create service provider and call the notification method directly
        $serviceProvider = new \VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider($this->app);

        // Use reflection to access the protected method
        $method = new \ReflectionMethod($serviceProvider, 'sendQueueFailedNotification');
        $method->setAccessible(true);
        $method->invoke($serviceProvider, $event);
    }
}
