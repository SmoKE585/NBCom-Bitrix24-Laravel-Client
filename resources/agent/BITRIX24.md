# Bitrix24 в Laravel-проекте: гайд для AI-агентов

> Пакет: `nbcom/laravel-bitrix24`, версия 1.x.
> Репозиторий: https://github.com/SmoKE585/NBCom-Bitrix24-Laravel-Client
> Целевая платформа: коробочный Bitrix24 (проверено на 26.700.0), Laravel 12/13, PHP 8.2+.

Этот файл описывает, как работать с корпоративным Bitrix24 **только через пакет**.
Не вызывай REST Bitrix напрямую, не копируй HTTP-код из старых проектов и не дублируй DTO.

---

## 1. Когда использовать пакет

Используй пакет, если задача связана с:

- поиском сотрудников: по ID, email, логину, ФИО, должности;
- списком сотрудников подразделения, включая вложенные;
- оргструктурой: подразделение, дерево, дети, предки, путь, руководитель;
- уведомлениями сотрудникам: системными, личными, сообщением в чат;
- проверкой, что webhook настроен правильно.

Не используй пакет для CRM, задач, календаря, диска и других разделов Bitrix —
для них в пакете нет типизированных методов. Если очень нужно, есть
`Bitrix24::call()`, но это крайний случай, и его результат нестабилен.

---

## 2. Установка

Пакет не публикуется в Packagist. Подключение — через VCS-репозиторий GitHub.

```bash
composer config repositories.nbcom-bitrix24 vcs https://github.com/SmoKE585/NBCom-Bitrix24-Laravel-Client.git
composer require nbcom/laravel-bitrix24:^1.0
```

Если тег `v1.0.0` ещё не опубликован:

```bash
composer require nbcom/laravel-bitrix24:dev-main
```

Пакет сам регистрирует провайдер и фасад `Bitrix24` через Laravel package discovery.
Публиковать конфиг вручную не обязательно, но можно:

```bash
php artisan vendor:publish --tag=bitrix24-config
```

### Переменные окружения

Добавь в `.env`:

```dotenv
# Полный URL входящего webhook. Это секрет, не коммить его.
BITRIX24_ENDPOINT=https://bitrix.example.ru/rest/1/ab12cd34ef56gh78/

# Нужен, только если используешь TAG/SUB_TAG в уведомлениях через webhook.
BITRIX24_CLIENT_ID=nbcom.laravel-bitrix24

# Опционально
BITRIX24_TIMEOUT=15
BITRIX24_CONNECT_TIMEOUT=5
BITRIX24_CACHE_ENABLED=auto
BITRIX24_CACHE_TTL=300
```

После изменения `.env` на проде:

```bash
php artisan config:cache
```

### Первичная проверка

```bash
php artisan bitrix24:install
```

Команда публикует конфиг и проверяет:

- валиден ли токен;
- какие scopes выданы webhook;
- доступны ли нужные методы;
- какой портал и пользователь используются.

Результат сохраняется в `storage/app/bitrix24/capabilities.json`, поэтому
на каждый запрос проверка **не выполняется**. Принудительно:

```bash
php artisan bitrix24:check --refresh
php artisan bitrix24:check --smoke
php artisan bitrix24:check --json
```

`--smoke` делает безопасные read-only вызовы `im.user.get` и `im.search.department.list`.
Уведомления при проверке не отправляются.

---

## 3. Золотые правила

1. **Только фасад.** Используй `Bitrix24::users()`, `Bitrix24::departments()`,
   `Bitrix24::notifications()`. Не вызывай `user.get`/`department.get` вручную.
2. **Не логируй `BITRIX24_ENDPOINT`.** В нём токен. Не выводи его в ошибках,
   не отправляй в Sentry, не коммить `.env`.
3. **Обрабатывай `Bitrix24Exception` по `reason()`,** а не по тексту ошибки.
   Текст Bitrix локализован и может меняться.
4. **Помни про 50 записей на страницу.** Сервисы листают сами, но не делай
   предположений, что один вызов вернул всех.
5. **Руководителя может не быть.** `headOf()` возвращает `?UserData`.
6. **Массовые уведомления — через очередь.** На 100 человек это 100 HTTP-запросов;
   в HTTP-запросе сайта это плохая идея.
7. **Кэш — не истина в последней инстанции.** Для критичных данных используй
   `->fresh()`; для массовых уведомлений — свежий список сотрудников.
8. **Тесты — только через `Bitrix24::fake()`.** Не ходи в реальный портал из тестов.
9. **Не сохраняй DTO в БД «как есть».** Маппируй в свои модели: контракт DTO
   стабилен, но схема БД — твоя ответственность.
10. **ID — целые числа.** Bitrix отдаёт их строками; пакет приводит к `int`.

---

## 4. Шпаргалка API

```php
use Nbcom\Bitrix24\Facades\Bitrix24;
use Nbcom\Bitrix24\Enums\NotificationChannel;
use Nbcom\Bitrix24\Support\UserFilter;
```

### Пользователи

```php
Bitrix24::users()->get(int $id, bool $fresh = false): ?UserData
Bitrix24::users()->getOrFail(int $id): UserData
Bitrix24::users()->findByEmail(string $email): ?UserData
Bitrix24::users()->findByLogin(string $login): ?UserData
Bitrix24::users()->search(string $query, int $limit = 50, ?UserFilter $filter = null): Collection
Bitrix24::users()->getMany(array $ids): Collection
Bitrix24::users()->inDepartment(int $departmentId, bool $includeNested = false, ?UserFilter $filter = null, bool $fresh = false): Collection
Bitrix24::users()->colleaguesOf(int $departmentId, ?int $excludeUserId = null, bool $includeNested = false, ?UserFilter $filter = null, bool $fresh = false): Collection
Bitrix24::users()->all(?UserFilter $filter = null): LazyCollection
Bitrix24::users()->current(bool $fresh = false): UserData
Bitrix24::users()->exists(int $id): bool
Bitrix24::users()->fresh(): UserService
```

`search()` ищет от 2 символов; более короткий запрос возвращает пустую коллекцию
без обращения к Bitrix.

### Подразделения

```php
Bitrix24::departments()->get(int $id, bool $fresh = false): ?DepartmentData
Bitrix24::departments()->getOrFail(int $id, bool $fresh = false): DepartmentData
Bitrix24::departments()->getMany(array $ids): Collection
Bitrix24::departments()->all(bool $fresh = false): Collection
Bitrix24::departments()->tree(bool $refresh = false): DepartmentTree
Bitrix24::departments()->childrenOf(int $id): Collection
Bitrix24::departments()->descendantsOf(int $id): Collection
Bitrix24::departments()->pathTo(int $id): array
Bitrix24::departments()->root(): ?DepartmentData
Bitrix24::departments()->findByName(string $name, bool $fresh = false): ?DepartmentData
Bitrix24::departments()->search(string $query, int $limit = 50, bool $fresh = false): Collection
Bitrix24::departments()->headOf(int $departmentId, bool $fresh = false): ?UserData
Bitrix24::departments()->headsOf(int $departmentId, bool $includeNested = true, bool $fresh = false): Collection
Bitrix24::departments()->managementChainOf(int $departmentId, bool $fresh = false): Collection
Bitrix24::departments()->team(int $departmentId, bool $includeNested = false, bool $withMembers = true, bool $fresh = false): DepartmentTeam
Bitrix24::departments()->employeeIds(int $departmentId, bool $includeNested = false, bool $fresh = false): array
Bitrix24::departments()->employees(int $departmentId, bool $includeNested = false, ?UserFilter $filter = null, bool $fresh = false): Collection
Bitrix24::departments()->members(int $departmentId, bool $includeNested = false, ?UserFilter $filter = null, bool $fresh = false): Collection
Bitrix24::departments()->fresh(): DepartmentService
```

Поиск и сотрудники идут через `im.search.user.list`, `im.search.department.list` и
`im.department.employees.get`. `search()` — от 2 символов.

### Уведомления

```php
Bitrix24::notifications()->toUser(
    int $userId,
    string $message,
    NotificationChannel $channel = NotificationChannel::System,
    ?string $messageOut = null,
    ?string $tag = null,
    ?string $subTag = null,
    ?array $attach = null,
): NotificationReceipt

Bitrix24::notifications()->toUsers(
    iterable $userIds,
    string $message,
    NotificationChannel $channel = NotificationChannel::System,
    // ... те же опции
    bool $throwOnFailure = false,
): NotificationReport

Bitrix24::notifications()->toDepartment(
    int $departmentId,
    string $message,
    bool $includeNested = false,
    NotificationChannel $channel = NotificationChannel::System,
    // ... те же опции
): NotificationReport

Bitrix24::notifications()->toDepartmentHead(
    int $departmentId,
    string $message,
    // ... те же опции
): ?NotificationReceipt

Bitrix24::notifications()->chat(
    int $userId,
    string $message,
    ?array $attach = null,
    ?array $keyboard = null,
    ?array $menu = null,
    bool $system = false,
    bool $urlPreview = true,
): NotificationReceipt
```

Каналы:

- `NotificationChannel::System` — центр уведомлений Bitrix, `im.notify.system.add`;
- `NotificationChannel::Personal` — личное уведомление, `im.notify.personal.add`;
- `NotificationChannel::Chat` — сообщение в личный чат, `im.message.add`.

### Служебное

```php
Bitrix24::me(bool $fresh = false): UserData
Bitrix24::health(bool $refresh = false): HealthReport
Bitrix24::cacheInfo(): CacheInfo
Bitrix24::forgetCapabilities(): void
Bitrix24::liveFeed()->post(string $message, array $dest = [], ?string $title = null): LiveFeedReceipt
Bitrix24::call(string $method, array $params = []): mixed // крайний случай
```

---

## 5. DTO: что внутри

### UserData

| Поле | Тип | Комментарий |
|---|---|---|
| `id` | `int` | |
| `active` | `bool` | |
| `firstName` | `string` | `NAME` |
| `lastName` | `?string` | |
| `secondName` | `?string` | |
| `fullName` | `string` | «Фамилия Имя Отчество» |
| `email` | `?string` | |
| `login` | `?string` | |
| `workPosition` | `?string` | |
| `workPhone` | `?string` | |
| `mobilePhone` | `?string` | `PERSONAL_MOBILE` |
| `innerPhone` | `?string` | `UF_PHONE_INNER` |
| `departmentIds` | `list<int>` | `UF_DEPARTMENT` |
| `userType` | `UserType` | `employee`, `extranet`, `email`, `unknown` |
| `timeZone` | `?string` | |
| `online` | `bool` | `IS_ONLINE === 'Y'` |
| `lastLoginAt` | `?CarbonImmutable` | |
| `registeredAt` | `?CarbonImmutable` | |
| `avatarUrl` | `?string` | |
| `customFields` | `array<string,mixed>` | остальные `UF_*` |

### DepartmentData

`id`, `name`, `parentId`, `sort`, `headUserId`, `customFields`.

### DepartmentTree

`all()`, `get()`, `roots()`, `childrenOf()`, `descendantsOf()`, `pathTo()`, `toArray()`.

### DepartmentTeam

Карточка подразделения одним вызовом `departments()->team()`:

- `department` — `DepartmentData`;
- `head` — `?UserData`;
- `heads` — `list<UserData>`;
- `members` — `list<UserData>`;
- `children` — `list<DepartmentData>`;
- `path` — `list<DepartmentData>`;
- `memberCount()`.

### NotificationReport

`total`, `sent` (`list<NotificationReceipt>`), `failed` (`list<NotificationFailure>`),
`hasFailures()`, `successCount()`, `failureCount()`.

---

## 6. Рецепты

### Найти пользователя по email

```php
$user = Bitrix24::users()->findByEmail('i.ivanov@example.ru');

if ($user === null) {
    // Сотрудник не найден или у webhook нет доступа к его карточке.
    return;
}

$userId = $user->id;
```

### Получить руководителя и отправить системное уведомление

```php
$head = Bitrix24::departments()->headOf(15);

if ($head === null) {
    // У подразделения не назначен руководитель.
    return;
}

Bitrix24::notifications()->toUser(
    userId: $head->id,
    message: 'Заявка №42 согласована',
    channel: NotificationChannel::System,
    tag: 'order-42',
);
```

`tag` требует `BITRIX24_CLIENT_ID`. Если не хочешь зависеть от него — не передавай `tag`.

### Сотрудники подразделения с вложенными

```php
$employees = Bitrix24::users()->inDepartment(15, includeNested: true);

foreach ($employees as $employee) {
    // ...
}
```

По умолчанию возвращаются только активные сотрудники. Чтобы включить всех:

```php
$filter = UserFilter::make()->includeInactive();
Bitrix24::users()->inDepartment(15, true, $filter);
```

### Коллеги по подразделению

```php
$me = Bitrix24::me();

$colleagues = Bitrix24::users()->colleaguesOf(
    departmentId: 15,
    excludeUserId: $me->id,
    includeNested: true,
);
```

Это обёртка над `inDepartment()`: возвращает активных сотрудников и убирает
текущего пользователя.

### Все руководители и карточка подразделения

```php
// Руководитель подразделения + руководители вложенных
$heads = Bitrix24::departments()->headsOf(15);

// Цепочка вверх: отдел → дивизион → компания
$chain = Bitrix24::departments()->managementChainOf(15);

// Один вызов — вся карточка
$team = Bitrix24::departments()->team(15, includeNested: true);

$team->department;
$team->head;
$team->heads;
$team->members;
$team->children;
$team->path;
```

`headsOf()` дедуплицирует людей: один руководитель нескольких подразделений
попадёт в результат один раз.

### Поиск

```php
$users = Bitrix24::users()->search('Иван');
$departments = Bitrix24::departments()->search('ИТ');
```

Оба поиска требуют минимум 2 символа. Более короткий запрос вернёт пустую
коллекцию, не обращаясь к Bitrix.

### Дерево и хлебные крошки

```php
$tree = Bitrix24::departments()->tree();

foreach ($tree->roots() as $root) {
    // ...
}

$path = $tree->pathTo(42); // [root, ..., 42]
```

### Массовое уведомление подразделения

```php
$report = Bitrix24::notifications()->toDepartment(
    departmentId: 15,
    message: 'Напоминание: закройте задачи до пятницы',
    includeNested: true,
    channel: NotificationChannel::System,
);

if ($report->hasFailures()) {
    // Показать администратору, залогировать, повторить только failed.
    foreach ($report->failed as $failure) {
        // $failure->userId, $failure->reason, $failure->errorCode
    }
}
```

В HTTP-запросе сайта так делать не стоит: 100 сотрудников = 100 запросов.
Оберни в `php artisan` команду или job.

### Свежие данные в обход кэша

```php
$user = Bitrix24::users()->fresh()->get(5);
$department = Bitrix24::departments()->fresh()->tree();
```

`fresh()` не только читает из Bitrix, но и обновляет кэш.

### Проверить, используется ли кэш

```php
$info = Bitrix24::cacheInfo();

$info->enabled; // bool
$info->store;   // например, "redis", "file", "array"
$info->ttl;     // секунды
```

По умолчанию кэш включается автоматически, если в приложении настроен
реальный store (`file`, `redis`, `database`, ...). Store `array` и `null`
считаются выключенными. Явно:

```dotenv
BITRIX24_CACHE_ENABLED=true
BITRIX24_CACHE_TTL=300
```

---

## 7. Ошибки

Все исключения пакета реализуют `Bitrix24ExceptionInterface` и наследуются
от `Bitrix24Exception`. Лови базовый класс и ветвись по `reason()`:

```php
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;

try {
    // ...
} catch (Bitrix24Exception $exception) {
    report($exception);

    return match ($exception->reason()) {
        Bitrix24ErrorReason::Authentication,
        Bitrix24ErrorReason::Configuration,
        Bitrix24ErrorReason::Capability => response()->json(['error' => 'bitrix_unavailable'], 503),

        Bitrix24ErrorReason::Forbidden => response()->json(['error' => 'bitrix_forbidden'], 403),
        Bitrix24ErrorReason::NotFound => response()->json(['error' => 'not_found'], 404),
        Bitrix24ErrorReason::Validation => response()->json(['error' => 'invalid_request'], 422),
        Bitrix24ErrorReason::RateLimit => response()->json(['error' => 'try_later'], 429),
        Bitrix24ErrorReason::Transport,
        Bitrix24ErrorReason::Server => response()->json(['error' => 'bitrix_unavailable'], 502),
        default => response()->json(['error' => 'bitrix_error'], 500),
    };
}
```

| `reason` | Что значит | Что делать |
|---|---|---|
| `authentication` | Неверный токен, webhook удалён, токен истёк | Проверить `BITRIX24_ENDPOINT`, алерт |
| `forbidden` | Нет scope или прав у пользователя webhook | Добавить scope, проверить права |
| `not_found` | Пользователь/подразделение не найдено | Вернуть 404 или null-сценарий |
| `validation` | Неверные параметры метода | Исправить запрос, не ретраить |
| `rate_limit` | Превышен лимит запросов | Повторить позже, backoff |
| `server` | 5xx Bitrix | Retry, затем алерт |
| `transport` | Сеть, timeout, не-JSON ответ | Retry, проверить доступность |
| `configuration` | Ошибка настройки (http, endpoint, CLIENT_ID) | Исправить `.env`/конфиг |
| `capability` | Не хватает scopes/методов | `bitrix24:check --refresh` |
| `method_not_found` | Метода нет или скрыт из-за scope | Проверить имя и scope |

`errorCode()` — сырой код Bitrix (`INVALID_CREDENTIALS`, `insufficient_scope`, ...).
Используй его для логов, но не для ветвления бизнес-логики.

---

## 8. Особенности данных Bitrix

- `UF_DEPARTMENT` может быть `false`, `null`, `[]` или массивом строк — пакет
  всегда отдаёт `list<int>`.
- `ACTIVE` и `IS_ONLINE` могут быть `true/false`, `"Y"/"N"`, `"1"/"0"` — пакет
  приводит к `bool`.
- `PARENT` у корневого подразделения может быть `false` — пакет отдаёт `null`.
- `UF_HEAD` может быть `false` или `0` — пакет отдаёт `null`.
- `LAST_LOGIN`, `DATE_REGISTER` могут быть пустой строкой — пакет отдаёт `null`.
- `PERSONAL_PHOTO` может быть строкой URL или отсутствовать.
- `im.user.get` и `im.user.list.get` — основной способ получить пользователя/ей.
- `im.search.user.list` / `im.search.department.list` требуют минимум 2 символа
  и отдают максимум 50 записей за страницу.
- `im.department.employees.get` возвращает объект, ключ — ID подразделения;
  с `USER_DATA=Y` — сотрудников, без него — их ID.
- `im.user.get` не возвращает ботов, email-пользователей, Open Channels и Replica.
- Extranet-пользователи могут не иметь части полей; проверяй `userType`.
- `im.department.get` отдаёт `id`, `name`, `full_name`, `manager_user_id`;
  `im.search.department.list` используется для списка, поиска и дерева.
- Названия подразделений могут дублироваться; `findByName()` вернёт первое
  совпадение, для надёжности используй ID.

---

## 9. Тестирование

В тестах не нужен реальный Bitrix:

```php
use Nbcom\Bitrix24\Facades\Bitrix24;

public function test_it_finds_a_user(): void
{
    Bitrix24::fake([
        'im.user.get' => [
            'result' => [
                'id' => 5,
                'name' => 'Иванов Иван',
                'first_name' => 'Иван',
                'last_name' => 'Иванов',
                'active' => true,
            ],
        ],
    ]);

    $user = Bitrix24::users()->get(5);

    self::assertSame('Иванов Иван', $user?->fullName);
}
```

`Bitrix24::fake()`:

- подменяет транспорт;
- отключает проверку capabilities и кэш;
- возвращает `Bitrix24Fake` с методами:
  `assertCalled()`, `assertNotCalled()`, `assertCalledCount()`,
  `assertNothingCalled()`, `recorded()`.

Если для метода не задан ответ, fake бросит `RuntimeException` — это защита
от «зелёных» тестов, которые на самом деле не проверяют вызов.

---

## 10. Чек-лист задачи

Перед сдачей задачи агенту:

- [ ] Используется фасад, нет прямых REST-вызовов.
- [ ] `BITRIX24_ENDPOINT` не залогирован и не закоммичен.
- [ ] Все вызовы обёрнуты в `try/catch (Bitrix24Exception)` или обработаны
      на уровне контроллера.
- [ ] Учтено, что `headOf()` может вернуть `null`.
- [ ] Учтена пагинация / большие подразделения.
- [ ] Массовая рассылка не выполняется в HTTP-запросе.
- [ ] Для критичных данных используется `fresh()`.
- [ ] Тесты используют `Bitrix24::fake()`.
- [ ] Проверены `php artisan bitrix24:check --refresh` и `--smoke`.
- [ ] Обновлены README/доки проекта, если менялось поведение.

---

## 11. Диагностика

```bash
php artisan bitrix24:check --refresh
php artisan bitrix24:check --smoke
php artisan bitrix24:check --json
php artisan bitrix24:forget
php artisan bitrix24:agent-docs --stdout
```

Типичные проблемы:

| Симптом | Причина | Решение |
|---|---|---|
| `capability` при первом вызове | В webhook не включён scope `im` | Включить `im` |
| `forbidden` на сотрудниках/поиске | Нет scope `im` | Добавить `im` |
| `forbidden` на уведомлениях | Нет scope `im` | Добавить `im` |
| `authentication` | Токен неверный/удалён | Пересоздать webhook, обновить `.env` |
| `configuration` про HTTPS | В endpoint `http://` | Использовать `https://` |
| `configuration` про `CLIENT_ID` | Передан `tag` без `BITRIX24_CLIENT_ID` | Задать `BITRIX24_CLIENT_ID` или убрать `tag` |
| Пустой список сотрудников | Все неактивны или нет прав | `UserFilter::make()->includeInactive()` |
| Долгая отправка | Массовая рассылка в HTTP | Вынести в job |

---

## 12. Что нельзя делать

- Не добавляй в проект свой `Bitrix24Client` «на всякий случай».
- Не вызывай `user.get` напрямую, чтобы «быстрее» — потеряешь DTO и ошибки.
- Не используй `Bitrix24::call()` в бизнес-логике: результат нестабилен.
- Не отключай `BITRIX24_PREFLIGHT` без причины.
- Не увеличивай `BITRIX24_CAPABILITIES_TTL` до бесконечности: права меняются.
- Не отправляй уведомления на всех сотрудников без фильтра и отчёта.
