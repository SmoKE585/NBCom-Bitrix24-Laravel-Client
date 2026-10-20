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
            'users.get' => 'user.get',
            'users.search' => 'user.search',
            'users.current' => 'user.current',
            'departments.get' => 'department.get',
            default => $feature,
        };
    }
}
