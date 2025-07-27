<?php

return [
    'notifications' => [
        'error_title' => 'エラー通知',
        'log_title' => 'ログ :level',
        'fields' => [
            'environment' => '環境',
            'application' => 'アプリケーション',
            'error_type' => 'エラータイプ',
            'file' => 'ファイル',
            'line' => '行',
            'url' => 'URL',
            'method' => 'メソッド',
            'ip' => 'IP',
            'user_id' => 'ユーザーID',
            'user_agent' => 'ユーザーエージェント',
            'stack_trace' => 'スタックトレース',
        ],
    ],
    'commands' => [
        'test' => [
            'sending_exception' => 'テスト例外通知を送信中...',
            'sending_log' => 'テスト :level 通知を送信中...',
            'success' => '✅ テスト通知が正常に送信されました！',
            'check_discord' => 'メッセージが受信されたかDiscordチャンネルを確認してください。',
            'not_enabled' => 'Watchdog Discordが有効になっていません。.envファイルでWATCHDOG_DISCORD_ENABLED=trueを設定してください。',
            'no_webhook' => 'Discord webhook URLが設定されていません。.envファイルでWATCHDOG_DISCORD_WEBHOOK_URLを設定してください。',
        ],
    ],
];
