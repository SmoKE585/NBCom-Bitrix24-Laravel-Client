<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Unit;

use Carbon\CarbonImmutable;
use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Capabilities\CapabilitySnapshot;
use Nbcom\Bitrix24\Capabilities\MethodStatus;
use Nbcom\Bitrix24\Capabilities\SnapshotStore\FileSnapshotStore;
use PHPUnit\Framework\TestCase;

final class FileSnapshotStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/bitrix24-snapshot-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);
    }

    public function test_writes_and_reads_a_snapshot(): void
    {
        $store = new FileSnapshotStore($this->directory);
        $snapshot = $this->snapshot();
        $store->write($snapshot);

        self::assertFileExists($store->path());

        $read = $store->read();

        self::assertNotNull($read);
        self::assertSame('bitrix.example.ru', $read->portal);
        self::assertSame('method.get', $read->strategy);
        self::assertTrue($read->supports('user.get'));
        self::assertFalse($read->hasMissing());
    }

    public function test_returns_null_for_a_corrupted_file(): void
    {
        $store = new FileSnapshotStore($this->directory);

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        file_put_contents($store->path(), '{broken');

        self::assertNull($store->read());
    }

    public function test_forgets_a_snapshot(): void
    {
        $store = new FileSnapshotStore($this->directory);
        $store->write($this->snapshot());
        $store->forget();

        self::assertNull($store->read());
        self::assertFileDoesNotExist($store->path());
    }

    private function snapshot(): CapabilitySnapshot
    {
        $endpoint = Endpoint::fromUrl('https://bitrix.example.ru/rest/1/token/');

        return new CapabilitySnapshot(
            version: 1,
            fingerprint: $endpoint->fingerprint(),
            portal: $endpoint->host(),
            userId: $endpoint->userId(),
            checkedAt: CarbonImmutable::now(),
            expiresAt: CarbonImmutable::now()->addHour(),
            packageVersion: '1.0.0',
            strategy: 'method.get',
            scopes: ['user'],
            methods: ['user.get' => new MethodStatus(true, true)],
            chosen: ['users.get' => 'user.get'],
            missingScopes: [],
            missingMethods: [],
        );
    }
}
