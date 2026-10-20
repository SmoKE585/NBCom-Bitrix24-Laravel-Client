<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Support;

final class SearchTerm
{
    public static function normalize(string $query): string
    {
        $query = trim($query);

        return preg_replace('/\s+/u', ' ', $query) ?? $query;
    }

    public static function tooShort(string $query): bool
    {
        return mb_strlen($query) < 2;
    }
}
