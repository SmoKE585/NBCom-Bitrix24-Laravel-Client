<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Client;

final class Throttle
{
    private ?float $lastCallAt = null;

    public function __construct(private readonly float $requestsPerSecond = 5.0) {}

    public function wait(): void
    {
        if ($this->requestsPerSecond <= 0) {
            return;
        }

        $interval = 1 / $this->requestsPerSecond;
        $now = microtime(true);

        if ($this->lastCallAt !== null) {
            $elapsed = $now - $this->lastCallAt;

            if ($elapsed < $interval) {
                usleep((int) (($interval - $elapsed) * 1_000_000));
            }
        }

        $this->lastCallAt = microtime(true);
    }
}
