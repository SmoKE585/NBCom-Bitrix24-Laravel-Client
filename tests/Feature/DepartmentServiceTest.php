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
    public function test_tree_children_and_path(): void
    {
        $service = $this->service($this->client());

        self::assertSame([1], $service->tree()->roots()->pluck('id')->all());
        self::assertSame([2], $service->childrenOf(1)->pluck('id')->all());
        self::assertSame([2, 3], $service->descendantsOf(1)->pluck('id')->all());
        self::assertSame([1, 2, 3], array_map(static fn ($department): int => $department->id, $service->pathTo(3)));
    }

    public function test_head_of_department(): void
    {
        $service = $this->service($this->client());

        self::assertSame(20, $service->headOf(2)?->id);
        self::assertNull($service->headOf(3));
    }

    public function test_members(): void
    {
        $client = $this->client();
        $service = $this->service($client);

        $members = $service->members(2);

        self::assertCount(1, $members);
        self::assertSame(10, $members->first()?->id);
    }

    public function test_search_finds_partial_names_and_requires_two_characters(): void
    {
        $service = $this->service($this->client());

        self::assertSame([2], $service->search('it')->pluck('id')->all());
        self::assertSame([3], $service->search('back')->pluck('id')->all());
        self::assertCount(0, $service->search('i'));
    }

    public function test_heads_of_nested_departments(): void
    {
        $service = $this->service($this->client());

        self::assertSame([20], $service->headsOf(1)->pluck('id')->all());
        self::assertSame([20], $service->headsOf(2, includeNested: false)->pluck('id')->all());
    }

    public function test_management_chain_goes_up_to_the_root(): void
    {
        $client = new FakeClient([
            'department.get' => static fn (array $params): array => isset($params['ID'])
                ? ['result' => []]
                : ['result' => [
                    ['ID' => 1, 'NAME' => 'Company', 'PARENT' => false, 'SORT' => 100, 'UF_HEAD' => 10],
                    ['ID' => 2, 'NAME' => 'IT', 'PARENT' => 1, 'SORT' => 200, 'UF_HEAD' => 20],
                    ['ID' => 3, 'NAME' => 'Backend', 'PARENT' => 2, 'SORT' => 300, 'UF_HEAD' => 30],
                ], 'total' => 3],
            'user.get' => static fn (array $params): array => [
                'result' => [['ID' => (int) $params['filter']['ID'], 'NAME' => 'User']],
            ],
        ]);

        $chain = $this->service($client)->managementChainOf(3);

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
        return new FakeClient([
            'department.get' => static function (array $params): array {
                if (isset($params['ID'])) {
                    $id = (int) $params['ID'];

                    if ($id === 2) {
                        return ['result' => [
                            ['ID' => 2, 'NAME' => 'IT', 'PARENT' => 1, 'SORT' => 200, 'UF_HEAD' => 20],
                        ]];
                    }

                    return ['result' => [
                        ['ID' => 3, 'NAME' => 'Backend', 'PARENT' => 2, 'SORT' => 300],
                    ]];
                }

                return ['result' => [
                    ['ID' => 1, 'NAME' => 'Company', 'PARENT' => false, 'SORT' => 100],
                    ['ID' => 2, 'NAME' => 'IT', 'PARENT' => 1, 'SORT' => 200, 'UF_HEAD' => 20],
                    ['ID' => 3, 'NAME' => 'Backend', 'PARENT' => 2, 'SORT' => 300],
                ], 'total' => 3];
            },
            'user.get' => static function (array $params): array {
                $id = isset($params['filter']['ID']) ? (int) $params['filter']['ID'] : 10;

                return ['result' => [['ID' => $id, 'NAME' => $id === 20 ? 'Head' : 'Alice', 'UF_DEPARTMENT' => [2]]]];
            },
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
