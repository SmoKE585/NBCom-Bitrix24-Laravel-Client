<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Capabilities;

final readonly class MethodStatus
{
    public function __construct(
        public bool $exists,
        public bool $available,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            exists: (bool) ($data['exists'] ?? $data['isExisting'] ?? false),
            available: (bool) ($data['available'] ?? $data['isAvailable'] ?? false),
        );
    }

    /**
     * @return array{exists: bool, available: bool}
     */
    public function toArray(): array
    {
        return [
            'exists' => $this->exists,
            'available' => $this->available,
        ];
    }
}
