<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Services;

use Illuminate\Support\Collection;
use Nbcom\Bitrix24\Contracts\CapabilityGuardInterface;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Dto\DepartmentData;
use Nbcom\Bitrix24\Dto\DepartmentTree;
use Nbcom\Bitrix24\Support\ResultCache;
use Nbcom\Bitrix24\Support\SearchTerm;

final class DepartmentLookup
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CapabilityGuardInterface $guard,
        private readonly ResultCache $cache,
    ) {}

    public function get(int $id, bool $fresh = false): ?DepartmentData
    {
        if ($id <= 0) {
            return null;
        }

        $key = 'department:'.$id;

        if (! $fresh) {
            $cached = $this->cache->get($key);

            if (is_array($cached)) {
                return DepartmentData::fromArray($cached);
            }
        }

        $this->guard->ensure('im.department.get');
        $body = $this->client->call('im.department.get', [
            'ID' => [$id],
        ]);
        $row = $this->extractImDepartments($body['result'] ?? [])[0] ?? null;

        if (! is_array($row)) {
            return null;
        }

        $department = DepartmentData::fromArray($row);
        $this->cache->put($key, $department->toArray());

        return $department;
    }

    /**
     * @return Collection<int, DepartmentData>
     */
    public function all(bool $fresh = false): Collection
    {
        $key = 'departments:all';

        if (! $fresh) {
            $cached = $this->cache->get($key);

            if (is_array($cached)) {
                return collect(array_map(
                    static fn (array $row): DepartmentData => DepartmentData::fromArray($row),
                    $cached,
                ));
            }
        }

        $this->guard->ensure('im.search.department.list');
        $rows = [];
        $offset = 0;

        do {
            $body = $this->client->call('im.search.department.list', [
                'FIND' => '',
                'LIMIT' => 50,
                'OFFSET' => $offset,
                'USER_DATA' => 'Y',
            ]);
            $chunk = $body['result'] ?? [];

            if (! is_array($chunk) || $chunk === []) {
                break;
            }

            $chunk = array_values(array_filter($chunk, 'is_array'));

            foreach ($chunk as $row) {
                $rows[] = DepartmentData::fromArray($row);
            }

            $count = count($chunk);
            $offset += $count;
        } while ($count === 50);

        $collection = collect($this->withParents($rows))->values();
        $this->cache->put($key, $collection->map(static fn (DepartmentData $department): array => $department->toArray())->all());

        return $collection;
    }

    public function tree(bool $fresh = false): DepartmentTree
    {
        return new DepartmentTree($this->all($fresh)->keyBy('id')->all());
    }

    public function findByName(string $name, bool $fresh = false): ?DepartmentData
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return $this->all($fresh)->first(
            static fn (DepartmentData $department): bool => $department->name === $name,
        );
    }

    /**
     * @return Collection<int, DepartmentData>
     */
    public function search(string $query, int $limit = 50, bool $fresh = false): Collection
    {
        $query = SearchTerm::normalize($query);
        $limit = max(0, $limit);

        if ($limit === 0 || SearchTerm::tooShort($query)) {
            return collect();
        }

        if ($this->guard->methodFor('departments.search') === 'im.search.department.list') {
            $body = $this->client->call('im.search.department.list', [
                'FIND' => $query,
                'LIMIT' => min(50, $limit),
                'OFFSET' => 0,
                'USER_DATA' => 'Y',
            ]);
            $rows = $body['result'] ?? [];

            if (! is_array($rows)) {
                return collect();
            }

            return collect(array_values(array_filter($rows, 'is_array')))
                ->map(static fn (array $row): DepartmentData => DepartmentData::fromArray($row))
                ->filter(static fn (DepartmentData $department): bool => $department->id > 0)
                ->take($limit)
                ->values();
        }

        return $this->all($fresh)
            ->filter(static fn (DepartmentData $department): bool => mb_stripos($department->name, $query) !== false)
            ->take($limit)
            ->values();
    }

    /**
     * IM returns a human-readable full_name ("Backend / IT / Company") but no
     * parent id. Resolve the parent by the previous path segment.
     *
     * @param  list<DepartmentData>  $departments
     * @return list<DepartmentData>
     */
    private function withParents(array $departments): array
    {
        $idsByName = [];

        foreach ($departments as $department) {
            if ($department->name !== '' && ! isset($idsByName[$department->name])) {
                $idsByName[$department->name] = $department->id;
            }
        }

        $result = [];

        foreach ($departments as $department) {
            $parentId = $department->parentId;

            if ($parentId === null && $department->fullName !== null) {
                $parts = array_values(array_filter(array_map('trim', explode('/', $department->fullName))));
                array_shift($parts);
                $parentName = $parts[0] ?? null;
                $parentId = $parentName !== null ? ($idsByName[$parentName] ?? null) : null;
            }

            $result[] = new DepartmentData(
                id: $department->id,
                name: $department->name,
                parentId: $parentId,
                sort: $department->sort,
                headUserId: $department->headUserId,
                customFields: $department->customFields,
                fullName: $department->fullName,
                managerUserId: $department->managerUserId,
            );
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractImDepartments(mixed $payload): array
    {
        $found = [];
        $stack = [$payload];

        while ($stack !== []) {
            $current = array_pop($stack);

            if (! is_array($current)) {
                continue;
            }

            $hasId = isset($current['id']) || isset($current['ID']);
            $isDepartment = isset($current['full_name'])
                || isset($current['FULL_NAME'])
                || isset($current['manager_user_id'])
                || isset($current['PARENT']);

            if ($hasId && $isDepartment) {
                $found[] = $current;
            }

            foreach ($current as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return $found;
    }
}
