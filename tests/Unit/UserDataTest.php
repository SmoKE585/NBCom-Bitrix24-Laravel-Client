<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Unit;

use Nbcom\Bitrix24\Dto\UserData;
use Nbcom\Bitrix24\Enums\UserType;
use PHPUnit\Framework\TestCase;

final class UserDataTest extends TestCase
{
    public function test_hydrates_a_full_user(): void
    {
        $user = UserData::fromArray([
            'ID' => '7',
            'ACTIVE' => 'Y',
            'NAME' => 'Иван',
            'LAST_NAME' => 'Иванов',
            'SECOND_NAME' => 'Иванович',
            'EMAIL' => 'ivanov@example.ru',
            'LOGIN' => 'ivanov',
            'WORK_POSITION' => 'Руководитель',
            'WORK_PHONE' => '+7 495 000-00-00',
            'PERSONAL_MOBILE' => '+7 900 000-00-00',
            'UF_PHONE_INNER' => '123',
            'UF_DEPARTMENT' => ['1', '2', '1'],
            'USER_TYPE' => 'employee',
            'TIME_ZONE' => 'Europe/Moscow',
            'IS_ONLINE' => 'Y',
            'LAST_LOGIN' => '2026-01-02T03:04:05+03:00',
            'DATE_REGISTER' => '2020-05-06T00:00:00+03:00',
            'PERSONAL_PHOTO' => 'https://bitrix.example.ru/upload/avatar.jpg',
            'UF_CUSTOM' => 'value',
        ]);

        self::assertSame(7, $user->id);
        self::assertTrue($user->active);
        self::assertSame('Иванов Иван Иванович', $user->fullName);
        self::assertSame([1, 2], $user->departmentIds);
        self::assertSame(UserType::Employee, $user->userType);
        self::assertTrue($user->online);
        self::assertSame('2026-01-02T03:04:05+03:00', $user->lastLoginAt?->toIso8601String());
        self::assertSame('value', $user->customFields['UF_CUSTOM'] ?? null);
        self::assertSame('123', $user->innerPhone);
    }

    public function test_hydrates_a_minimal_user(): void
    {
        $user = UserData::fromArray(['ID' => 5]);

        self::assertSame(5, $user->id);
        self::assertSame('', $user->firstName);
        self::assertSame('#5', $user->fullName);
        self::assertSame([], $user->departmentIds);
        self::assertSame(UserType::Unknown, $user->userType);
        self::assertFalse($user->active);
        self::assertNull($user->lastLoginAt);
    }

    public function test_treats_inactive_and_no_as_false(): void
    {
        $user = UserData::fromArray(['ID' => 5, 'ACTIVE' => 'N', 'IS_ONLINE' => 'N']);

        self::assertFalse($user->active);
        self::assertFalse($user->online);
    }

    public function test_serializes_to_a_stable_array(): void
    {
        $user = UserData::fromArray(['ID' => 5, 'NAME' => 'John', 'LAST_NAME' => 'Doe']);
        $array = $user->toArray();

        self::assertSame(5, $array['id']);
        self::assertSame('Doe John', $array['fullName']);
        self::assertSame('unknown', $array['userType']);
        self::assertArrayHasKey('customFields', $array);
    }
}
