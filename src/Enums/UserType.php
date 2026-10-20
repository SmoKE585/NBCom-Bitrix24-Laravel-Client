<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Enums;

enum UserType: string
{
    case Employee = 'employee';
    case Extranet = 'extranet';
    case Email = 'email';
    case Unknown = 'unknown';

    public static function fromBitrix(mixed $value): self
    {
        if (! is_string($value)) {
            return self::Unknown;
        }

        return self::tryFrom(strtolower(trim($value))) ?? self::Unknown;
    }
}
