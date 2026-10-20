<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Dto;

use Carbon\CarbonImmutable;
use JsonSerializable;
use Nbcom\Bitrix24\Enums\UserType;
use Throwable;

final readonly class UserData implements JsonSerializable
{
    /**
     * @param  list<int>  $departmentIds
     * @param  array<string, mixed>  $customFields
     */
    public function __construct(
        public int $id,
        public bool $active,
        public string $firstName,
        public ?string $lastName,
        public ?string $secondName,
        public string $fullName,
        public ?string $email,
        public ?string $login,
        public ?string $workPosition,
        public ?string $workPhone,
        public ?string $mobilePhone,
        public ?string $innerPhone,
        public array $departmentIds,
        public UserType $userType,
        public ?string $timeZone,
        public bool $online,
        public ?CarbonImmutable $lastLoginAt,
        public ?CarbonImmutable $registeredAt,
        public ?string $avatarUrl,
        public array $customFields = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = (int) ($data['ID'] ?? $data['id'] ?? 0);
        $firstName = self::string($data['NAME'] ?? $data['firstName'] ?? $data['first_name'] ?? $data['FIRST_NAME'] ?? null) ?? '';
        $lastName = self::string($data['LAST_NAME'] ?? $data['lastName'] ?? $data['last_name'] ?? $data['LAST_NAME'] ?? null);
        $secondName = self::string($data['SECOND_NAME'] ?? $data['secondName'] ?? $data['second_name'] ?? null);
        $email = self::string($data['EMAIL'] ?? $data['email'] ?? null);
        $fullName = self::string($data['fullName'] ?? $data['name'] ?? null)
            ?? trim(implode(' ', array_filter([$lastName, $firstName, $secondName], static fn (?string $part): bool => $part !== null && $part !== '')));

        if ($fullName === '') {
            $fullName = $email ?? '#'.$id;
        }

        $phones = is_array($data['phones'] ?? null) ? $data['phones'] : [];

        return new self(
            id: $id,
            active: self::bool($data['ACTIVE'] ?? $data['active'] ?? false),
            firstName: $firstName,
            lastName: $lastName,
            secondName: $secondName,
            fullName: $fullName,
            email: $email,
            login: self::string($data['LOGIN'] ?? $data['login'] ?? null),
            workPosition: self::string($data['WORK_POSITION'] ?? $data['workPosition'] ?? $data['work_position'] ?? null),
            workPhone: self::string($data['WORK_PHONE'] ?? $data['workPhone'] ?? $data['work_phone'] ?? $phones['work_phone'] ?? null),
            mobilePhone: self::string($data['PERSONAL_MOBILE'] ?? $data['mobilePhone'] ?? $data['personal_mobile'] ?? $phones['personal_mobile'] ?? null),
            innerPhone: self::string($data['UF_PHONE_INNER'] ?? $data['innerPhone'] ?? $data['inner_phone'] ?? $phones['inner_phone'] ?? null),
            departmentIds: self::intList($data['UF_DEPARTMENT'] ?? $data['departmentIds'] ?? $data['departments'] ?? []),
            userType: UserType::fromBitrix($data['USER_TYPE'] ?? $data['userType'] ?? $data['type'] ?? null),
            timeZone: self::string($data['TIME_ZONE'] ?? $data['timeZone'] ?? null),
            online: self::bool($data['IS_ONLINE'] ?? $data['online'] ?? (($data['status'] ?? null) === 'online')),
            lastLoginAt: self::date($data['LAST_LOGIN'] ?? $data['lastLoginAt'] ?? null),
            registeredAt: self::date($data['DATE_REGISTER'] ?? $data['registeredAt'] ?? null),
            avatarUrl: self::avatar($data['PERSONAL_PHOTO'] ?? $data['avatarUrl'] ?? $data['avatar_hr'] ?? $data['avatar'] ?? null),
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
            'active' => $this->active,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'secondName' => $this->secondName,
            'fullName' => $this->fullName,
            'email' => $this->email,
            'login' => $this->login,
            'workPosition' => $this->workPosition,
            'workPhone' => $this->workPhone,
            'mobilePhone' => $this->mobilePhone,
            'innerPhone' => $this->innerPhone,
            'departmentIds' => $this->departmentIds,
            'userType' => $this->userType->value,
            'timeZone' => $this->timeZone,
            'online' => $this->online,
            'lastLoginAt' => $this->lastLoginAt?->toIso8601String(),
            'registeredAt' => $this->registeredAt?->toIso8601String(),
            'avatarUrl' => $this->avatarUrl,
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

    private static function string(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'y', 'yes', 'on'], true);
    }

    /**
     * @return list<int>
     */
    private static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            $value = $value === null || $value === false || $value === '' ? [] : [$value];
        }

        $result = [];

        foreach ($value as $item) {
            if (is_numeric($item) && (int) $item > 0) {
                $result[] = (int) $item;
            }
        }

        return array_values(array_unique($result));
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return CarbonImmutable::createFromTimestamp((int) $value);
            }

            if (is_string($value)) {
                return CarbonImmutable::parse($value);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function avatar(mixed $value): ?string
    {
        if (is_string($value)) {
            return self::string($value);
        }

        if (is_array($value)) {
            return self::string($value['src'] ?? $value['URL'] ?? null);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function customFields(array $data): array
    {
        $known = ['UF_DEPARTMENT', 'UF_PHONE_INNER'];
        $fields = is_array($data['customFields'] ?? null) ? $data['customFields'] : [];

        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'UF_') && ! in_array($key, $known, true)) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }
}
