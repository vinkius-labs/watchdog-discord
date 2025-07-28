<?php

return [
    'notifications' => [
        'error_title' => 'Уведомление об Ошибке',
        'job_error_title' => 'Сбой задания очереди',
        'log_title' => 'Журнал :level',
        'fields' => [
            'environment' => 'Окружение',
            'application' => 'Приложение',
            'error_type' => 'Тип Ошибки',
            'file' => 'Файл',
            'line' => 'Строка',
            'url' => 'URL',
            'method' => 'Метод',
            'ip' => 'IP',
            'user_id' => 'ID Пользователя',
            'user_agent' => 'User Agent',
            'stack_trace' => 'Стек Вызовов',
            'frequency' => 'Частота',
            'severity' => 'Серьёзность',
        ],
    ],
    'commands' => [
        'test' => [
            'sending_exception' => 'Отправка тестового уведомления об исключении...',
            'sending_log' => 'Отправка тестового уведомления :level...',
            'success' => '✅ Тестовое уведомление успешно отправлено!',
            'check_discord' => 'Проверьте ваш канал Discord, чтобы увидеть, было ли получено сообщение.',
            'not_enabled' => 'Watchdog Discord не включен. Пожалуйста, настройте WATCHDOG_DISCORD_ENABLED=true в вашем файле .env.',
            'no_webhook' => 'URL вебхука Discord не настроен. Пожалуйста, настройте WATCHDOG_DISCORD_WEBHOOK_URL в вашем файле .env.',
        ],
    ],
];
