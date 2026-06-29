<?php

declare(strict_types=1);

namespace App\Services\Instapaper;

final readonly class ArticleContent
{
    public function __construct(
        public ?string $title,
        public ?string $author,
        public ?string $url,
        public string $html,
    ) {}

    public static function empty(): self
    {
        return new self(
            title: null,
            author: null,
            url: null,
            html: '',
        );
    }
}
