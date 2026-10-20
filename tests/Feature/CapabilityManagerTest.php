<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Feature;

use Illuminate\Config\Repository;
use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Capabilities\CapabilityChecker;
use Nbcom\Bitrix24\Capabilities\CapabilityManager;
use Nbcom\Bitrix24\Capabilities\CapabilityRegistry;
use Nbcom\Bitrix24\Capabilities\FileLock;
use Nbcom\Bitrix24\Capabilities\SnapshotStore\FileSnapshotStore;
use Nbcom\Bitrix24\Exceptions\Bitrix24CapabilityException;
use Nbcom\Bitrix24\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class CapabilityManagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/bitrix24-manager-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);
    }

    public function test_disabled_manager_trusts_all_methods(): void
    {
        $manager = $this->manager(new FakeClient, enabled: false);

        self::assertSame('disabled', $manager->snapshot()->strategy);
        $manager->ensure('anything');
        self::assertSame('im.user.get', $manager->methodFor('users.get'));
    }

    public function test_snapshot_is_reused_without_network_calls(): void
    {
        $client = $this->client();
        $manager = $this->manager($client);

        $first = $manager->snapshot();
        $calls = count($client->calls);

        $second = $manager->snapshot();

        self::assertSame($first->fingerprint, $second->fingerprint);
        self::assertCount($calls, $client->calls);
        self::assertGreaterThanOrEqual(3, $calls);

        $manager->snapshot(true);
        self::assertGreaterThan($calls, count($client->calls));
    }

    public function test_missing_method_throws_a_capability_exception(): void
    {
        $client = new FakeClient([
            'scope' => ['result' => ['user']],
            'methods' => ['result' => ['method.get', 'user.get']],
            'batch' => static fn (array $params): array => [
                'result' => [
                    'result' => array_fill_keys(array_keys($params['cmd']), [
                        'isExisting' => true,
                        'isAvailable' => false,
                    ]),
                    'result_error' => [],
                ],
            ],
        ]);
        $manager = $this->manager($client);

        $this->expectException(Bitrix24CapabilityException::class);

        $manager->ensure('department.get');
    }

    public function test_forget_removes_the_snapshot(): void
    {
        $manager = $this->manager($this->client());
        $manager->snapshot();
        $manager->forget();

        self::assertFileDoesNotExist($this->directory.'/capabilities.json');
    }

    private function manager(
        FakeClient $client,
        bool $enabled = true,
        bool $failFast = true,
    ): CapabilityManager {
        $config = new Repository([
            'bitrix24' => [
                'features' => [
                    'users' => true,
                    'departments' => true,
                    'notifications' => [
                        'system' => false,
                        'personal' => false,
                        'chat' => false,
                    ],
                ],
            ],
        ]);
        $endpoint = Endpoint::fromUrl('https://bitrix.example.ru/rest/1/token/');
        $registry = new CapabilityRegistry($config);

        return new CapabilityManager(
            checker: new CapabilityChecker($client, $registry, $endpoint, '1.0.0'),
            store: new FileSnapshotStore($this->directory),
            endpoint: $endpoint,
            lock: new FileLock($this->directory.'/capabilities.lock'),
            registry: $registry,
            enabled: $enabled,
            failFast: $failFast,
            preflight: true,
            allowStale: true,
            ttl: 3600,
            negativeTtl: 60,
            lockTimeout: 1.0,
        );
    }

    private function client(): FakeClient
    {
        return new FakeClient([
            'scope' => ['result' => ['user', 'department']],
            'methods' => ['result' => ['method.get', 'user.get', 'user.search', 'user.current', 'department.get']],
            'batch' => static function (array $params): array {
                $results = [];

                foreach ($params['cmd'] as $key => $command) {
                    $name = rawurldecode(substr((string) $command, strlen('method.get?name=')));
                    $results[$key] = [
                        'isExisting' => true,
                        'isAvailable' => in_array($name, ['user.get', 'user.search', 'user.current', 'department.get'], true),
                    ];
                }

                return ['result' => ['result' => $results, 'result_error' => []]];
            },
        ]);
    }
}
