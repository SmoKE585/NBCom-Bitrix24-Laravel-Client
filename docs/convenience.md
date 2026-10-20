# Кодовый сахар и перекрёстные выборки

Это методы, которые собирают данные из нескольких сущностей одним вызовом фасада.
Внутри может быть несколько HTTP-запросов к Bitrix, но чтения кэшируются, а наружу
всегда приходит готовый DTO.

## Коллеги по подразделению

```php
$me = Bitrix24::me();

$colleagues = Bitrix24::users()->colleaguesOf(
    departmentId: 15,
    excludeUserId: $me->id,
    includeNested: true,
);

foreach ($colleagues as $colleague) {
    // UserData
}
```

Параметры:

- `departmentId` — подразделение;
- `excludeUserId` — кого исключить (обычно текущий пользователь);
- `includeNested` — включить вложенные подразделения;
- `filter` — `UserFilter`, по умолчанию только активные;
- `fresh` — обойти кэш.

## Все руководители подразделения

```php
// Руководитель подразделения и руководители всех вложенных подразделений
$heads = Bitrix24::departments()->headsOf(15);

// Только руководитель этого подразделения
$heads = Bitrix24::departments()->headsOf(15, includeNested: false);

// Цепочка руководителей вверх: руководитель отдела → руководитель дивизиона → ...
$chain = Bitrix24::departments()->managementChainOf(15);
```

`headsOf()` возвращает уникальных пользователей: если один человек руководит
несколькими подразделениями, он попадёт в результат один раз.

`managementChainOf()` идёт от указанного подразделения вверх к корню.

## Карточка подразделения одним вызовом

```php
$team = Bitrix24::departments()->team(
    departmentId: 15,
    includeNested: true,
    withMembers: true,
);

$team->department;   // DepartmentData
$team->head;         // ?UserData
$team->heads;        // list<UserData>
$team->members;      // list<UserData>
$team->children;     // list<DepartmentData>
$team->path;         // list<DepartmentData>, от корня к подразделению
$team->memberCount();
```

`team()` — это «один вызов фасада» для типичной карточки подразделения.
Если сотрудники не нужны, передай `withMembers: false`.

## Поиск

```php
$users = Bitrix24::users()->search('Иван');
$departments = Bitrix24::departments()->search('ИТ');
```

Оба поиска:

- требуют минимум **2 символа**; более короткий запрос возвращает пустую коллекцию
  без обращения к Bitrix;
- не кэшируются (это динамические запросы);
- принимают `limit`, по умолчанию 50.

Поиск подразделений — частичный, без учёта регистра, по названию. Он выполняется
по уже загруженному списку подразделений, поэтому не делает отдельный HTTP-запрос
на каждый символ, если список уже в кэше.

## Комбинированный пример

```php
$team = Bitrix24::departments()->team(15, includeNested: true);
$me = Bitrix24::me();

$colleagues = Bitrix24::users()->colleaguesOf(
    departmentId: 15,
    excludeUserId: $me->id,
);

Bitrix24::notifications()->toUsers(
    userIds: $team->heads ? array_map(static fn ($user) => $user->id, $team->heads) : [],
    message: 'Планёрка в 10:00',
);
```
