<?php

return [
    'notifications' => [
        'error_title' => 'Error Notification',
        'log_title' => ':level Log',
        'fields' => [
            'environment' => 'Environment',
            'application' => 'Application',
            'error_type' => 'Error Type',
            'file' => 'File',
            'line' => 'Line',
            'url' => 'URL',
            'method' => 'Method',
            'ip' => 'IP',
            'user_id' => 'User ID',
            'user_agent' => 'User Agent',
            'stack_trace' => 'Stack Trace',
            'frequency' => 'Frequency',
            'severity' => 'Severity',
        ],
    ],
    'commands' => [
        'test' => [
            'sending_exception' => 'Sending test exception notification...',
            'sending_log' => 'Sending test :level notification...',
            'success' => '✅ Test notification sent successfully!',
            'check_discord' => 'Check your Discord channel to see if the message was received.',
            'not_enabled' => 'Watchdog Discord is not enabled. Please set WATCHDOG_DISCORD_ENABLED=true in your .env file.',
            'no_webhook' => 'Discord webhook URL is not configured. Please set WATCHDOG_DISCORD_WEBHOOK_URL in your .env file.',
        ],
    ],
];
