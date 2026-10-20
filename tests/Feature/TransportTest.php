<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Feature;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Client\ErrorMapper;
use Nbcom\Bitrix24\Client\RetryPolicy;
use Nbcom\Bitrix24\Client\Throttle;
use Nbcom\Bitrix24\Client\Transport;
use Nbcom\Bitrix24\Exceptions\Bitrix24AuthenticationException;
use Nbcom\Bitrix24\Exceptions\Bitrix24TransportException;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase
{
    public function test_calls_the_endpoint_and_returns_json(): void
    {
        $factory = new Factory;
        $factory->fake([
            '*' => Factory::response(['result' => ['ID' => '1']]),
        ]);
        $transport = $this->transport($factory);

        $body = $transport->call('user.get', ['filter' => ['ID' => 1]]);

        self::assertSame(['ID' => '1'], $body['result']);
        $factory->assertSent(static function (Request $request): bool {
            return $request->url() === 'https://bitrix.example.ru/rest/1/token/user.get'
                && $request->method() === 'POST';
        });
    }

    public function test_maps_authentication_errors(): void
    {
        $factory = new Factory;
        $factory->fake([
            '*' => Factory::response([
                'error' => 'INVALID_CREDENTIALS',
                'error_description' => 'Invalid request credentials',
            ], 401),
        ]);

        $this->expectException(Bitrix24AuthenticationException::class);

        $this->transport($factory)->call('user.get');
    }

    public function test_retries_a_retryable_error(): void
    {
        $factory = new Factory;
        $attempts = 0;
        $factory->fake([
            '*' => static function () use (&$attempts) {
                $attempts++;

                return $attempts === 1
                    ? Factory::response(['error' => 'QUERY_LIMIT_EXCEEDED'], 503)
                    : Factory::response(['result' => ['ok' => true]]);
            },
        ]);
        $transport = $this->transport($factory, new RetryPolicy(maxAttempts: 2, baseDelayMs: 0, maxDelayMs: 0));

        $body = $transport->call('user.get');

        self::assertSame(2, $attempts);
        self::assertSame(['ok' => true], $body['result']);
    }

    public function test_does_not_retry_authentication_errors(): void
    {
        $factory = new Factory;
        $factory->fake([
            '*' => Factory::response(['error' => 'INVALID_CREDENTIALS'], 401),
        ]);

        try {
            $this->transport($factory, new RetryPolicy(maxAttempts: 3))->call('user.get');
            self::fail('Expected an authentication exception.');
        } catch (Bitrix24AuthenticationException) {
            $factory->assertSentCount(1);
        }
    }

    public function test_maps_connection_failures(): void
    {
        $factory = new Factory;
        $factory->fake([
            '*' => Factory::failedConnection('connection refused'),
        ]);

        $this->expectException(Bitrix24TransportException::class);

        $this->transport($factory, RetryPolicy::none())->call('user.get');
    }

    public function test_rejects_non_json_responses(): void
    {
        $factory = new Factory;
        $factory->fake([
            '*' => Factory::response('<html>login</html>'),
        ]);

        $this->expectException(Bitrix24TransportException::class);
        $this->expectExceptionMessage('non-JSON');

        $this->transport($factory, RetryPolicy::none())->call('user.get');
    }

    private function transport(Factory $factory, ?RetryPolicy $policy = null): Transport
    {
        return new Transport(
            http: $factory,
            endpoint: Endpoint::fromUrl('https://bitrix.example.ru/rest/1/token/'),
            errors: new ErrorMapper,
            defaultPolicy: $policy ?? RetryPolicy::none(),
            throttle: new Throttle(0),
            timeout: 5,
            connectTimeout: 5,
        );
    }
}
