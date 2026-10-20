<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Nbcom\Bitrix24\Dto\CacheInfo;

final class ResultCache
{
    public function __construct(
        private readonly ?Repository $cache,
        private readonly bool $enabled,
        private readonly int $ttl,
        private readonly string $prefix,
        private readonly string $store,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled && $this->cache !== null;
    }

    public function get(string $key): mixed
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return $this->cache->get($this->key($key));
    }

    public function put(string $key, mixed $value): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->cache->put($this->key($key), $value, max(1, $this->ttl));
    }

    public function remember(string $key, Closure $callback): mixed
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->put($key, $value);

        return $value;
    }

    public function forget(string $key): void
    {
        if ($this->isEnabled()) {
            $this->cache->forget($this->key($key));
        }
    }

    public function info(): CacheInfo
    {
        return new CacheInfo(
            enabled: $this->isEnabled(),
            store: $this->store,
            ttl: $this->ttl,
        );
    }

    private function key(string $key): string
    {
        return rtrim($this->prefix, ':').':'.$key;
    }
}
