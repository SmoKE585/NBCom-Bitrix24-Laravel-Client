<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

use JsonSerializable;

final readonly class NotificationReport implements JsonSerializable
{
    /**
     * @param  list<NotificationReceipt>  $sent
     * @param  list<NotificationFailure>  $failed
     */
    public function __construct(
        public array $sent,
        public array $failed,
        public int $total,
    ) {}

    public static function empty(): self
    {
        return new self([], [], 0);
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }

    public function successCount(): int
    {
        return count($this->sent);
    }

    public function failureCount(): int
    {
        return count($this->failed);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'sent' => array_map(static fn (NotificationReceipt $receipt): array => $receipt->toArray(), $this->sent),
            'failed' => array_map(static fn (NotificationFailure $failure): array => $failure->toArray(), $this->failed),
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
