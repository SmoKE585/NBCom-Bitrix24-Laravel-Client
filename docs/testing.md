# Тестирование

Пакет предоставляет `Bitrix24::fake()` — подмену REST-транспорта без реального
портала. Fake отключает проверку capabilities и кэш, поэтому тесту не нужны
`scope`, `method.get` и пр.

```php
use Nbcom\Bitrix24\Facades\Bitrix24;

public function test_it_sends_a_notification(): void
{
    $fake = Bitrix24::fake([
        'im.notify.system.add' => ['result' => 123],
    ]);

    $receipt = Bitrix24::notifications()->toUser(5, 'Hello');

    self::assertSame(123, $receipt->notificationId);
    $fake->assertCalled('im.notify.system.add', function (array $params): bool {
        return $params['USER_ID'] === 5 && $params['MESSAGE'] === 'Hello';
    });
}
```

Ответ можно задать:

- массивом полного ответа Bitrix (`['result' => ...]` или `['error' => ...]`);
- скаляром/массивом результата — fake обернёт в `['result' => ...]`;
- замыканием `function (array $params, string $method): array`.

Если метод не описан, fake бросит `RuntimeException` — тест не пройдёт
«вхолостую».

## Assertions

```php
$fake->assertCalled('im.user.get');
$fake->assertNotCalled('im.user.get');
$fake->assertCalledCount('im.user.get', 2);
$fake->assertNothingCalled();
$fake->recorded(); // список вызовов
```

## Пагинация

Для тестов пагинации используй замыкание, которое смотрит на `start`:

```php
Bitrix24::fake([
    'im.user.get' => function (array $params): array {
        $start = (int) ($params['start'] ?? 0);

        return [
            'result' => $start === 0
                ? array_fill(0, 50, ['ID' => 1, 'NAME' => 'User'])
                : [],
            'total' => 50,
        ];
    },
]);
```

## Реальный портал

Для contract-тестов используй отдельный тестовый webhook и только чтение.
Не отправляй уведомления из CI.
