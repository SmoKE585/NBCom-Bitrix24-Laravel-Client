<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Services;

use Nbcom\Bitrix24\Client\RetryPolicy;
use Nbcom\Bitrix24\Contracts\CapabilityGuardInterface;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Dto\NotificationFailure;
use Nbcom\Bitrix24\Dto\NotificationReceipt;
use Nbcom\Bitrix24\Dto\NotificationReport;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Enums\NotificationChannel;
use Nbcom\Bitrix24\Exceptions\Bitrix24ConfigurationException;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;
use Nbcom\Bitrix24\Exceptions\Bitrix24ValidationException;
use Nbcom\Bitrix24\Support\UserFilter;

final class NotificationService
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CapabilityGuardInterface $guard,
        private readonly UserService $users,
        private readonly DepartmentService $departments,
        private readonly string $clientId = '',
    ) {}

    /**
     * Send a personal chat message with an optional keyboard, menu and attachment.
     *
     * @param  array<string, mixed>|null  $attach
     * @param  array<int, array<string, mixed>>|null  $keyboard
     * @param  array<string, mixed>|null  $menu
     */
    public function chat(
        int $userId,
        string $message,
        ?array $attach = null,
        ?array $keyboard = null,
        ?array $menu = null,
        bool $system = false,
        bool $urlPreview = true,
    ): NotificationReceipt {
        $this->assertUser($userId);
        $this->assertMessage($message);

        $method = $this->guard->methodFor('notifications.chat');
        $this->guard->ensure($method);

        $params = [
            'DIALOG_ID' => (string) $userId,
            'MESSAGE' => trim($message),
            'SYSTEM' => $system ? 'Y' : 'N',
            'URL_PREVIEW' => $urlPreview ? 'Y' : 'N',
        ];

        if ($attach !== null) {
            $params['ATTACH'] = $attach;
        }

        if ($keyboard !== null) {
            $params['KEYBOARD'] = $keyboard;
        }

        if ($menu !== null) {
            $params['MENU'] = $menu;
        }

        $body = $this->client->call(
            method: $method,
            params: $params,
            policy: RetryPolicy::forNotifications(),
        );
        $result = $body['result'] ?? null;

        return new NotificationReceipt(
            userId: $userId,
            channel: NotificationChannel::Chat,
            notificationId: is_numeric($result) && (int) $result > 0 ? (int) $result : null,
            delivered: $result !== false && $result !== null,
        );
    }

    public function toUser(
        int $userId,
        string $message,
        NotificationChannel $channel = NotificationChannel::System,
        ?string $messageOut = null,
        ?string $tag = null,
        ?string $subTag = null,
        ?array $attach = null,
        ?RetryPolicy $retryPolicy = null,
    ): NotificationReceipt {
        $this->assertUser($userId);
        $this->assertMessage($message);

        $feature = match ($channel) {
            NotificationChannel::System => 'notifications.system',
            NotificationChannel::Personal => 'notifications.personal',
            NotificationChannel::Chat => 'notifications.chat',
        };

        $method = $this->guard->methodFor($feature);
        $this->guard->ensure($method);

        if ($channel === NotificationChannel::Chat) {
            $params = [
                'DIALOG_ID' => (string) $userId,
                'MESSAGE' => trim($message),
                'SYSTEM' => 'N',
            ];

            if ($attach !== null) {
                $params['ATTACH'] = $attach;
            }
        } else {
            $params = [
                'USER_ID' => $userId,
                'MESSAGE' => trim($message),
            ];

            if ($messageOut !== null && trim($messageOut) !== '') {
                $params['MESSAGE_OUT'] = trim($messageOut);
            }

            if ($tag !== null && trim($tag) !== '') {
                $this->assertClientId();
                $params['TAG'] = trim($tag);
                $params['CLIENT_ID'] = $this->clientId;
            }

            if ($subTag !== null && trim($subTag) !== '') {
                $this->assertClientId();
                $params['SUB_TAG'] = trim($subTag);
                $params['CLIENT_ID'] = $this->clientId;
            }

            if ($attach !== null) {
                $params['ATTACH'] = $attach;
            }
        }

        $body = $this->client->call(
            method: $method,
            params: $params,
            policy: $retryPolicy ?? RetryPolicy::forNotifications(),
        );
        $result = $body['result'] ?? null;
        $delivered = $result !== false && $result !== null;
        $notificationId = is_numeric($result) && (int) $result > 0 ? (int) $result : null;

        return new NotificationReceipt(
            userId: $userId,
            channel: $channel,
            notificationId: $notificationId,
            delivered: $delivered,
        );
    }

    /**
     * @param  iterable<int>  $userIds
     */
    public function toUsers(
        iterable $userIds,
        string $message,
        NotificationChannel $channel = NotificationChannel::System,
        ?string $messageOut = null,
        ?string $tag = null,
        ?string $subTag = null,
        ?array $attach = null,
        bool $throwOnFailure = false,
    ): NotificationReport {
        $ids = [];

        foreach ($userIds as $userId) {
            if (is_numeric($userId) && (int) $userId > 0) {
                $ids[(int) $userId] = true;
            }
        }

        $sent = [];
        $failed = [];

        foreach (array_keys($ids) as $userId) {
            try {
                $sent[] = $this->toUser(
                    userId: $userId,
                    message: $message,
                    channel: $channel,
                    messageOut: $messageOut,
                    tag: $tag,
                    subTag: $subTag,
                    attach: $attach,
                );
            } catch (Bitrix24Exception $exception) {
                if ($throwOnFailure) {
                    throw $exception;
                }

                $failed[] = new NotificationFailure(
                    userId: $userId,
                    reason: $exception->reason(),
                    errorCode: $exception->errorCode(),
                    message: $exception->getMessage(),
                );
            }
        }

        return new NotificationReport(
            sent: $sent,
            failed: $failed,
            total: count($ids),
        );
    }

    public function toDepartment(
        int $departmentId,
        string $message,
        bool $includeNested = false,
        NotificationChannel $channel = NotificationChannel::System,
        ?string $messageOut = null,
        ?string $tag = null,
        ?string $subTag = null,
        ?array $attach = null,
        ?UserFilter $filter = null,
        bool $throwOnFailure = false,
    ): NotificationReport {
        $members = $this->departments->members(
            departmentId: $departmentId,
            includeNested: $includeNested,
            filter: $filter,
        );

        if ($members->isEmpty()) {
            return NotificationReport::empty();
        }

        return $this->toUsers(
            userIds: $members->pluck('id')->all(),
            message: $message,
            channel: $channel,
            messageOut: $messageOut,
            tag: $tag,
            subTag: $subTag,
            attach: $attach,
            throwOnFailure: $throwOnFailure,
        );
    }

    public function toDepartmentHead(
        int $departmentId,
        string $message,
        NotificationChannel $channel = NotificationChannel::System,
        ?string $messageOut = null,
        ?string $tag = null,
        ?string $subTag = null,
        ?array $attach = null,
    ): ?NotificationReceipt {
        $head = $this->departments->headOf($departmentId);

        if ($head === null) {
            return null;
        }

        return $this->toUser(
            userId: $head->id,
            message: $message,
            channel: $channel,
            messageOut: $messageOut,
            tag: $tag,
            subTag: $subTag,
            attach: $attach,
        );
    }

    private function assertUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new Bitrix24ValidationException(
                message: 'Bitrix24 user id must be a positive integer.',
                reason: Bitrix24ErrorReason::Validation,
                context: ['user_id' => $userId],
            );
        }
    }

    private function assertMessage(string $message): void
    {
        if (trim($message) === '') {
            throw new Bitrix24ValidationException(
                message: 'Bitrix24 notification message must not be empty.',
                reason: Bitrix24ErrorReason::Validation,
            );
        }
    }

    private function assertClientId(): void
    {
        if (trim($this->clientId) === '') {
            throw new Bitrix24ConfigurationException(
                message: 'BITRIX24_CLIENT_ID is required when TAG or SUB_TAG is used with a webhook.',
                reason: Bitrix24ErrorReason::Configuration,
            );
        }
    }
}
