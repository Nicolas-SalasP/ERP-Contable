<?php

return [

    'tenri_web' => [
        'base_url' => env('TENRI_WEB_BASE_URL', 'http://localhost:8000'),
        'api_key' => env('ERP_INTEGRATION_KEY'),
        'web_integration_key' => env('WEB_INTEGRATION_KEY'),
        // S-6: el canal interno autentica solo con firma HMAC. La llave estática
        // legacy (X-WEB-API-KEY) queda deshabilitada por defecto; solo se acepta si
        // se activa explícitamente este flag durante una migración coordinada.
        'allow_legacy_key' => env('WEB_INTEGRATION_ALLOW_LEGACY_KEY', false),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];