<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Feature;

use Nbcom\Bitrix24\Services\DepartmentLookup;
use Nbcom\Bitrix24\Services\DepartmentService;
use Nbcom\Bitrix24\Services\UserService;
use Nbcom\Bitrix24\Tests\Support\AllowAllCapabilities;
use Nbcom\Bitrix24\Tests\Support\FakeClient;
use Nbcom\Bitrix24\Tests\Support\TestCache;
use PHPUnit\Framework\TestCase;

final class DepartmentServiceTest extends TestCase
{
    public function test_all_returns_im_departments(): void
    {
        $service = $this->service($this->client());

        self::assertSame([1, 2, 3], $service->all()->pluck('id')->all());
        self::assertSame('IT / Company', $service->get(2)?->fullName);
        self::assertSame([1], $service->tree()->roots()->pluck('id')->all());
        self::assertSame([2], $service->tree()->childrenOf(1)->pluck('id')->all());
        self::assertSame([1, 2, 3], array_map(static fn ($department): int => $department->id, $service->tree()->pathTo(3)));
    }

    public function test_head_of_department(): void
    {
        $service = $this->service($this->client());

        self::assertSame(20, $service->headOf(2)?->id);
        self::assertSame(30, $service->headOf(3)?->id);
    }

    public function test_members_and_employee_ids(): void
    {
        $client = $this->client();
        $service = $this->service($client);

        self::assertSame([10], $service->employeeIds(2));
        self::assertSame([10], $service->members(2)->pluck('id')->all());
        self::assertCount(2, $client->callsFor('im.department.employees.get'));
    }

    public function test_search_uses_im_search(): void
    {
        $client = $this->client();
        $service = $this->service($client);

        self::assertSame([2], $service->search('it')->pluck('id')->all());
        self::assertSame([3], $service->search('back')->pluck('id')->all());
        self::assertCount(0, $service->search('i'));
    }

    public function test_heads_of_department(): void
    {
        $service = $this->service($this->client());

        self::assertSame([20], $service->headsOf(2, includeNested: false)->pluck('id')->all());
        self::assertSame([10, 20, 30], $service->headsOf(1)->pluck('id')->all());
    }

    public function test_management_chain_uses_im_departments(): void
    {
        $chain = $this->service($this->client())->managementChainOf(3);

        self::assertSame([30, 20, 10], $chain->pluck('id')->all());
    }

    public function test_team_builds_a_cross_entity_view(): void
    {
        $team = $this->service($this->client())->team(2);

        self::assertSame(2, $team->department->id);
        self::assertSame(20, $team->head?->id);
        self::assertSame([20], array_map(static fn ($user): int => $user->id, $team->heads));
        self::assertSame([10], array_map(static fn ($user): int => $user->id, $team->members));
        self::assertSame([3], array_map(static fn ($department): int => $department->id, $team->children));
        self::assertSame([1, 2], array_map(static fn ($department): int => $department->id, $team->path));
    }

    private function client(): FakeClient
    {
        $departments = [
            ['id' => 1, 'name' => 'Company', 'full_name' => 'Company', 'manager_user_id' => 10],
            ['id' => 2, 'name' => 'IT', 'full_name' => 'IT / Company', 'manager_user_id' => 20],
            ['id' => 3, 'name' => 'Backend', 'full_name' => 'Backend / IT / Company', 'manager_user_id' => 30],
        ];

        return new FakeClient([
            'im.department.get' => static function (array $params) use ($departments): array {
                $id = (int) ($params['ID'][0] ?? 0);

                return ['result' => array_values(array_filter(
                    $departments,
                    static fn (array $row): bool => $row['id'] === $id,
                ))];
            },
            'im.search.department.list' => static function (array $params) use ($departments): array {
                $find = (string) ($params['FIND'] ?? '');

                return ['result' => array_values(array_filter(
                    $departments,
                    static fn (array $row): bool => $find === '' || mb_stripos($row['name'], $find) !== false,
                ))];
            },
            'im.department.employees.get' => static fn (array $params): array => [
                'result' => ($params['USER_DATA'] ?? null) === 'Y'
                    ? ['2' => [['id' => 10, 'name' => 'Alice', 'active' => true, 'departments' => [2]]]]
                    : ['2' => [10]],
            ],
            'im.user.get' => static fn (array $params): array => [
                'result' => [
                    'id' => (int) ($params['ID'] ?? 10),
                    'name' => 'User',
                    'active' => true,
                ],
            ],
        ]);
    }

    private function service(FakeClient $client): DepartmentService
    {
        $cache = TestCache::disabled();
        $guard = new AllowAllCapabilities;
        $lookup = new DepartmentLookup($client, $guard, $cache);
        $users = new UserService($client, $guard, $cache, $lookup);

        return new DepartmentService($lookup, $users);
    }
}
