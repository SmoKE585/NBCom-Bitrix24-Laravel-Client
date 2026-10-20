<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24;

use Nbcom\Bitrix24\Capabilities\CapabilityManager;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Dto\CacheInfo;
use Nbcom\Bitrix24\Dto\HealthReport;
use Nbcom\Bitrix24\Dto\UserData;
use Nbcom\Bitrix24\Services\DepartmentService;
use Nbcom\Bitrix24\Services\NotificationService;
use Nbcom\Bitrix24\Services\UserService;
use Nbcom\Bitrix24\Support\ResultCache;
use Nbcom\Bitrix24\Support\Version;

final class Bitrix24Manager
{
    public function __construct(
        private readonly UserService $users,
        private readonly DepartmentService $departments,
        private readonly NotificationService $notifications,
        private readonly CapabilityManager $capabilities,
        private readonly ResultCache $cache,
        private readonly ClientInterface $client,
    ) {}

    public function users(): UserService
    {
        return $this->users;
    }

    public function departments(): DepartmentService
    {
        return $this->departments;
    }

    public function notifications(): NotificationService
    {
        return $this->notifications;
    }

    public function me(bool $fresh = false): UserData
    {
        return $this->users->current($fresh);
    }

    public function health(bool $refresh = false): HealthReport
    {
        $snapshot = $this->capabilities->snapshot($refresh);
        $stale = $snapshot->isExpired();

        return new HealthReport(
            ok: ! $snapshot->hasMissing() && ! $stale,
            portal: $snapshot->portal,
            userId: $snapshot->userId,
            checkedAt: $snapshot->checkedAt,
            stale: $stale,
            strategy: $snapshot->strategy,
            scopes: $snapshot->scopes,
            missingScopes: $snapshot->missingScopes,
            missingMethods: $snapshot->missingMethods,
            packageVersion: Version::VERSION,
            cache: $this->cache->info(),
        );
    }

    public function cacheInfo(): CacheInfo
    {
        return $this->cache->info();
    }

    /**
     * Low-level escape hatch. Prefer the typed services.
     *
     * @param  array<string, mixed>  $params
     */
    public function call(string $method, array $params = []): mixed
    {
        $body = $this->client->call($method, $params);

        return $body['result'] ?? null;
    }

    public function capabilities(): CapabilityManager
    {
        return $this->capabilities;
    }

    public function forgetCapabilities(): void
    {
        $this->capabilities->forget();
    }
}
