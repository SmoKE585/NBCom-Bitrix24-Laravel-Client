<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Contracts;

use Nbcom\Bitrix24\Capabilities\CapabilitySnapshot;

interface SnapshotStoreInterface
{
    public function read(): ?CapabilitySnapshot;

    public function write(CapabilitySnapshot $snapshot): void;

    public function forget(): void;
}
