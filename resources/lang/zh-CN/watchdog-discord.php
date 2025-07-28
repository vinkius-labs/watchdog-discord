<?php

return [
    'notifications' => [
        'error_title' => '错误通知',
        'job_error_title' => '队列任务失败',
        'log_title' => '日志 :level',
        'fields' => [
            'environment' => '环境',
            'application' => '应用程序',
            'error_type' => '错误类型',
            'file' => '文件',
            'line' => '行',
            'url' => 'URL',
            'method' => '方法',
            'ip' => 'IP',
            'user_id' => '用户ID',
            'user_agent' => '用户代理',
            'stack_trace' => '堆栈跟踪',
            'frequency' => '频率',
            'severity' => '严重性',
        ],
    ],
    'commands' => [
        'test' => [
            'sending_exception' => '发送测试异常通知...',
            'sending_log' => '发送测试 :level 通知...',
            'success' => '✅ 测试通知发送成功！',
            'check_discord' => '请检查您的Discord频道以查看是否收到消息。',
            'not_enabled' => 'Watchdog Discord未启用。请在您的 .env 文件中配置 WATCHDOG_DISCORD_ENABLED=true。',
            'no_webhook' => 'Discord webhook URL 未配置。请在您的 .env 文件中配置 WATCHDOG_DISCORD_WEBHOOK_URL。',
        ],
    ],
];
