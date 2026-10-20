<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bitrix24 webhook endpoint
    |--------------------------------------------------------------------------
    |
    | Full inbound webhook URL:
    | https://bitrix.example.ru/rest/1/ab12cd34ef56gh78/
    |
    | The URL contains the webhook token. Keep it in .env or a secret manager,
    | never commit it to the repository.
    |
    */

    'endpoint' => env('BITRIX24_ENDPOINT'),

    /*
    |--------------------------------------------------------------------------
    | Allow plain HTTP (local development only)
    |--------------------------------------------------------------------------
    */

    'allow_http' => env('BITRIX24_ALLOW_HTTP', false),

    /*
    |--------------------------------------------------------------------------
    | Client id for TAG / SUB_TAG in webhook notifications
    |--------------------------------------------------------------------------
    |
    | Bitrix24 requires CLIENT_ID together with TAG/SUB_TAG when notifications
    | are sent from a webhook. Leave empty if you do not use tags.
    |
    */

    'client_id' => env('BITRIX24_CLIENT_ID'),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => (int) env('BITRIX24_TIMEOUT', 15),
        'connect_timeout' => (int) env('BITRIX24_CONNECT_TIMEOUT', 5),
        'throttle_rps' => (float) env('BITRIX24_THROTTLE_RPS', 5),
        'retry' => [
            'enabled' => env('BITRIX24_RETRY_ENABLED', true),
            'max_attempts' => (int) env('BITRIX24_RETRY_ATTEMPTS', 3),
            'base_delay_ms' => (int) env('BITRIX24_RETRY_BASE_DELAY_MS', 200),
            'max_delay_ms' => (int) env('BITRIX24_RETRY_MAX_DELAY_MS', 2000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Only enabled features are checked for scopes and methods. A project that
    | only reads users must not fail because the webhook has no "im" scope.
    |
    */

    'features' => [
        'users' => true,
        'departments' => true,
        'notifications' => [
            'system' => true,
            'personal' => true,
            'chat' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capabilities snapshot
    |--------------------------------------------------------------------------
    |
    | The result of scope/method checks is stored in a JSON file and reused.
    | No network call is made on every request.
    |
    */

    'capabilities' => [
        'enabled' => env('BITRIX24_CAPABILITIES_ENABLED', true),
        'store' => env('BITRIX24_CAPABILITIES_STORE', 'file'), // file|cache
        'path' => env('BITRIX24_CAPABILITIES_PATH', storage_path('app/bitrix24')),
        'ttl' => (int) env('BITRIX24_CAPABILITIES_TTL', 604800),
        'negative_ttl' => (int) env('BITRIX24_CAPABILITIES_NEGATIVE_TTL', 300),
        'fail_fast' => env('BITRIX24_FAIL_FAST', true),
        'preflight' => env('BITRIX24_PREFLIGHT', true),
        'allow_stale' => env('BITRIX24_ALLOW_STALE', true),
        'lock_timeout' => (int) env('BITRIX24_CAPABILITIES_LOCK_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Result cache
    |--------------------------------------------------------------------------
    |
    | "auto" enables caching when the application uses a real cache store.
    | The "array" and "null" stores are ignored because they do not persist
    | values between requests. Use Bitrix24::users()->fresh() to bypass cache.
    |
    */

    'cache' => [
        'enabled' => env('BITRIX24_CACHE_ENABLED', 'auto'),
        'ttl' => (int) env('BITRIX24_CACHE_TTL', 300),
        'prefix' => env('BITRIX24_CACHE_PREFIX', 'bitrix24'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'channel' => env('BITRIX24_LOG_CHANNEL'),
        'debug' => env('BITRIX24_LOG_DEBUG', false),
    ],

];
