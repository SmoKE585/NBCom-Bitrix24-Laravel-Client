# Changelog

Все заметные изменения пакета. Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — [SemVer](https://semver.org/lang/ru/).

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
