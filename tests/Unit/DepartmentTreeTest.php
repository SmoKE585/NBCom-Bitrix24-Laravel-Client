<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Unit;

use Nbcom\Bitrix24\Dto\DepartmentTree;
use PHPUnit\Framework\TestCase;

final class DepartmentTreeTest extends TestCase
{
    public function test_builds_a_tree(): void
    {
        $tree = DepartmentTree::fromArray([
            ['ID' => 1, 'NAME' => 'Company', 'PARENT' => false, 'SORT' => 100, 'UF_HEAD' => 10],
            ['ID' => 2, 'NAME' => 'IT', 'PARENT' => 1, 'SORT' => 200, 'UF_HEAD' => 20],
            ['ID' => 3, 'NAME' => 'Backend', 'PARENT' => 2, 'SORT' => 300, 'UF_HEAD' => 30],
            ['ID' => 4, 'NAME' => 'Sales', 'PARENT' => 1, 'SORT' => 100, 'UF_HEAD' => 40],
        ]);

        self::assertCount(4, $tree->all());
        self::assertSame([1], $tree->roots()->pluck('id')->all());
        self::assertSame([4, 2], $tree->childrenOf(1)->pluck('id')->all());
        self::assertSame([4, 2, 3], $tree->descendantsOf(1)->pluck('id')->all());
        self::assertSame([1, 2, 3], array_map(static fn ($department): int => $department->id, $tree->pathTo(3)));
        self::assertNull($tree->get(99));
        self::assertSame(10, $tree->get(1)?->headUserId);
    }

    public function test_serializes_nested_children(): void
    {
        $tree = DepartmentTree::fromArray([
            ['ID' => 1, 'NAME' => 'Company', 'PARENT' => false, 'SORT' => 100],
            ['ID' => 2, 'NAME' => 'IT', 'PARENT' => 1, 'SORT' => 200],
        ]);

        $array = $tree->toArray();

        self::assertCount(1, $array);
        self::assertSame('Company', $array[0]['name']);
        self::assertCount(1, $array[0]['children']);
        self::assertSame('IT', $array[0]['children'][0]['name']);
    }

    public function test_handles_a_missing_parent_as_root(): void
    {
        $tree = DepartmentTree::fromArray([
            ['ID' => 5, 'NAME' => 'Orphan', 'PARENT' => 999, 'SORT' => 100],
        ]);

        self::assertSame([5], $tree->roots()->pluck('id')->all());
    }
}
