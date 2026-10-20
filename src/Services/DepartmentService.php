<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Services;

use Illuminate\Support\Collection;
use Nbcom\Bitrix24\Dto\DepartmentData;
use Nbcom\Bitrix24\Dto\DepartmentTree;
use Nbcom\Bitrix24\Dto\UserData;
use Nbcom\Bitrix24\Support\UserFilter;

final class DepartmentService
{
    private bool $fresh = false;

    public function __construct(
        private readonly DepartmentLookup $lookup,
        private readonly UserService $users,
    ) {}

    public function fresh(): static
    {
        $clone = clone $this;
        $clone->fresh = true;

        return $clone;
    }

    public function get(int $id, bool $fresh = false): ?DepartmentData
    {
        return $this->lookup->get($id, $fresh || $this->fresh);
    }

    /**
     * @return Collection<int, DepartmentData>
     */
    public function all(bool $fresh = false): Collection
    {
        return $this->lookup->all($fresh || $this->fresh);
    }

    public function tree(bool $refresh = false): DepartmentTree
    {
        return $this->lookup->tree($refresh || $this->fresh);
    }

    /**
     * @return Collection<int, DepartmentData>
     */
    public function childrenOf(int $id): Collection
    {
        return $this->tree()->childrenOf($id);
    }

    /**
     * @return Collection<int, DepartmentData>
     */
    public function descendantsOf(int $id): Collection
    {
        return $this->tree()->descendantsOf($id);
    }

    /**
     * @return list<DepartmentData>
     */
    public function pathTo(int $id): array
    {
        return $this->tree()->pathTo($id);
    }

    public function root(): ?DepartmentData
    {
        return $this->tree()->roots()->first();
    }

    public function findByName(string $name, bool $fresh = false): ?DepartmentData
    {
        return $this->lookup->findByName($name, $fresh || $this->fresh);
    }

    public function headOf(int $departmentId, bool $fresh = false): ?UserData
    {
        $department = $this->lookup->get($departmentId, $fresh || $this->fresh);

        if ($department === null || $department->headUserId === null) {
            return null;
        }

        return $this->users->get($department->headUserId, $fresh || $this->fresh);
    }

    /**
     * @return Collection<int, UserData>
     */
    public function members(
        int $departmentId,
        bool $includeNested = false,
        ?UserFilter $filter = null,
        bool $fresh = false,
    ): Collection {
        return $this->users->inDepartment(
            departmentId: $departmentId,
            includeNested: $includeNested,
            filter: $filter,
            fresh: $fresh || $this->fresh,
        );
    }
}
