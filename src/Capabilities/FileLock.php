<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Capabilities;

final class FileLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $path) {}

    public function acquire(float $timeoutSeconds = 10.0): bool
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        $handle = @fopen($this->path, 'c');

        if ($handle === false) {
            return false;
        }

        $deadline = microtime(true) + max(0.0, $timeoutSeconds);

        do {
            if (@flock($handle, LOCK_EX | LOCK_NB)) {
                $this->handle = $handle;

                return true;
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        fclose($handle);

        return false;
    }

    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }

        @flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
