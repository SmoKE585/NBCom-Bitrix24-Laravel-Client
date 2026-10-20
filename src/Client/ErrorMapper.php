<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Client;

use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24AccessDeniedException;
use Nbcom\Bitrix24\Exceptions\Bitrix24AuthenticationException;
use Nbcom\Bitrix24\Exceptions\Bitrix24ConfigurationException;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;
use Nbcom\Bitrix24\Exceptions\Bitrix24MethodNotFoundException;
use Nbcom\Bitrix24\Exceptions\Bitrix24RateLimitException;
use Nbcom\Bitrix24\Exceptions\Bitrix24ServerException;
use Nbcom\Bitrix24\Exceptions\Bitrix24TransportException;
use Nbcom\Bitrix24\Exceptions\Bitrix24ValidationException;
use Throwable;

final class ErrorMapper
{
    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $data
     */
    public function fromResponse(string $method, int $status, array $headers, array $data): Bitrix24Exception
    {
        $errorCode = is_string($data['error'] ?? null) ? trim($data['error']) : '';
        $description = is_string($data['error_description'] ?? null) ? trim($data['error_description']) : '';
        $retryAfter = $this->retryAfter($headers, $data);
        $message = $this->message($method, $status, $errorCode, $description);
        $context = [
            'error_code' => $errorCode,
            'error_description' => $description,
            'http_status' => $status,
        ];

        if (in_array($errorCode, ['INVALID_CREDENTIALS', 'NO_AUTH_FOUND', 'expired_token'], true)) {
            return new Bitrix24AuthenticationException(
                message: $message,
                reason: Bitrix24ErrorReason::Authentication,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: false,
            );
        }

        if (in_array($errorCode, ['insufficient_scope', 'user_access_error', 'ACCESS_DENIED'], true)) {
            return new Bitrix24AccessDeniedException(
                message: $message,
                reason: Bitrix24ErrorReason::Forbidden,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: false,
            );
        }

        if ($errorCode === 'ERROR_METHOD_NOT_FOUND') {
            return new Bitrix24MethodNotFoundException(
                message: $message.' Check the method name and the webhook scopes: a missing scope can hide a method.',
                reason: Bitrix24ErrorReason::MethodNotFound,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: false,
            );
        }

        if (in_array($errorCode, ['QUERY_LIMIT_EXCEEDED', 'OPERATION_TIME_LIMIT', 'OVERLOAD_LIMIT'], true)) {
            return new Bitrix24RateLimitException(
                message: $message,
                reason: Bitrix24ErrorReason::RateLimit,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: $errorCode !== 'OVERLOAD_LIMIT',
            );
        }

        if (in_array($errorCode, ['INTERNAL_SERVER_ERROR', 'ERROR_UNEXPECTED_ANSWER'], true)) {
            return new Bitrix24ServerException(
                message: $message,
                reason: Bitrix24ErrorReason::Server,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: true,
            );
        }

        if (in_array($errorCode, ['INVALID_REQUEST', 'PORTAL_DELETED', 'WRONG_AUTH_TYPE'], true)) {
            return new Bitrix24ConfigurationException(
                message: $message,
                reason: Bitrix24ErrorReason::Configuration,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: false,
            );
        }

        return match (true) {
            $status === 400 => new Bitrix24ValidationException(
                message: $message,
                reason: Bitrix24ErrorReason::Validation,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: false,
            ),
            $status === 401 => new Bitrix24AuthenticationException(
                message: $message,
                reason: Bitrix24ErrorReason::Authentication,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: false,
            ),
            $status === 403 => new Bitrix24AccessDeniedException(
                message: $message,
                reason: Bitrix24ErrorReason::Forbidden,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: false,
            ),
            $status === 404 => new Bitrix24MethodNotFoundException(
                message: $message,
                reason: Bitrix24ErrorReason::MethodNotFound,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: false,
            ),
            $status === 429 => new Bitrix24RateLimitException(
                message: $message,
                reason: Bitrix24ErrorReason::RateLimit,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: true,
            ),
            $status >= 500 => new Bitrix24ServerException(
                message: $message,
                reason: Bitrix24ErrorReason::Server,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: true,
            ),
            default => new Bitrix24Exception(
                message: $message,
                reason: Bitrix24ErrorReason::Unknown,
                errorCode: $errorCode,
                errorDescription: $description,
                httpStatus: $status,
                method: $method,
                retryAfter: $retryAfter,
                context: $context,
                retryable: false,
            ),
        };
    }

    public function fromThrowable(string $method, Throwable $exception): Bitrix24Exception
    {
        return new Bitrix24TransportException(
            message: "Bitrix24 [{$method}] transport error: {$exception->getMessage()}",
            reason: Bitrix24ErrorReason::Transport,
            method: $method,
            previous: $exception,
            retryable: true,
        );
    }

    public function malformed(string $method, string $body): Bitrix24Exception
    {
        $preview = mb_substr(trim($body), 0, 200);

        return new Bitrix24TransportException(
            message: "Bitrix24 [{$method}] returned a non-JSON response: {$preview}",
            reason: Bitrix24ErrorReason::Transport,
            method: $method,
            retryable: false,
        );
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $data
     */
    private function retryAfter(array $headers, array $data): ?int
    {
        $header = null;

        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'retry-after') {
                $header = is_array($value) ? reset($value) : $value;

                break;
            }
        }

        if (is_numeric($header)) {
            return max(0, (int) $header);
        }

        if (is_string($header) && $header !== '') {
            $timestamp = strtotime($header);

            if ($timestamp !== false) {
                return max(0, $timestamp - time());
            }
        }

        $resetAt = $data['time']['operating_reset_at'] ?? null;

        if (is_numeric($resetAt)) {
            return max(0, (int) $resetAt - time());
        }

        return null;
    }

    private function message(string $method, int $status, string $errorCode, string $description): string
    {
        $code = $errorCode !== '' ? $errorCode : 'HTTP '.$status;

        return trim("Bitrix24 [{$method}] failed with {$code}: {$description}");
    }
}
