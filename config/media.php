<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Storage
    |--------------------------------------------------------------------------
    |
    | Approved media is published to a public disk so guests can view it.
    | Pending guest uploads are staged on a private disk and only become
    | publicly readable after an authorised moderator approves them.
    |
    */

    'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),

    'staging_disk' => env('MEDIA_STAGING_DISK', 'local'),

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
];
