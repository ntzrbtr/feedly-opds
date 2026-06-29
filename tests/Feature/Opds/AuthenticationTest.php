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

it('rejects requests without a token', function (): void {
    $this->get('/opds')->assertUnauthorized();
});

it('rejects requests with wrong token', function (): void {
    $this->get('/opds?token=wrong')->assertUnauthorized();
});

it('accepts token via query parameter', function (): void {
    Http::fake([
        'cloud.feedly.com/*' => Http::response(['items' => []], 200),
    ]);

    $this->get('/opds?token=test-secret-token')->assertOk();
});

it('accepts token via basic auth header', function (): void {
    Http::fake([
        'cloud.feedly.com/*' => Http::response(['items' => []], 200),
    ]);

    $this->withHeaders([
        'Authorization' => 'Basic '.base64_encode(':test-secret-token'),
    ])->get('/opds')->assertOk();
});

it('ignores the username in basic auth and uses the password as token', function (): void {
    Http::fake([
        'cloud.feedly.com/*' => Http::response(['items' => []], 200),
    ]);

    $this->withHeaders([
        'Authorization' => 'Basic '.base64_encode('any-username:test-secret-token'),
    ])->get('/opds')->assertOk();
});
