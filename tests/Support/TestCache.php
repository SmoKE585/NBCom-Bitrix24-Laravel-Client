<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Support;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Nbcom\Bitrix24\Support\ResultCache;

final class TestCache
{
    public static function enabled(): ResultCache
    {
        return new ResultCache(
            cache: new Repository(new ArrayStore),
            enabled: true,
            ttl: 300,
            prefix: 'test:bitrix24',
            store: 'array',
        );
    }

    public static function disabled(): ResultCache
    {
        return new ResultCache(
            cache: null,
            enabled: false,
            ttl: 300,
            prefix: 'test:bitrix24',
            store: 'null',
        );
    }
}
