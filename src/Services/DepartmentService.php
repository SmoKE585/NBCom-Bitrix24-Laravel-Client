<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Services;

use Illuminate\Support\Collection;
use Nbcom\Bitrix24\Dto\DepartmentData;
use Nbcom\Bitrix24\Dto\DepartmentTeam;
use Nbcom\Bitrix24\Dto\DepartmentTree;
use Nbcom\Bitrix24\Dto\UserData;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24NotFoundException;
use Nbcom\Bitrix24\Support\SearchTerm;
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

    public function getOrFail(int $id, bool $fresh = false): DepartmentData
    {
        $department = $this->get($id, $fresh);

        if ($department === null) {
            throw new Bitrix24NotFoundException(
                message: "Bitrix24 department [{$id}] was not found.",
                reason: Bitrix24ErrorReason::NotFound,
                method: 'department.get',
                context: ['department_id' => $id],
            );
        }

        return $department;
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

    /**
     * Partial, case-insensitive department search. Minimum 2 characters.
     *
     * @return Collection<int, DepartmentData>
     */
    public function search(string $query, int $limit = 50, bool $fresh = false): Collection
    {
        $query = SearchTerm::normalize($query);
        $limit = max(0, $limit);

        if ($limit === 0 || SearchTerm::tooShort($query)) {
            return collect();
        }

        return $this->all($fresh)
            ->filter(static fn (DepartmentData $department): bool => mb_stripos($department->name, $query) !== false)
            ->take($limit)
            ->values();
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
     * Heads of the department and, optionally, of all nested departments.
     *
     * @return Collection<int, UserData>
     */
    public function headsOf(int $departmentId, bool $includeNested = true, bool $fresh = false): Collection
    {
        $fresh = $fresh || $this->fresh;
        $tree = $this->tree($fresh);
        $departmentIds = [$departmentId];

        if ($includeNested) {
            $departmentIds = array_merge(
                $departmentIds,
                $tree->descendantsOf($departmentId)->pluck('id')->all(),
            );
        }

        $heads = [];
        $seen = [];

        foreach ($departmentIds as $id) {
            $department = $tree->get($id);
            $headUserId = $department?->headUserId;

            if ($headUserId === null || isset($seen[$headUserId])) {
                continue;
            }

            $seen[$headUserId] = true;
            $user = $this->users->get($headUserId, $fresh);

            if ($user !== null) {
                $heads[] = $user;
            }
        }

        return collect($heads);
    }

    /**
     * Management chain from the department head up to the root department head.
     *
     * @return Collection<int, UserData>
     */
    public function managementChainOf(int $departmentId, bool $fresh = false): Collection
    {
        $fresh = $fresh || $this->fresh;
        $path = $this->tree($fresh)->pathTo($departmentId);
        $heads = [];
        $seen = [];

        foreach (array_reverse($path) as $department) {
            $headUserId = $department->headUserId;

            if ($headUserId === null || isset($seen[$headUserId])) {
                continue;
            }

            $seen[$headUserId] = true;
            $user = $this->users->get($headUserId, $fresh);

            if ($user !== null) {
                $heads[] = $user;
            }
        }

        return collect($heads);
    }

    /**
     * One-call department overview: department, head, heads, members, children, path.
     */
    public function team(
        int $departmentId,
        bool $includeNested = false,
        bool $withMembers = true,
        bool $fresh = false,
    ): DepartmentTeam {
        $fresh = $fresh || $this->fresh;
        $department = $this->getOrFail($departmentId, $fresh);
        $tree = $this->tree($fresh);
        $members = $withMembers
            ? $this->members($departmentId, $includeNested, null, $fresh)->all()
            : [];

        return new DepartmentTeam(
            department: $department,
            head: $this->headOf($departmentId, $fresh),
            heads: $this->headsOf($departmentId, $includeNested, $fresh)->all(),
            members: $members,
            children: $tree->childrenOf($departmentId)->all(),
            path: $tree->pathTo($departmentId),
        );
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
