<?php

return [
    'catalog_ttl' => (int) env('MUSIC_CATALOG_TTL', 21600),
    'search_ttl' => (int) env('MUSIC_SEARCH_TTL', 3600),
    'metadata_ttl' => (int) env('MUSIC_METADATA_TTL', 604800),
    'lock_seconds' => (int) env('MUSIC_LOCK_SECONDS', 300),
    'stuck_after_seconds' => (int) env('MUSIC_STUCK_AFTER_SECONDS', 900),
    'request_budget_seconds' => (int) env('MUSIC_REQUEST_BUDGET_SECONDS', 60),
    'youtube_music' => [
        'base_url' => 'https://music.youtube.com/youtubei/v1',
        'hl' => env('YOUTUBE_MUSIC_HL', 'en'),
        'gl' => env('YOUTUBE_MUSIC_GL', 'US'),
        'timeout' => (int) env('YOUTUBE_MUSIC_TIMEOUT', 10),
        'connect_timeout' => (int) env('YOUTUBE_MUSIC_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('YOUTUBE_MUSIC_RETRIES', 2),
        'retry_delay_ms' => (int) env('YOUTUBE_MUSIC_RETRY_DELAY_MS', 250),
        'max_retry_after_seconds' => (int) env('YOUTUBE_MUSIC_MAX_RETRY_AFTER_SECONDS', 5),
        'max_pages' => (int) env('YOUTUBE_MUSIC_MAX_PAGES', 100),
        'max_duration_seconds' => (int) env('YOUTUBE_MUSIC_MAX_DURATION_SECONDS', 60),
    ],
];
