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

it('embeds instaparser html in the detail entry', function (): void {
    Http::fake([
        'cloud.feedly.com/v3/streams/contents*' => Http::response([
            'items' => [
                [
                    'id' => 'entry-1',
                    'title' => 'Article',
                    'published' => 1_700_000_000_000,
                    'summary' => ['content' => 'Summary'],
                    'alternate' => [['href' => 'https://example.com/article']],
                ],
            ],
        ], 200),
        'www.instaparser.com/api/article*' => Http::response([
            'title' => 'Article',
            'url' => 'https://example.com/article',
            'content' => '<p>parsed body</p>',
        ], 200),
    ]);

    $response = $this->get('/opds/entry/entry-1?token=test-secret-token')->assertOk();

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse()
        ->and($xml->entry->content->count())->toBe(1)
        ->and((string) $xml->entry->content['type'])->toBe('xhtml')
        ->and((string) $xml->entry->content)->toContain('<p>parsed body</p>');
});

it('falls back gracefully when instaparser fails', function (): void {
    Http::fake([
        'cloud.feedly.com/v3/streams/contents*' => Http::response([
            'items' => [
                [
                    'id' => 'entry-1',
                    'title' => 'Article',
                    'summary' => ['content' => 'Summary'],
                    'alternate' => [['href' => 'https://example.com/article']],
                ],
            ],
        ], 200),
        'www.instaparser.com/api/article*' => Http::response('error', 500),
    ]);

    $response = $this->get('/opds/entry/entry-1?token=test-secret-token')->assertOk();

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse()
        ->and($xml->entry->summary->count())->toBe(1)
        ->and((string) $xml->entry->summary)->toBe('Summary')
        ->and($xml->entry->content->count())->toBe(0);
});

it('returns 404 for unknown entry', function (): void {
    Http::fake([
        'cloud.feedly.com/v3/streams/contents*' => Http::response(['items' => []], 200),
        'cloud.feedly.com/v3/entries/*' => Http::response('{}', 200),
    ]);

    $this->get('/opds/entry/unknown?token=test-secret-token')->assertNotFound();
});
