<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Support;

use Nbcom\Bitrix24\Contracts\CapabilityGuardInterface;

final class AllowAllCapabilities implements CapabilityGuardInterface
{
    public function ensure(string $method): void {}

    public function methodFor(string $feature): string
    {
        return match ($feature) {
            'notifications.system' => 'im.notify.system.add',
            'notifications.personal' => 'im.notify.personal.add',
            'notifications.chat' => 'im.message.add',
            'users.get' => 'im.user.get',
            'users.list' => 'im.user.list.get',
            'users.search' => 'im.search.user.list',
            'users.current' => 'im.user.get',
            'departments.get' => 'im.department.get',
            'departments.search' => 'im.search.department.list',
            'departments.employees' => 'im.department.employees.get',
            'live_feed.post' => 'log.blogpost.add',
            default => $feature,
        };
    }
}
