<?php

return [
    'notifications' => [
        'error_title' => 'Notificación de Error',
        'log_title' => 'Log de :level',
        'fields' => [
            'environment' => 'Entorno',
            'application' => 'Aplicación',
            'error_type' => 'Tipo de Error',
            'file' => 'Archivo',
            'line' => 'Línea',
            'url' => 'URL',
            'method' => 'Método',
            'ip' => 'IP',
            'user_id' => 'ID de Usuario',
            'user_agent' => 'Agente de Usuario',
            'stack_trace' => 'Rastreo de Pila',
        ],
    ],
    'commands' => [
        'test' => [
            'sending_exception' => 'Enviando notificación de excepción de prueba...',
            'sending_log' => 'Enviando notificación de :level de prueba...',
            'success' => '✅ ¡Notificación de prueba enviada con éxito!',
            'check_discord' => 'Verifica tu canal de Discord para ver si el mensaje fue recibido.',
            'not_enabled' => 'Watchdog Discord no está habilitado. Por favor configura WATCHDOG_DISCORD_ENABLED=true en tu archivo .env.',
            'no_webhook' => 'La URL del webhook de Discord no está configurada. Por favor configura WATCHDOG_DISCORD_WEBHOOK_URL en tu archivo .env.',
        ],
    ],
];
