<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Feedly\Dtos\Entry;
use App\Services\Feedly\FeedlyClient;
use App\Services\Instapaper\ArticleContent;
use App\Services\Instapaper\InstaparserClient;
use App\Services\Opds\OpdsDocumentBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Uri;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OpdsController extends Controller
{
    private const string ATOM_CONTENT_TYPE = 'application/atom+xml; charset=UTF-8';

    public function __construct(
        private readonly FeedlyClient $feedly,
        private readonly InstaparserClient $instaparser,
        private readonly OpdsDocumentBuilder $opds,
    ) {}

    public function catalog(Request $request): Response
    {
        $savedHref = $this->absolute($request, route('opds.saved'));

        $xml = $this->opds->navigationFeed('urn:feedly-opds:catalog', [
            [
                'title' => config('opds.title', 'Read Later'),
                'href' => $savedHref,
                'rel' => 'http://opds-spec.org/catalog',
                'type' => 'application/atom+xml;profile=opds-catalog;kind=acquisition',
                'summary' => 'Saved articles from Feedly.',
            ],
        ]);

        return $this->atomResponse($xml);
    }

    public function saved(Request $request): Response
    {
        $streamId = $this->savedStreamId();

        $entries = Cache::remember(
            'feedly.saved.page.0',
            now()->addSeconds((int) config('feedly.cache.feed_ttl', 300)),
            fn (): array => array_values($this->feedly->savedEntries($streamId, 50)->all()),
        );

        $selfHref = $this->absolute($request, route('opds.saved'));
        $xml = $this->opds->acquisitionFeed('urn:feedly-opds:saved', $entries, $selfHref);

        return $this->atomResponse($xml);
    }

    public function entry(Request $request, string $entryId): Response
    {
        $entry = $this->lookupEntry($entryId);
        if (!$entry instanceof \App\Services\Feedly\Dtos\Entry) {
            throw new NotFoundHttpException('Entry not found.');
        }

        $content = $entry->url
            ? $this->instaparser->article($entry->url)
            : ArticleContent::empty();

        $selfHref = $this->absolute($request, route('opds.entry', $entryId));
        $xml = $this->opds->detailEntry($entry, $content, $selfHref);

        return $this->atomResponse($xml);
    }

    public function download(string $entryId): Response
    {
        $entry = $this->lookupEntry($entryId);
        if (!$entry instanceof \App\Services\Feedly\Dtos\Entry || $entry->url === null) {
            throw new NotFoundHttpException('Entry or download not found.');
        }

        $content = $this->instaparser->article($entry->url);

        return new Response($content->html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function lookupEntry(string $entryId): ?Entry
    {
        $entries = Cache::remember(
            'feedly.saved.page.0',
            now()->addSeconds((int) config('feedly.cache.feed_ttl', 300)),
            fn (): array => array_values($this->feedly->savedEntries($this->savedStreamId(), 50)->all()),
        );

        foreach ($entries as $entry) {
            if ($entry->id === $entryId) {
                return $entry;
            }
        }

        try {
            return $this->feedly->entry($entryId);
        } catch (RuntimeException $runtimeException) {
            Log::info('Feedly single entry lookup failed', ['entryId' => $entryId, 'exception' => $runtimeException]);

            return null;
        }
    }

    private function savedStreamId(): string
    {
        $userId = (string) config('feedly.user_id');
        if ($userId === '') {
            throw new RuntimeException('Feedly: FEEDLY_USER_ID is not configured.');
        }

        $explicit = config('feedly.saved_stream_id');

        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        return $this->feedly->savedStreamId($userId, (string) config('feedly.saved_tag', 'saved'));
    }

    private function absolute(Request $request, string $path): string
    {
        $port = $request->getPort();

        return Uri::of($path)
            ->withScheme($request->getScheme())
            ->withHost($request->getHost())
            ->withPort(is_string($port) ? (int) $port : $port)
            ->__toString();
    }

    private function atomResponse(string $xml): Response
    {
        return new Response($xml, 200, [
            'Content-Type' => self::ATOM_CONTENT_TYPE,
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
