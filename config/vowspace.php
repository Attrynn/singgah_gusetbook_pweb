<?php

return [
    'brand' => [
        'name' => 'VOWSPACE',
        'tagline' => 'Beyond the Guestbook. A Complete Wedding Experience.',
        'support_email' => 'hello@vowspace.test',
    ],

    'uploads' => [
        'image_max_kb' => 5120,
        'avatar_max_kb' => 2048,
        'qris_max_kb' => 2048,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    'tokens' => [
        'invitation_ttl_days' => 365,
        'qr_ttl_days' => null,
    ],

    'pagination' => [
        'per_page' => 15,
        'guest_per_page' => 20,
    ],

    'guest_import_limit' => (int) env('GUEST_IMPORT_LIMIT', 1000),

    'retention' => [
        'trashed_days' => (int) env('TRASHED_RETENTION_DAYS', 30),
    ],

    'rate_limits' => [
        'guest_actions_per_minute' => 12,
        'guestbook_per_minute' => 4,
        'uploads_per_minute' => 6,
        'poll_per_minute' => 6,
    ],
];
