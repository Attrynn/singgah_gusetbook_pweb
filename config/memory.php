<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guest contribution limits
    |--------------------------------------------------------------------------
    |
    | Per-submission file count and the lifetime cap of contributions a single
    | guest party may submit to one event. File size and MIME limits are owned
    | by config/media.php so uploads stay consistent across the application.
    |
    */

    'guest_max_files' => (int) env('MEMORY_GUEST_MAX_FILES', 5),

    'guest_max_per_event' => (int) env('MEMORY_GUEST_MAX_PER_EVENT', 50),

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    |
    | Exports run asynchronously. The archive link expires after the configured
    | number of hours, and a single export refuses to exceed the item cap so a
    | request cannot exhaust server memory or disk.
    |
    */

    'export_ttl_hours' => (int) env('MEMORY_EXPORT_TTL_HOURS', 48),

    'export_max_items' => (int) env('MEMORY_EXPORT_MAX_ITEMS', 2000),

    /*
    |--------------------------------------------------------------------------
    | Public sharing
    |--------------------------------------------------------------------------
    |
    | A value of 0 means the revocable share token never expires on its own;
    | it still stops working the moment it is revoked or sharing is disabled.
    |
    */

    'share_token_ttl_days' => (int) env('MEMORY_SHARE_TOKEN_TTL_DAYS', 0),

    'terms_version' => env('MEMORY_TERMS_VERSION', '2026-11'),

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    'gallery_page_size' => 24,

    'timeline_page_size' => 30,

    'collection_max_items' => 500,

    'upload_path' => 'events/{event}/memory',

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Requests per minute. Contribution limits are keyed per guest token and IP;
    | public limits are keyed per share token and IP.
    |
    */

    'rate_limits' => [
        'contribute' => (int) env('MEMORY_RATE_CONTRIBUTE', 10),
        'public' => (int) env('MEMORY_RATE_PUBLIC', 30),
    ],

];
