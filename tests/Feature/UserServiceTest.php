<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Feature;

use Nbcom\Bitrix24\Services\DepartmentLookup;
use Nbcom\Bitrix24\Services\UserService;
use Nbcom\Bitrix24\Support\ResultCache;
use Nbcom\Bitrix24\Tests\Support\AllowAllCapabilities;
use Nbcom\Bitrix24\Tests\Support\FakeClient;
use Nbcom\Bitrix24\Tests\Support\TestCache;
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    public function test_get_caches_and_fresh_bypasses_cache(): void
    {
        $calls = 0;
        $client = new FakeClient([
            'user.get' => static function () use (&$calls): array {
                $calls++;

                return ['result' => [['ID' => 5, 'NAME' => $calls === 1 ? 'First' : 'Second']]];
            },
        ]);
        $users = $this->users($client, TestCache::enabled());

        self::assertSame('First', $users->get(5)?->firstName);
        self::assertSame('First', $users->get(5)?->firstName);
        self::assertSame(1, $calls);
        self::assertSame('Second', $users->fresh()->get(5)?->firstName);
        self::assertSame(2, $calls);
    }

    public function test_find_by_email(): void
    {
        $client = new FakeClient([
            'user.get' => ['result' => [['ID' => 7, 'NAME' => 'John', 'EMAIL' => 'john@example.ru']]],
        ]);
        $users = $this->users($client, TestCache::disabled());

        $user = $users->findByEmail('john@example.ru');

        self::assertSame(7, $user?->id);
        $call = $client->callsFor('user.get')[0];
        self::assertSame('john@example.ru', $call['params']['filter']['EMAIL']);
    }

    public function test_search_paginates_and_respects_limit(): void
    {
        $client = new FakeClient([
            'user.search' => static function (array $params): array {
                $start = (int) ($params['start'] ?? 0);

                if ($start === 0) {
                    $rows = [];

                    for ($i = 1; $i <= 50; $i++) {
                        $rows[] = ['ID' => $i, 'NAME' => 'User '.$i];
                    }

                    return ['result' => $rows, 'total' => 52];
                }

                return ['result' => [['ID' => 51, 'NAME' => 'User 51'], ['ID' => 52, 'NAME' => 'User 52']], 'total' => 52];
            },
        ]);
        $users = $this->users($client, TestCache::disabled());

        $result = $users->search('User', limit: 60);

        self::assertCount(52, $result);
        self::assertCount(2, $client->callsFor('user.search'));
    }

    public function test_in_department_includes_nested_departments(): void
    {
        $client = new FakeClient([
            'department.get' => static function (array $params): array {
                if (isset($params['ID'])) {
                    return ['result' => [['ID' => 1, 'NAME' => 'Company', 'PARENT' => false, 'SORT' => 100]]];
                }

                return [
                    'result' => [
                        ['ID' => 1, 'NAME' => 'Company', 'PARENT' => false, 'SORT' => 100],
                        ['ID' => 2, 'NAME' => 'IT', 'PARENT' => 1, 'SORT' => 200],
                    ],
                    'total' => 2,
                ];
            },
            'user.get' => static fn (array $params): array => ['result' => [['ID' => 10, 'NAME' => 'Alice', 'UF_DEPARTMENT' => [2]]]],
        ]);
        $users = $this->users($client, TestCache::disabled());

        $members = $users->inDepartment(1, includeNested: true);

        self::assertCount(1, $members);
        self::assertSame(10, $members->first()?->id);

        $call = $client->callsFor('user.get')[0];
        self::assertSame([1, 2], $call['params']['filter']['@UF_DEPARTMENT']);
    }

    public function test_current_user(): void
    {
        $client = new FakeClient([
            'user.current' => ['result' => ['ID' => 1, 'NAME' => 'Admin']],
        ]);
        $users = $this->users($client, TestCache::disabled());

        self::assertSame('Admin', $users->current()->firstName);
    }

    private function users(FakeClient $client, ResultCache $cache): UserService
    {
        $guard = new AllowAllCapabilities;
        $lookup = new DepartmentLookup($client, $guard, $cache);

        return new UserService($client, $guard, $cache, $lookup);
    }
}
