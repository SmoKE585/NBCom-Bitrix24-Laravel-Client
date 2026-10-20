<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

use JsonSerializable;
use Nbcom\Bitrix24\Enums\NotificationChannel;

final readonly class NotificationReceipt implements JsonSerializable
{
    public function __construct(
        public int $userId,
        public NotificationChannel $channel,
        public ?int $notificationId,
        public bool $delivered,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'channel' => $this->channel->value,
            'notificationId' => $this->notificationId,
            'delivered' => $this->delivered,
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
