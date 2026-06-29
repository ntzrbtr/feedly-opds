<?php

return [

    'base_url' => env('INSTAPARSER_BASE_URL', 'https://www.instaparser.com/api'),

    'api_key' => env('INSTAPARSER_API_KEY'),

    'cache' => [
        'article_ttl' => (int) env('CACHE_ARTICLE_TTL', 86400),
    ],

];
