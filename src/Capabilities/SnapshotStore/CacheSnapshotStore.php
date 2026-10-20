<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Capabilities\SnapshotStore;

use Illuminate\Contracts\Cache\Repository;
use Nbcom\Bitrix24\Capabilities\CapabilitySnapshot;
use Nbcom\Bitrix24\Contracts\SnapshotStoreInterface;
use Throwable;

final class CacheSnapshotStore implements SnapshotStoreInterface
{
    public function __construct(
        private readonly Repository $cache,
        private readonly string $key = 'bitrix24:capabilities',
        private readonly int $ttl = 0,
    ) {}

    public function read(): ?CapabilitySnapshot
    {
        $data = $this->cache->get($this->key);

        if (! is_array($data)) {
            return null;
        }

        try {
            return CapabilitySnapshot::fromArray($data);
        } catch (Throwable) {
            return null;
        }
    }

    public function write(CapabilitySnapshot $snapshot): void
    {
        $this->cache->put($this->key, $snapshot->toArray(), $this->ttl > 0 ? $this->ttl : null);
    }

    public function forget(): void
    {
        $this->cache->forget($this->key);
    }
}
