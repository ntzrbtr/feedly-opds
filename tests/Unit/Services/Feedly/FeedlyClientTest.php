<?php

declare(strict_types=1);

use App\Services\Feedly\FeedlyClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    Http::preventStrayRequests();
});

it('refreshes the access token after receiving a 401 from feedly', function (): void {
    Http::fake([
        'https://cloud.feedly.com/v3/streams/contents*' => Http::sequence()
            ->push('Unauthorized', 401)
            ->push(['items' => []], 200),
        'https://cloud.feedly.com/v3/auth/token' => Http::response([
            'access_token' => 'new-token',
            'expires_in' => 3600,
        ], 200),
    ]);

    $client = new FeedlyClient(
        baseUrl: 'https://cloud.feedly.com/v3',
        developerToken: 'stale-env-token',
        refreshToken: 'refresh-token-123',
        clientId: 'client-id',
        clientSecret: 'client-secret',
    );

    $client->savedEntries('user/uuid/tag/global.saved');

    expect(Cache::get('feedly.access_token'))->toBe('new-token')
        ->and(Cache::get('feedly.token_expires_at'))->not->toBeNull();
});

it('uses the env developer token first and only refreshes after 401', function (): void {
    Http::fake([
        'https://cloud.feedly.com/v3/streams/contents*' => Http::sequence()
            ->push(['items' => []], 200),
    ]);

    $client = new FeedlyClient(
        baseUrl: 'https://cloud.feedly.com/v3',
        developerToken: 'env-token',
        refreshToken: 'refresh-token-123',
        clientId: null,
        clientSecret: null,
    );

    $client->savedEntries('user/uuid/tag/global.saved');

    expect(Cache::get('feedly.access_token'))->toBeNull();

    Http::assertSent(fn ($request): bool => $request->header('Authorization')[0] === 'OAuth env-token');
});

it('throws when no developer token and no refresh token are configured', function (): void {
    $client = new FeedlyClient(
        baseUrl: 'https://cloud.feedly.com/v3',
        developerToken: null,
        refreshToken: null,
        clientId: null,
        clientSecret: null,
    );

    $client->savedEntries('user/uuid/tag/global.saved');
})->throws(RuntimeException::class, 'FEEDLY_DEVELOPER_TOKEN');
