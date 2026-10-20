<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Capabilities\CapabilityChecker;
use Nbcom\Bitrix24\Capabilities\CapabilityManager;
use Nbcom\Bitrix24\Capabilities\CapabilityRegistry;
use Nbcom\Bitrix24\Capabilities\FileLock;
use Nbcom\Bitrix24\Capabilities\SnapshotStore\CacheSnapshotStore;
use Nbcom\Bitrix24\Capabilities\SnapshotStore\FileSnapshotStore;
use Nbcom\Bitrix24\Client\ErrorMapper;
use Nbcom\Bitrix24\Client\RetryPolicy;
use Nbcom\Bitrix24\Client\Throttle;
use Nbcom\Bitrix24\Client\Transport;
use Nbcom\Bitrix24\Console\AgentDocsCommand;
use Nbcom\Bitrix24\Console\CheckCommand;
use Nbcom\Bitrix24\Console\ForgetCommand;
use Nbcom\Bitrix24\Console\InstallCommand;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Contracts\SnapshotStoreInterface;
use Nbcom\Bitrix24\Services\DepartmentLookup;
use Nbcom\Bitrix24\Services\DepartmentService;
use Nbcom\Bitrix24\Services\NotificationService;
use Nbcom\Bitrix24\Services\UserService;
use Nbcom\Bitrix24\Support\ResultCache;
use Nbcom\Bitrix24\Support\Version;
use Psr\Log\LoggerInterface;

final class Bitrix24ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bitrix24.php', 'bitrix24');

        $this->app->singleton(Endpoint::class, static fn ($app): Endpoint => Endpoint::fromConfig($app['config']));
        $this->app->singleton(ErrorMapper::class);
        $this->app->singleton(RetryPolicy::class, static fn ($app): RetryPolicy => RetryPolicy::fromConfig($app['config']));
        $this->app->singleton(CapabilityRegistry::class, static fn ($app): CapabilityRegistry => new CapabilityRegistry($app['config']));
        $this->app->singleton(FileLock::class, static fn ($app): FileLock => new FileLock(
            $app['config']->get('bitrix24.capabilities.path').DIRECTORY_SEPARATOR.'capabilities.lock',
        ));

        $this->app->scoped(Throttle::class, static fn ($app): Throttle => new Throttle(
            (float) $app['config']->get('bitrix24.http.throttle_rps', 5),
        ));

        $this->app->scoped(ClientInterface::class, static function ($app): ClientInterface {
            $config = $app['config'];
            $logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;
            $factory = $app->bound(Factory::class) ? $app->make(Factory::class) : new Factory;

            return new Transport(
                http: $factory,
                endpoint: $app->make(Endpoint::class),
                errors: $app->make(ErrorMapper::class),
                defaultPolicy: $app->make(RetryPolicy::class),
                throttle: $app->make(Throttle::class),
                timeout: (int) $config->get('bitrix24.http.timeout', 15),
                connectTimeout: (int) $config->get('bitrix24.http.connect_timeout', 5),
                logger: $logger,
                debug: (bool) $config->get('bitrix24.logging.debug', false),
            );
        });

        $this->app->singleton(SnapshotStoreInterface::class, static function ($app): SnapshotStoreInterface {
            if ($app['config']->get('bitrix24.capabilities.store', 'file') === 'cache') {
                return new CacheSnapshotStore($app->make('cache.store'));
            }

            return new FileSnapshotStore($app['config']->get('bitrix24.capabilities.path'));
        });

        $this->app->scoped(CapabilityChecker::class, static fn ($app): CapabilityChecker => new CapabilityChecker(
            client: $app->make(ClientInterface::class),
            registry: $app->make(CapabilityRegistry::class),
            endpoint: $app->make(Endpoint::class),
            packageVersion: Version::VERSION,
        ));

        $this->app->scoped(CapabilityManager::class, static function ($app): CapabilityManager {
            $config = $app['config'];

            return new CapabilityManager(
                checker: $app->make(CapabilityChecker::class),
                store: $app->make(SnapshotStoreInterface::class),
                endpoint: $app->make(Endpoint::class),
                lock: $app->make(FileLock::class),
                registry: $app->make(CapabilityRegistry::class),
                enabled: (bool) $config->get('bitrix24.capabilities.enabled', true),
                failFast: (bool) $config->get('bitrix24.capabilities.fail_fast', true),
                preflight: (bool) $config->get('bitrix24.capabilities.preflight', true),
                allowStale: (bool) $config->get('bitrix24.capabilities.allow_stale', true),
                ttl: (int) $config->get('bitrix24.capabilities.ttl', 604800),
                negativeTtl: (int) $config->get('bitrix24.capabilities.negative_ttl', 300),
                lockTimeout: (float) $config->get('bitrix24.capabilities.lock_timeout', 10),
                logger: $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            );
        });

        $this->app->singleton(ResultCache::class, static function ($app): ResultCache {
            $config = $app['config'];
            $store = (string) $config->get('cache.default', '');
            $cache = $app->bound('cache.store') ? $app->make('cache.store') : null;
            $setting = $config->get('bitrix24.cache.enabled', 'auto');
            $enabled = is_bool($setting)
                ? $setting
                : filter_var($setting, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($enabled === null) {
                $enabled = $cache !== null && ! in_array($store, ['', 'array', 'null'], true);
            }

            $fingerprint = $app->make(Endpoint::class)->fingerprint();

            return new ResultCache(
                cache: $cache,
                enabled: $enabled,
                ttl: (int) $config->get('bitrix24.cache.ttl', 300),
                prefix: 'bitrix24:'.substr($fingerprint, 0, 12),
                store: $store,
            );
        });

        $this->app->scoped(DepartmentLookup::class, static fn ($app): DepartmentLookup => new DepartmentLookup(
            client: $app->make(ClientInterface::class),
            guard: $app->make(CapabilityManager::class),
            cache: $app->make(ResultCache::class),
        ));

        $this->app->scoped(UserService::class, static fn ($app): UserService => new UserService(
            client: $app->make(ClientInterface::class),
            guard: $app->make(CapabilityManager::class),
            cache: $app->make(ResultCache::class),
            departments: $app->make(DepartmentLookup::class),
        ));

        $this->app->scoped(DepartmentService::class, static fn ($app): DepartmentService => new DepartmentService(
            lookup: $app->make(DepartmentLookup::class),
            users: $app->make(UserService::class),
        ));

        $this->app->scoped(NotificationService::class, static fn ($app): NotificationService => new NotificationService(
            client: $app->make(ClientInterface::class),
            guard: $app->make(CapabilityManager::class),
            users: $app->make(UserService::class),
            departments: $app->make(DepartmentService::class),
            clientId: (string) $app['config']->get('bitrix24.client_id', ''),
        ));

        $this->app->scoped(Bitrix24Manager::class, static fn ($app): Bitrix24Manager => new Bitrix24Manager(
            users: $app->make(UserService::class),
            departments: $app->make(DepartmentService::class),
            notifications: $app->make(NotificationService::class),
            capabilities: $app->make(CapabilityManager::class),
            cache: $app->make(ResultCache::class),
            client: $app->make(ClientInterface::class),
        ));

        $this->app->alias(Bitrix24Manager::class, 'bitrix24');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckCommand::class,
                ForgetCommand::class,
                InstallCommand::class,
                AgentDocsCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/bitrix24.php' => config_path('bitrix24.php'),
        ], 'bitrix24-config');

        $this->publishes([
            __DIR__.'/../resources/agent/BITRIX24.md' => base_path('.ai/bitrix24.md'),
        ], 'bitrix24-agent');
    }
}
