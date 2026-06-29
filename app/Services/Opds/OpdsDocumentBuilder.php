<?php

declare(strict_types=1);

namespace App\Services\Opds;

use App\Services\Feedly\Dtos\Entry;
use App\Services\Instapaper\ArticleContent;
use Carbon\Carbon;
use Illuminate\Support\Uri;
use Stringable;

/**
 * Baut OPDS-2-konforme Atom-XML-Dokumente:
 *  - Navigation-Feeds (Root-Katalog)
 *  - Acquisition-Feeds (Listen der gespeicherten Artikel)
 *  - Detail-Entries (einzelner Artikel mit Inline-Content)
 *
 * Die XML-Erzeugung erfolgt mit {@see AtomBuilder}, was den
 * Slate of Namespaces (opds, dc, thr) auf vereinheitlichte Weise handhabt.
 */
final class OpdsDocumentBuilder
{
    public const NS_ATOM = 'http://www.w3.org/2005/Atom';

    public const NS_OPDS = 'http://opds-spec.org/2010/catalog';

    public const NS_DC = 'http://purl.org/dc/elements/1.1/';

    public function __construct(
        private readonly string $title,
        private readonly string $author,
        private readonly Stringable|string $authorUri,
    ) {}

    /**
     * @param  array<int, array{title: string, href: string, rel: string, type: string, summary?: string}>  $links
     */
    public function navigationFeed(string $id, array $links): string
    {
        $atom = $this->baseFeed($id);

        foreach ($links as $link) {
            $entry = $atom->addChild('entry');
            $entry->addChild('title', $this->escape($link['title']));
            $entry->addChild('id', $this->escape($link['href']));
            $entry->addChild('updated', Carbon::now()->toAtomString());
            if (isset($link['summary'])) {
                $summary = $entry->addChild('summary');
                $summary->addAttribute('type', 'text');
                $this->addCData($summary, $link['summary']);
            }
            $this->addLink($entry, $link['href'], $link['rel'], $link['type']);
        }

        return (string) $atom->asXML();
    }

    /**
     * @param  iterable<int, Entry>  $entries
     */
    public function acquisitionFeed(string $id, iterable $entries, string $selfHref): string
    {
        $atom = $this->baseFeed($id);
        $this->addLink($atom, $selfHref, 'self', 'application/atom+xml;profile=opds-catalog;kind=acquisition');

        foreach ($entries as $entry) {
            $entryEl = $atom->addChild('entry');
            $entryEl->addChild('title', $this->escape($entry->title !== '' ? $entry->title : '(untitled)'));
            $entryEl->addChild('id', $this->escape($entry->id));

            $updated = $entry->published ?? Carbon::now();
            $entryEl->addChild('updated', $updated->toAtomString());
            if ($entry->published) {
                $entryEl->addChild('published', $entry->published->toAtomString());
            }

            if ($entry->author) {
                $authorEl = $entryEl->addChild('author');
                $authorEl->addChild('name', $this->escape($entry->author));
            }

            if ($entry->summary) {
                $summary = $entryEl->addChild('summary');
                $summary->addAttribute('type', 'text');
                $this->addCData($summary, $entry->summary);
            }

            if ($entry->url) {
                $this->addLink($entryEl, $entry->url, 'alternate', 'text/html');
            }

            $downloadHref = Uri::of('/opds/download/'.rawurlencode($entry->id))->__toString();
            $this->addLink(
                $entryEl,
                $downloadHref,
                'http://opds-spec.org/acquisition',
                'text/html',
            );
        }

        return (string) $atom->asXML();
    }

    public function detailEntry(Entry $entry, ArticleContent $content, string $selfHref): string
    {
        $atom = $this->baseFeed($this->title.' · '.$entry->title);
        $this->addLink($atom, $selfHref, 'self', 'application/atom+xml;type=entry;profile=opds-catalog');

        $entryEl = $atom->addChild('entry');
        $entryEl->addChild('title', $this->escape($entry->title !== '' ? $entry->title : '(untitled)'));
        $entryEl->addChild('id', $this->escape($entry->id));

        $updated = $entry->published ?? Carbon::now();
        $entryEl->addChild('updated', $updated->toAtomString());
        if ($entry->published) {
            $entryEl->addChild('published', $entry->published->toAtomString());
        }

        if ($entry->author) {
            $author = $entryEl->addChild('author');
            $author->addChild('name', $this->escape($entry->author));
        }

        if ($entry->summary) {
            $summary = $entryEl->addChild('summary');
            $summary->addAttribute('type', 'text');
            $this->addCData($summary, $entry->summary);
        }

        if ($entry->url) {
            $this->addLink($entryEl, $entry->url, 'alternate', 'text/html');
        }

        $this->addLink(
            $entryEl,
            Uri::of('/opds/download/'.rawurlencode($entry->id))->__toString(),
            'http://opds-spec.org/acquisition',
            'text/html',
        );

        if ($content->html !== '') {
            $contentEl = $entryEl->addChild('content');
            $contentEl->addAttribute('type', 'xhtml');
            $this->addCData($contentEl, $content->html);
        }

        return (string) ($atom->asXML() ?: '');
    }

    private function baseFeed(string $id): \SimpleXMLElement
    {
        $atom = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<feed xmlns="'.self::NS_ATOM.'" xmlns:opds="'.self::NS_OPDS.'" xmlns:dc="'.self::NS_DC.'"></feed>',
        );

        $atom->addChild('title', $this->escape($this->title));
        $atom->addChild('id', $this->escape($id));
        $atom->addChild('updated', Carbon::now()->toAtomString());

        $author = $atom->addChild('author');
        $author->addChild('name', $this->escape($this->author));
        $uri = (string) $this->authorUri;
        if ($uri !== '') {
            $author->addChild('uri', $this->escape($uri));
        }

        return $atom;
    }

    private function addLink(\SimpleXMLElement $parent, string $href, string $rel, string $type): void
    {
        $link = $parent->addChild('link');
        $link->addAttribute('href', $this->escape($href));
        $link->addAttribute('rel', $rel);
        $link->addAttribute('type', $type);
    }

    private function addCData(\SimpleXMLElement $parent, string $text): void
    {
        $node = dom_import_simplexml($parent);
        $owner = $node->ownerDocument;
        if ($owner === null) {
            $parent[0] = $text;

            return;
        }

        $cdata = $owner->createCDATASection($text);
        $node->appendChild($cdata);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
