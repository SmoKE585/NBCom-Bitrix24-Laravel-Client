# Кэширование

Пакет кэширует только некритичные чтения. Уведомления и поиск не кэшируются.

## Автоопределение

```dotenv
BITRIX24_CACHE_ENABLED=auto
BITRIX24_CACHE_TTL=300
```

`auto` включает кэш, если `config('cache.default')` не `array` и не `null`.
Это значит, что в проекте с `CACHE_STORE=file`, `redis`, `database` кэш
заработает без дополнительной настройки. В тестах с `array` — нет.

Проверить:

```php
$info = Bitrix24::cacheInfo();

$info->enabled;
$info->store;
$info->ttl;
```

## Что кэшируется

- `users()->get($id)`
- `users()->findByEmail($email)`
- `users()->findByLogin($login)`
- `users()->current()`
- `departments()->get($id)`
- `departments()->all()`
- `departments()->tree()`

Не кэшируются:

- `users()->search()`
- `users()->inDepartment()`
- `notifications()`
- `health()` и `capabilities`

## Ключи

Ключи включают короткий fingerprint портала и токена, поэтому два разных
webhook в одном приложении не пересекаются. Префикс — `bitrix24:{hash}:`.

## Свежие данные

```php
$user = Bitrix24::users()->fresh()->get(5);
$department = Bitrix24::departments()->fresh()->get(15);
$tree = Bitrix24::departments()->fresh()->tree();
```

`fresh()` обходит чтение из кэша, но записывает свежее значение обратно.
Это удобно для фоновых синхронизаций и критичных сценариев.

Также можно передать `fresh: true` точечно:

```php
Bitrix24::users()->get(5, fresh: true);
Bitrix24::departments()->tree(refresh: true);
```

## Инвалидация

Автоматической инвалидации нет: TTL и `fresh()` — основные механизмы.
`php artisan bitrix24:forget` очищает только snapshot прав, не данные.
