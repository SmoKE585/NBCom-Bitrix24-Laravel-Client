<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Capabilities;

use Carbon\CarbonImmutable;
use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Contracts\CapabilityGuardInterface;
use Nbcom\Bitrix24\Contracts\SnapshotStoreInterface;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24CapabilityException;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;
use Nbcom\Bitrix24\Support\Version;
use Psr\Log\LoggerInterface;

final class CapabilityManager implements CapabilityGuardInterface
{
    public function __construct(
        private readonly CapabilityChecker $checker,
        private readonly SnapshotStoreInterface $store,
        private readonly Endpoint $endpoint,
        private readonly FileLock $lock,
        private readonly CapabilityRegistry $registry,
        private readonly bool $enabled = true,
        private readonly bool $failFast = true,
        private readonly bool $preflight = true,
        private readonly bool $allowStale = true,
        private readonly int $ttl = 604800,
        private readonly int $negativeTtl = 300,
        private readonly float $lockTimeout = 10.0,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function snapshot(bool $refresh = false): CapabilitySnapshot
    {
        if (! $this->enabled) {
            return $this->trustedSnapshot();
        }

        $existing = $this->store->read();

        if (! $refresh && $existing !== null && $existing->isValidFor($this->endpoint)) {
            return $existing;
        }

        $locked = $this->lock->acquire($this->lockTimeout);

        try {
            $existing = $this->store->read();

            if (! $refresh && $existing !== null && $existing->isValidFor($this->endpoint)) {
                return $existing;
            }

            try {
                $snapshot = $this->checker->check();
                $seconds = $snapshot->hasMissing() ? $this->negativeTtl : $this->ttl;
                $snapshot = $snapshot->withExpiresAt(CarbonImmutable::now()->addSeconds(max(0, $seconds)));
                $this->store->write($snapshot);

                return $snapshot;
            } catch (Bitrix24Exception $exception) {
                if ($existing !== null && $this->allowStale) {
                    $this->logger?->warning('Bitrix24 capabilities check failed, using stale snapshot', [
                        'reason' => $exception->reason()->value,
                    ]);

                    return $existing;
                }

                throw $exception;
            }
        } finally {
            if ($locked) {
                $this->lock->release();
            }
        }
    }

    public function ensure(string $method): void
    {
        if (! $this->enabled || ! $this->preflight) {
            return;
        }

        $snapshot = $this->snapshot();

        if ($snapshot->supports($method)) {
            return;
        }

        $exception = $this->capabilityException($snapshot, $method);

        if ($this->failFast) {
            throw $exception;
        }

        $this->logger?->warning('Bitrix24 method is not available, continuing without preflight', [
            'method' => $method,
            'missing_scopes' => $snapshot->missingScopes,
            'missing_methods' => $snapshot->missingMethods,
        ]);
    }

    public function methodFor(string $feature): string
    {
        if (! $this->enabled) {
            return $this->registry->primaryMethod($feature);
        }

        $snapshot = $this->snapshot();
        $method = $snapshot->chosen[$feature] ?? null;

        if ($method !== null) {
            return $method;
        }

        $exception = $this->capabilityException($snapshot, $feature);

        if ($this->failFast) {
            throw $exception;
        }

        return $this->registry->primaryMethod($feature);
    }

    public function forget(): void
    {
        $this->store->forget();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    private function trustedSnapshot(): CapabilitySnapshot
    {
        $chosen = [];

        foreach ($this->registry->featureMethods() as $feature => $alternatives) {
            $chosen[$feature] = $alternatives[0];
        }

        return CapabilitySnapshot::trusted(
            endpoint: $this->endpoint,
            scopes: $this->registry->scopes(),
            methods: $this->registry->allMethods(),
            chosen: $chosen,
            packageVersion: Version::VERSION,
        );
    }

    private function capabilityException(CapabilitySnapshot $snapshot, string $method): Bitrix24CapabilityException
    {
        $parts = [];

        if ($snapshot->missingScopes !== []) {
            $parts[] = 'missing scopes: '.implode(', ', $snapshot->missingScopes);
        }

        if ($snapshot->missingMethods !== []) {
            $parts[] = 'missing methods: '.implode(', ', $snapshot->missingMethods);
        }

        $details = $parts === [] ? 'method is not available' : implode('; ', $parts);
        $message = "Bitrix24 capabilities are incomplete for [{$method}] ({$details}). "
            .'Add the required scopes to the inbound webhook and run "php artisan bitrix24:check --refresh".';

        return new Bitrix24CapabilityException(
            message: $message,
            reason: Bitrix24ErrorReason::Capability,
            method: $method,
            context: [
                'missing_scopes' => $snapshot->missingScopes,
                'missing_methods' => $snapshot->missingMethods,
            ],
            retryable: false,
        );
    }
}
