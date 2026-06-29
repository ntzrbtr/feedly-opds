<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('opds.auth_token', 'test-secret-token');
    config()->set('feedly.user_id', 'a1b2c3d4-0000-0000-0000-000000000000');
    config()->set('feedly.saved_tag', 'saved');
    config()->set('feedly.developer_token', 'feedly-token');
});

it('shows the welcome page without auth', function (): void {
    $this->get('/')->assertOk()->assertSee('OPDS', false);
});

it('renders the root opds navigation feed', function (): void {
    Http::fake([
        'cloud.feedly.com/*' => Http::response(['items' => []], 200),
    ]);

    $response = $this->get('/opds?token=test-secret-token')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/atom+xml; charset=UTF-8');

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse()
        ->and((string) $xml->getName())->toBe('feed')
        ->and($xml->entry->count())->toBe(1)
        ->and((string) $xml->entry->title)->toContain('Read Later')
        ->and((string) $xml->entry->link['rel'])->toBe('http://opds-spec.org/catalog');
});
