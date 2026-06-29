<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('opds.auth_token', 'test-secret-token');
    config()->set('feedly.user_id', 'a1b2c3d4-0000-0000-0000-000000000000');
    config()->set('feedly.saved_tag', 'saved');
    config()->set('feedly.developer_token', 'feedly-token');
    config()->set('instaparser.api_key', 'instaparser-key');
});

it('proxies instaparser html as text/html download', function (): void {
    Http::fake([
        'cloud.feedly.com/v3/streams/contents*' => Http::response([
            'items' => [
                [
                    'id' => 'entry-1',
                    'title' => 'Article',
                    'alternate' => [['href' => 'https://example.com/article']],
                ],
            ],
        ], 200),
        'www.instaparser.com/api/article*' => Http::response([
            'content' => '<article>body</article>',
        ], 200),
    ]);

    $this->get('/opds/download/entry-1?token=test-secret-token')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('<article>body</article>', false);
});

it('returns 404 for unknown download', function (): void {
    Http::fake([
        'cloud.feedly.com/v3/streams/contents*' => Http::response(['items' => []], 200),
        'cloud.feedly.com/v3/entries/*' => Http::response('{}', 200),
    ]);

    $this->get('/opds/download/unknown?token=test-secret-token')->assertNotFound();
});
