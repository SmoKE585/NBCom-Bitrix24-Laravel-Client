<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Client;

use Illuminate\Contracts\Config\Repository;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;

final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $baseDelayMs = 200,
        public int $maxDelayMs = 2000,
        public bool $retryTransport = true,
        public bool $retryRateLimit = true,
        public bool $retryServer = true,
    ) {}

    public static function fromConfig(Repository $config): self
    {
        $enabled = (bool) $config->get('bitrix24.http.retry.enabled', true);

        return new self(
            maxAttempts: $enabled ? max(1, (int) $config->get('bitrix24.http.retry.max_attempts', 3)) : 1,
            baseDelayMs: max(0, (int) $config->get('bitrix24.http.retry.base_delay_ms', 200)),
            maxDelayMs: max(0, (int) $config->get('bitrix24.http.retry.max_delay_ms', 2000)),
        );
    }

    public static function none(): self
    {
        return new self(maxAttempts: 1);
    }

    /**
     * Notifications must not be retried after a connection timeout: the request
     * may have been processed already and a retry would create a duplicate.
     */
    public static function forNotifications(): self
    {
        return new self(
            maxAttempts: 3,
            retryTransport: false,
            retryRateLimit: true,
            retryServer: true,
        );
    }

    public function allows(Bitrix24Exception $exception): bool
    {
        if ($this->maxAttempts <= 1) {
            return false;
        }

        return match ($exception->reason()) {
            Bitrix24ErrorReason::Transport => $this->retryTransport,
            Bitrix24ErrorReason::RateLimit => $this->retryRateLimit,
            Bitrix24ErrorReason::Server => $this->retryServer,
            default => false,
        };
    }
}
