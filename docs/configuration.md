# Конфигурация

Публикация:

```bash
php artisan vendor:publish --tag=bitrix24-config
```

## Полный список

```php
return [
    'endpoint' => env('BITRIX24_ENDPOINT'),
    'allow_http' => env('BITRIX24_ALLOW_HTTP', false),
    'client_id' => env('BITRIX24_CLIENT_ID'),

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

    'features' => [
        'users' => true,
        'departments' => true,
        'notifications' => [
            'system' => true,
            'personal' => true,
            'chat' => true,
        ],
    ],

    'capabilities' => [
        'enabled' => env('BITRIX24_CAPABILITIES_ENABLED', true),
        'store' => env('BITRIX24_CAPABILITIES_STORE', 'file'),
        'path' => env('BITRIX24_CAPABILITIES_PATH', storage_path('app/bitrix24')),
        'ttl' => (int) env('BITRIX24_CAPABILITIES_TTL', 604800),
        'negative_ttl' => (int) env('BITRIX24_CAPABILITIES_NEGATIVE_TTL', 300),
        'fail_fast' => env('BITRIX24_FAIL_FAST', true),
        'preflight' => env('BITRIX24_PREFLIGHT', true),
        'allow_stale' => env('BITRIX24_ALLOW_STALE', true),
        'lock_timeout' => (int) env('BITRIX24_CAPABILITIES_LOCK_TIMEOUT', 10),
    ],

    'cache' => [
        'enabled' => env('BITRIX24_CACHE_ENABLED', 'auto'),
        'ttl' => (int) env('BITRIX24_CACHE_TTL', 300),
        'prefix' => env('BITRIX24_CACHE_PREFIX', 'bitrix24'),
    ],

    'logging' => [
        'channel' => env('BITRIX24_LOG_CHANNEL'),
        'debug' => env('BITRIX24_LOG_DEBUG', false),
    ],
];
```

## `features`

Включай только то, что использует проект. Требования к scopes собираются
только по включённым фичам.

Например, проекту только с пользователями не нужен scope `im`:

```php
'features' => [
    'users' => true,
    'departments' => false,
    'notifications' => [
        'system' => false,
        'personal' => false,
        'chat' => false,
    ],
],
```

## `capabilities`

- `store=file` — JSON в `storage/app/bitrix24/capabilities.json`;
- `store=cache` — Laravel cache (для read-only/эфемерного `storage`);
- `fail_fast=true` — бросать `Bitrix24CapabilityException`, если прав не хватает;
- `preflight=true` — проверять метод по snapshot перед вызовом;
- `allow_stale=true` — использовать старый snapshot, если портал временно недоступен.

## `cache`

`auto` включает кэш, если `cache.default` не `array` и не `null`.
Явно: `true`/`false`.

`BITRIX24_CLIENT_ID` обязателен только при использовании `TAG`/`SUB_TAG`
в уведомлениях через webhook.

## Обновление конфига

После изменения `.env`:

```bash
php artisan config:cache
```
