# AGENTS.md — разработка пакета `nbcom/laravel-bitrix24`

Этот файл — для агентов и разработчиков, которые меняют **сам пакет**.
Гайд для проектов-потребителей лежит в `resources/agent/BITRIX24.md`.

## Что это

Laravel-пакет: типизированная обёртка над REST API коробочного Bitrix24.
Публичный API — фасад `Bitrix24`, readonly DTO и иерархия исключений.
Внутри — транспорт, проверка capabilities, snapshot и кэш.

## Инварианты

1. **Публичный API стабилен.** Фасад, DTO, `reason()` и имена исключений —
   контракт. Ломающие изменения только в major.
2. **Никакой сети в `register()`/`boot()`.** Проверка прав — лениво или командой.
3. **Секреты не логируются.** Endpoint содержит токен; в логи и исключения
   попадает только host/redacted.
4. **DTO не зависят от Laravel-фасадов, Eloquent и HTTP.**
5. **Сервисы зависят от `ClientInterface`, а не от `Transport`.**
   Это нужно для `Bitrix24::fake()`.
6. **`scoped`-биндинги** для состояния запроса (manager, services, throttle).
7. **PHP 8.2-совместимость.** Не использовать 8.3+ синтаксис.
8. **Никаких реальных сетевых вызовов в тестах.**

## Команды

```bash
composer test          # PHPUnit
composer analyse       # PHPStan + Larastan
composer format        # Pint
composer format:test   # Pint --test
composer check         # всё вместе
```

Локально без `composer install` можно использовать vendor другого Laravel-проекта:

```powershell
$env:BITRIX24_TEST_VENDOR='C:\path\to\laravel\vendor'
php vendor/bin/phpunit --configuration phpunit.xml.dist
```

## Структура

```
src/
  Auth/Endpoint.php              # парсинг webhook URL, fingerprint, redaction
  Client/Transport.php           # HTTP + retry + throttle
  Client/ErrorMapper.php         # Bitrix error -> exception
  Capabilities/                  # registry, checker, snapshot, manager, lock
  Dto/                           # readonly DTO
  Services/                      # users, departments, notifications
  Support/ResultCache.php        # кэш чтений
  Testing/Bitrix24Fake.php       # fake-транспорт
  Facades/Bitrix24.php
  Bitrix24ServiceProvider.php
```

## Правила изменений

### Новый метод Bitrix

1. Добавь DTO, если метод возвращает новую сущность.
2. Добавь метод в соответствующий сервис, не в фасад напрямую.
3. Добавь требование в `CapabilityRegistry` (scope + method + fallback).
4. Добавь тест с `FakeClient`.
5. Обнови `resources/agent/BITRIX24.md` и README.
6. Если метод нестандартный — добавь его в `docs/` или в гайд.

### Новая ошибка

1. Добавь код в `ErrorMapper`.
2. Выбери `reason`; не добавляй новый enum case без необходимости.
3. Добавь тест на маппинг.
4. Обнови `docs/errors.md` и таблицу в гайде агента.

### Кэш

Кэшировать можно только чтения, которые терпят устаревание. Уведомления,
поиск и списки сотрудников — не кэшировать. Для обхода — `fresh()`.

## Тесты

- Unit: парсинг, маппинг, DTO, дерево, snapshot, cache.
- Feature: сервисы с `FakeClient`, транспорт с `Http::fake`.
- Contract: только вручную/по расписанию, read-only.
- Любой новый публичный метод — с тестом.
- Покрытие `src/` — цель 85%+.

## Публичный API и BC

- Добавление необязательного поля DTO — minor.
- Переименование/удаление поля, метода, enum case — major.
- Новый обязательный аргумент метода — major.
- Изменение формата `toArray()` — major.

## Документация

При изменении API обновляй:

- `README.md`;
- `resources/agent/BITRIX24.md`;
- `docs/*.md`;
- `CHANGELOG.md`.

`php artisan bitrix24:agent-docs` должен выдавать актуальный гайд.

## Чего не делать

- Не добавлять `guzzlehttp/guzzle` напрямую: используется Laravel HTTP Client.
- Не вызывать `app()` внутри сервисов.
- Не хранить токен в snapshot, кэше, DTO и исключениях.
- Не делать сетевые вызовы в сервис-провайдере.
- Не добавлять зависимости без обсуждения.
- Не коммитить `.env`, реальные endpoint и фикстуры с персональными данными.
