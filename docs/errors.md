# Ошибки

Все ошибки пакета наследуются от `Nbcom\Bitrix24\Exceptions\Bitrix24Exception`
и реализуют `Bitrix24ExceptionInterface`. Наружу не утекают исключения Guzzle
или Laravel HTTP Client.

## Контракт

```php
$exception->reason(): Bitrix24ErrorReason; // стабильный enum
$exception->errorCode(): string;           // сырой код Bitrix, может быть ""
$exception->errorDescription(): string;
$exception->httpStatus(): ?int;
$exception->method(): ?string;
$exception->retryAfter(): ?int;
$exception->isRetryable(): bool;
$exception->context(): array;              // без токена и PII
```

## Иерархия

```
Bitrix24Exception
├─ Bitrix24ConfigurationException
├─ Bitrix24AuthenticationException
├─ Bitrix24AccessDeniedException
├─ Bitrix24NotFoundException
├─ Bitrix24MethodNotFoundException
├─ Bitrix24ValidationException
├─ Bitrix24RateLimitException
├─ Bitrix24ServerException
├─ Bitrix24TransportException
├─ Bitrix24CapabilityException
└─ Bitrix24MalformedResponseException (алиас transport-ошибки)
```

## Маппинг

| HTTP | Код Bitrix | Исключение | `reason` | Retry |
|---|---|---|---|---|
| 401 | `INVALID_CREDENTIALS`, `NO_AUTH_FOUND`, `expired_token` | Authentication | `authentication` | нет |
| 401 | `insufficient_scope` | AccessDenied | `forbidden` | нет |
| 401/403 | `ACCESS_DENIED` | AccessDenied | `forbidden` | нет |
| 401 | `user_access_error` | AccessDenied | `forbidden` | нет |
| 403 | `PORTAL_DELETED` | Configuration | `configuration` | нет |
| 404 | `ERROR_METHOD_NOT_FOUND` | MethodNotFound | `method_not_found` | нет |
| 401 | `INVALID_REQUEST` (http) | Configuration | `configuration` | нет |
| 401 | `WRONG_AUTH_TYPE` | Configuration | `configuration` | нет |
| 429 | `OPERATION_TIME_LIMIT` | RateLimit | `rate_limit` | да |
| 503 | `QUERY_LIMIT_EXCEEDED` | RateLimit | `rate_limit` | да |
| 401 | `OVERLOAD_LIMIT` | RateLimit | `rate_limit` | нет |
| 500 | `INTERNAL_SERVER_ERROR`, `ERROR_UNEXPECTED_ANSWER` | Server | `server` | да |
| 400 | пустой `error` | Validation | `validation` | нет |
| — | сеть/timeout | Transport | `transport` | да |
| — | не-JSON | Transport | `transport` | нет |

`ERROR_METHOD_NOT_FOUND` может означать как отсутствие метода, так и отсутствие
scope. Проверь `php artisan bitrix24:check --refresh`.

## Retry

- Чтение: retry на `transport`, `rate_limit`, `server`.
- Уведомления: retry только на `rate_limit` и `server`; на timeout/connection —
  **нет**, чтобы не создать дубль. Для дедупликации используй `tag` (нужен
  `BITRIX24_CLIENT_ID`).
- `Retry-After` и `time.operating_reset_at` учитываются.
- Максимум попыток и задержки настраиваются в `config/bitrix24.php`.

## Пример

```php
try {
    Bitrix24::users()->getOrFail(5);
} catch (Bitrix24Exception $exception) {
    if ($exception->reason() === Bitrix24ErrorReason::NotFound) {
        abort(404);
    }

    report($exception);
    abort(503);
}
```
