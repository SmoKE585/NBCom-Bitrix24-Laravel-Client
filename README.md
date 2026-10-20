# Laravel Bitrix24 Client

Типизированный клиент REST API корпоративного Bitrix24 для Laravel 12/13.
Стабильные DTO, понятные ошибки, однократная проверка прав webhook и кэш
некритичных чтений.

Пакет не публикуется в Packagist и устанавливается напрямую из GitHub через Composer VCS.

- **Bitrix24:** коробочная версия, классический REST v2, входящий webhook.
- **Laravel:** 12.x, 13.x.
- **PHP:** 8.2+.
- **Проверено на:** Bitrix24 26.700.0, PHP 8.4, Laravel 12.

---

## Возможности

- Пользователи: по ID, email, логину, поиск, сотрудники подразделения, коллеги.
- Оргструктура: подразделения, дерево, дети, предки, путь, руководители.
- Кодовый сахар: `colleaguesOf()`, `headsOf()`, `managementChainOf()`, `team()`.
- Поиск по пользователям и подразделениям от 2 символов.
- Уведомления: системные, личные, сообщения в личный чат.
- Массовая отправка с отчётом об успехах и ошибках.
- Проверка scopes и методов через `scope` + `method.get`, с fallback на `methods`.
- Snapshot проверки в JSON-файле: без сетевого вызова на каждый запрос.
- Кэш чтений с автоопределением store и методами `fresh()`.
- Исключения с машинно-читаемым `reason()` и сырым `errorCode()`.
- Fake-транспорт для тестов проектов-потребителей.
- Гайд для AI-агентов, который публикуется в проект одной командой.

---

## Установка

Добавь VCS-репозиторий и установи пакет:

```bash
composer config repositories.nbcom-bitrix24 vcs https://github.com/SmoKE585/NBCom-Bitrix24-Laravel-Client.git
composer require nbcom/laravel-bitrix24:^1.0
```

Если тег `v1.0.0` ещё не создан:

```bash
composer require nbcom/laravel-bitrix24:dev-main
```

Laravel найдёт `Bitrix24ServiceProvider` и фасад `Bitrix24` автоматически.
При необходимости опубликуй конфиг:

```bash
php artisan vendor:publish --tag=bitrix24-config
```

---

## Настройка

```dotenv
# Полный URL входящего webhook. Секрет, не коммитить.
BITRIX24_ENDPOINT=https://bitrix.example.ru/rest/1/ab12cd34ef56gh78/

# Нужен только для TAG/SUB_TAG в уведомлениях через webhook.
BITRIX24_CLIENT_ID=nbcom.laravel-bitrix24

# HTTP
BITRIX24_TIMEOUT=15
BITRIX24_CONNECT_TIMEOUT=5
BITRIX24_RETRY_ENABLED=true
BITRIX24_RETRY_ATTEMPTS=3
BITRIX24_THROTTLE_RPS=5

# Capabilities
BITRIX24_CAPABILITIES_STORE=file
BITRIX24_CAPABILITIES_TTL=604800
BITRIX24_FAIL_FAST=true
BITRIX24_PREFLIGHT=true

# Cache
BITRIX24_CACHE_ENABLED=auto
BITRIX24_CACHE_TTL=300
```

Проверь webhook:

```bash
php artisan bitrix24:install
# или
php artisan bitrix24:check --refresh
php artisan bitrix24:check --smoke
```

Проверка проверяет:

1. валиден ли токен;
2. выданы ли scopes `user`, `department`, `im`;
3. доступны ли `user.get`, `user.search`, `user.current`, `department.get`,
   `im.notify.system.add`, `im.notify.personal.add`, `im.message.add`;
4. может ли webhook реально читать данные (`--smoke`).

Результат кладётся в `storage/app/bitrix24/capabilities.json`. На каждый запрос
файл только читается, сеть не дёргается.

Если прав не хватает — команда завершится с кодом 1 и покажет, какие scopes добавить.
При первом вызове API без snapshot проверка выполнится лениво.

---

## Быстрый старт

```php
use Nbcom\Bitrix24\Facades\Bitrix24;
use Nbcom\Bitrix24\Enums\NotificationChannel;

// Пользователь
$user = Bitrix24::users()->findByEmail('i.ivanov@example.ru');

if ($user !== null) {
    // ...
}

// Сотрудники подразделения, включая вложенные
$employees = Bitrix24::users()->inDepartment(15, includeNested: true);

// Руководитель
$head = Bitrix24::departments()->headOf(15);

if ($head !== null) {
    Bitrix24::notifications()->toUser(
        userId: $head->id,
        message: 'Заявка №42 согласована',
        channel: NotificationChannel::System,
        tag: 'order-42',
    );
}

// Дерево
$tree = Bitrix24::departments()->tree();
$path = $tree->pathTo(42);

// Массовое уведомление
$report = Bitrix24::notifications()->toDepartment(
    departmentId: 15,
    message: 'Напоминание: закройте задачи до пятницы',
    includeNested: true,
);

$report->successCount();
$report->failureCount();
```

---

## Кодовый сахар

Короткие методы для типичных перекрёстных задач. Внутри может быть несколько
HTTP-запросов, но чтения кэшируются, а наружу приходит готовый DTO.

```php
use Nbcom\Bitrix24\Facades\Bitrix24;

$me = Bitrix24::me();

// Все коллеги по подразделению, кроме текущего пользователя
$colleagues = Bitrix24::users()->colleaguesOf(
    departmentId: 15,
    excludeUserId: $me->id,
    includeNested: true,
);

// Руководитель подразделения + руководители всех вложенных
$heads = Bitrix24::departments()->headsOf(15);

// Цепочка руководителей вверх: отдел → дивизион → компания
$chain = Bitrix24::departments()->managementChainOf(15);

// Карточка подразделения одним вызовом
$team = Bitrix24::departments()->team(15, includeNested: true);

$team->department;  // DepartmentData
$team->head;        // ?UserData
$team->heads;       // list<UserData>
$team->members;     // list<UserData>
$team->children;    // list<DepartmentData>
$team->path;        // list<DepartmentData>
$team->memberCount();

// Поиск от 2 символов
$users = Bitrix24::users()->search('Иван');
$departments = Bitrix24::departments()->search('ИТ');
```

Запрос короче 2 символов возвращает пустую коллекцию без обращения к Bitrix.
Подробнее — в [docs/convenience.md](docs/convenience.md).

---

## Кэширование

По умолчанию кэш включается автоматически, если в приложении настроен реальный
store (`file`, `redis`, `database`, ...). Store `array` и `null` игнорируются.
Управление:

```dotenv
BITRIX24_CACHE_ENABLED=auto   # auto|true|false
BITRIX24_CACHE_TTL=300
```

Кэшируются некритичные чтения:

- `users()->get()`, `findByEmail()`, `findByLogin()`, `current()`;
- `departments()->get()`, `all()`, `tree()`.

Не кэшируются поиск, списки сотрудников подразделения и уведомления.

Свежие данные в обход кэша:

```php
$user = Bitrix24::users()->fresh()->get(5);
$tree = Bitrix24::departments()->fresh()->tree();
```

`fresh()` читает из Bitrix и обновляет кэш. Проверить состояние:

```php
$info = Bitrix24::cacheInfo();
$info->enabled;
$info->store;
$info->ttl;
```

---

## Ошибки

Все исключения наследуются от `Bitrix24Exception` и реализуют
`Bitrix24ExceptionInterface`:

```php
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;

try {
    Bitrix24::notifications()->toUser(5, 'Hello');
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

`reason()` — стабильный контракт. `errorCode()` — сырой код Bitrix для логов.

Подробная таблица — в [docs/errors.md](docs/errors.md).

---

## Проверка прав

```bash
php artisan bitrix24:check
php artisan bitrix24:check --refresh
php artisan bitrix24:check --smoke
php artisan bitrix24:check --json
php artisan bitrix24:forget
```

Проверка использует `scope` и `method.get`; на старых порталах, где `method.get`
нет, — fallback на `methods`. Уведомления при проверке не отправляются.
Snapshot содержит только host, user id, scopes и статусы методов; токена в файле нет.

Настройки:

```php
'capabilities' => [
    'enabled' => true,
    'store' => 'file',       // file|cache
    'path' => storage_path('app/bitrix24'),
    'ttl' => 604800,
    'negative_ttl' => 300,
    'fail_fast' => true,
    'preflight' => true,
    'allow_stale' => true,
],
```

---

## Тестирование

```php
use Nbcom\Bitrix24\Facades\Bitrix24;

Bitrix24::fake([
    'user.get' => [
        'result' => [
            ['ID' => '5', 'NAME' => 'Иван', 'LAST_NAME' => 'Иванов'],
        ],
    ],
]);

$user = Bitrix24::users()->get(5);

self::assertSame('Иванов Иван', $user?->fullName);
```

`Bitrix24::fake()` отключает capabilities и кэш, подменяет транспорт и возвращает
`Bitrix24Fake` с `assertCalled()`, `assertNotCalled()`, `assertCalledCount()`,
`assertNothingCalled()` и `recorded()`.

---

## Для AI-агентов

В пакете есть готовый гайд:

```bash
php artisan bitrix24:agent-docs
# создаёт .ai/bitrix24.md и печатает строку для AGENTS.md
```

Или посмотреть без записи:

```bash
php artisan bitrix24:agent-docs --stdout
```

Содержимое: правила, шпаргалка API, рецепты, ошибки, особенности данных Bitrix,
тестирование и чек-лист задачи.

---

## Требования и совместимость

| Компонент | Версия |
|---|---|
| PHP | 8.2–8.5 |
| Laravel | 12.x, 13.x |
| Bitrix24 | коробка, REST v2, ориентир 26.700.0 |
| Webhook | inbound, `https://portal/rest/{userId}/{token}/` |

Пакет не поддерживает OAuth, REST 3.0 (`humanresources.*`), несколько порталов
в одном проекте, email/SMS и синхронизацию оргструктуры в БД.

---

## Безопасность

Webhook даёт права своего пользователя. Рекомендуется:

- отдельный сервисный пользователь с минимальными правами;
- хранение endpoint в секретах, а не в репозитории;
- ротация токена и `php artisan bitrix24:forget` после неё;
- запрет логирования `BITRIX24_ENDPOINT`;
- HTTPS.

Пакет не пишет токен в snapshot, исключения и отладочную информацию.

---

## Лицензия

MIT. См. [LICENSE](LICENSE).
