<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Unit;

use Illuminate\Config\Repository;
use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Capabilities\CapabilityChecker;
use Nbcom\Bitrix24\Capabilities\CapabilityRegistry;
use Nbcom\Bitrix24\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class CapabilityCheckerTest extends TestCase
{
    public function test_checks_methods_with_method_get_and_uses_fallbacks(): void
    {
        $client = new FakeClient([
            'scope' => ['result' => ['im']],
            'methods' => ['result' => [
                'method.get',
                'im.user.get',
                'im.user.list.get',
                'im.search.user.list',
                'im.department.get',
                'im.search.department.list',
                'im.department.employees.get',
                'im.department.managers.get',
                'im.notify.system.add',
                'im.notify.personal.add',
                'im.message.add',
            ]],
            'batch' => $this->batchResponse([
                'im.user.get',
                'im.user.list.get',
                'im.search.user.list',
                'im.department.get',
                'im.search.department.list',
                'im.department.employees.get',
                'im.department.managers.get',
                'im.notify',
                'im.notify.personal.add',
                'im.message.add',
            ]),
        ]);

        $snapshot = $this->checker($client)->check();

        self::assertSame('method.get', $snapshot->strategy);
        self::assertSame('im.notify', $snapshot->chosen['notifications.system']);
        self::assertSame('im.notify.personal.add', $snapshot->chosen['notifications.personal']);
        self::assertSame('im.message.add', $snapshot->chosen['notifications.chat']);
        self::assertFalse($snapshot->hasMissing());
    }

    public function test_reports_missing_scopes_and_methods(): void
    {
        $client = new FakeClient([
            'scope' => ['result' => []],
            'methods' => ['result' => ['method.get']],
            'batch' => $this->batchResponse([]),
        ]);

        $snapshot = $this->checker($client)->check();

        self::assertContains('im', $snapshot->missingScopes);
        self::assertContains('im.user.get', $snapshot->missingMethods);
        self::assertTrue($snapshot->hasMissing());
    }

    public function test_falls_back_to_methods_list_on_old_portals(): void
    {
        $client = new FakeClient([
            'scope' => ['result' => ['im']],
            'methods' => ['result' => ['im.user.get', 'im.user.list.get', 'im.search.user.list']],
            'method.get' => ['error' => 'ERROR_METHOD_NOT_FOUND', 'error_description' => 'Method not found!'],
        ]);

        $snapshot = $this->checker($client)->check();

        self::assertSame('methods', $snapshot->strategy);
        self::assertSame('im.user.get', $snapshot->chosen['users.get']);
        self::assertContains('im.department.get', $snapshot->missingMethods);
    }

    private function checker(FakeClient $client): CapabilityChecker
    {
        $config = new Repository([
            'bitrix24' => [
                'features' => [
                    'users' => true,
                    'departments' => true,
                    'notifications' => [
                        'system' => true,
                        'personal' => true,
                        'chat' => true,
                    ],
                ],
            ],
        ]);

        return new CapabilityChecker(
            client: $client,
            registry: new CapabilityRegistry($config),
            endpoint: Endpoint::fromUrl('https://bitrix.example.ru/rest/1/token/'),
            packageVersion: '1.0.0',
        );
    }

    /**
     * @param  list<string>  $available
     */
    private function batchResponse(array $available): \Closure
    {
        return static function (array $params) use ($available): array {
            $results = [];

            foreach ($params['cmd'] as $key => $command) {
                $name = rawurldecode(substr((string) $command, strlen('method.get?name=')));
                $results[$key] = [
                    'isExisting' => true,
                    'isAvailable' => in_array($name, $available, true),
                ];
            }

            return ['result' => ['result' => $results, 'result_error' => []]];
        };
    }
}
