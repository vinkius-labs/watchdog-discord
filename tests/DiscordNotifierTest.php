<?php

namespace VinkiusLabs\WatchdogDiscord\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;
use PHPUnit\Framework\Attributes\Test;

class DiscordNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected DiscordNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'watchdog-discord.enabled' => true,
            'watchdog-discord.webhook_url' => 'https://discord.com/api/webhooks/test/webhook/url',
        ]);

        $this->notifier = new DiscordNotifier();
    }

    #[Test]
    public function it_can_send_error_notifications_synchronously()
    {
        Http::fake();

        $exception = new \Exception('Test exception');
        $this->notifier->send($exception);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $expectedTitle = '🚨 Error Notification';
            return $request->url() === 'https://discord.com/api/webhooks/test/webhook/url'
                && $request->data()['embeds'][0]['title'] === $expectedTitle;
        });
    }

    #[Test]
    public function it_can_build_mentions()
    {
        config([
            'watchdog-discord.message.mentions' => ['123456789012345678', '@&987654321098765432'],
        ]);

        $reflection = new \ReflectionClass($this->notifier);
        $method = $reflection->getMethod('getMentions');
        $method->setAccessible(true);

        $mentions = $method->invoke($this->notifier);

        $this->assertEquals('<@123456789012345678> @&987654321098765432 ', $mentions);
    }

    #[Test]
    public function it_can_build_mentions_with_empty_values()
    {
        config([
            'watchdog-discord.message.mentions' => ['123456789012345678'],
        ]);

        $reflection = new \ReflectionClass($this->notifier);
        $method = $reflection->getMethod('getMentions');
        $method->setAccessible(true);

        $mentions = $method->invoke($this->notifier);

        $this->assertEquals('<@123456789012345678> ', $mentions);
    }

    #[Test]
    public function it_can_send_log_notifications()
    {
        Http::fake();

        $this->notifier->sendLog('error', 'Test log message', ['key' => 'value']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $expectedTitle = '🚨 Error Log';
            return $request->url() === 'https://discord.com/api/webhooks/test/webhook/url'
                && $request->data()['embeds'][0]['title'] === $expectedTitle;
        });
    }

    #[Test]
    public function it_respects_enabled_configuration()
    {
        Http::fake();

        config(['watchdog-discord.enabled' => false]);
        $notifier = new DiscordNotifier();

        $exception = new \Exception('Test exception');
        $notifier->send($exception);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_does_not_send_notification_when_disabled()
    {
        Http::fake();

        config([
            'watchdog-discord.webhook_url' => 'https://discord.com/api/webhooks/test/webhook/url',
            'watchdog-discord.enabled' => false,
        ]);

        // Create a new instance after config change
        $notifier = new DiscordNotifier();

        $exception = new \Exception('Test exception');
        $notifier->send($exception);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_does_not_send_notification_when_webhook_url_is_empty()
    {
        Http::fake();

        config(['watchdog-discord.webhook_url' => null]);

        $exception = new \Exception('Test exception');
        $this->notifier->send($exception);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_can_send_different_log_levels()
    {
        Http::fake();

        config(['watchdog-discord.log_levels' => ['info', 'warning', 'error', 'critical']]);

        $this->notifier->info('Info message');
        $this->notifier->warning('Warning message');
        $this->notifier->error('Error message');
        $this->notifier->critical('Critical message');

        Http::assertSentCount(4);
    }

    #[Test]
    public function it_filters_log_levels_based_on_configuration()
    {
        Http::fake();

        config(['watchdog-discord.log_levels' => ['error']]);

        $this->notifier->info('Info message');
        $this->notifier->warning('Warning message');
        $this->notifier->error('Error message');

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_can_disable_and_enable_dynamically()
    {
        Http::fake();

        $this->assertTrue($this->notifier->isEnabled());

        $this->notifier->disable();
        $this->assertFalse($this->notifier->isEnabled());

        $exception = new \Exception('Test exception');
        $this->notifier->send($exception);

        Http::assertNothingSent();

        $this->notifier->enable();
        $this->assertTrue($this->notifier->isEnabled());

        $this->notifier->send($exception);

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_handles_rate_limiting()
    {
        Http::fake();

        config([
            'watchdog-discord.rate_limiting.enabled' => true,
            'watchdog-discord.rate_limiting.max_notifications' => 2,
        ]);

        $exception = new \Exception('Test exception');

        // Send 3 notifications, but only 2 should go through due to rate limiting
        $this->notifier->send($exception);
        $this->notifier->send($exception);
        $this->notifier->send($exception);

        Http::assertSentCount(2);
    }

    #[Test]
    public function it_can_ignore_specific_exceptions()
    {
        Http::fake();

        config([
            'watchdog-discord.ignore_exceptions' => [\InvalidArgumentException::class],
        ]);

        $ignoredException = new \InvalidArgumentException('This should be ignored');
        $this->notifier->send($ignoredException);

        $regularException = new \Exception('This should be sent');
        $this->notifier->send($regularException);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return str_contains($request->data()['embeds'][0]['description'], 'This should be sent');
        });
    }

    #[Test]
    public function it_includes_environment_in_payload()
    {
        Http::fake();

        app()->detectEnvironment(function () {
            return 'testing';
        });

        $exception = new \Exception('Test exception');
        $this->notifier->send($exception);

        Http::assertSent(function (Request $request) {
            $fields = $request->data()['embeds'][0]['fields'];
            $environmentFieldName = 'Environment';
            foreach ($fields as $field) {
                if ($field['name'] === $environmentFieldName && $field['value'] === 'Testing') {
                    return true;
                }
            }

            return false;
        });
    }

    #[Test]
    public function it_includes_request_data_when_enabled()
    {
        Http::fake();

        config(['watchdog-discord.include_request_data' => true]);

        $exception = new \Exception('Test exception');
        $this->notifier->send($exception);

        Http::assertSent(function (Request $request) {
            $fields = $request->data()['embeds'][0]['fields'];
            $methodFieldName = 'Method';
            foreach ($fields as $field) {
                if ($field['name'] === $methodFieldName) {
                    return true;
                }
            }

            return false;
        });
    }

    #[Test]
    public function it_has_helper_methods_for_log_levels()
    {
        Http::fake();

        config(['watchdog-discord.log_levels' => ['info', 'warning', 'error']]);

        $this->notifier->info('Info message');
        $this->notifier->warning('Warning message');
        $this->notifier->error('Error message');

        Http::assertSentCount(3);
    }

    #[Test]
    public function it_truncates_long_field_values()
    {
        config([
            'watchdog-discord.formatting.max_field_length' => 10,
        ]);

        $reflection = new \ReflectionClass($this->notifier);
        $method = $reflection->getMethod('truncateField');
        $method->setAccessible(true);

        $longText = 'This is a very long text that should be truncated';
        $result = $method->invoke($this->notifier, $longText);

        $this->assertEquals('This is...', $result);
        $this->assertEquals(10, strlen($result)); // 7 characters + 3 dots
    }

    #[Test]
    public function it_handles_null_values_in_truncate_field()
    {
        $reflection = new \ReflectionClass($this->notifier);
        $method = $reflection->getMethod('truncateField');
        $method->setAccessible(true);

        $result = $method->invoke($this->notifier, null);

        $this->assertEquals('N/A', $result);
    }

    #[Test]
    public function it_gets_correct_colors_for_log_levels()
    {
        $reflection = new \ReflectionClass($this->notifier);
        $method = $reflection->getMethod('getColorForLevel');
        $method->setAccessible(true);

        $errorColor = $method->invoke($this->notifier, 'error');
        $warningColor = $method->invoke($this->notifier, 'warning');
        $infoColor = $method->invoke($this->notifier, 'info');

        $this->assertEquals(0xFF0000, $errorColor); // Red
        $this->assertEquals(0xFFA500, $warningColor); // Orange
        $this->assertEquals(0x00BFFF, $infoColor); // Deep Sky Blue
    }

    #[Test]
    public function it_can_send_job_failure_notifications()
    {
        Http::fake();

        $exception = new \Exception('Job failed exception');
        $jobContext = [
            'job_name' => 'ProcessPayment',
            'queue' => 'payments',
            'connection' => 'redis',
            'attempts' => 3,
        ];

        $this->notifier->sendJobFailure($exception, $jobContext);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $embeds = $request->data()['embeds'];
            $fields = $embeds[0]['fields'];
            $fieldNames = array_column($fields, 'name');

            return $request->url() === 'https://discord.com/api/webhooks/test/webhook/url'
                && str_contains($embeds[0]['title'], 'Queue Job Failed')
                && in_array('🔧 Job Name', $fieldNames)
                && in_array('📋 Queue', $fieldNames)
                && in_array('🔗 Connection', $fieldNames)
                && in_array('🔄 Attempts', $fieldNames);
        });
    }

    #[Test]
    public function it_handles_job_failure_without_context()
    {
        Http::fake();

        $exception = new \Exception('Job failed without context');
        $this->notifier->sendJobFailure($exception, []);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $embeds = $request->data()['embeds'];
            
            // Should fall back to regular error notification when no job context
            return $request->url() === 'https://discord.com/api/webhooks/test/webhook/url'
                && str_contains($embeds[0]['title'], 'Error Notification');
        });
    }

    #[Test]
    public function it_formats_job_context_correctly()
    {
        $exception = new \Exception('Test exception');
        $jobContext = [
            'job_name' => 'ProcessPayment',
            'queue' => 'payments',
            'connection' => 'redis',
            'attempts' => 2,
        ];

        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($this->notifier);
        $method = $reflection->getMethod('formatJobFailureError');
        $method->setAccessible(true);
        $result = $method->invoke($this->notifier, $exception, $jobContext);

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

        // Verify the title is updated for job failures
        $this->assertStringContainsString('Queue Job Failed', $result['embeds'][0]['title']);
        
        // Verify color is red for job failures
        $this->assertEquals(0xFF0000, $result['embeds'][0]['color']);
    }
}
