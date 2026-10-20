<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Auth;

use Illuminate\Contracts\Config\Repository;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24ConfigurationException;

final class Endpoint
{
    private function __construct(
        private readonly string $scheme,
        private readonly string $host,
        private readonly ?int $port,
        private readonly string $prefix,
        private readonly int $userId,
        private readonly string $token,
    ) {}

    public static function fromConfig(Repository $config): self
    {
        $url = $config->get('bitrix24.endpoint');

        if (! is_string($url) || trim($url) === '') {
            throw new Bitrix24ConfigurationException(
                message: 'Bitrix24 endpoint is not configured. Set BITRIX24_ENDPOINT to the full webhook URL.',
                reason: Bitrix24ErrorReason::Configuration,
            );
        }

        return self::fromUrl($url, (bool) $config->get('bitrix24.allow_http', false));
    }

    public static function fromUrl(string $url, bool $allowHttp = false): self
    {
        $url = trim($url);

        if ($url === '') {
            throw self::invalid('The Bitrix24 endpoint is empty.');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw self::invalid('The Bitrix24 endpoint is not a valid URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);

        if ($scheme !== 'https' && ! ($allowHttp && $scheme === 'http')) {
            throw self::invalid('The Bitrix24 endpoint must use HTTPS (set BITRIX24_ALLOW_HTTP=true only for local development).');
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw self::invalid('The Bitrix24 endpoint must not contain a query string or a fragment.');
        }

        $path = (string) ($parts['path'] ?? '');

        if (preg_match('#^(.*?)/rest/(\d+)/([^/]+)/?$#', $path, $matches) !== 1) {
            throw self::invalid('The Bitrix24 endpoint must look like https://portal.example.ru/rest/{userId}/{token}/.');
        }

        $userId = (int) $matches[2];
        $token = rawurldecode($matches[3]);

        if ($userId <= 0 || $token === '') {
            throw self::invalid('The Bitrix24 endpoint contains an invalid user id or token.');
        }

        $prefix = rtrim($matches[1], '/');
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        return new self(
            scheme: $scheme,
            host: (string) $parts['host'],
            port: $port,
            prefix: $prefix,
            userId: $userId,
            token: $token,
        );
    }

    public function url(string $method): string
    {
        $method = strtolower(trim($method));

        if (preg_match('/^[a-z0-9._]+$/', $method) !== 1) {
            throw new Bitrix24ConfigurationException(
                message: "Invalid Bitrix24 method name [{$method}].",
                reason: Bitrix24ErrorReason::Configuration,
                method: $method,
            );
        }

        return $this->baseUrl().'/'.$method;
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->host.'|'.$this->userId.'|'.$this->token);
    }

    public function host(): string
    {
        return $this->host;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function redacted(): string
    {
        return $this->origin().$this->prefix.'/rest/'.$this->userId.'/***/';
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'host' => $this->host,
            'user_id' => $this->userId,
            'endpoint' => $this->redacted(),
        ];
    }

    private function baseUrl(): string
    {
        return $this->origin().$this->prefix.'/rest/'.$this->userId.'/'.rawurlencode($this->token);
    }

    private function origin(): string
    {
        $port = $this->port !== null ? ':'.$this->port : '';

        return $this->scheme.'://'.$this->host.$port;
    }

    private static function invalid(string $message): Bitrix24ConfigurationException
    {
        return new Bitrix24ConfigurationException(
            message: $message,
            reason: Bitrix24ErrorReason::Configuration,
        );
    }
}
