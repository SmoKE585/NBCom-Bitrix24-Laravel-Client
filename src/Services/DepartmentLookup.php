<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Services;

use Illuminate\Support\Collection;
use Nbcom\Bitrix24\Contracts\CapabilityGuardInterface;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Dto\DepartmentData;
use Nbcom\Bitrix24\Dto\DepartmentTree;
use Nbcom\Bitrix24\Support\ResultCache;

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

        $this->guard->ensure('department.get');
        $body = $this->client->call('department.get', [
            'ID' => $id,
        ]);
        $rows = $body['result'] ?? [];
        $row = is_array($rows) ? ($rows[0] ?? null) : null;

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

        $this->guard->ensure('department.get');
        $rows = [];
        $start = 0;

        do {
            $body = $this->client->call('department.get', [
                'sort' => 'SORT',
                'order' => 'ASC',
                'start' => $start,
            ]);
            $chunk = $body['result'] ?? [];

            if (! is_array($chunk) || $chunk === []) {
                break;
            }

            foreach ($chunk as $row) {
                if (is_array($row)) {
                    $rows[] = DepartmentData::fromArray($row);
                }
            }

            $count = count($chunk);
            $start += $count;
            $total = isset($body['total']) ? (int) $body['total'] : null;
        } while ($count === 50 && ($total === null || $start < $total));

        $collection = collect($rows)->values();
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
}
