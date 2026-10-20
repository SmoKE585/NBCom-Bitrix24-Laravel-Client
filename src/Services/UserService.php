<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Services;

use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Nbcom\Bitrix24\Contracts\CapabilityGuardInterface;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Dto\UserData;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;
use Nbcom\Bitrix24\Exceptions\Bitrix24NotFoundException;
use Nbcom\Bitrix24\Support\ResultCache;
use Nbcom\Bitrix24\Support\SearchTerm;
use Nbcom\Bitrix24\Support\UserFilter;

final class UserService
{
    private const SELECT = [
        'ID',
        'ACTIVE',
        'NAME',
        'LAST_NAME',
        'SECOND_NAME',
        'EMAIL',
        'LOGIN',
        'WORK_POSITION',
        'WORK_PHONE',
        'PERSONAL_MOBILE',
        'UF_PHONE_INNER',
        'UF_DEPARTMENT',
        'USER_TYPE',
        'TIME_ZONE',
        'LAST_LOGIN',
        'DATE_REGISTER',
        'IS_ONLINE',
        'PERSONAL_PHOTO',
    ];

    private bool $fresh = false;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly CapabilityGuardInterface $guard,
        private readonly ResultCache $cache,
        private readonly DepartmentLookup $departments,
    ) {}

    public function fresh(): static
    {
        $clone = clone $this;
        $clone->fresh = true;

        return $clone;
    }

    public function get(int $id, bool $fresh = false): ?UserData
    {
        if ($id <= 0) {
            return null;
        }

        $fresh = $fresh || $this->fresh;
        $key = 'user:'.$id;

        if (! $fresh) {
            $cached = $this->cache->get($key);

            if (is_array($cached)) {
                return UserData::fromArray($cached);
            }
        }

        $this->guard->ensure('user.get');
        $body = $this->client->call('user.get', [
            'filter' => ['ID' => $id],
            'select' => self::SELECT,
        ]);
        $rows = $body['result'] ?? [];
        $row = is_array($rows) ? ($rows[0] ?? null) : null;

        if (! is_array($row)) {
            return null;
        }

        $user = UserData::fromArray($row);
        $this->cache->put($key, $user->toArray());

        return $user;
    }

    public function getOrFail(int $id): UserData
    {
        $user = $this->get($id);

        if ($user === null) {
            throw new Bitrix24NotFoundException(
                message: "Bitrix24 user [{$id}] was not found.",
                reason: Bitrix24ErrorReason::NotFound,
                method: 'user.get',
                context: ['user_id' => $id],
            );
        }

        return $user;
    }

    public function findByEmail(string $email): ?UserData
    {
        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $key = 'user:email:'.sha1(strtolower($email));
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return UserData::fromArray($cached);
        }

        $this->guard->ensure('user.get');
        $body = $this->client->call('user.get', [
            'filter' => ['EMAIL' => $email],
            'select' => self::SELECT,
        ]);
        $rows = $body['result'] ?? [];
        $row = is_array($rows) ? ($rows[0] ?? null) : null;

        if (! is_array($row)) {
            return null;
        }

        $user = UserData::fromArray($row);
        $this->cache->put($key, $user->toArray());

        return $user;
    }

    public function findByLogin(string $login): ?UserData
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        $this->guard->ensure('user.get');
        $body = $this->client->call('user.get', [
            'filter' => ['LOGIN' => $login],
            'select' => self::SELECT,
        ]);
        $rows = $body['result'] ?? [];
        $row = is_array($rows) ? ($rows[0] ?? null) : null;

        return is_array($row) ? UserData::fromArray($row) : null;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, UserData>
     */
    public function getMany(array $ids): Collection
    {
        $users = [];
        $seen = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }

            $user = $this->get($id);

            if ($user !== null) {
                $seen[$id] = true;
                $users[] = $user;
            }
        }

        return collect($users);
    }

    /**
     * @return Collection<int, UserData>
     */
    public function search(string $query, int $limit = 50, ?UserFilter $filter = null): Collection
    {
        $query = SearchTerm::normalize($query);
        $limit = max(0, $limit);

        if ($limit === 0 || SearchTerm::tooShort($query)) {
            return collect();
        }

        $filter ??= UserFilter::make()->activeOnly();
        $method = $this->guard->methodFor('users.search');

        if ($method === 'im.search.user.list') {
            return $this->searchWithIm($query, $limit, $filter);
        }

        $this->guard->ensure('user.search');
        $params = [
            'FILTER' => array_merge(['FIND' => $query], $filter->toArray()),
        ];
        $users = [];

        foreach ($this->paginate('user.search', $params, $limit) as $row) {
            $users[] = UserData::fromArray($row);
        }

        return collect($users);
    }

    /**
     * @return Collection<int, UserData>
     */
    private function searchWithIm(string $query, int $limit, UserFilter $filter): Collection
    {
        $users = [];
        $offset = 0;

        while (count($users) < $limit) {
            $requested = min(50, $limit - count($users));
            $body = $this->client->call('im.search.user.list', [
                'FIND' => $query,
                'LIMIT' => $requested,
                'OFFSET' => $offset,
            ]);
            $rows = $this->normalizeImRows($body['result'] ?? []);

            if ($rows === []) {
                break;
            }

            $added = 0;

            foreach ($rows as $row) {
                $user = UserData::fromArray($row);

                if ($user->id <= 0 || ! $this->matchesFilter($user, $filter)) {
                    continue;
                }

                if (! isset($users[$user->id])) {
                    $users[$user->id] = $user;
                    $added++;
                }
            }

            $offset += count($rows);

            if ($added === 0 || count($rows) < $requested) {
                break;
            }
        }

        return collect(array_values($users));
    }

    /**
     * @return Collection<int, UserData>
     */
    public function inDepartment(
        int $departmentId,
        bool $includeNested = false,
        ?UserFilter $filter = null,
        bool $fresh = false,
    ): Collection {
        if ($departmentId <= 0) {
            return collect();
        }

        $departmentIds = $this->resolveDepartmentIds($departmentId, $includeNested, $fresh);
        $filter = $filter !== null ? clone $filter : UserFilter::make()->activeOnly();
        $method = $this->guard->methodFor('departments.employees');

        if ($method === 'im.department.employees.get') {
            return $this->inDepartmentWithIm($departmentIds, $filter);
        }

        return $this->inDepartmentClassic($departmentIds, $filter);
    }

    /**
     * @return list<int>
     */
    public function departmentEmployeeIds(int $departmentId, bool $includeNested = false, bool $fresh = false): array
    {
        if ($departmentId <= 0) {
            return [];
        }

        $departmentIds = $this->resolveDepartmentIds($departmentId, $includeNested, $fresh);
        $method = $this->guard->methodFor('departments.employees');

        if ($method === 'im.department.employees.get') {
            try {
                $body = $this->client->call('im.department.employees.get', [
                    'ID' => $departmentIds,
                ]);

                $ids = $this->extractImIds($body['result'] ?? []);

                if ($ids !== []) {
                    return $ids;
                }
            } catch (Bitrix24Exception $exception) {
                if (! in_array($exception->reason(), [Bitrix24ErrorReason::Forbidden, Bitrix24ErrorReason::MethodNotFound], true)) {
                    throw $exception;
                }
            }
        }

        return $this->inDepartmentClassic($departmentIds, UserFilter::make())->pluck('id')->all();
    }

    /**
     * @param  list<int>  $departmentIds
     * @return Collection<int, UserData>
     */
    private function inDepartmentWithIm(array $departmentIds, UserFilter $filter): Collection
    {
        try {
            $body = $this->client->call('im.department.employees.get', [
                'ID' => $departmentIds,
                'USER_DATA' => 'Y',
            ]);
        } catch (Bitrix24Exception $exception) {
            if (! in_array($exception->reason(), [Bitrix24ErrorReason::Forbidden, Bitrix24ErrorReason::MethodNotFound], true)) {
                throw $exception;
            }

            return $this->inDepartmentClassic($departmentIds, $filter);
        }

        $users = [];
        $seen = [];

        foreach ($this->extractImUsers($body['result'] ?? []) as $row) {
            $user = UserData::fromArray($row);

            if ($user->id <= 0 || isset($seen[$user->id]) || ! $this->matchesFilter($user, $filter)) {
                continue;
            }

            $seen[$user->id] = true;
            $users[] = $user;
        }

        return collect($users)->sortBy('id')->values();
    }

    /**
     * @param  list<int>  $departmentIds
     * @return Collection<int, UserData>
     */
    private function inDepartmentClassic(array $departmentIds, UserFilter $filter): Collection
    {
        $filter = clone $filter;
        $filter->inDepartments($departmentIds);
        $this->guard->ensure('user.get');
        $users = [];

        foreach ($this->paginate('user.get', ['filter' => $filter->toArray()]) as $row) {
            $users[] = UserData::fromArray($row);
        }

        return collect($users);
    }

    /**
     * @return list<int>
     */
    private function resolveDepartmentIds(int $departmentId, bool $includeNested, bool $fresh): array
    {
        $departmentIds = [$departmentId];

        if ($includeNested) {
            $tree = $this->departments->tree($fresh || $this->fresh);
            $departmentIds = array_merge(
                $departmentIds,
                $tree->descendantsOf($departmentId)->pluck('id')->all(),
            );
        }

        return array_values(array_unique(array_map('intval', $departmentIds)));
    }

    /**
     * All employees of a department except the given user.
     *
     * @return Collection<int, UserData>
     */
    public function colleaguesOf(
        int $departmentId,
        ?int $excludeUserId = null,
        bool $includeNested = false,
        ?UserFilter $filter = null,
        bool $fresh = false,
    ): Collection {
        $users = $this->inDepartment(
            departmentId: $departmentId,
            includeNested: $includeNested,
            filter: $filter,
            fresh: $fresh,
        );

        if ($excludeUserId === null) {
            return $users;
        }

        return $users
            ->reject(static fn (UserData $user): bool => $user->id === $excludeUserId)
            ->values();
    }

    /**
     * @return LazyCollection<int, UserData>
     */
    public function all(?UserFilter $filter = null): LazyCollection
    {
        $params = $filter !== null ? ['filter' => $filter->toArray()] : [];
        $this->guard->ensure('user.get');

        return LazyCollection::make(function () use ($params): Generator {
            foreach ($this->paginate('user.get', $params) as $row) {
                yield UserData::fromArray($row);
            }
        });
    }

    public function current(bool $fresh = false): UserData
    {
        $fresh = $fresh || $this->fresh;
        $key = 'user:current';

        if (! $fresh) {
            $cached = $this->cache->get($key);

            if (is_array($cached)) {
                return UserData::fromArray($cached);
            }
        }

        $this->guard->ensure('user.current');
        $body = $this->client->call('user.current');
        $row = $body['result'] ?? null;

        if (! is_array($row)) {
            throw new Bitrix24NotFoundException(
                message: 'Bitrix24 did not return the current user.',
                reason: Bitrix24ErrorReason::NotFound,
                method: 'user.current',
            );
        }

        $user = UserData::fromArray($row);
        $this->cache->put($key, $user->toArray());

        return $user;
    }

    public function exists(int $id): bool
    {
        return $this->get($id) !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeImRows(mixed $result): array
    {
        if (! is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, 'is_array'));
    }

    private function matchesFilter(UserData $user, UserFilter $filter): bool
    {
        if ($filter->active() !== null && $user->active !== $filter->active()) {
            return false;
        }

        if ($filter->userTypeFilter() !== null && $user->userType !== $filter->userTypeFilter()) {
            return false;
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractImUsers(mixed $payload): array
    {
        $found = [];
        $stack = [$payload];

        while ($stack !== []) {
            $current = array_pop($stack);

            if (! is_array($current)) {
                continue;
            }

            $hasId = isset($current['id']) || isset($current['ID']);
            $hasName = isset($current['name'])
                || isset($current['NAME'])
                || isset($current['first_name'])
                || isset($current['FIRST_NAME']);

            if ($hasId && $hasName) {
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

    /**
     * @return list<int>
     */
    private function extractImIds(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $ids = [];

        array_walk_recursive($payload, static function (mixed $value) use (&$ids): void {
            if (is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        });

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Generator<int, array<string, mixed>>
     */
    private function paginate(string $method, array $params, int $limit = PHP_INT_MAX): Generator
    {
        $start = 0;
        $yielded = 0;

        do {
            $body = $this->client->call($method, array_merge($params, ['start' => $start]));
            $rows = $body['result'] ?? [];

            if (! is_array($rows) || $rows === []) {
                return;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                yield $row;
                $yielded++;

                if ($yielded >= $limit) {
                    return;
                }
            }

            $count = count($rows);
            $start += $count;
            $total = isset($body['total']) ? (int) $body['total'] : null;
        } while ($count === 50 && ($total === null || $start < $total));
    }
}
