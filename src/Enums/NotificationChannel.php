<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Enums;

enum NotificationChannel: string
{
    case System = 'system';
    case Personal = 'personal';
    case Chat = 'chat';
}
