<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Nbcom\Bitrix24\Support\ResultCache;
use PHPUnit\Framework\TestCase;

final class ResultCacheTest extends TestCase
{
    public function test_remember_uses_cache_when_enabled(): void
    {
        $cache = new ResultCache(new Repository(new ArrayStore), true, 60, 'test', 'array');
        $calls = 0;

        $first = $cache->remember('key', function () use (&$calls): string {
            $calls++;

            return 'value';
        });
        $second = $cache->remember('key', function () use (&$calls): string {
            $calls++;

            return 'other';
        });

        self::assertSame('value', $first);
        self::assertSame('value', $second);
        self::assertSame(1, $calls);
        self::assertTrue($cache->isEnabled());
    }

    public function test_disabled_cache_always_calls_the_callback(): void
    {
        $cache = new ResultCache(null, false, 60, 'test', 'null');
        $calls = 0;

        $cache->remember('key', function () use (&$calls): string {
            $calls++;

            return 'value';
        });
        $cache->remember('key', function () use (&$calls): string {
            $calls++;

            return 'value';
        });

        self::assertSame(2, $calls);
        self::assertNull($cache->get('key'));
        self::assertFalse($cache->isEnabled());
    }

    public function test_forget_and_info(): void
    {
        $cache = new ResultCache(new Repository(new ArrayStore), true, 120, 'test', 'array');
        $cache->put('key', ['value' => 1]);

        self::assertSame(['value' => 1], $cache->get('key'));
        self::assertSame(120, $cache->info()->ttl);
        self::assertSame('array', $cache->info()->store);

        $cache->forget('key');
        self::assertNull($cache->get('key'));
    }
}
