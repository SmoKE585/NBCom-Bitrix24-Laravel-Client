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
