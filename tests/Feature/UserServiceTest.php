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
            'im.search.user.list' => static function (array $params): array {
                $offset = (int) ($params['OFFSET'] ?? 0);
                $limit = (int) ($params['LIMIT'] ?? 10);
                $rows = [];

                if ($offset === 0) {
                    for ($i = 1; $i <= min(50, $limit); $i++) {
                        $rows[] = ['id' => $i, 'name' => 'User '.$i, 'active' => true];
                    }
                } else {
                    for ($i = 51; $i <= 52; $i++) {
                        $rows[] = ['id' => $i, 'name' => 'User '.$i, 'active' => true];
                    }
                }

                return ['result' => $rows, 'total' => 52];
            },
        ]);
        $users = $this->users($client, TestCache::disabled());

        $result = $users->search('User', limit: 60);

        self::assertCount(52, $result);
        self::assertCount(2, $client->callsFor('im.search.user.list'));
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
            'im.department.employees.get' => static fn (array $params): array => [
                'result' => [
                    '1' => [['id' => 10, 'name' => 'Alice', 'active' => true, 'departments' => [1]]],
                    '2' => [['id' => 20, 'name' => 'Bob', 'active' => true, 'departments' => [2]]],
                ],
            ],
        ]);
        $users = $this->users($client, TestCache::disabled());

        $members = $users->inDepartment(1, includeNested: true);

        self::assertCount(2, $members);
        self::assertSame([10, 20], $members->pluck('id')->all());

        $call = $client->callsFor('im.department.employees.get')[0];
        self::assertSame([1, 2], $call['params']['ID']);
        self::assertSame('Y', $call['params']['USER_DATA']);
    }

    public function test_search_requires_at_least_two_characters(): void
    {
        $client = new FakeClient;
        $users = $this->users($client, TestCache::disabled());

        self::assertCount(0, $users->search('a'));
        self::assertCount(0, $client->calls);
    }

    public function test_colleagues_exclude_the_current_user(): void
    {
        $client = new FakeClient([
            'im.department.employees.get' => static fn (array $params): array => ['result' => [
                '2' => [
                    ['id' => 10, 'name' => 'Alice', 'active' => true],
                    ['id' => 20, 'name' => 'Bob', 'active' => true],
                ],
            ]],
        ]);
        $users = $this->users($client, TestCache::disabled());

        $colleagues = $users->colleaguesOf(2, excludeUserId: 10);

        self::assertCount(1, $colleagues);
        self::assertSame(20, $colleagues->first()?->id);
    }

    public function test_get_many_returns_unique_users(): void
    {
        $client = new FakeClient([
            'user.get' => static fn (array $params): array => [
                'result' => [['id' => (int) $params['filter']['ID'], 'name' => 'User', 'active' => true]],
            ],
        ]);
        $users = $this->users($client, TestCache::disabled());

        $result = $users->getMany([5, 6, 5]);

        self::assertSame([5, 6], $result->pluck('id')->all());
        self::assertCount(2, $client->callsFor('user.get'));
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
