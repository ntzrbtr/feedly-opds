<?php

declare(strict_types=1);

namespace App\Services\Feedly\Dtos;

use Carbon\Carbon;

final readonly class Entry
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $url,
        public ?string $summary,
        public ?string $originUrl,
        public ?string $author,
        public ?Carbon $published,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromFeedlyPayload(array $payload): self
    {
        $url = self::extractCanonicalUrl($payload);
        $summary = self::extractSummary($payload);
        $origin = $payload['origin'] ?? null;
        $author = $payload['author'] ?? null;
        $published = isset($payload['published'])
            ? Carbon::createFromTimestampMs((int) $payload['published'])
            : null;

        return new self(
            id: (string) ($payload['id'] ?? ''),
            title: (string) ($payload['title'] ?? ''),
            url: $url,
            summary: $summary,
            originUrl: isset($origin['htmlUrl']) ? (string) $origin['htmlUrl'] : null,
            author: is_string($author) ? $author : null,
            published: $published,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function extractCanonicalUrl(array $payload): ?string
    {
        $canonical = $payload['canonicalUrl'] ?? null;
        if (is_string($canonical) && $canonical !== '') {
            return $canonical;
        }

        if (is_array($canonical) && isset($canonical[0]) && is_string($canonical[0])) {
            return $canonical[0];
        }

        $alternates = $payload['alternate'] ?? null;
        if (is_array($alternates) && isset($alternates[0]['href'])) {
            return (string) $alternates[0]['href'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function extractSummary(array $payload): ?string
    {
        $summary = $payload['summary'] ?? null;
        if (is_array($summary) && isset($summary['content'])) {
            return (string) $summary['content'];
        }

        return null;
    }
}
