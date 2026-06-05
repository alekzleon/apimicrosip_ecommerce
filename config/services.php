<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    'internal_api' => [
        'key' => env('INTERNAL_API_KEY'),
    ],

    'ecommerce_api' => [
        'base_url' => env('ECOMMERCE_API_URL', 'http://host.docker.internal:8001'),
        'login' => env('ECOMMERCE_API_LOGIN', env('ECOMMERCE_API_EMAIL')),
        'email' => env('ECOMMERCE_API_EMAIL'),
        'password' => env('ECOMMERCE_API_PASSWORD'),
        'device_name' => env('ECOMMERCE_API_DEVICE_NAME', 'sync-microsip'),
    ],

    'ecommerce_sync' => [
        'batch_limit' => env('SYNC_BATCH_LIMIT', 100),
        'http_chunk_size' => env('SYNC_HTTP_CHUNK_SIZE', 100),
    ],

    'sales_documents_sync' => [
        'per_page' => env('SALES_DOCUMENTS_SYNC_PER_PAGE', 50),
    ],

];
