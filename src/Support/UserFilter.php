<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Support;

use Nbcom\Bitrix24\Enums\UserType;

final class UserFilter
{
    private ?bool $active = null;

    private ?UserType $userType = null;

    private ?string $name = null;

    private ?string $lastName = null;

    private ?string $email = null;

    private ?string $position = null;

    private ?string $innerPhone = null;

    public static function make(): self
    {
        return new self;
    }

    public function active(): ?bool
    {
        return $this->active;
    }

    public function userTypeFilter(): ?UserType
    {
        return $this->userType;
    }

    public function activeOnly(bool $active = true): self
    {
        $this->active = $active;

        return $this;
    }

    public function includeInactive(): self
    {
        $this->active = null;

        return $this;
    }

    public function userType(UserType $userType): self
    {
        $this->userType = $userType;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function lastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function email(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function positionContains(string $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function innerPhone(string $innerPhone): self
    {
        $this->innerPhone = $innerPhone;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $filter = [];

        if ($this->active !== null) {
            $filter['ACTIVE'] = $this->active;
        }

        if ($this->userType !== null) {
            $filter['USER_TYPE'] = $this->userType->value;
        }

        if ($this->name !== null && $this->name !== '') {
            $filter['NAME'] = $this->name;
        }

        if ($this->lastName !== null && $this->lastName !== '') {
            $filter['LAST_NAME'] = $this->lastName;
        }

        if ($this->email !== null && $this->email !== '') {
            $filter['EMAIL'] = $this->email;
        }

        if ($this->position !== null && $this->position !== '') {
            $filter['%WORK_POSITION'] = $this->position;
        }

        if ($this->innerPhone !== null && $this->innerPhone !== '') {
            $filter['UF_PHONE_INNER'] = $this->innerPhone;
        }

        return $filter;
    }
}
