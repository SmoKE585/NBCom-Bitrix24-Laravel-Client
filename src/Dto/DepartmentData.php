<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

use JsonSerializable;

final readonly class DepartmentData implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $customFields
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?int $parentId,
        public int $sort,
        public ?int $headUserId,
        public array $customFields = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['ID'] ?? $data['id'] ?? 0),
            name: (string) ($data['NAME'] ?? $data['name'] ?? ''),
            parentId: self::intOrNull($data['PARENT'] ?? $data['parentId'] ?? $data['parent'] ?? null),
            sort: (int) ($data['SORT'] ?? $data['sort'] ?? 0),
            headUserId: self::intOrNull($data['UF_HEAD'] ?? $data['headUserId'] ?? $data['head'] ?? null),
            customFields: self::customFields($data),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parentId' => $this->parentId,
            'sort' => $this->sort,
            'headUserId' => $this->headUserId,
            'customFields' => $this->customFields,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function customFields(array $data): array
    {
        $fields = is_array($data['customFields'] ?? null) ? $data['customFields'] : [];

        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'UF_') && $key !== 'UF_HEAD') {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }
}
