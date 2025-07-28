<?php

return [
    'notifications' => [
        'error_title' => 'Notifica di Errore',
        'job_error_title' => 'Job in Coda Fallito',
        'log_title' => 'Log :level',
        'fields' => [
            'environment' => 'Ambiente',
            'application' => 'Applicazione',
            'error_type' => 'Tipo di Errore',
            'file' => 'File',
            'line' => 'Riga',
            'url' => 'URL',
            'method' => 'Metodo',
            'ip' => 'IP',
            'user_id' => 'ID Utente',
            'user_agent' => 'User Agent',
            'stack_trace' => 'Stack Trace',
            'frequency' => 'Frequenza',
            'severity' => 'Gravità',
        ],
    ],
    'commands' => [
        'test' => [
            'sending_exception' => 'Invio notifica eccezione di test...',
            'sending_log' => 'Invio notifica :level di test...',
            'success' => '✅ Notifica di test inviata con successo!',
            'check_discord' => 'Controlla il tuo canale Discord per vedere se il messaggio è stato ricevuto.',
            'not_enabled' => 'Watchdog Discord non è abilitato. Configura WATCHDOG_DISCORD_ENABLED=true nel tuo file .env.',
            'no_webhook' => 'URL webhook Discord non configurato. Configura WATCHDOG_DISCORD_WEBHOOK_URL nel tuo file .env.',
        ],
    ],
];
