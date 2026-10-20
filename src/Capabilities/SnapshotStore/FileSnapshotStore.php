<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Capabilities\SnapshotStore;

use JsonException;
use Nbcom\Bitrix24\Capabilities\CapabilitySnapshot;
use Nbcom\Bitrix24\Contracts\SnapshotStoreInterface;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24ConfigurationException;
use Throwable;

final class FileSnapshotStore implements SnapshotStoreInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly string $filename = 'capabilities.json',
    ) {}

    public function read(): ?CapabilitySnapshot
    {
        $path = $this->path();

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        try {
            return CapabilitySnapshot::fromArray($data);
        } catch (Throwable) {
            return null;
        }
    }

    public function write(CapabilitySnapshot $snapshot): void
    {
        $directory = $this->directory;

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new Bitrix24ConfigurationException(
                message: "Cannot create Bitrix24 capabilities directory [{$directory}].",
                reason: Bitrix24ErrorReason::Configuration,
            );
        }

        try {
            $json = json_encode(
                $snapshot->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new Bitrix24ConfigurationException(
                message: 'Cannot encode Bitrix24 capabilities snapshot: '.$exception->getMessage(),
                reason: Bitrix24ErrorReason::Configuration,
                previous: $exception,
            );
        }

        $path = $this->path();
        $temporary = tempnam($directory, 'capabilities-');

        if ($temporary === false) {
            throw new Bitrix24ConfigurationException(
                message: "Cannot create a temporary file in [{$directory}].",
                reason: Bitrix24ErrorReason::Configuration,
            );
        }

        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            @unlink($temporary);

            throw new Bitrix24ConfigurationException(
                message: "Cannot write Bitrix24 capabilities snapshot to [{$path}].",
                reason: Bitrix24ErrorReason::Configuration,
            );
        }

        @chmod($temporary, 0600);

        if (! @rename($temporary, $path)) {
            @unlink($path);

            if (! @rename($temporary, $path)) {
                @unlink($temporary);

                throw new Bitrix24ConfigurationException(
                    message: "Cannot replace Bitrix24 capabilities snapshot [{$path}].",
                    reason: Bitrix24ErrorReason::Configuration,
                );
            }
        }

        @chmod($path, 0600);
    }

    public function forget(): void
    {
        @unlink($this->path());
    }

    public function path(): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->filename;
    }
}
