<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Support;

use Closure;
use Nbcom\Bitrix24\Client\RetryPolicy;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use RuntimeException;

final class FakeClient implements ClientInterface
{
    /** @var list<array{method: string, params: array<string, mixed>}> */
    public array $calls = [];

    /**
     * @param  array<string, mixed>  $responses
     */
    public function __construct(private readonly array $responses = []) {}

    public function call(string $method, array $params = [], ?RetryPolicy $policy = null): array
    {
        $this->calls[] = ['method' => $method, 'params' => $params];

        if (! array_key_exists($method, $this->responses)) {
            throw new RuntimeException("No fake response registered for [{$method}].");
        }

        $response = $this->responses[$method];

        if ($response instanceof Closure) {
            $response = $response($params, $method);
        }

        if (is_array($response) && (array_key_exists('result', $response) || array_key_exists('error', $response))) {
            return $response;
        }

        return ['result' => $response];
    }

    /**
     * @return list<array{method: string, params: array<string, mixed>}>
     */
    public function callsFor(string $method): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn (array $call): bool => $call['method'] === $method,
        ));
    }
}
