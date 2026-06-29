<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('opds.auth_token', 'test-secret-token');
    config()->set('feedly.user_id', 'a1b2c3d4-0000-0000-0000-000000000000');
    config()->set('feedly.saved_tag', 'saved');
    config()->set('feedly.developer_token', 'feedly-token');
});

it('renders an acquisition feed without inline content', function (): void {
    Http::fake([
        'cloud.feedly.com/v3/streams/contents*' => Http::response([
            'items' => [
                [
                    'id' => 'entry-1',
                    'title' => 'First article',
                    'published' => 1_700_000_000_000,
                    'author' => 'Jane Doe',
                    'summary' => ['content' => 'Short summary'],
                    'alternate' => [['href' => 'https://example.com/article-1']],
                ],
                [
                    'id' => 'entry-2',
                    'title' => 'Second article',
                    'published' => 1_700_010_000_000,
                    'alternate' => [['href' => 'https://example.com/article-2']],
                ],
            ],
        ], 200),
    ]);

    $response = $this->get('/opds/saved?token=test-secret-token')->assertOk();

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse()
        ->and($xml->entry->count())->toBe(2)
        ->and((string) $xml->entry[0]->title)->toBe('First article')
        ->and($xml->entry[0]->content)->toBeEmpty()
        ->and((string) $xml->entry[0]->link['rel'])->toBe('alternate');
});

it('caches the saved feed between requests', function (): void {
    Http::fake([
        'cloud.feedly.com/v3/streams/contents*' => Http::response(['items' => []], 200),
    ]);

    $this->get('/opds/saved?token=test-secret-token')->assertOk();
    $this->get('/opds/saved?token=test-secret-token')->assertOk();

    Http::assertSentCount(1);
});
