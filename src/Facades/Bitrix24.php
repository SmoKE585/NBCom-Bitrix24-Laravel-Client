<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Facades;

use Illuminate\Support\Facades\Facade;
use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Bitrix24Manager;
use Nbcom\Bitrix24\Capabilities\CapabilityChecker;
use Nbcom\Bitrix24\Capabilities\CapabilityManager;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Services\DepartmentLookup;
use Nbcom\Bitrix24\Services\DepartmentService;
use Nbcom\Bitrix24\Services\LiveFeedService;
use Nbcom\Bitrix24\Services\NotificationService;
use Nbcom\Bitrix24\Services\UserService;
use Nbcom\Bitrix24\Support\ResultCache;
use Nbcom\Bitrix24\Testing\Bitrix24Fake;
use RuntimeException;

/**
 * @method static \Nbcom\Bitrix24\Services\UserService users()
 * @method static \Nbcom\Bitrix24\Services\DepartmentService departments()
 * @method static \Nbcom\Bitrix24\Services\NotificationService notifications()
 * @method static \Nbcom\Bitrix24\Services\LiveFeedService liveFeed()
 * @method static \Nbcom\Bitrix24\Dto\UserData me(bool $fresh = false)
 * @method static \Nbcom\Bitrix24\Dto\HealthReport health(bool $refresh = false)
 * @method static \Nbcom\Bitrix24\Dto\CacheInfo cacheInfo()
 * @method static mixed call(string $method, array $params = [])
 * @method static \Nbcom\Bitrix24\Capabilities\CapabilityManager capabilities()
 * @method static void forgetCapabilities()
 *
 * @see \Nbcom\Bitrix24\Bitrix24Manager
 */
final class Bitrix24 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bitrix24';
    }

    /**
     * Replace the REST transport with an in-memory fake for tests.
     *
     * @param  array<string, mixed>  $responses
     */
    public static function fake(array $responses = []): Bitrix24Fake
    {
        $app = self::getFacadeApplication();

        if ($app === null) {
            throw new RuntimeException('The Laravel application is not available.');
        }

        if (method_exists($app, 'forgetScopedInstances')) {
            $app->forgetScopedInstances();
        }

        $fake = new Bitrix24Fake($responses);
        $app->instance(ClientInterface::class, $fake);

        if (! is_string($app['config']->get('bitrix24.endpoint')) || trim((string) $app['config']->get('bitrix24.endpoint')) === '') {
            $app['config']->set('bitrix24.endpoint', 'https://fake.bitrix24.local/rest/1/fake-token/');
        }

        $app['config']->set('bitrix24.capabilities.enabled', false);
        $app['config']->set('bitrix24.cache.enabled', false);

        foreach ([
            'bitrix24',
            Endpoint::class,
            Bitrix24Manager::class,
            UserService::class,
            DepartmentService::class,
            NotificationService::class,
            LiveFeedService::class,
            DepartmentLookup::class,
            CapabilityManager::class,
            CapabilityChecker::class,
            ResultCache::class,
        ] as $abstract) {
            $app->forgetInstance($abstract);
        }

        return $fake;
    }
}
