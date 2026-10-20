<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Unit;

use Nbcom\Bitrix24\Auth\Endpoint;
use Nbcom\Bitrix24\Exceptions\Bitrix24ConfigurationException;
use PHPUnit\Framework\TestCase;

final class EndpointTest extends TestCase
{
    public function test_parses_a_webhook_url(): void
    {
        $endpoint = Endpoint::fromUrl('https://bitrix.example.ru/rest/1/secret-token/');

        self::assertSame('bitrix.example.ru', $endpoint->host());
        self::assertSame(1, $endpoint->userId());
        self::assertSame('https://bitrix.example.ru/rest/1/secret-token/user.get', $endpoint->url('user.get'));
        self::assertSame('https://bitrix.example.ru/rest/1/secret-token/user.get', $endpoint->url('User.Get'));
        self::assertStringNotContainsString('secret-token', $endpoint->redacted());
        self::assertStringContainsString('***', $endpoint->redacted());
    }

    public function test_fingerprint_depends_on_token(): void
    {
        $first = Endpoint::fromUrl('https://bitrix.example.ru/rest/1/first/');
        $second = Endpoint::fromUrl('https://bitrix.example.ru/rest/1/second/');

        self::assertNotSame($first->fingerprint(), $second->fingerprint());
        self::assertSame(
            $first->fingerprint(),
            Endpoint::fromUrl('https://bitrix.example.ru/rest/1/first/')->fingerprint(),
        );
    }

    public function test_supports_a_path_prefix_and_port(): void
    {
        $endpoint = Endpoint::fromUrl('https://example.ru:8443/bitrix/rest/7/token/');

        self::assertSame('https://example.ru:8443/bitrix/rest/7/token/department.get', $endpoint->url('department.get'));
        self::assertStringContainsString(':8443', $endpoint->redacted());
    }

    public function test_rejects_http_by_default(): void
    {
        $this->expectException(Bitrix24ConfigurationException::class);

        Endpoint::fromUrl('http://bitrix.example.ru/rest/1/token/');
    }

    public function test_allows_http_when_explicitly_enabled(): void
    {
        $endpoint = Endpoint::fromUrl('http://bitrix.example.ru/rest/1/token/', true);

        self::assertSame('http://bitrix.example.ru/rest/1/token/scope', $endpoint->url('scope'));
    }

    public function test_rejects_invalid_urls(): void
    {
        foreach ([
            '',
            'https://bitrix.example.ru/',
            'https://bitrix.example.ru/rest/0/token/',
            'https://bitrix.example.ru/rest/1//',
            'https://bitrix.example.ru/rest/1/token/?foo=bar',
        ] as $url) {
            try {
                Endpoint::fromUrl($url);
                self::fail("URL [{$url}] should be rejected.");
            } catch (Bitrix24ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_debug_info_does_not_expose_the_token(): void
    {
        $endpoint = Endpoint::fromUrl('https://bitrix.example.ru/rest/1/secret-token/');
        $debug = $endpoint->__debugInfo();

        self::assertSame('bitrix.example.ru', $debug['host']);
        self::assertStringNotContainsString('secret-token', (string) $debug['endpoint']);
    }
}
