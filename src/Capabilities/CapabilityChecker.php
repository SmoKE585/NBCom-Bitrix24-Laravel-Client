<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Capabilities;

use Carbon\CarbonImmutable;
use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;

final class CapabilityChecker
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CapabilityRegistry $registry,
        private readonly Endpoint $endpoint,
        private readonly string $packageVersion,
    ) {}

    public function check(): CapabilitySnapshot
    {
        $scopes = $this->stringList($this->client->call('scope')['result'] ?? []);
        $availableMethods = $this->availableMethods();
        $useMethodGet = in_array('method.get', $availableMethods, true) || $this->probeMethodGet();

        $statuses = $useMethodGet
            ? $this->checkWithMethodGet($this->registry->allMethods())
            : $this->checkWithMethods($this->registry->allMethods(), $availableMethods);

        $chosen = [];
        $missingMethods = [];

        foreach ($this->registry->featureMethods() as $feature => $alternatives) {
            foreach ($alternatives as $method) {
                if (($statuses[$method] ?? null)?->available === true) {
                    $chosen[$feature] = $method;

                    break;
                }
            }

            if (! isset($chosen[$feature])) {
                foreach ($alternatives as $method) {
                    $missingMethods[] = $method;
                }
            }
        }

        return new CapabilitySnapshot(
            version: 1,
            fingerprint: $this->endpoint->fingerprint(),
            portal: $this->endpoint->host(),
            userId: $this->endpoint->userId(),
            checkedAt: CarbonImmutable::now(),
            expiresAt: null,
            packageVersion: $this->packageVersion,
            strategy: $useMethodGet ? 'method.get' : 'methods',
            scopes: $scopes,
            methods: $statuses,
            chosen: $chosen,
            missingScopes: array_values(array_diff($this->registry->scopes(), $scopes)),
            missingMethods: array_values(array_unique($missingMethods)),
        );
    }

    /**
     * @return list<string>
     */
    private function availableMethods(): array
    {
        try {
            return $this->stringList($this->client->call('methods')['result'] ?? []);
        } catch (Bitrix24Exception) {
            return [];
        }
    }

    private function probeMethodGet(): bool
    {
        try {
            $body = $this->client->call('method.get', ['name' => 'im.user.get']);

            return is_array($body['result'] ?? null);
        } catch (Bitrix24Exception) {
            return false;
        }
    }

    /**
     * @param  list<string>  $methods
     * @return array<string, MethodStatus>
     */
    private function checkWithMethodGet(array $methods): array
    {
        $commands = [];
        $map = [];

        foreach ($methods as $index => $method) {
            $key = 'm'.$index;
            $commands[$key] = 'method.get?name='.rawurlencode($method);
            $map[$key] = $method;
        }

        $statuses = [];

        try {
            $body = $this->client->call('batch', [
                'halt' => 0,
                'cmd' => $commands,
            ]);
            $results = $body['result']['result'] ?? [];

            foreach ($map as $key => $method) {
                $result = $results[$key] ?? null;

                $statuses[$method] = is_array($result)
                    ? MethodStatus::fromArray($result)
                    : $this->checkOne($method);
            }
        } catch (Bitrix24Exception $exception) {
            if (in_array($exception->reason(), [Bitrix24ErrorReason::Authentication, Bitrix24ErrorReason::Forbidden], true)) {
                throw $exception;
            }

            foreach ($methods as $method) {
                $statuses[$method] = $this->checkOne($method);
            }
        }

        return $statuses;
    }

    private function checkOne(string $method): MethodStatus
    {
        try {
            $body = $this->client->call('method.get', ['name' => $method]);
            $result = $body['result'] ?? null;

            return is_array($result)
                ? MethodStatus::fromArray($result)
                : new MethodStatus(false, false);
        } catch (Bitrix24Exception $exception) {
            if (in_array($exception->reason(), [Bitrix24ErrorReason::Authentication, Bitrix24ErrorReason::Forbidden], true)) {
                throw $exception;
            }

            return new MethodStatus(false, false);
        }
    }

    /**
     * @param  list<string>  $methods
     * @param  list<string>  $available
     * @return array<string, MethodStatus>
     */
    private function checkWithMethods(array $methods, array $available): array
    {
        $set = array_fill_keys($available, true);
        $statuses = [];

        foreach ($methods as $method) {
            $statuses[$method] = new MethodStatus(
                exists: isset($set[$method]),
                available: isset($set[$method]),
            );
        }

        return $statuses;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
