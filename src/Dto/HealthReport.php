<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class HealthReport implements JsonSerializable
{
    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $missingScopes
     * @param  list<string>  $missingMethods
     */
    public function __construct(
        public bool $ok,
        public string $portal,
        public int $userId,
        public ?CarbonImmutable $checkedAt,
        public bool $stale,
        public string $strategy,
        public array $scopes,
        public array $missingScopes,
        public array $missingMethods,
        public string $packageVersion,
        public CacheInfo $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'portal' => $this->portal,
            'userId' => $this->userId,
            'checkedAt' => $this->checkedAt?->toIso8601String(),
            'stale' => $this->stale,
            'strategy' => $this->strategy,
            'scopes' => $this->scopes,
            'missingScopes' => $this->missingScopes,
            'missingMethods' => $this->missingMethods,
            'packageVersion' => $this->packageVersion,
            'cache' => $this->cache->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
