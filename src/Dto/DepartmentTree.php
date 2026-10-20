<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

use Illuminate\Support\Collection;
use JsonSerializable;

final readonly class DepartmentTree implements JsonSerializable
{
    /** @var array<int, DepartmentData> */
    private array $departments;

    /** @var array<int, list<int>> */
    private array $children;

    /**
     * @param  array<int, DepartmentData>  $departments
     */
    public function __construct(array $departments)
    {
        $this->departments = $departments;
        $children = [];

        foreach ($departments as $department) {
            if ($department->parentId !== null) {
                $children[$department->parentId][] = $department->id;
            }
        }

        $this->children = $children;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function fromArray(array $rows): self
    {
        $departments = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $department = DepartmentData::fromArray($row);
            $departments[$department->id] = $department;
        }

        return new self($departments);
    }

    /**
     * @return Collection<int, DepartmentData>
     */
    public function all(): Collection
    {
        return collect($this->sorted($this->departments))->values();
    }

    public function get(int $id): ?DepartmentData
    {
        return $this->departments[$id] ?? null;
    }

    /**
     * @return Collection<int, DepartmentData>
     */
    public function roots(): Collection
    {
        $roots = array_filter(
            $this->departments,
            fn (DepartmentData $department): bool => $department->parentId === null
                || ! isset($this->departments[$department->parentId])
                || $department->parentId === $department->id,
        );

        return collect($this->sorted($roots))->values();
    }

    /**
     * @return Collection<int, DepartmentData>
     */
    public function childrenOf(int $id): Collection
    {
        $children = [];

        foreach ($this->children[$id] ?? [] as $childId) {
            if (isset($this->departments[$childId])) {
                $children[] = $this->departments[$childId];
            }
        }

        return collect($this->sorted($children))->values();
    }

    /**
     * @return Collection<int, DepartmentData>
     */
    public function descendantsOf(int $id): Collection
    {
        $result = [];
        $visited = [];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($this->children[$current] ?? [] as $childId) {
                if (isset($visited[$childId]) || ! isset($this->departments[$childId])) {
                    continue;
                }

                $visited[$childId] = true;
                $result[] = $this->departments[$childId];
                $queue[] = $childId;
            }
        }

        return collect($this->sorted($result))->values();
    }

    /**
     * @return list<DepartmentData>
     */
    public function pathTo(int $id): array
    {
        $path = [];
        $current = $this->departments[$id] ?? null;
        $visited = [];

        while ($current !== null) {
            if (isset($visited[$current->id])) {
                break;
            }

            $visited[$current->id] = true;
            $path[] = $current;
            $current = $current->parentId !== null ? ($this->departments[$current->parentId] ?? null) : null;
        }

        return array_reverse($path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->roots() as $root) {
            $result[] = $this->nodeToArray($root);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, DepartmentData>  $departments
     * @return array<int, DepartmentData>
     */
    private function sorted(array $departments): array
    {
        usort($departments, static function (DepartmentData $left, DepartmentData $right): int {
            return [$left->sort, $left->name] <=> [$right->sort, $right->name];
        });

        return $departments;
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeToArray(DepartmentData $department): array
    {
        $children = [];

        foreach ($this->childrenOf($department->id) as $child) {
            $children[] = $this->nodeToArray($child);
        }

        return $department->toArray() + ['children' => $children];
    }
}
