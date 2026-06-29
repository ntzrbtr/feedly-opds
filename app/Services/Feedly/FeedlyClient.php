<?php

declare(strict_types=1);

namespace App\Services\Feedly;

use App\Services\Feedly\Dtos\Entry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Tiny HTTP client for the parts of the Feedly v3 API used by this project.
 *
 * Implements an idempotent OAuth refresh flow: access tokens are persisted in the
 * Laravel cache; the .env token acts as bootstrap. On 401 (or imminent expiry)
 * the client refreshes once and retries the original request.
 */
final readonly class FeedlyClient
{
    private const string CACHE_ACCESS_TOKEN = 'feedly.access_token';

    private const string CACHE_TOKEN_EXPIRES_AT = 'feedly.token_expires_at';

    private const string FEEDLY_AUTH_HEADER = 'OAuth';

    public function __construct(
        private string $baseUrl,
        private ?string $developerToken,
        private ?string $refreshToken,
        private ?string $clientId,
        private ?string $clientSecret,
    ) {}

    /**
     * @return Collection<int, Entry>
     */
    public function savedEntries(string $streamId, int $count = 50, ?string $continuation = null): Collection
    {
        $query = array_filter([
            'streamId' => $streamId,
            'count' => $count,
            'ranked' => 'newest',
            'unreadOnly' => 'false',
            'continuation' => $continuation,
        ]);

        $payload = $this->request('get', '/streams/contents', $query);
        /** @var list<array<string, mixed>> $items */
        $items = $payload['items'] ?? [];

        return collect($items)
            ->map(fn (array $item): Entry => Entry::fromFeedlyPayload($item));
    }

    public function entry(string $entryId): ?Entry
    {
        $payload = $this->request('get', '/entries/'.rawurlencode($entryId), []);

        return isset($payload['id']) ? Entry::fromFeedlyPayload($payload) : null;
    }

    public function savedStreamId(string $userUuid, string $tag): string
    {
        return sprintf('user/%s/tag/global.%s', $userUuid, $tag);
    }

    /**
     * @param  array<string, string|int|string|null>  $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $query = []): array
    {
        $attemptRefresh = false;

        return $this->retry(2, function () use ($method, $path, $query, &$attemptRefresh): array {
            if ($attemptRefresh || $this->tokenNeedsRefresh()) {
                $this->refreshAccessToken();
                $attemptRefresh = false;
            }

            $response = Http::withToken($this->currentAccessToken(), self::FEEDLY_AUTH_HEADER)
                ->{$method}($this->baseUrl.$path, $method === 'get' ? $query : []);

            /** @var bool $attemptRefresh */
            if ($response->status() === 401 && ! $attemptRefresh) {
                Cache::forget(self::CACHE_ACCESS_TOKEN);
                Cache::forget(self::CACHE_TOKEN_EXPIRES_AT);
                $attemptRefresh = true;

                throw new RuntimeException('Feedly returned 401 – triggering token refresh.');
            }

            $response->throw();

            return $response->json() ?? [];
        }, 100, fn (Throwable $e): bool => $e instanceof RuntimeException || $e instanceof ConnectionException);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  callable(Throwable): bool  $when
     * @return T
     */
    private function retry(int $times, callable $callback, int $sleepMs, callable $when): mixed
    {
        $attempts = 0;

        beginning:
        try {
            $attempts++;

            return $callback();
        } catch (Throwable $throwable) {
            if ($attempts >= $times || ! $when($throwable)) {
                throw $throwable;
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }

            goto beginning;
        }
    }

    private function tokenNeedsRefresh(): bool
    {
        $expiresAt = Cache::get(self::CACHE_TOKEN_EXPIRES_AT);
        if (! $expiresAt) {
            return false;
        }

        try {
            return now()->addSeconds(60)->isAfter($expiresAt);
        } catch (Throwable) {
            return true;
        }
    }

    private function currentAccessToken(): string
    {
        $cached = Cache::get(self::CACHE_ACCESS_TOKEN);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (! $this->developerToken) {
            throw new RuntimeException('Feedly: no access token available (configure FEEDLY_DEVELOPER_TOKEN).');
        }

        return $this->developerToken;
    }

    private function refreshAccessToken(): void
    {
        if (! $this->refreshToken) {
            throw new RuntimeException('Feedly: cannot refresh access token (FEEDLY_REFRESH_TOKEN missing).');
        }

        $payload = array_filter([
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ], filled(...));

        try {
            $response = Http::asForm()
                ->post($this->baseUrl.'/auth/token', $payload);

            $response->throw();
            $body = $response->json() ?? [];
        } catch (Throwable $throwable) {
            Log::error('Feedly token refresh failed', ['exception' => $throwable]);
            throw new RuntimeException('Feedly: token refresh failed.', 0, $throwable);
        }

        $token = $body['access_token'] ?? null;
        $expiresIn = (int) ($body['expires_in'] ?? 3600);

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Feedly: refresh response did not contain access_token.');
        }

        Cache::put(self::CACHE_ACCESS_TOKEN, $token);
        Cache::put(self::CACHE_TOKEN_EXPIRES_AT, now()->addSeconds($expiresIn));
    }
}
