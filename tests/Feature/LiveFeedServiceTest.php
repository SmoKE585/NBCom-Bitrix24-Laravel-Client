<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Tests\Feature;

use Nbcom\Bitrix24\Services\LiveFeedService;
use Nbcom\Bitrix24\Tests\Support\AllowAllCapabilities;
use Nbcom\Bitrix24\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class LiveFeedServiceTest extends TestCase
{
    public function test_posts_to_the_live_feed(): void
    {
        $client = new FakeClient(['log.blogpost.add' => ['result' => 42]]);
        $service = new LiveFeedService($client, new AllowAllCapabilities);

        $receipt = $service->post('Race finished', ['UA'], 'Results');

        self::assertSame(42, $receipt->postId);
        self::assertTrue($receipt->delivered);

        $params = $client->callsFor('log.blogpost.add')[0]['params'];
        self::assertSame('Race finished', $params['POST_MESSAGE']);
        self::assertSame(['UA'], $params['DEST']);
        self::assertSame('Results', $params['TITLE']);
    }
}
