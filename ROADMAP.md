# Дорожная карта: `nbcom/laravel-bitrix24`

> Статус: v1.1 реализован. Дата: 20.10.2026.
> Пакет для внутренних Laravel-проектов группы компаний: тонкая обёртка над REST API
> коробочного Bitrix24, стабильные DTO, понятные ошибки, однократная проверка прав.
>
> Уточнения после обсуждения: Packagist не используем, установка через Composer VCS
> из https://github.com/SmoKE585/NBCom-Bitrix24-Laravel-Client; целевая коробка —
> 26.700.0; добавлен кэш некритичных чтений и `fresh()`; SSL/прокси не требуются.
> В v1.1 добавлены `colleaguesOf()`, `headsOf()`, `managementChainOf()`, `team()`,
> поиск по подразделениям и минимум 2 символа для поиска.

---

## 1. TL;DR

- Делаем Composer-пакет `nbcom/laravel-bitrix24` с фасадом `Bitrix24`.
- Поддержка: **Laravel 12 и 13**, **PHP 8.2+** (Laravel 13 сам требует 8.3, но для Laravel 12
  оставляем 8.2, иначе часть проектов не установит пакет).
- Bitrix: **только коробка (on-premise)**, классический REST v2 через inbound webhook
  (`https://portal/rest/{userId}/{token}/`). OAuth, REST 3.0 и несколько порталов — не в v1.
- Публичный GitHub, установка через Composer VCS (без Packagist).
- Проверка прав: `scope` + `method.get` (с fallback на `methods` для старых коробок),
  один раз, результат — в JSON-файл в `storage/app/bitrix24/`, без сетевого вызова на каждый запрос.
- Ошибки: иерархия исключений + стабильный `reason()` (enum) + сырой `errorCode()` Bitrix.
- DTO: `final readonly` классы, без зависимостей от Eloquent, с явным контрактом и политикой BC.
- Для агентов: `resources/agent/BITRIX24.md` (гайд для проектов-потребителей),
  публикуется командой `php artisan bitrix24:agent-docs`; плюс корневой `AGENTS.md` для разработки
  самого пакета.
- Оценка до v1.0: **20–27 человеко-дней** (~4–5,5 недель), затем пилот на 1–2 проектах.

---

## 2. Что делаем и чего не делаем

### Входит в v1

- Получение пользователя по ID, email, логину.
- Поиск пользователей по ФИО/должности/подразделению.
- Список сотрудников подразделения, включая вложенные.
- Получение подразделения, списка подразделений, дерева, детей, предков/пути.
- Получение руководителя подразделения.
- Системные уведомления Bitrix (`im.notify.system.add`).
- Личные уведомления (`im.notify.personal.add`).
- Сообщения в личный чат (`im.message.add`) — как отдельный канал.
- Массовая отправка по списку пользователей/подразделению с отчётом об успехах и ошибках.
- Проверка доступности методов и scopes, кэш результата в файле.
- Понятные исключения с машинно-читаемым `reason`.
- Тестовый fake-транспорт для проектов-потребителей.
- Документация + файл для агентов.

### Не входит в v1

- OAuth-приложения, refresh token, хранение токенов в БД.
- REST 3.0 (`humanresources.*`, адрес `/rest/api/`).
- Несколько порталов в одном проекте (заложим возможность расширения, но API v1 — один портал).
- Email-уведомления, SMS, messageservice.
- Исходящие вебхуки/события Bitrix.
- Синхронизация оргструктуры в БД (кэш некритичных чтений при этом есть).
- UI, админка, панель мониторинга.
- Laravel 11 и PHP < 8.2.

---

## 3. Зафиксированные решения

| Вопрос | Решение |
|---|---|
| Версии Laravel | 12.x и 13.x |
| PHP | `^8.2` (Laravel 12 — 8.2+, Laravel 13 — 8.3+) |
| Bitrix | только on-premise, классический REST v2 |
| Авторизация | inbound webhook: user_id + token в URL |
| Порталы | один портал на проект |
| Уведомления | системные + личные + сообщения в чат |
| Репозиторий | публичный GitHub, установка через Composer VCS |
| Имя пакета | `nbcom/laravel-bitrix24`, репозиторий `SmoKE585/NBCom-Bitrix24-Laravel-Client` |
| Версия Bitrix | 26.700.0 |
| Лицензия | MIT |
| Язык документации | русский основной, короткий английский блок в README |

### Важное уточнение по Laravel 12

Laravel 12 вышел 24.02.2025, bug-fix поддержка закончилась 13.08.2026, security — до 24.02.2027.
Поддерживать его в пакете смысл есть, но новые возможности пакета развиваем под Laravel 13,
а для 12 держим совместимость и security-фиксы. Это стоит зафиксировать в README.

---

## 4. Факты и ограничения, на которые опираемся

### 4.1 Laravel

- Laravel 13: релиз 17.03.2026, PHP 8.3–8.5.
- Laravel 12: PHP 8.2–8.5.
- Пакет использует `illuminate/support`, `illuminate/http`, `illuminate/console`,
  `illuminate/contracts` с ограничением `^12.0|^13.0`.
- HTTP-клиент — Laravel HTTP Client (сверху Guzzle). Это даёт `Http::fake()` в тестах
  и не тянет второй HTTP-слой.

### 4.2 Bitrix24, классический REST

- Webhook: `https://portal/rest/{userId}/{token}/{method}`.
- `scope` — возвращает список выданных scopes.
- `method.get` — рекомендованный способ проверки метода: возвращает
  `isExisting` и `isAvailable` с учётом текущих scopes.
- `methods` — устаревший, но полезный fallback: возвращает список методов, доступных
  текущему webhook. Список неполный для методов-контроллеров (`tasks.task.*`, `crm.item.*`),
  но для `user.*`, `department.*`, `im.*` подходит.
- `batch` — до 50 подзапросов в одном HTTP-вызове; ошибки подзапросов приходят в `result_error`
  с HTTP 200.
- `department.get`: scope `department`; поля `ID`, `NAME`, `SORT`, `PARENT`, `UF_HEAD`
  (по документации `department.*` работает именно с этими полями). Пагинация — 50 записей,
  есть `total`.
- `user.get` / `user.search`: scope `user` (или `user_brief`/`user_basic`); пагинация 50,
  `total`, у `user.search` есть `next`; состав полей зависит от scope.
- `im.notify.system.add`, `im.notify.personal.add`: scope `im`, через webhook работают;
  возвращают ID уведомления (или `false`).
- `im.message.add`: scope `im`, `DIALOG_ID` = ID пользователя для личного чата.
- Для старых коробок: `im.notify` (deprecated) как fallback, если `im.notify.system.add`
  или `im.notify.personal.add` недоступны.
- Системные ошибки: `INVALID_CREDENTIALS`, `NO_AUTH_FOUND`, `insufficient_scope`,
  `ERROR_METHOD_NOT_FOUND`, `ACCESS_DENIED`, `QUERY_LIMIT_EXCEEDED`, `OPERATION_TIME_LIMIT`,
  `OVERLOAD_LIMIT`, `INTERNAL_SERVER_ERROR`, `ERROR_UNEXPECTED_ANSWER`, `INVALID_REQUEST`,
  `PORTAL_DELETED`, `expired_token`, `user_access_error`, `WRONG_AUTH_TYPE`.
- `ERROR_METHOD_NOT_FOUND` может означать как отсутствие метода, так и отсутствие scope —
  это важно для текста ошибки.

### 4.3 Три уровня «прав», которые нельзя смешивать

1. **Аутентификация** — валиден ли user_id/token. Проверяется любым вызовом, лучше `scope`.
2. **Scope и доступность метода** — включён ли scope в настройках webhook и существует ли метод.
   Это проверяют `scope`, `method.get`, `methods`.
3. **Права конкретного пользователя** — например, webhook принадлежит сотруднику, который
   не видит часть оргструктуры. Это проверяется только реальным вызовом метода.

Пакет надёжно проверяет уровни 1–2 и даёт понятную ошибку на уровне 3 во время вызова.
Опциональный `--smoke` в команде проверки делает безопасные read-only вызовы, чтобы
проверить и уровень 3. Тестовое уведомление при проверке **не отправляется** (side effect).

---

## 5. Архитектура

```
Facade Bitrix24
   │
   ├─ Bitrix24Manager (scoped-биндинг, не singleton)
   │    ├─ Users        → UserService
   │    ├─ Departments  → DepartmentService
   │    ├─ Notifications→ NotificationService
   │    ├─ me()         → user.current
   │    └─ health()     → HealthReport
   │
   ├─ Auth\Endpoint (нормализация URL, fingerprint, redaction)
   ├─ Capabilities\CapabilityManager
   │    ├─ CapabilityRegistry (feature → scopes/methods)
   │    ├─ CapabilityChecker  (scope + method.get / methods)
   │    └─ SnapshotStore      (file | cache)
   ├─ Client\Transport        (HTTP, retry, throttle, JSON)
   ├─ Client\ErrorMapper      (Bitrix error → exception)
   └─ Dto\*                   (readonly, fromArray/toArray)
```

Принципы:

- **Никаких сетевых вызовов в `register()`/`boot()`.** Иначе `composer install`, `artisan`
  и деплой падают при недоступном портале.
- **`scoped`-биндинги**, а не `singleton`: в Octane и queue worker состояние (дерево,
  snapshot, `user.current`) не должно протекать между запросами/задачами.
- Сервисы не знают про Laravel-контейнер; DTO не знают про HTTP.
- Низкоуровневый `Bitrix24::call(string $method, array $params = []): array` — только
  как escape hatch, не для бизнес-кода. Возвращает `result` как есть, без стабильного контракта.

---

## 6. Публичный API v1

```php
use Nbcom\Bitrix24\Facades\Bitrix24;
use Nbcom\Bitrix24\Enums\NotificationChannel;

// Пользователи
$user   = Bitrix24::users()->get(15);                        // ?UserData
$user   = Bitrix24::users()->findByEmail('i.ivanov@example.ru'); // ?UserData
$users  = Bitrix24::users()->search('Иванов', limit: 50);    // Collection<int, UserData>
$staff  = Bitrix24::users()->inDepartment(15, includeNested: true);
$me     = Bitrix24::me();                                    // UserData

// Подразделения
$dept   = Bitrix24::departments()->get(15);                  // ?DepartmentData
$head   = Bitrix24::departments()->headOf(15);                // ?UserData
$tree   = Bitrix24::departments()->tree();                   // DepartmentTree
$kids   = $tree->childrenOf(15);
$sub    = $tree->descendantsOf(15);
$path   = $tree->pathTo(42);                                 // [root, ..., 42]

// Уведомления
$receipt = Bitrix24::notifications()->toUser(
    userId: $head->id,
    message: 'Заявка №42 согласована',
    channel: NotificationChannel::System,
    tag: 'order-42',
);                                                           // NotificationReceipt

$report = Bitrix24::notifications()->toDepartment(
    departmentId: 15,
    message: 'Напоминание: закройте задачи до пятницы',
    includeNested: true,
    channel: NotificationChannel::Personal,
);                                                           // NotificationReport
$report->sent; $report->failed; $report->hasFailures();

// Диагностика
$health = Bitrix24::health();                                // HealthReport
```

### Сервис пользователей

| Метод | Bitrix | Поведение |
|---|---|---|
| `get(int $id): ?UserData` | `user.get` | `null`, если не найден |
| `getOrFail(int $id): UserData` | `user.get` | `Bitrix24NotFoundException` |
| `findByEmail(string $email): ?UserData` | `user.get` | точное совпадение |
| `findByLogin(string $login): ?UserData` | `user.get` | если поле `LOGIN` доступно |
| `search(string $query, int $limit = 50): Collection` | `user.search` | поиск по ФИО/должности/подразделению |
| `inDepartment(int $id, bool $includeNested = false, ?UserFilter $filter = null): Collection` | `user.get` | все страницы, дедупликация |
| `all(?UserFilter $filter = null): LazyCollection` | `user.get` | ленивая пагинация |
| `current(): UserData` | `user.current` | кэш в пределах запроса |
| `exists(int $id): bool` | `user.get` | |

### Сервис подразделений

| Метод | Bitrix | Поведение |
|---|---|---|
| `get(int $id): ?DepartmentData` | `department.get` | |
| `getOrFail(int $id): DepartmentData` | `department.get` | |
| `all(): Collection` | `department.get` | кэш в памяти запроса |
| `tree(bool $refresh = false): DepartmentTree` | `department.get` | строит дерево |
| `childrenOf(int $id): Collection` | | из дерева |
| `descendantsOf(int $id): Collection` | | BFS, защита от циклов |
| `headOf(int $id): ?UserData` | `department.get` | `UF_HEAD` + `user.get` |
| `members(int $id, bool $includeNested = false, ?UserFilter $filter = null): Collection` | `user.get` | |
| `findByName(string $name): ?DepartmentData` | `department.get` | точное полное имя |

### Сервис уведомлений

| Метод | Канал | Bitrix |
|---|---|---|
| `toUser(int $userId, string $message, NotificationChannel $channel = System, ?string $messageOut = null, ?string $tag = null, ?string $subTag = null, ?array $attach = null)` | System | `im.notify.system.add` |
| | Personal | `im.notify.personal.add` |
| | Chat | `im.message.add` (`DIALOG_ID = (string) $userId`) |
| `toUsers(iterable $userIds, string $message, ...): NotificationReport` | любой | цикл + throttle |
| `toDepartment(int $departmentId, string $message, bool $includeNested = false, ...): NotificationReport` | любой | резолв сотрудников + цикл |
| `toDepartmentHead(int $departmentId, string $message, ...): ?NotificationReceipt` | любой | `headOf` + отправка |

`NotificationReport`:

```php
final readonly class NotificationReport
{
    /** @param list<NotificationReceipt> $sent */
    /** @param list<NotificationFailure> $failed */
    public function __construct(
        public array $sent,
        public array $failed,
        public int $total,
    ) {}

    public function hasFailures(): bool;
    public function successCount(): int;
}
```

Массовая отправка по умолчанию **не бросает исключение на первой ошибке**: возвращает отчёт,
где по каждому пользователю видно `reason`/`errorCode`. Это удобно для сайта: можно показать
«отправлено 48 из 50». Если нужно «всё или ничего» — параметр `throwOnFailure: true`.

### `UserFilter`

Типизированный фильтр с белым списком полей, чтобы не протаскивать произвольные массивы
Bitrix в публичный API:

```php
Bitrix24::users()->inDepartment(15, filter: UserFilter::make()
    ->activeOnly()
    ->userType(UserType::Employee)
    ->positionContains('менеджер'));
```

Поддерживаемые поля: `ACTIVE`, `USER_TYPE`, `UF_DEPARTMENT`, `NAME`, `LAST_NAME`,
`SECOND_NAME`, `EMAIL`, `WORK_POSITION`, `UF_PHONE_INNER`. Для нестандартных фильтров —
`Bitrix24::call()`.

---

## 7. Проверка прав (capability check) — ключевая часть

### 7.1 Что проверяем

Фичи объявляются в конфиге, а не хардкодятся:

```php
'features' => [
    'users' => true,
    'departments' => true,
    'notifications' => [
        'system' => true,
        'personal' => true,
        'chat' => false,
    ],
],
```

Реестр сопоставляет фичу с требованиями:

| Фича | Scope | Основной метод | Fallback |
|---|---|---|---|
| `users` | `user` | `user.get`, `user.search`, `user.current` | — |
| `departments` | `department` | `department.get` | `im.department.get` для руководителя |
| `notifications.system` | `im` | `im.notify.system.add` | `im.notify` |
| `notifications.personal` | `im` | `im.notify.personal.add` | `im.notify` |
| `notifications.chat` | `im` | `im.message.add` | — |

Проект, которому нужны только пользователи, не должен падать из-за отсутствия `im`.
Поэтому требования — это объединение только включённых фич.

### 7.2 Алгоритм

1. Считаем `fingerprint = sha256(host + userId + token)`. Токен в файл не пишем.
2. Читаем snapshot. Если `fingerprint` совпал и `expires_at` в будущем — используем его,
   сети не касаемся.
3. Иначе берём lock (файловый `flock` или `Cache::lock`), перечитываем snapshot
   (double-check), чтобы не делать проверку дважды.
4. Вызываем `scope` — это одновременно проверка токена и список scopes.
5. Вызываем `methods` — получаем список доступных методов.
6. Если в списке есть `method.get`:
   - одним `batch` вызываем `method.get` для каждого required-метода и fallback-варианта;
   - из ответа берём `isExisting` и `isAvailable`.
   Если `method.get` нет (старая коробка):
   - используем membership в списке `methods` как признак доступности;
   - в snapshot пишем `strategy: methods` и предупреждение о неполноте.
7. Если чего-то не хватает:
   - пишем negative snapshot с коротким TTL (`negative_ttl`, по умолчанию 300 с),
     чтобы не долбить портал на каждом запросе;
   - если `fail_fast` — бросаем `Bitrix24CapabilityException` со списком:
     какой scope включить, какие методы недоступны, что сделать.
8. Если всё есть — атомарно пишем positive snapshot (`tmp` + `rename`, `chmod 0600`).
9. Отпускаем lock.

### 7.3 Формат snapshot

`storage/app/bitrix24/capabilities.json`:

```json
{
  "version": 1,
  "fingerprint": "sha256:...",
  "portal": "bitrix.example.ru",
  "user_id": 1,
  "checked_at": "2026-10-20T19:30:00+03:00",
  "expires_at": "2026-10-27T19:30:00+03:00",
  "package_version": "1.0.0",
  "strategy": "method.get",
  "scopes": ["user", "department", "im"],
  "methods": {
    "user.get": {"exists": true, "available": true},
    "user.search": {"exists": true, "available": true},
    "user.current": {"exists": true, "available": true},
    "department.get": {"exists": true, "available": true},
    "im.notify.system.add": {"exists": true, "available": true}
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

Токена, полного URL и текстов сообщений в файле нет.

### 7.4 Когда происходит проверка

- `php artisan bitrix24:install` — публикует конфиг и запускает проверку.
- `php artisan bitrix24:check [--refresh] [--smoke] [--json]` — ручная/CI-проверка.
- Первый вызов API в проекте — ленивая проверка, если snapshot нет/протух.
- **Не** на каждый запрос: в рантайме читается файл и делается дешёвый preflight по массиву.

### 7.5 Поведение при сбоях

| Ситуация | Поведение |
|---|---|
| Нет snapshot, портал недоступен | `Bitrix24CapabilityException` (или `TransportException` как previous) |
| Snapshot протух, портал недоступен | Если `allow_stale=true` — используем старый и пишем warning; иначе исключение |
| Не хватает scope/метода | `Bitrix24CapabilityException`, список missing, negative snapshot на 5 минут |
| В рантайме пришёл `insufficient_scope` | Инвалидируем snapshot, следующая проверка увидит изменение |
| В рантайме пришёл `ERROR_METHOD_NOT_FOUND` | То же; в тексте подсказка про scope |
| Файл повреждён/не читается | Считаем, что snapshot нет; проверяем заново |
| Нет прав на запись в storage | Понятное `Bitrix24ConfigurationException` с предложением `store=cache` |

### 7.6 Файл или кэш

По умолчанию — файл, как просили:

- переживает `php artisan cache:clear`;
- не требует Redis;
- легко посмотреть глазами;
- не содержит секретов.

Альтернатива `store=cache` — для read-only контейнеров и эфемерного `storage`.
Интерфейс `SnapshotStore` один, реализаций две.

### 7.7 Команды

```bash
php artisan bitrix24:install          # publish config + check
php artisan bitrix24:check            # таблица: scopes, методы, итог; exit 1 при missing
php artisan bitrix24:check --refresh  # принудительно перепроверить
php artisan bitrix24:check --smoke    # + безопасные read-only вызовы user.get/department.get
php artisan bitrix24:check --json     # для CI
php artisan bitrix24:forget           # удалить snapshot (перед сменой токена)
php artisan bitrix24:agent-docs       # опубликовать гайд для агентов
```

Пример вывода:

```
Bitrix24: default
Portal:   bitrix.example.ru (user 1)
Checked:  2026-10-20 19:30:00 +03:00

Scopes:   user ✓  department ✓  im ✗
Methods:
  user.get                ✓
  user.search             ✓
  user.current            ✓
  department.get          ✓
  im.notify.system.add    ✗  (нужен scope "im")

Result: FAILED — 1 missing. Добавьте scope "im" в настройках webhook.
```

### 7.8 Preflight в рантайме

Перед вызовом сервис проверяет по snapshot, что метод доступен. Это даёт понятную ошибку
вместо «сырого» `ERROR_METHOD_NOT_FOUND`. Отключается `BITRIX24_PREFLIGHT=false`.
Если `fail_fast=false`, preflight только логирует и пропускает вызов — сайт получит
нативную ошибку Bitrix.

### 7.9 TTL и обновление

- `ttl` по умолчанию 7 дней: права меняются редко.
- `negative_ttl` 5 минут: чтобы после исправления настроек не ждать долго.
- `--refresh` и `bitrix24:forget` — для немедленного обновления.
- При смене токена fingerprint меняется автоматически — старый snapshot игнорируется.

### 7.10 Ограничения

- `method.get` проверяет scopes, но **не** права конкретного пользователя.
- `methods` неполный для методов-контроллеров; для наших методов это приемлемо,
  но в snapshot пишем `strategy`, чтобы диагностика была честной.
- Проверка не отправляет уведомления и не меняет данные.
- На очень старых коробках может не быть `method.get`; тогда точность ниже,
  но базовые scope-проверки работают.

---

## 8. Ошибки

### 8.1 Иерархия

```
Bitrix24Exception (RuntimeException, Bitrix24ExceptionInterface)
├─ Bitrix24ConfigurationException     // неверный endpoint, нет токена, http вместо https
├─ Bitrix24AuthenticationException    // INVALID_CREDENTIALS, NO_AUTH_FOUND, expired_token
├─ Bitrix24AccessDeniedException      // insufficient_scope, ACCESS_DENIED
├─ Bitrix24NotFoundException          // getOrFail, сущность не найдена
├─ Bitrix24MethodNotFoundException    // ERROR_METHOD_NOT_FOUND
├─ Bitrix24ValidationException        // 400 от метода, пустой error
├─ Bitrix24RateLimitException         // QUERY_LIMIT_EXCEEDED, OPERATION_TIME_LIMIT
├─ Bitrix24ServerException            // 5xx
├─ Bitrix24TransportException         // сеть, timeout, не-JSON ответ
├─ Bitrix24CapabilityException        // не хватает scopes/методов
└─ Bitrix24MalformedResponseException // структура ответа не та
```

Все исключения реализуют `Bitrix24ExceptionInterface`; наружу не утекают
`GuzzleHttp\Exception\*` и `Illuminate\Http\Client\*`.

### 8.2 Контракт исключения

```php
interface Bitrix24ExceptionInterface extends Throwable
{
    public function reason(): Bitrix24ErrorReason;
    public function errorCode(): string;          // сырой код Bitrix, может быть ""
    public function errorDescription(): string;
    public function httpStatus(): ?int;
    public function method(): ?string;
    public function isRetryable(): bool;
    /** @return array<string, mixed> */
    public function context(): array;             // без токена и PII
}
```

`Bitrix24ErrorReason`:

```php
enum Bitrix24ErrorReason: string
{
    case Authentication = 'authentication';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case Validation = 'validation';
    case RateLimit = 'rate_limit';
    case Server = 'server';
    case Transport = 'transport';
    case Configuration = 'configuration';
    case Capability = 'capability';
    case MethodNotFound = 'method_not_found';
    case Unknown = 'unknown';
}
```

Сайт ловит `Bitrix24Exception` и делает `match ($e->reason())`, не завязываясь на тексты
и редко меняющиеся коды Bitrix. `errorCode()` остаётся для логов и тонкой логики.

### 8.3 Маппинг

| HTTP | Код Bitrix | Исключение | reason | Retry |
|---|---|---|---|---|
| 401 | `INVALID_CREDENTIALS`, `NO_AUTH_FOUND` | Authentication | authentication | нет |
| 401 | `expired_token` | Authentication | authentication | нет (OAuth не в v1) |
| 401 | `user_access_error` | AccessDenied | forbidden | нет |
| 401 | `insufficient_scope` | AccessDenied | forbidden | нет |
| 401 | `ACCESS_DENIED` | AccessDenied | forbidden | нет |
| 401 | `INVALID_REQUEST` (https) | Configuration | configuration | нет |
| 401 | `OVERLOAD_LIMIT` | RateLimit | rate_limit | нет (ручной блок) |
| 403 | `ACCESS_DENIED` | AccessDenied | forbidden | нет |
| 403 | `PORTAL_DELETED` | Configuration | configuration | нет |
| 404 | `ERROR_METHOD_NOT_FOUND` | MethodNotFound | method_not_found | нет |
| 429 | `OPERATION_TIME_LIMIT` | RateLimit | rate_limit | да, после `operating_reset_at` |
| 500 | `INTERNAL_SERVER_ERROR`, `ERROR_UNEXPECTED_ANSWER` | Server | server | да |
| 503 | `QUERY_LIMIT_EXCEEDED` | RateLimit | rate_limit | да, с backoff |
| 400 | пустой `error` + описание | Validation | validation | нет |
| 200 | `batch.result_error` | зависит от кода | — | для подзапроса |
| — | не-JSON/HTML (прокси, login) | Transport | transport | да, 1 раз |

### 8.4 Retry и throttle

- Чтение (`user.get`, `user.search`, `department.get`): retry на transport, rate limit, 5xx.
- Уведомления: retry только на 429/503 (запрос не обработан), **не** на timeout/connection
  (риск дубля). Если настроен `tag` и Bitrix его поддерживает — дубли схлопываются.
- Backoff: экспонента + jitter, `base_delay_ms=200`, `max_delay_ms=2000`.
- Учитываем `Retry-After` и `time.operating_reset_at`.
- Throttle: `throttle_rps` по умолчанию 5 (коробка), для облака было бы 2; настраивается.
- Максимум попыток: 3 (1 + 2 retry), настраивается.

### 8.5 Пример обработки на сайте

```php
try {
    Bitrix24::notifications()->toUser($user->id, 'Заявка согласована');
} catch (Bitrix24Exception $e) {
    report($e);

    return match ($e->reason()) {
        Bitrix24ErrorReason::Authentication,
        Bitrix24ErrorReason::Configuration => response()->json(['error' => 'bitrix_unavailable'], 503),
        Bitrix24ErrorReason::Forbidden       => response()->json(['error' => 'bitrix_forbidden'], 403),
        Bitrix24ErrorReason::NotFound        => response()->json(['error' => 'user_not_found'], 404),
        Bitrix24ErrorReason::RateLimit       => response()->json(['error' => 'try_later'], 429),
        default                              => response()->json(['error' => 'bitrix_error'], 502),
    };
}
```

---

## 9. DTO

### 9.1 Общие правила

- `final readonly class`, только `fromArray(array): self` и `toArray(): array`.
- Никакого Eloquent, валидаторов, HTTP, Laravel-фасадов внутри DTO.
- Терпимая гидратация: `""`, `false`, `null`, строковые ID, отсутствующие ключи.
- Даты — `CarbonImmutable`; невалидная дата → `null`, не исключение.
- Лишние ключи игнорируются; для кастомных полей — явный `customFields`.
- `toArray()` — стабильный контракт: camelCase, enum → value, даты ISO 8601.
- Политика BC: добавление опционального поля — minor; переименование/удаление — major.

### 9.2 `UserData`

| Поле | Тип | Источник |
|---|---|---|
| `id` | `int` | `ID` |
| `active` | `bool` | `ACTIVE` |
| `firstName` | `string` | `NAME` |
| `lastName` | `?string` | `LAST_NAME` |
| `secondName` | `?string` | `SECOND_NAME` |
| `fullName` | `string` | вычисляемое |
| `email` | `?string` | `EMAIL` |
| `login` | `?string` | `LOGIN`, если доступен |
| `workPosition` | `?string` | `WORK_POSITION` |
| `workPhone` | `?string` | `WORK_PHONE` |
| `mobilePhone` | `?string` | `PERSONAL_MOBILE` |
| `innerPhone` | `?string` | `UF_PHONE_INNER` |
| `departmentIds` | `list<int>` | `UF_DEPARTMENT` |
| `userType` | `UserType` | `USER_TYPE` |
| `timeZone` | `?string` | `TIME_ZONE` |
| `online` | `bool` | `IS_ONLINE === 'Y'` |
| `lastLoginAt` | `?CarbonImmutable` | `LAST_LOGIN` |
| `registeredAt` | `?CarbonImmutable` | `DATE_REGISTER` |
| `avatarUrl` | `?string` | `PERSONAL_PHOTO` |
| `customFields` | `array<string, mixed>` | `UF_*`, только по запросу |

`UserType`: `Employee`, `Extranet`, `Email`, `Unknown`.

`fullName`: `LAST_NAME + NAME + SECOND_NAME`, схлопнутые пробелы; если пусто — `email`,
иначе `#id`. Порядок «фамилия имя отчество» фиксируем и документируем; для англоязычных
порталов это может быть непривычно, но предсказуемо. При необходимости добавим
`displayName` с настройкой позже.

### 9.3 `DepartmentData`

| Поле | Тип | Источник |
|---|---|---|
| `id` | `int` | `ID` |
| `name` | `string` | `NAME` |
| `parentId` | `?int` | `PARENT` (`false`/`0`/`""` → `null`) |
| `sort` | `int` | `SORT` |
| `headUserId` | `?int` | `UF_HEAD` |
| `customFields` | `array<string, mixed>` | если появятся |

### 9.4 `DepartmentTree`

- `all(): Collection<int, DepartmentData>`
- `get(int $id): ?DepartmentData`
- `roots(): Collection`
- `childrenOf(int $id): Collection`
- `descendantsOf(int $id): Collection` — BFS, защита от циклов
- `pathTo(int $id): list<DepartmentData>` — от корня к узлу
- `toArray(): array` — вложенная структура для JSON

Дерево строится из плоского списка `department.get`; родитель, которого нет в списке,
считается корнем (с warning в лог). Это устойчиво к неполным правам.

### 9.5 `NotificationReceipt`, `NotificationFailure`, `NotificationReport`

```php
final readonly class NotificationReceipt
{
    public function __construct(
        public int $userId,
        public NotificationChannel $channel,
        public ?int $notificationId,   // null, если Bitrix вернул false/true
        public bool $delivered,
    ) {}
}

final readonly class NotificationFailure
{
    public function __construct(
        public int $userId,
        public Bitrix24ErrorReason $reason,
        public string $errorCode,
        public string $message,
    ) {}
}
```

### 9.6 `Page<T>`

```php
final readonly class Page
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public ?int $total,
        public ?int $nextStart,
    ) {}

    public function hasMore(): bool;
}
```

Сервисы возвращают `Collection` для ограниченных выборок и `LazyCollection` для `all()`.
Пагинация: 50 записей; `next` используем, если Bitrix его вернул, иначе вычисляем
`start + count`, пока `count === 50` и есть `total`.

### 9.7 `HealthReport`

- `ok: bool`
- `portal: ?string`
- `userId: ?int`
- `checkedAt: ?CarbonImmutable`
- `stale: bool`
- `strategy: string`
- `scopes: list<string>`
- `missingScopes: list<string>`
- `missingMethods: list<string>`
- `packageVersion: string`

`health()` не ходит в сеть, если snapshot свежий; `health(refresh: true)` — ходит.

---

## 10. Конфигурация и ENV

```dotenv
# Секретный URL webhook. Не коммитить.
BITRIX24_ENDPOINT=https://bitrix.example.ru/rest/1/ab12cd34ef56gh78/

# HTTP
BITRIX24_TIMEOUT=15
BITRIX24_CONNECT_TIMEOUT=5
BITRIX24_VERIFY_SSL=true
BITRIX24_CA_BUNDLE=
BITRIX24_PROXY=
BITRIX24_RETRY_ENABLED=true
BITRIX24_RETRY_ATTEMPTS=3
BITRIX24_THROTTLE_RPS=5

# Capabilities
BITRIX24_CAPABILITIES_STORE=file
BITRIX24_CAPABILITIES_TTL=604800
BITRIX24_FAIL_FAST=true
BITRIX24_PREFLIGHT=true
BITRIX24_ALLOW_STALE=true

# Уведомления: client_id нужен для TAG/SUB_TAG через webhook
BITRIX24_CLIENT_ID=nbcom.laravel-bitrix24

# Логи
BITRIX24_LOG_CHANNEL=stack
BITRIX24_LOG_DEBUG=false
```

```php
// config/bitrix24.php
return [
    'endpoint' => env('BITRIX24_ENDPOINT'),

    'client_id' => env('BITRIX24_CLIENT_ID', 'nbcom.laravel-bitrix24'),

    'http' => [
        'timeout' => (int) env('BITRIX24_TIMEOUT', 15),
        'connect_timeout' => (int) env('BITRIX24_CONNECT_TIMEOUT', 5),
        'verify' => env('BITRIX24_VERIFY_SSL', true), // bool | путь к CA bundle
        'proxy' => env('BITRIX24_PROXY'),
        'retry' => [
            'enabled' => env('BITRIX24_RETRY_ENABLED', true),
            'max_attempts' => (int) env('BITRIX24_RETRY_ATTEMPTS', 3),
            'base_delay_ms' => 200,
            'max_delay_ms' => 2000,
        ],
        'throttle_rps' => (float) env('BITRIX24_THROTTLE_RPS', 5),
    ],

    'features' => [
        'users' => true,
        'departments' => true,
        'notifications' => [
            'system' => true,
            'personal' => true,
            'chat' => false,
        ],
    ],

    'capabilities' => [
        'store' => env('BITRIX24_CAPABILITIES_STORE', 'file'), // file|cache
        'path' => env('BITRIX24_CAPABILITIES_PATH', storage_path('app/bitrix24')),
        'ttl' => (int) env('BITRIX24_CAPABILITIES_TTL', 604800),
        'negative_ttl' => 300,
        'fail_fast' => env('BITRIX24_FAIL_FAST', true),
        'preflight' => env('BITRIX24_PREFLIGHT', true),
        'allow_stale' => env('BITRIX24_ALLOW_STALE', true),
        'lock_timeout' => 10,
    ],

    'logging' => [
        'channel' => env('BITRIX24_LOG_CHANNEL'),
        'debug' => env('BITRIX24_LOG_DEBUG', false),
    ],

    'register_alias' => true,
];
```

Особенности коробки:

- HTTPS обязателен; `http://` даст `INVALID_REQUEST`.
- Корпоративный/самоподписанный сертификат — `BITRIX24_VERIFY_SSL=/path/ca.pem`
  (или `false` только для локальной отладки).
- Прокси — `BITRIX24_PROXY`.
- После изменения `.env` на проде — `php artisan config:cache` (или `config:clear`).

---

## 11. Структура репозитория

```
composer.json
LICENSE
README.md
CHANGELOG.md
AGENTS.md                       # инструкции для агентов, разрабатывающих сам пакет
phpunit.xml.dist
phpstan.neon.dist
pint.json
.github/workflows/tests.yml
config/bitrix24.php
resources/agent/BITRIX24.md     # гайд для проектов-потребителей
resources/agent/llms.txt        # короткий индекс для LLM
src/
  Bitrix24ServiceProvider.php
  Facades/Bitrix24.php
  Bitrix24Manager.php
  Contracts/TransportInterface.php
  Contracts/SnapshotStoreInterface.php
  Auth/Endpoint.php
  Auth/EndpointParser.php
  Client/Transport.php
  Client/RetryPolicy.php
  Client/ErrorMapper.php
  Capabilities/CapabilityRegistry.php
  Capabilities/CapabilityChecker.php
  Capabilities/CapabilitySnapshot.php
  Capabilities/SnapshotStore/FileSnapshotStore.php
  Capabilities/SnapshotStore/CacheSnapshotStore.php
  Console/CheckCommand.php
  Console/ForgetCommand.php
  Console/InstallCommand.php
  Console/AgentDocsCommand.php
  Dto/UserData.php
  Dto/DepartmentData.php
  Dto/DepartmentTree.php
  Dto/Page.php
  Dto/NotificationReceipt.php
  Dto/NotificationFailure.php
  Dto/NotificationReport.php
  Dto/HealthReport.php
  Enums/UserType.php
  Enums/NotificationChannel.php
  Enums/Bitrix24ErrorReason.php
  Exceptions/*.php
  Services/UserService.php
  Services/DepartmentService.php
  Services/NotificationService.php
  Support/Redactor.php
  Testing/Bitrix24Fake.php
tests/
  Unit/
  Feature/
  Fixtures/
docs/
  installation.md
  configuration.md
  users.md
  departments.md
  notifications.md
  errors.md
  capabilities.md
  testing.md
  agents.md
  troubleshooting.md
  upgrade.md
  spikes.md
```

`docs/` — не дублирование README, а подробные страницы; README — быстрый старт.

---

## 12. Тестирование и качество

### 12.1 Матрица

| PHP | Laravel | Testbench | PHPUnit |
|---|---|---|---|
| 8.2 | 12 | 10 | 11 |
| 8.3 | 12, 13 | 10, 11 | 11, 12 |
| 8.4 | 12, 13 | 10, 11 | 11, 12 |
| 8.5 | 12, 13 | 10, 11 | 11, 12 |

`prefer-lowest` и `prefer-stable` для ключевых комбинаций. Ubuntu в CI; локально — Windows
тоже должен работать (пакет не должен зависеть от Unix-прав).

### 12.2 Что покрываем

Unit:

- парсинг endpoint (валидный/невалидный, trailing slash, http, query, redaction);
- маппинг ошибок (все коды из таблицы, приоритет кода над статусом);
- гидратация DTO (полный/минимальный/пустые строки/false/строковые ID/даты);
- `DepartmentTree` (корни, дети, циклы, отсутствующий родитель);
- `UserFilter` (белый список, операторы, массивы);
- snapshot (fingerprint, TTL, битый JSON, атомарная запись);
- retry policy (какие reason ретраить, какие нет).

Feature (Testbench + `Http::fake`):

- каждый метод сервиса: URL, тело, заголовки;
- пагинация 50 + вторая страница, `total`, `each()`;
- 503 → retry → 200; 429 с `Retry-After`; 400 без retry;
- уведомления: нет retry на timeout;
- capability: свежий snapshot, повторное использование (1 HTTP-вызов), протухание,
  missing scope → исключение, negative cache, смена fingerprint;
- команды: exit code, `--json`, `--refresh`;
- публикация конфига и agent-docs;
- `Bitrix24::fake()`.

Contract (опционально, `BITRIX24_CONTRACT=1`):

- реальный тестовый портал;
- только чтение: `scope`, `method.get`, `user.get` (1 запись), `department.get` (1 запись);
- уведомления не отправляются;
- в CI по расписанию или вручную, не на каждый PR.

### 12.3 Инструменты

- PHPStan + Larastan, уровень 8 (по возможности max).
- Laravel Pint.
- Покрытие: цель 85%+ по `src/`, не фанатично.
- `composer validate --strict`.
- Никаких реальных сетевых вызовов в unit/feature-тестах.

---

## 13. Документация и файл для агентов

### 13.1 Два разных `AGENTS.md`

1. **Корневой `AGENTS.md` пакета** — для агентов, которые развивают сам пакет:
   архитектура, инварианты, команды, правила тестов, BC, куда добавлять новый метод.
2. **`resources/agent/BITRIX24.md`** — для агентов, которые пишут сторонние проекты
   с использованием пакета. Публикуется в проект командой:

```bash
php artisan bitrix24:agent-docs
# создаёт .ai/bitrix24.md и печатает, как подключить его к AGENTS.md/CLAUDE.md
```

Почему не пишем в корень `AGENTS.md` автоматически: в проекте уже может быть свой файл,
и перезапись — плохой сюрприз. Лучше отдельный файл + ссылка.

### 13.2 Содержание гайда для агентов

1. Что это за пакет и когда его использовать.
2. Установка и настройка: `composer require`, `.env`, `bitrix24:check`.
3. Золотые правила:
   - только фасад, не вызывать REST напрямую;
   - не логировать endpoint/token;
   - обрабатывать `Bitrix24Exception` по `reason()`;
   - не считать, что у подразделения есть руководитель;
   - помнить про лимит 50 и пагинацию;
   - массовые уведомления — через очередь;
   - не кэшировать DTO в БД без маппинга;
   - тесты — через `Bitrix24::fake()`.
4. Шпаргалка API с точными сигнатурами.
5. Рецепты:
   - найти пользователя по email;
   - получить руководителя и отправить уведомление;
   - список сотрудников подразделения с вложенными;
   - дерево и хлебные крошки;
   - массовая рассылка с отчётом.
6. Таблица ошибок: `reason` → что делать → какой HTTP-код вернуть.
7. Особенности данных Bitrix: пустые строки, `false`, `UF_DEPARTMENT`, extranet, неактивные,
   отсутствие руководителя, ID как строки, часовые пояса.
8. Тестирование с `Bitrix24::fake()`.
9. Чек-лист «Definition of Done» для задачи агента.
10. Ссылки на docs и официальный API.

Объём — 300–450 строк, чтобы агент мог прочитать целиком и не потерять контекст.

### 13.3 README

- Что это, зачем, чем отличается от прямых вызовов REST.
- Требования: PHP 8.2+, Laravel 12/13, Bitrix24 on-prem.
- Установка, `.env`, `bitrix24:check`.
- Быстрый старт на 5 примерах.
- Обработка ошибок.
- Capabilities.
- Тестирование.
- Агентам: ссылка на гайд.
- Безопасность: webhook = права владельца, выделенный сервисный пользователь,
  ротация, не коммитить endpoint.
- Лицензия.

---

## 14. Этапы

Оценка — человеко-дни одного разработчика, без учёта ожиданий и ревью.

### Этап 0. Решения и спайки (2–3 дня)

- Подтвердить минимальную версию коробки и наличие методов на реальном портале.
- Проверить: `method.get`, `methods`, `batch`, `UF_HEAD`, `im.notify.system.add`,
  `im.notify.personal.add`, `im.message.add`, `TAG` + `CLIENT_ID`.
- Проверить формат ошибок, HTTPS/CA, прокси, лимиты.
- Зафиксировать имя пакета, лицензию, язык docs.
- Создать `docs/spikes.md` с результатами и санитизированными фикстурами.

**Выход:** подтверждённый API v1 и фикстуры ответов.

### Этап 1. Скелет и транспорт (3–4 дня)

- `composer.json`, provider, facade, config, auto-discovery.
- `Endpoint` + парсер + redaction.
- `Transport` на Laravel HTTP Client: timeout, connect timeout, SSL, proxy, JSON.
- `ErrorMapper` + иерархия исключений.
- Retry/throttle.
- Базовые тесты, Pint, PHPStan, CI-скелет.

**Выход:** `Bitrix24::call('scope')` работает; ошибки маппятся; тесты зелёные.

### Этап 2. Capabilities (3–4 дня)

- `CapabilityRegistry`, `CapabilityChecker`, snapshot.
- File/cache store, lock, TTL, negative cache, stale.
- Preflight и self-heal.
- Команды `install`, `check`, `forget`.
- Тесты.

**Выход:** проверка один раз, повторно из файла; missing scopes — понятная ошибка;
`bitrix24:check` возвращает 1 при проблеме.

### Этап 3. DTO и сервисы (6–8 дней)

- `UserData`, `DepartmentData`, `DepartmentTree`, `Page`, `Notification*`, `HealthReport`.
- `UserFilter`, пагинация, `LazyCollection`.
- `UserService`: get/findByEmail/search/inDepartment/current.
- `DepartmentService`: get/all/tree/children/descendants/head/members.
- `NotificationService`: system/personal/chat, bulk, report.
- `Bitrix24::fake()`.
- Тесты на каждый метод.

**Выход:** весь API v1 покрыт тестами; публичный контракт заморожен для ревью.

### Этап 4. Документация и агентский кит (3–4 дня)

- README, docs/*.
- Корневой `AGENTS.md`.
- `resources/agent/BITRIX24.md`, `llms.txt`.
- `bitrix24:agent-docs`.
- Примеры для агентов, проверенные на тестах.

**Выход:** новый разработчик/агент интегрирует пакет по документации без вопросов.

### Этап 5. Хардненинг и релиз (3–4 дня)

- PHPStan 8, покрытие, contract-тест на реальном портале.
- CI-матрица, Dependabot, issue/PR-шаблоны, SECURITY.md.
- CHANGELOG, тег `v1.0.0`, проверка `composer require` через VCS в чистом проекте.
- Проверка на Laravel 12 и 13, PHP 8.2–8.5.

**Выход:** `v1.0.0` ставится через Composer VCS, CI зелёный.

### Этап 6. Пилот (1–2 недели, параллельно)

- Внедрить в 1–2 внутренних проекта.
- Собрать feedback: чего не хватает, где неудобно.
- Исправить, выпустить `v1.1.0`.
- Убедиться, что дублирование REST-кода исчезло.

**Итого до v1.0:** 20–27 рабочих дней (~4–5,5 недель).

---

## 15. Риски и спайки

| Риск | Влияние | Митигция |
|---|---|---|
| Старая коробка без `method.get` | Проверка менее точная | Fallback на `methods` + `scope`; предупреждение в snapshot |
| Нет `im.notify.system.add` / `personal.add` | Уведомления не работают | Fallback на `im.notify`; capability check покажет |
| `UF_HEAD` не приходит в `department.get` | Нельзя получить руководителя | Спайк; fallback на `im.department.get`; иначе документированное ограничение |
| Webhook принадлежит пользователю с малыми правами | Часть данных/отправки недоступна | `--smoke`, понятная ошибка, рекомендация выделенного пользователя |
| `TAG` + `CLIENT_ID` через webhook не работает | Нет дедупликации | Спайк; иначе не обещать идемпотентность, рекомендация очереди |
| Дубли уведомлений при retry | Спам сотрудникам | Не ретраить timeout для уведомлений; TAG, где поддерживается |
| Большое подразделение (сотни человек) | Долгая рассылка, rate limit | Throttle, отчёт, рекомендация очереди; batch не поможет (разные методы) |
| Корпоративный SSL/прокси | По заявлению не требуется | Не усложняем конфиг; при необходимости добавим отдельно |
| Секреты в публичном репозитории | Утечка доступа | `.env.example` без реальных данных; санитизация фикстур; redaction |
| `config:cache` и env | Изменения не применяются | Документация: `config:cache` после изменения |
| Octane/queue и состояние | Устаревшее дерево/snapshot | `scoped`-биндинги, TTL, проверка `checked_at` |
| Laravel 12 security-only | Со временем придётся уходить | Поддержка 12 до security EOL, новые фичи под 13 |
| Имя пакета занято | Проблема с релизом | Проверено: `nbcom/laravel-bitrix24` свободно; зарезервировать до релиза |

### Спайки (до этапа 1)

1. **S1. Endpoint/auth.** Формат URL, trailing slash, поведение при неверном токене.
2. **S2. Capabilities.** Есть ли `method.get`, `methods`, `batch`; что возвращают на этой коробке.
3. **S3. Department head.** Приходит ли `UF_HEAD`; работает ли фильтр; что если руководителя нет.
4. **S4. Notifications.** Какие методы есть; что возвращают; работает ли `TAG`/`CLIENT_ID`;
   можно ли отправить самому себе тестовое сообщение.
5. **S5. Errors/SSL.** Коды и статусы, поведение за прокси, self-signed CA.
6. **S6. Limits.** Реальный rate limit, `QUERY_LIMIT_EXCEEDED`, `OPERATION_TIME_LIMIT`.
7. **S7. Target projects.** Версии PHP/Laravel, чтобы матрица не была теоретической.

Результаты — в `docs/spikes.md`, фикстуры — в `tests/Fixtures/` без секретов.

---

## 16. Открытые вопросы

| # | Вопрос | Почему важно | Дефолт, если не ответят |
|---|---|---|---|
| 1 | Минимальная версия коробки Bitrix24 | От неё зависят `method.get`, `im.notify.*`, `UF_HEAD` | Поддерживаем актуальную 24.x/25.x, для старых — fallback |
| 2 | Имя пакета `nbcom/laravel-bitrix24` | Публичный GitHub VCS | Оставляем это имя |
| 3 | Язык документации: RU + короткий EN или полностью EN | Публичный репозиторий | RU основной, EN-блок в README |
| 4 | Выделенный сервисный пользователь для webhook | Безопасность и предсказуемые права | Рекомендуем в docs, но не требуем |
| 5 | Нужны ли сообщения в чат (`im.message.add`) в v1 или достаточно личных уведомлений | Разный UX и права | Делаем, но выключено в конфиге по умолчанию |
| 6 | Нужен ли email-канал | Доп. настройка и согласия | Не делаем в v1 |
| 7 | Нужна ли очередь для массовых рассылок в v1 | Иначе большие подразделения отправляются синхронно | В v1 — синхронно + рекомендация очереди, helper в v1.1 |
| 8 | Есть ли тестовый портал для contract-тестов | Иначе проверяем только на моках | Просим доступ к dev-порталу |

---

## 17. Что делаем дальше

1. Подтвердить ответы на вопросы 1–3 (версия коробки, имя, язык docs).
2. Создать публичный репозиторий и скелет пакета (этап 1).
3. Получить доступ к тестовому порталу и провести спайки S1–S6.
4. Зафиксировать `docs/spikes.md` и фикстуры.
5. Согласовать публичный API v1 (раздел 6) до написания сервисов.
6. Реализовать этапы 2–5, выпустить `v1.0.0`.
7. Пилот на 1–2 проектах, собрать feedback, выпустить `v1.1.0`.

---

## Приложение A. Официальные ссылки

- [Laravel 13 release notes](https://laravel.com/framework/docs/13.x/releases)
- [Bitrix24 `method.get`](https://apidocs.bitrix24.com/api-reference/common/system/method-get.html)
- [Bitrix24 `scope`](https://apidocs.bitrix24.com/api-reference/common/system/scope.html)
- [Bitrix24 `department.get`](https://apidocs.bitrix24.com/api-reference/departments/department-get.html)
- [Bitrix24 `im.notify.system.add`](https://apidocs.bitrix24.com/api-reference/chats/notifications/im-notify-system-add.html)
- [Bitrix24 `im.notify.personal.add`](https://apidocs.bitrix24.com/api-reference/chats/notifications/im-notify-personal-add.html)
- [Bitrix24 `im.message.add`](https://apidocs.bitrix24.com/api-reference/chats/messages/im-message-add.html)
- [Bitrix24 error codes](https://apidocs.bitrix24.com/error-codes.html)

## Приложение B. Пример агентского гайда (фрагмент)

```markdown
# Bitrix24 в этом проекте

Пакет: nbcom/laravel-bitrix24. Работаем только через фасад Bitrix24.

## Правила
1. Не вызывай REST Bitrix напрямую и не дублируй HTTP-код — используй сервисы пакета.
2. Не логируй BITRIX24_ENDPOINT: в нём токен.
3. Лови Bitrix24Exception и ветвись по $e->reason(), а не по тексту ошибки.
4. Помни: department.get отдаёт 50 записей; используй сервисы, они листают сами.
5. Руководителя может не быть — headOf() возвращает ?UserData.
6. Массовые уведомления отправляй через очередь, а не в HTTP-запросе.
7. Для тестов используй Bitrix24::fake(), не ходи в реальный портал.

## Быстрый старт

$user = Bitrix24::users()->findByEmail('i.ivanov@example.ru');
$head = Bitrix24::departments()->headOf(15);

if ($head !== null) {
    Bitrix24::notifications()->toUser($head->id, 'Заявка согласована');
}

## Ошибки

| reason | Что делать | HTTP сайта |
|---|---|---|
| authentication | Проверить endpoint/токен, алерт | 503 |
| forbidden | Нет scope/прав, показать администратору | 403 |
| not_found | Сущность не найдена | 404 |
| rate_limit | Повторить позже | 429 |
| transport | Портал недоступен, retry/алерт | 502 |
| capability | Неверная настройка webhook | 503 |
```

Полная версия — в `resources/agent/BITRIX24.md`.
