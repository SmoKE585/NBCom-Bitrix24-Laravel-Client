<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Enums;

enum Bitrix24ErrorReason: string
{
    case Authentication = 'authentication';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case Validation = 'validation';
    case RateLimit = 'rate_limit';
    case Server = 'server';
    case Transport = 'transport';
    case Configuration = 'configuration';
    case Capability = 'capability';
    case MethodNotFound = 'method_not_found';
    case Unknown = 'unknown';
}
