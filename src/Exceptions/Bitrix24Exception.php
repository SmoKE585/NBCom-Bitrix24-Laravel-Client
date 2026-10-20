<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Exceptions;

use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use RuntimeException;
use Throwable;

class Bitrix24Exception extends RuntimeException implements Bitrix24ExceptionInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        private readonly Bitrix24ErrorReason $reason = Bitrix24ErrorReason::Unknown,
        private readonly string $errorCode = '',
        private readonly string $errorDescription = '',
        private readonly ?int $httpStatus = null,
        private readonly ?string $method = null,
        private readonly ?int $retryAfter = null,
        private readonly array $context = [],
        ?Throwable $previous = null,
        private readonly ?bool $retryable = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function reason(): Bitrix24ErrorReason
    {
        return $this->reason;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function errorDescription(): string
    {
        return $this->errorDescription;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function method(): ?string
    {
        return $this->method;
    }

    public function isRetryable(): bool
    {
        if ($this->retryable !== null) {
            return $this->retryable;
        }

        return match ($this->reason) {
            Bitrix24ErrorReason::RateLimit => $this->errorCode !== 'OVERLOAD_LIMIT',
            Bitrix24ErrorReason::Server,
            Bitrix24ErrorReason::Transport => true,
            default => false,
        };
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function context(): array
    {
        return array_merge($this->context, [
            'reason' => $this->reason->value,
            'error_code' => $this->errorCode,
            'http_status' => $this->httpStatus,
            'method' => $this->method,
            'retry_after' => $this->retryAfter,
        ]);
    }
}
