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
        'invitation_ttl_days' => (int) env('INVITATION_TTL_DAYS', 365),
        /*
        | QR passes are temporary. The effective expiry is the earlier of the
        | rollover TTL below and the event end time plus the grace period.
        */
        'qr_ttl_days' => (int) env('QR_TOKEN_TTL_DAYS', 30),
        'qr_grace_days' => (int) env('QR_TOKEN_GRACE_DAYS', 3),
        'email_change_ttl_minutes' => (int) env('EMAIL_CHANGE_TTL_MINUTES', 60),
    ],

    'organization' => [
        'legacy_workspace_slug' => 'legacy-workspace',
        'legacy_workspace_name' => 'Legacy Workspace',
    ],

    'legacy_workspace_id' => (int) env('LEGACY_WORKSPACE_ID', 0),

    'pagination' => [
        'per_page' => 15,
        'guest_per_page' => 20,
    ],

    'guest_import_limit' => (int) env('GUEST_IMPORT_LIMIT', 1000),

    'retention' => [
        'trashed_days' => (int) env('TRASHED_RETENTION_DAYS', 30),
        /*
        | Audit rows are kept for accountability, but the network identifiers
        | attached to them are minimised after this window. Set to 0 to keep
        | identifiers indefinitely (not recommended).
        */
        'audit_log_ip_days' => (int) env('AUDIT_LOG_IP_RETENTION_DAYS', 90),
    ],

    'rate_limits' => [
        'guest_actions_per_minute' => 12,
        'guestbook_per_minute' => 4,
        'uploads_per_minute' => 6,
        'poll_per_minute' => 6,
    ],
];
