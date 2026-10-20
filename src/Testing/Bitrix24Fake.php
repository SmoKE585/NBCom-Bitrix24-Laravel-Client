<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Testing;

use Closure;
use Nbcom\Bitrix24\Client\RetryPolicy;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use RuntimeException;

final class Bitrix24Fake implements ClientInterface
{
    /** @var list<array{method: string, params: array<string, mixed>}> */
    private array $calls = [];

    /**
     * @param  array<string, mixed>  $responses
     */
    public function __construct(private readonly array $responses = []) {}

    public function call(string $method, array $params = [], ?RetryPolicy $policy = null): array
    {
        $this->calls[] = ['method' => $method, 'params' => $params];

        if (! array_key_exists($method, $this->responses)) {
            throw new RuntimeException("No Bitrix24 fake response registered for [{$method}].");
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
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     */
    public function assertCalled(string $method, ?Closure $callback = null): void
    {
        foreach ($this->calls as $call) {
            if ($call['method'] !== $method) {
                continue;
            }

            if ($callback === null || $callback($call['params'])) {
                return;
            }
        }

        throw new RuntimeException("Bitrix24 method [{$method}] was not called with the expected parameters.");
    }

    public function assertNotCalled(string $method): void
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                throw new RuntimeException("Bitrix24 method [{$method}] was called unexpectedly.");
            }
        }
    }

    public function assertCalledCount(string $method, int $count): void
    {
        $actual = 0;

        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                $actual++;
            }
        }

        if ($actual !== $count) {
            throw new RuntimeException("Bitrix24 method [{$method}] was called {$actual} times, expected {$count}.");
        }
    }

    public function assertNothingCalled(): void
    {
        if ($this->calls !== []) {
            $methods = implode(', ', array_column($this->calls, 'method'));

            throw new RuntimeException("Bitrix24 methods were called unexpectedly: {$methods}.");
        }
    }

    /**
     * @return list<array{method: string, params: array<string, mixed>}>
     */
    public function recorded(): array
    {
        return $this->calls;
    }
}
