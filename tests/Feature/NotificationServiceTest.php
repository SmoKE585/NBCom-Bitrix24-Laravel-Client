<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Feature;

use Nbcom\Bitrix24\Contracts\CapabilityGuardInterface;
use Nbcom\Bitrix24\Dto\NotificationReceipt;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Enums\NotificationChannel;
use Nbcom\Bitrix24\Exceptions\Bitrix24AccessDeniedException;
use Nbcom\Bitrix24\Exceptions\Bitrix24ConfigurationException;
use Nbcom\Bitrix24\Services\DepartmentLookup;
use Nbcom\Bitrix24\Services\DepartmentService;
use Nbcom\Bitrix24\Services\NotificationService;
use Nbcom\Bitrix24\Services\UserService;
use Nbcom\Bitrix24\Tests\Support\AllowAllCapabilities;
use Nbcom\Bitrix24\Tests\Support\FakeClient;
use Nbcom\Bitrix24\Tests\Support\TestCache;
use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function test_sends_a_system_notification(): void
    {
        $client = new FakeClient([
            'im.notify.system.add' => ['result' => 123],
        ]);

        $receipt = $this->service($client)->toUser(5, 'Hello');

        self::assertInstanceOf(NotificationReceipt::class, $receipt);
        self::assertSame(123, $receipt->notificationId);
        self::assertTrue($receipt->delivered);
        self::assertSame(5, $client->callsFor('im.notify.system.add')[0]['params']['USER_ID']);
        self::assertSame('Hello', $client->callsFor('im.notify.system.add')[0]['params']['MESSAGE']);
    }

    public function test_uses_the_fallback_method_from_capabilities(): void
    {
        $guard = new class implements CapabilityGuardInterface
        {
            public function ensure(string $method): void {}

            public function methodFor(string $feature): string
            {
                return 'im.notify';
            }
        };
        $client = new FakeClient(['im.notify' => ['result' => true]]);

        $receipt = $this->service($client, $guard)->toUser(5, 'Hello');

        self::assertTrue($receipt->delivered);
        self::assertNull($receipt->notificationId);
        self::assertCount(1, $client->callsFor('im.notify'));
    }

    public function test_sends_a_chat_message_to_a_personal_dialog(): void
    {
        $client = new FakeClient(['im.message.add' => ['result' => 77]]);

        $this->service($client)->toUser(5, 'Hello', NotificationChannel::Chat);

        $params = $client->callsFor('im.message.add')[0]['params'];
        self::assertSame('5', $params['DIALOG_ID']);
        self::assertSame('N', $params['SYSTEM']);
    }

    public function test_tag_requires_a_client_id(): void
    {
        $this->expectException(Bitrix24ConfigurationException::class);

        $this->service(new FakeClient([
            'im.notify.system.add' => ['result' => 1],
        ]))->toUser(5, 'Hello', tag: 'order-42');
    }

    public function test_bulk_send_reports_partial_failures(): void
    {
        $client = new FakeClient([
            'im.notify.system.add' => static function (array $params): array {
                if ($params['USER_ID'] === 2) {
                    throw new Bitrix24AccessDeniedException(
                        message: 'No access for user 2',
                        reason: Bitrix24ErrorReason::Forbidden,
                    );
                }

                return ['result' => 1];
            },
        ]);

        $report = $this->service($client)->toUsers([1, 2, 3], 'Hello');

        self::assertSame(3, $report->total);
        self::assertSame(2, $report->successCount());
        self::assertSame(1, $report->failureCount());
        self::assertTrue($report->hasFailures());
        self::assertSame(Bitrix24ErrorReason::Forbidden, $report->failed[0]->reason);
    }

    private function service(
        FakeClient $client,
        ?CapabilityGuardInterface $guard = null,
        string $clientId = '',
    ): NotificationService {
        $cache = TestCache::disabled();
        $guard ??= new AllowAllCapabilities;
        $lookup = new DepartmentLookup($client, $guard, $cache);
        $users = new UserService($client, $guard, $cache, $lookup);
        $departments = new DepartmentService($lookup, $users);

        return new NotificationService($client, $guard, $users, $departments, $clientId);
    }
}
