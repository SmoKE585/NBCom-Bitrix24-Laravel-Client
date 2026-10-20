<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Exceptions;

use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Throwable;

interface Bitrix24ExceptionInterface extends Throwable
{
    public function reason(): Bitrix24ErrorReason;

    public function errorCode(): string;

    public function errorDescription(): string;

    public function httpStatus(): ?int;

    public function method(): ?string;

    public function isRetryable(): bool;

    public function retryAfter(): ?int;

    /**
     * @return array<string, mixed>
     */
    public function context(): array;
}
