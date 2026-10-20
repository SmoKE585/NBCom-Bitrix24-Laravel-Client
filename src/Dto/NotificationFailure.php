<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

use JsonSerializable;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;

final readonly class NotificationFailure implements JsonSerializable
{
    public function __construct(
        public int $userId,
        public Bitrix24ErrorReason $reason,
        public string $errorCode,
        public string $message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'reason' => $this->reason->value,
            'errorCode' => $this->errorCode,
            'message' => $this->message,
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
