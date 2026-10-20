<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

/**
 * @template T
 */
final readonly class Page
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public array $items,
        public ?int $total,
        public ?int $nextStart,
    ) {}

    public function hasMore(): bool
    {
        return $this->nextStart !== null;
    }
}
