<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Contracts;

interface CapabilityGuardInterface
{
    /**
     * Ensure the given Bitrix method is available before calling it.
     *
     * @throws \Nbcom\Bitrix24\Exceptions\Bitrix24CapabilityException
     */
    public function ensure(string $method): void;

    /**
     * Return the method chosen for a logical feature, taking fallbacks into account.
     *
     * @throws \Nbcom\Bitrix24\Exceptions\Bitrix24CapabilityException
     */
    public function methodFor(string $feature): string;
}
