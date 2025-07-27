<?php

return [
    'notifications' => [
        'error_title' => 'Fehlerbenachrichtigung',
        'log_title' => 'Protokoll :level',
        'fields' => [
            'environment' => 'Umgebung',
            'application' => 'Anwendung',
            'error_type' => 'Fehlertyp',
            'file' => 'Datei',
            'line' => 'Zeile',
            'url' => 'URL',
            'method' => 'Methode',
            'ip' => 'IP',
            'user_id' => 'Benutzer-ID',
            'user_agent' => 'Benutzeragent',
            'stack_trace' => 'Stack-Trace',
        ],
    ],
    'commands' => [
        'test' => [
            'sending_exception' => 'Sende Test-Ausnahme-Benachrichtigung...',
            'sending_log' => 'Sende Test :level Benachrichtigung...',
            'success' => '✅ Test-Benachrichtigung erfolgreich gesendet!',
            'check_discord' => 'Überprüfen Sie Ihren Discord-Kanal, um zu sehen, ob die Nachricht empfangen wurde.',
            'not_enabled' => 'Watchdog Discord ist nicht aktiviert. Bitte konfigurieren Sie WATCHDOG_DISCORD_ENABLED=true in Ihrer .env-Datei.',
            'no_webhook' => 'Discord-Webhook-URL ist nicht konfiguriert. Bitte konfigurieren Sie WATCHDOG_DISCORD_WEBHOOK_URL in Ihrer .env-Datei.',
        ],
    ],
];
