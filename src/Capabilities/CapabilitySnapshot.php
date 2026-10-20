<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Capabilities;

use Carbon\CarbonImmutable;
use Nbcom\Bitrix24\Auth\Endpoint;

final readonly class CapabilitySnapshot
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, MethodStatus>  $methods
     * @param  array<string, string>  $chosen
     * @param  list<string>  $missingScopes
     * @param  list<string>  $missingMethods
     */
    public function __construct(
        public int $version,
        public string $fingerprint,
        public string $portal,
        public int $userId,
        public CarbonImmutable $checkedAt,
        public ?CarbonImmutable $expiresAt,
        public string $packageVersion,
        public string $strategy,
        public array $scopes,
        public array $methods,
        public array $chosen,
        public array $missingScopes,
        public array $missingMethods,
    ) {}

    public function isExpired(?CarbonImmutable $now = null): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }

    public function isValidFor(Endpoint $endpoint, ?CarbonImmutable $now = null): bool
    {
        return $this->fingerprint === $endpoint->fingerprint()
            && ! $this->isExpired($now);
    }

    public function supports(string $method): bool
    {
        return ($this->methods[$method] ?? null)?->available === true;
    }

    public function hasMissing(): bool
    {
        return $this->missingScopes !== [] || $this->missingMethods !== [];
    }

    public function withExpiresAt(CarbonImmutable $expiresAt): self
    {
        return new self(
            version: $this->version,
            fingerprint: $this->fingerprint,
            portal: $this->portal,
            userId: $this->userId,
            checkedAt: $this->checkedAt,
            expiresAt: $expiresAt,
            packageVersion: $this->packageVersion,
            strategy: $this->strategy,
            scopes: $this->scopes,
            methods: $this->methods,
            chosen: $this->chosen,
            missingScopes: $this->missingScopes,
            missingMethods: $this->missingMethods,
        );
    }

    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $methods
     * @param  array<string, string>  $chosen
     */
    public static function trusted(
        Endpoint $endpoint,
        array $scopes,
        array $methods,
        array $chosen,
        string $packageVersion,
    ): self {
        $statuses = [];

        foreach ($methods as $method) {
            $statuses[$method] = new MethodStatus(exists: true, available: true);
        }

        return new self(
            version: 1,
            fingerprint: $endpoint->fingerprint(),
            portal: $endpoint->host(),
            userId: $endpoint->userId(),
            checkedAt: CarbonImmutable::now(),
            expiresAt: null,
            packageVersion: $packageVersion,
            strategy: 'disabled',
            scopes: $scopes,
            methods: $statuses,
            chosen: $chosen,
            missingScopes: [],
            missingMethods: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'fingerprint' => $this->fingerprint,
            'portal' => $this->portal,
            'user_id' => $this->userId,
            'checked_at' => $this->checkedAt->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'package_version' => $this->packageVersion,
            'strategy' => $this->strategy,
            'scopes' => $this->scopes,
            'methods' => array_map(
                static fn (MethodStatus $status): array => $status->toArray(),
                $this->methods,
            ),
            'chosen' => $this->chosen,
            'missing' => [
                'scopes' => $this->missingScopes,
                'methods' => $this->missingMethods,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $methods = [];

        foreach (($data['methods'] ?? []) as $name => $status) {
            if (is_string($name) && is_array($status)) {
                $methods[$name] = MethodStatus::fromArray($status);
            }
        }

        $chosen = [];

        foreach (($data['chosen'] ?? []) as $feature => $method) {
            if (is_string($feature) && is_string($method)) {
                $chosen[$feature] = $method;
            }
        }

        $missing = is_array($data['missing'] ?? null) ? $data['missing'] : [];

        return new self(
            version: (int) ($data['version'] ?? 1),
            fingerprint: (string) ($data['fingerprint'] ?? ''),
            portal: (string) ($data['portal'] ?? ''),
            userId: (int) ($data['user_id'] ?? 0),
            checkedAt: CarbonImmutable::parse((string) ($data['checked_at'] ?? 'now')),
            expiresAt: isset($data['expires_at']) && is_string($data['expires_at'])
                ? CarbonImmutable::parse($data['expires_at'])
                : null,
            packageVersion: (string) ($data['package_version'] ?? ''),
            strategy: (string) ($data['strategy'] ?? 'unknown'),
            scopes: self::stringList($data['scopes'] ?? []),
            methods: $methods,
            chosen: $chosen,
            missingScopes: self::stringList($missing['scopes'] ?? []),
            missingMethods: self::stringList($missing['methods'] ?? []),
        );
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
