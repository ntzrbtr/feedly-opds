<?php

declare(strict_types=1);

namespace App\Services\Instapaper;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Client für die Instaparser-Cloud-API (https://www.instaparser.com/api).
 *
 * Liefert pro URL den bereinigten Artikel als HTML, nah am Instaparser-Format.
 * Ergebnisse werden pro URL gecacht, um Lese-Apps nicht wiederholt zu beauftragen
 * und die externen Rate-Limits des Dienstes zu schonen.
 */
final class InstaparserClient
{
    private readonly ?string $apiKey;

    public function __construct(
        private readonly string $baseUrl,
        ?string $apiKey,
        private readonly int $cacheTtl,
    ) {
        $this->apiKey = filled($apiKey) ? $apiKey : null;
    }

    public function article(string $url): ArticleContent
    {
        if ($this->apiKey === null) {
            throw new RuntimeException('Instaparser: API key missing (INSTAPARSER_API_KEY).');
        }

        $cacheKey = 'instaparser.'.hash('sha256', $url);

        /** @var ArticleContent|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached instanceof ArticleContent) {
            return $cached;
        }

        try {
            $response = Http::withQueryParameters([
                'key' => $this->apiKey,
                'url' => $url,
            ])
                ->get($this->baseUrl.'/article');

            $response->throw();
            $payload = $response->json() ?? [];
        } catch (ConnectionException $e) {
            Log::warning('Instaparser connection failed', ['url' => $url, 'exception' => $e]);

            return ArticleContent::empty();
        } catch (Throwable $e) {
            Log::warning('Instaparser request failed', ['url' => $url, 'exception' => $e]);

            return ArticleContent::empty();
        }

        $content = new ArticleContent(
            title: self::stringOrNull($payload['title'] ?? null),
            author: self::stringOrNull($payload['author'] ?? null),
            url: self::stringOrNull($payload['url'] ?? $url),
            html: self::stringOrNull($payload['content'] ?? '') ?? '',
        );

        Cache::put($cacheKey, $content, now()->addSeconds($this->cacheTtl));

        return $content;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
