# Проверка прав (capabilities)

Пакет проверяет три уровня доступа:

1. **Аутентификация** — валиден ли user_id/token. Проверяется вызовом `scope`.
2. **Scope и метод** — включён ли scope и существует ли метод. Проверяется
   `method.get` (предпочтительно) или `methods` (fallback).
3. **Права пользователя webhook** — проверяются только реальным вызовом.
   Для этого есть `--smoke` (read-only), уведомления не отправляются.

## Алгоритм

1. Fingerprint = SHA-256 от host + user id + token.
2. Читается snapshot. Если fingerprint совпал и TTL не истёк — сеть не нужна.
3. Иначе — lock, повторное чтение, проверка.
4. `scope` — токен и scopes.
5. `methods` — список доступных методов.
6. Если `method.get` доступен — batch-проверка каждого требуемого метода.
   Если нет — используется membership в `methods`.
7. Missing scopes/methods пишутся в snapshot с коротким TTL (5 минут).
8. Если `fail_fast=true` — выбрасывается `Bitrix24CapabilityException`.

## Snapshot

`storage/app/bitrix24/capabilities.json`:

```json
{
  "version": 1,
  "fingerprint": "sha256:...",
  "portal": "bitrix.example.ru",
  "user_id": 1,
  "checked_at": "2026-10-20T19:30:00+03:00",
  "expires_at": "2026-10-27T19:30:00+03:00",
  "strategy": "method.get",
  "scopes": ["user", "department", "im"],
  "methods": {
    "user.get": {"exists": true, "available": true}
  },
  "chosen": {
    "notifications.system": "im.notify.system.add"
  },
  "missing": {
    "scopes": [],
    "methods": []
  }
}
```

Токена и полного URL в файле нет.

## Команды

```bash
php artisan bitrix24:check
php artisan bitrix24:check --refresh
php artisan bitrix24:check --smoke
php artisan bitrix24:check --json
php artisan bitrix24:forget
```

`--json` подходит для CI: код возврата 1, если чего-то не хватает.

## Self-healing

Если в рантайме приходит `insufficient_scope` или `ERROR_METHOD_NOT_FOUND`,
пакет инвалидирует snapshot. Следующая проверка увидит изменения в webhook.

## Ограничения

- `method.get` проверяет scopes, но не права конкретного пользователя.
- `methods` неполный для методов-контроллеров, но для `user.*`, `department.*`,
  `im.*` его достаточно.
- На старых порталах без `method.get` точность ниже; strategy будет `methods`.
