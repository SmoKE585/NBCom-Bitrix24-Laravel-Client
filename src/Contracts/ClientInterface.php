<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Contracts;

use Nbcom\Bitrix24\Client\RetryPolicy;

interface ClientInterface
{
    /**
     * Execute a Bitrix24 REST method and return the decoded response body.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws \Nbcom\Bitrix24\Exceptions\Bitrix24Exception
     */
    public function call(string $method, array $params = [], ?RetryPolicy $policy = null): array;
}
