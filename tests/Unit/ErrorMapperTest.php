<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Unit;

use Nbcom\Bitrix24\Client\ErrorMapper;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24AccessDeniedException;
use Nbcom\Bitrix24\Exceptions\Bitrix24AuthenticationException;
use Nbcom\Bitrix24\Exceptions\Bitrix24ConfigurationException;
use Nbcom\Bitrix24\Exceptions\Bitrix24MethodNotFoundException;
use Nbcom\Bitrix24\Exceptions\Bitrix24RateLimitException;
use Nbcom\Bitrix24\Exceptions\Bitrix24ServerException;
use Nbcom\Bitrix24\Exceptions\Bitrix24TransportException;
use Nbcom\Bitrix24\Exceptions\Bitrix24ValidationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorMapperTest extends TestCase
{
    private ErrorMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ErrorMapper;
    }

    public function test_maps_authentication_errors(): void
    {
        $exception = $this->mapper->fromResponse('user.get', 401, [], [
            'error' => 'INVALID_CREDENTIALS',
            'error_description' => 'Invalid request credentials',
        ]);

        self::assertInstanceOf(Bitrix24AuthenticationException::class, $exception);
        self::assertSame(Bitrix24ErrorReason::Authentication, $exception->reason());
        self::assertSame('INVALID_CREDENTIALS', $exception->errorCode());
        self::assertFalse($exception->isRetryable());
    }

    public function test_maps_insufficient_scope_to_forbidden(): void
    {
        $exception = $this->mapper->fromResponse('department.get', 401, [], [
            'error' => 'insufficient_scope',
            'error_description' => 'The request requires higher privileges',
        ]);

        self::assertInstanceOf(Bitrix24AccessDeniedException::class, $exception);
        self::assertSame(Bitrix24ErrorReason::Forbidden, $exception->reason());
        self::assertFalse($exception->isRetryable());
    }

    public function test_maps_missing_method(): void
    {
        $exception = $this->mapper->fromResponse('user.unknown', 404, [], [
            'error' => 'ERROR_METHOD_NOT_FOUND',
            'error_description' => 'Method not found!',
        ]);

        self::assertInstanceOf(Bitrix24MethodNotFoundException::class, $exception);
        self::assertSame(Bitrix24ErrorReason::MethodNotFound, $exception->reason());
        self::assertStringContainsString('scope', $exception->getMessage());
    }

    public function test_maps_rate_limit_and_retry_after_header(): void
    {
        $exception = $this->mapper->fromResponse('user.get', 503, ['Retry-After' => ['3']], [
            'error' => 'QUERY_LIMIT_EXCEEDED',
            'error_description' => 'Too many requests',
        ]);

        self::assertInstanceOf(Bitrix24RateLimitException::class, $exception);
        self::assertSame(Bitrix24ErrorReason::RateLimit, $exception->reason());
        self::assertTrue($exception->isRetryable());
        self::assertSame(3, $exception->retryAfter());
    }

    public function test_overload_limit_is_not_retryable(): void
    {
        $exception = $this->mapper->fromResponse('user.get', 401, [], [
            'error' => 'OVERLOAD_LIMIT',
            'error_description' => 'REST API is blocked',
        ]);

        self::assertInstanceOf(Bitrix24RateLimitException::class, $exception);
        self::assertFalse($exception->isRetryable());
    }

    public function test_maps_server_errors(): void
    {
        $exception = $this->mapper->fromResponse('user.get', 500, [], [
            'error' => 'INTERNAL_SERVER_ERROR',
            'error_description' => 'Internal server error',
        ]);

        self::assertInstanceOf(Bitrix24ServerException::class, $exception);
        self::assertSame(Bitrix24ErrorReason::Server, $exception->reason());
        self::assertTrue($exception->isRetryable());
    }

    public function test_maps_method_level_validation_errors(): void
    {
        $exception = $this->mapper->fromResponse('im.message.add', 400, [], [
            'error' => '',
            'error_description' => 'Message can not be empty',
        ]);

        self::assertInstanceOf(Bitrix24ValidationException::class, $exception);
        self::assertSame(Bitrix24ErrorReason::Validation, $exception->reason());
    }

    public function test_maps_configuration_errors(): void
    {
        $exception = $this->mapper->fromResponse('user.get', 401, [], [
            'error' => 'INVALID_REQUEST',
            'error_description' => 'Https required',
        ]);

        self::assertInstanceOf(Bitrix24ConfigurationException::class, $exception);
        self::assertSame(Bitrix24ErrorReason::Configuration, $exception->reason());
    }

    public function test_maps_transport_exceptions(): void
    {
        $exception = $this->mapper->fromThrowable('user.get', new RuntimeException('connection refused'));

        self::assertInstanceOf(Bitrix24TransportException::class, $exception);
        self::assertSame(Bitrix24ErrorReason::Transport, $exception->reason());
        self::assertTrue($exception->isRetryable());
        self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
    }

    public function test_maps_malformed_responses(): void
    {
        $exception = $this->mapper->malformed('user.get', '<html>login</html>');

        self::assertSame(Bitrix24ErrorReason::Transport, $exception->reason());
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('non-JSON', $exception->getMessage());
    }
}
