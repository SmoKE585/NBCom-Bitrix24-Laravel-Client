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
            'im.user.get' => static function () use (&$calls): array {
                $calls++;

                return ['result' => ['id' => 5, 'name' => $calls === 1 ? 'First' : 'Second', 'first_name' => $calls === 1 ? 'First' : 'Second', 'active' => true]];
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
            'im.search.user.list' => ['result' => [
                ['id' => 7, 'name' => 'John', 'email' => 'john@example.ru', 'active' => true],
            ]],
        ]);
        $users = $this->users($client, TestCache::disabled());

        $user = $users->findByEmail('john@example.ru');

        self::assertSame(7, $user?->id);
        $call = $client->callsFor('im.search.user.list')[0];
        self::assertSame('john@example.ru', $call['params']['FIND']);
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

    public function test_search_page_returns_total_and_next_offset(): void
    {
        $client = new FakeClient([
            'im.search.user.list' => static fn (array $params): array => [
                'result' => array_map(
                    static fn (int $id): array => ['id' => $id, 'name' => 'User '.$id, 'active' => true],
                    range(1, (int) $params['LIMIT']),
                ),
                'total' => 52,
                'next' => (int) $params['OFFSET'] + (int) $params['LIMIT'],
            ],
        ]);
        $users = $this->users($client, TestCache::disabled());

        $page = $users->searchPage('User', limit: 10);

        self::assertCount(10, $page->items);
        self::assertSame(52, $page->total);
        self::assertSame(10, $page->nextStart);
        self::assertTrue($page->hasMore());
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
            'im.search.department.list' => ['result' => [
                ['id' => 1, 'name' => 'Company', 'full_name' => 'Company'],
            ], 'total' => 1],
            'im.department.employees.get' => static fn (array $params): array => [
                'result' => [
                    '1' => [['id' => 10, 'name' => 'Alice', 'active' => true, 'departments' => [1]]],
                ],
            ],
        ]);
        $users = $this->users($client, TestCache::disabled());

        $members = $users->inDepartment(1, includeNested: true);

        self::assertCount(1, $members);
        self::assertSame([10], $members->pluck('id')->all());

        $call = $client->callsFor('im.department.employees.get')[0];
        self::assertSame([1], $call['params']['ID']);
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
            'im.user.list.get' => static fn (array $params): array => [
                'result' => array_map(static fn (int $id): array => ['id' => $id, 'name' => 'User', 'active' => true], $params['ID']),
            ],
        ]);
        $users = $this->users($client, TestCache::disabled());

        $result = $users->getMany([5, 6, 5]);

        self::assertSame([5, 6], $result->pluck('id')->all());
        self::assertCount(1, $client->callsFor('im.user.list.get'));
    }

    public function test_current_user(): void
    {
        $client = new FakeClient([
            'im.user.get' => ['result' => ['id' => 1, 'name' => 'Admin', 'first_name' => 'Admin', 'active' => true]],
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
