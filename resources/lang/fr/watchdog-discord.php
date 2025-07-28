<?php

return [
    'notifications' => [
        'error_title' => 'Notification d\'Erreur',
        'job_error_title' => 'Échec du Job de File',
        'log_title' => 'Journal :level',
        'fields' => [
            'environment' => 'Environnement',
            'application' => 'Application',
            'error_type' => 'Type d\'Erreur',
            'file' => 'Fichier',
            'line' => 'Ligne',
            'url' => 'URL',
            'method' => 'Méthode',
            'ip' => 'IP',
            'user_id' => 'ID Utilisateur',
            'user_agent' => 'Agent Utilisateur',
            'stack_trace' => 'Trace de Pile',
            'frequency' => 'Fréquence',
            'severity' => 'Gravité',
        ],
    ],
    'commands' => [
        'test' => [
            'sending_exception' => 'Envoi de notification d\'exception de test...',
            'sending_log' => 'Envoi de notification :level de test...',
            'success' => '✅ Notification de test envoyée avec succès !',
            'check_discord' => 'Vérifiez votre canal Discord pour voir si le message a été reçu.',
            'not_enabled' => 'Watchdog Discord n\'est pas activé. Veuillez configurer WATCHDOG_DISCORD_ENABLED=true dans votre fichier .env.',
            'no_webhook' => 'L\'URL du webhook Discord n\'est pas configurée. Veuillez configurer WATCHDOG_DISCORD_WEBHOOK_URL dans votre fichier .env.',
        ],
    ],
];
