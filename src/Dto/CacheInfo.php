<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

final readonly class CacheInfo
{
    public function __construct(
        public bool $enabled,
        public string $store,
        public int $ttl,
    ) {}

    /**
     * @return array{enabled: bool, store: string, ttl: int}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'store' => $this->store,
            'ttl' => $this->ttl,
        ];
    }
}
