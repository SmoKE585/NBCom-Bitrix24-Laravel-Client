<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

use JsonSerializable;

final readonly class DepartmentTeam implements JsonSerializable
{
    /**
     * @param  list<UserData>  $heads
     * @param  list<UserData>  $members
     * @param  list<DepartmentData>  $children
     * @param  list<DepartmentData>  $path
     */
    public function __construct(
        public DepartmentData $department,
        public ?UserData $head,
        public array $heads,
        public array $members,
        public array $children,
        public array $path,
    ) {}

    public function memberCount(): int
    {
        return count($this->members);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'department' => $this->department->toArray(),
            'head' => $this->head?->toArray(),
            'heads' => array_map(static fn (UserData $user): array => $user->toArray(), $this->heads),
            'members' => array_map(static fn (UserData $user): array => $user->toArray(), $this->members),
            'children' => array_map(static fn (DepartmentData $department): array => $department->toArray(), $this->children),
            'path' => array_map(static fn (DepartmentData $department): array => $department->toArray(), $this->path),
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
