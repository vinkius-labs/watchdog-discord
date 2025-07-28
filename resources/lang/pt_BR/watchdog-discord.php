<?php

return [
    'notifications' => [
        'error_title' => 'Notificação de Erro',
        'job_error_title' => 'Falha em Job de Fila',
        'log_title' => 'Log :level',
        'fields' => [
            'environment' => 'Ambiente',
            'application' => 'Aplicação',
            'error_type' => 'Tipo de Erro',
            'file' => 'Arquivo',
            'line' => 'Linha',
            'url' => 'URL',
            'method' => 'Método',
            'ip' => 'IP',
            'user_id' => 'ID do Usuário',
            'user_agent' => 'User Agent',
            'stack_trace' => 'Stack Trace',
            'frequency' => 'Frequência',
            'severity' => 'Severidade',
        ],
    ],
    'commands' => [
        'test' => [
            'sending_exception' => 'Enviando notificação de teste de exceção...',
            'sending_log' => 'Enviando notificação de teste :level...',
            'success' => '✅ Notificação de teste enviada com sucesso!',
            'check_discord' => 'Verifique seu canal do Discord para ver se a mensagem foi recebida.',
            'not_enabled' => 'Watchdog Discord não está habilitado. Por favor, defina WATCHDOG_DISCORD_ENABLED=true no seu arquivo .env.',
            'no_webhook' => 'URL do webhook do Discord não está configurada. Por favor, defina WATCHDOG_DISCORD_WEBHOOK_URL no seu arquivo .env.',
        ],
    ],
];
