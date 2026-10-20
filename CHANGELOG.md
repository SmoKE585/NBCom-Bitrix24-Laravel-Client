# Changelog

Все заметные изменения пакета. Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — [SemVer](https://semver.org/lang/ru/).

## [1.4.1] — 2026-10-20

### Added

- `UserData::$personalPhone` из `PERSONAL_PHONE` / `phones.personal_phone`.
- `UserData::$phones` — сырой объект телефонов из IM.
- `UserData::$lastActivityAt` из `last_activity_date`.
- В `toArray()` добавлены raw-алиасы `personal_phone` и `last_activity_date`
  для инструментов, которые читают исходные имена Bitrix.

## [1.4.0] — 2026-10-20

### Added

- `users()->searchPage()` и `departments()->searchPage()`: `Page` с `items`,
  `total`, `nextStart` и `hasMore()`.
- `search()` остаётся коллекцией без total для обратной совместимости.

## [1.3.2] — 2026-10-20

### Removed

- Все fallback-вызовы `user.get`, `user.search`, `department.get` и фильтр
  `@UF_DEPARTMENT`. Обычный пользователь без scopes `user`/`department`
  их всё равно не выполнит, поэтому оставлен только `im.*`.

## [1.3.1] — 2026-10-20

### Added

- `UserData::$status` — статус из `im.user.get` (`online`, `dnd`, `away`, `break`).

## [1.3.0] — 2026-10-20

### Changed

- Все чтения пользователей и подразделений переведены на `im.*`:
  `im.user.get`, `im.user.list.get`, `im.search.user.list`,
  `im.department.get`, `im.search.department.list`.
- Сотрудники подразделения — только `im.department.employees.get`.
- Capability scopes сокращены до `im` (+ `log` для живой ленты).
- `all()` собирает пользователей через IM-подразделения и `im.user.list.get`.
- Дерево подразделений строится по `full_name` из `im.search.department.list`.
- README: список методов и scopes обновлён под IM.

## [1.2.1] — 2026-10-20

### Docs

- В README добавлен полный список используемых методов Bitrix24.
- Добавлена таблица scopes для входящего webhook и инструкция по его созданию.

## [1.2.0] — 2026-10-20

### Added

- Поиск пользователей через `im.search.user.list` (fallback на `user.search`).
- Поиск подразделений через `im.search.department.list` (fallback на `department.get`).
- Сотрудники подразделения через `im.department.employees.get`:
  `departments()->employeeIds()` и `departments()->employees()`.
- `users()->getMany()` и `departments()->getMany()`.
- `notifications()->chat()` с `keyboard`, `menu`, `attach`, `system`, `urlPreview`.
- `liveFeed()->post()` через `log.blogpost.add` (feature `live_feed`, scope `log`).
- DTO понимают IM-поля: `first_name`, `last_name`, `work_position`, `avatar`,
  `departments`, `full_name`, `manager_user_id`.
- Fallback `BITRIX24_WEBHOOK_URL` для `BITRIX24_ENDPOINT`.

## [1.1.0] — 2026-10-20

### Added

- `users()->colleaguesOf()` — коллеги по подразделению с исключением текущего пользователя.
- `departments()->headsOf()` — руководители подразделения и вложенных.
- `departments()->managementChainOf()` — цепочка руководителей вверх до корня.
- `departments()->team()` и DTO `DepartmentTeam` — карточка подразделения одним вызовом.
- `departments()->getOrFail()` и `departments()->search()`.
- Минимальная длина поиска — 2 символа для пользователей и подразделений.
- Требование `ext-mbstring`.

## [1.0.0] — 2026-10-20

### Added

- Фасад `Bitrix24` и сервисы `users()`, `departments()`, `notifications()`.
- DTO: `UserData`, `DepartmentData`, `DepartmentTree`, `NotificationReceipt`,
  `NotificationFailure`, `NotificationReport`, `HealthReport`, `CacheInfo`.
- Пользователи: get, getOrFail, findByEmail, findByLogin, search, inDepartment,
  all, current, exists, fresh.
- Подразделения: get, all, tree, childrenOf, descendantsOf, pathTo, root,
  findByName, headOf, members, fresh.
- Уведомления: system, personal, chat, toUser, toUsers, toDepartment,
  toDepartmentHead, отчёт о частичных ошибках.
- Проверка capabilities через `scope` + `method.get` с fallback на `methods`.
- Snapshot в `storage/app/bitrix24/capabilities.json` с fingerprint, TTL
  и negative TTL.
- Команды `bitrix24:install`, `bitrix24:check`, `bitrix24:forget`,
  `bitrix24:agent-docs`.
- Кэш чтений с автоопределением store, `fresh()` и `cacheInfo()`.
- Исключения с `reason()`/`errorCode()` и retry-политика.
- `Bitrix24::fake()` для тестов.
- README, docs и гайд для AI-агентов.
