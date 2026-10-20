<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Client;

use Illuminate\Http\Client\Factory;
use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;
use Psr\Log\LoggerInterface;
use Throwable;

final class Transport implements ClientInterface
{
    public function __construct(
        private readonly Factory $http,
        private readonly Endpoint $endpoint,
        private readonly ErrorMapper $errors,
        private readonly RetryPolicy $defaultPolicy,
        private readonly Throttle $throttle,
        private readonly int $timeout = 15,
        private readonly int $connectTimeout = 5,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $debug = false,
    ) {}

    public function call(string $method, array $params = [], ?RetryPolicy $policy = null): array
    {
        $policy ??= $this->defaultPolicy;
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $this->throttle->wait();

                return $this->send($method, $params);
            } catch (Bitrix24Exception $exception) {
                if ($attempt >= $policy->maxAttempts || ! $policy->allows($exception)) {
                    throw $exception;
                }

                $delayMs = $this->delayMs($attempt, $exception, $policy);

                $this->logger?->warning('Bitrix24 request retry', [
                    'method' => $method,
                    'reason' => $exception->reason()->value,
                    'error_code' => $exception->errorCode(),
                    'attempt' => $attempt,
                    'delay_ms' => $delayMs,
                ]);

                usleep($delayMs * 1000);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function send(string $method, array $params): array
    {
        $startedAt = microtime(true);

        try {
            $response = $this->http
                ->asJson()
                ->acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->post($this->endpoint->url($method), $params);
        } catch (Throwable $exception) {
            throw $this->errors->fromThrowable($method, $exception);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw $this->errors->malformed($method, $response->body());
        }

        if ($this->debug) {
            $this->logger?->debug('Bitrix24 request', [
                'method' => $method,
                'http_status' => $response->status(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        }

        if (array_key_exists('error', $data) && $data['error'] !== null) {
            throw $this->errors->fromResponse($method, $response->status(), $response->headers(), $data);
        }

        if (! $response->successful()) {
            throw $this->errors->fromResponse($method, $response->status(), $response->headers(), $data);
        }

        return $data;
    }

    private function delayMs(int $attempt, Bitrix24Exception $exception, RetryPolicy $policy): int
    {
        $backoff = (int) ($policy->baseDelayMs * (2 ** ($attempt - 1)));
        $backoff = min($backoff, $policy->maxDelayMs);
        $retryAfter = $exception->retryAfter();

        return max($backoff, ($retryAfter ?? 0) * 1000);
    }
}
