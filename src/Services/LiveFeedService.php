<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Services;

use Nbcom\Bitrix24\Client\RetryPolicy;
use Nbcom\Bitrix24\Contracts\CapabilityGuardInterface;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Dto\LiveFeedReceipt;
use Nbcom\Bitrix24\Enums\Bitrix24ErrorReason;
use Nbcom\Bitrix24\Exceptions\Bitrix24ValidationException;

final class LiveFeedService
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CapabilityGuardInterface $guard,
    ) {}

    /**
     * @param  list<string>  $dest
     */
    public function post(string $message, array $dest = [], ?string $title = null): LiveFeedReceipt
    {
        $message = trim($message);

        if ($message === '') {
            throw new Bitrix24ValidationException(
                message: 'Bitrix24 live feed message must not be empty.',
                reason: Bitrix24ErrorReason::Validation,
            );
        }

        $method = $this->guard->methodFor('live_feed.post');
        $this->guard->ensure($method);

        $params = [
            'POST_MESSAGE' => $message,
            'DEST' => $dest,
        ];

        if ($title !== null && trim($title) !== '') {
            $params['TITLE'] = trim($title);
        }

        $body = $this->client->call($method, $params, RetryPolicy::none());
        $result = $body['result'] ?? null;

        return new LiveFeedReceipt(
            postId: is_numeric($result) && (int) $result > 0 ? (int) $result : null,
            delivered: $result !== false && $result !== null,
        );
    }
}
