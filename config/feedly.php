<?php

return [

    'base_url' => 'https://cloud.feedly.com/v3',

    'developer_token' => env('FEEDLY_DEVELOPER_TOKEN'),

    'refresh_token' => env('FEEDLY_REFRESH_TOKEN'),

    'client_id' => env('FEEDLY_CLIENT_ID'),

    'client_secret' => env('FEEDLY_CLIENT_SECRET'),

    'user_id' => env('FEEDLY_USER_ID'),

    'saved_tag' => env('FEEDLY_SAVED_TAG', 'saved'),

    /*
     * Vollständige Stream-ID für den Read-Later-Feed.
     * Schema: user/<uuid>/tag/global.<tag>
     */
    'saved_stream_id' => env('FEEDLY_SAVED_STREAM_ID', null),

    'cache' => [
        'feed_ttl' => (int) env('CACHE_FEED_TTL', 300),
    ],

];
