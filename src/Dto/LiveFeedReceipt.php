<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

use JsonSerializable;

final readonly class LiveFeedReceipt implements JsonSerializable
{
    public function __construct(
        public ?int $postId,
        public bool $delivered,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'postId' => $this->postId,
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
