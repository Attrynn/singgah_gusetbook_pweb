<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Storage
    |--------------------------------------------------------------------------
    |
    | Guest-uploaded media is private by default. Originals, thumbnails and
    | medium renditions all live on the private disk and are only delivered
    | through an authorised, signed endpoint. Approval changes the access
    | policy, never the physical visibility of the object.
    |
    | The public disk is reserved for staff-owned artefacts such as event
    | covers, avatars and QRIS images.
    |
    */

    'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),

    'private_disk' => env('MEDIA_PRIVATE_DISK', env('MEDIA_STAGING_DISK', 'local')),

    /*
    | Kept for backwards compatibility with the original staging configuration.
    */
    'staging_disk' => env('MEDIA_STAGING_DISK', 'local'),

    /*
    | Lifetime of the signed delivery URL handed to the browser. Short-lived
    | URLs keep previously rendered markup from becoming a permanent link.
    */
    'delivery_ttl_minutes' => (int) env('MEDIA_DELIVERY_TTL_MINUTES', 60),

    /*
    | Maximum accepted upload size in kilobytes (per file).
    | Keep in sync with php.ini upload_max_filesize and the web server limit.
    */
    'max_kb' => (int) env('MEDIA_MAX_KB', 5120),

    /*
    | Maximum number of files accepted in a single batch upload.
    */
    'max_files' => (int) env('MEDIA_MAX_FILES', 10),

    /*
    | Maximum total request payload budget in kilobytes across all files.
    | 50 MB total matches max_files (10) * max_kb (5120).
    */
    'max_total_kb' => (int) env('MEDIA_MAX_TOTAL_KB', 51200),

    /*
    | Maximum decoded pixel budget for media processing (prevents memory exhaustion).
    | Default: 36 megapixels (e.g. 6000 x 6000).
    */
    'max_pixels' => (int) env('MEDIA_MAX_PIXELS', 36000000),

    /*
    | MIME types eligible for derivative generation. Everything else is
    | skipped before decoding so a spoofed or exotic file can never be
    | fed into GD.
    */
    'derivative_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
];
