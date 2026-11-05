# Database Dictionary

32 domain tables plus the framework tables (`password_reset_tokens`, `sessions`, `cache`,
`cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`).

Conventions:

- Every table has `id`, `created_at`, `updated_at` unless noted.
- `event_id` is the tenancy key. Deleting an event cascades to its children.
- Soft deletes are used on `users`, `events`, `guests`, `guestbook_messages` and `media_assets`.

---

## Events and configuration

### `events`

| Column | Type | Notes |
| --- | --- | --- |
| `name` | string | Display name, e.g. “Raka & Alya” |
| `slug` | string unique | Public landing URL `/e/{slug}` |
| `groom_name`, `bride_name` | string | |
| `groom_profile`, `bride_profile`, `couple_description` | text null | Story content |
| `cover_image` | string null | Path on the `public` disk |
| `event_date` | date | Indexed with `status` |
| `start_time`, `end_time` | time null | |
| `venue_name` | string | Denormalised primary venue |
| `venue_address` | text | |
| `google_maps_url` | string(2048) null | |
| `dress_code` | string null | |
| `description` | text null | |
| `status` | enum | `draft`, `published`, `live`, `completed`, `archived` |
| `created_by` | FK users null | `nullOnDelete` |
| `rsvp_deadline` | datetime null | |
| `rsvp_enabled`, `guestbook_enabled`, `gallery_enabled`, `gift_enabled` | boolean | Default `true` |
| `live_wall_enabled` | boolean | Default `false` |
| `timezone` | string(64) | Default `Asia/Jakarta` |
| `published_at`, `archived_at` | timestamp null | |
| `deleted_at` | timestamp null | Soft delete |

Index: `(status, event_date)`.

### `event_user`

Pivot for owners attached to an event.

| Column | Type | Notes |
| --- | --- | --- |
| `event_id`, `user_id` | FK | Unique pair |
| `role_label` | enum | `groom`, `bride`, `partner` |

### `event_settings`

Per-event key/value overrides.

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `key` | string(100) | Unique with `event_id` |
| `value` | text null | |

### `event_venues`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `name`, `address` | string/text | |
| `google_maps_url` | string(2048) null | |
| `latitude`, `longitude` | decimal(10,7) null | |
| `is_primary` | boolean | Exactly one primary is maintained by the service |
| `sort_order` | smallint | |

### `event_schedules`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | Indexed with `start_time` |
| `title` | string | |
| `description` | text null | |
| `start_time` | datetime | Required |
| `end_time` | datetime null | |
| `status` | enum | `planned`, `ongoing`, `done` |
| `sort_order` | smallint | |

### `gift_accounts`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `bank_name`, `account_number`, `account_owner` | string | |
| `qris_path` | string null | Uploaded QRIS image |
| `gift_address` | text null | Physical gift address |
| `note` | text null | |
| `is_active` | boolean | |
| `sort_order` | smallint | |

---

## Guests and invitations

### `guest_categories`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `name` | string(80) | |
| `slug` | string(80) | Unique with `event_id` |
| `color` | string(20) | Hex colour, default `#8EB4D3` |
| `description` | string null | |

### `guest_groups`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `name` | string(100) | Unique with `event_id` |
| `description` | string null | |

### `guests`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `guest_category_id`, `guest_group_id` | FK null | `nullOnDelete` |
| `name` | string | Indexed with `event_id` |
| `email` | string null | Indexed |
| `phone` | string(30) null | Indexed |
| `notes` | text null | |
| `status` | enum | `active`, `inactive`, `blocked` |
| `max_attendees` | tinyint unsigned | Default 2 |
| `rsvp_status` | enum | `pending`, `confirmed`, `declined`, `maybe` |
| `checked_in_at` | timestamp null | Denormalised fast check |
| `created_by` | FK users null | |
| `deleted_at` | timestamp null | Soft delete |

Indexes: `(event_id, rsvp_status)`, `(event_id, status)`, `(event_id, name)`, `email`, `phone`.

### `guest_tokens`

| Column | Type | Notes |
| --- | --- | --- |
| `guest_id`, `event_id` | FK | |
| `type` | enum | `invitation`, `qr` |
| `token_hash` | char(64) unique | SHA-256 of the raw token |
| `token_ciphertext` | text | Laravel-encrypted raw token (re-display only) |
| `token_prefix` | string(16) | First 8 chars for support/debug |
| `expires_at`, `revoked_at`, `last_used_at` | timestamp null | |

Indexes: `(guest_id, type)`, `(event_id, type)`.

### `invitations`

| Column | Type | Notes |
| --- | --- | --- |
| `guest_id` | FK unique | One invitation row per guest |
| `event_id` | FK | |
| `guest_token_id` | FK guest_tokens null | Currently active invitation token |
| `status` | enum | `not_sent`, `link_opened`, `shared_manually`, `sent` |
| `template` | string(50) | Default `classic` |
| `sent_at`, `opened_at`, `last_shared_at` | timestamp null | |
| `open_count` | int unsigned | Incremented on each open |

Index: `(event_id, status)`.

---

## RSVP and check-in

### `rsvps`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id`, `guest_id` | FK | Unique pair |
| `status` | enum | `pending`, `confirmed`, `declined`, `maybe` |
| `attendance_count` | tinyint unsigned | Forced to 0 when declined |
| `companion_names` | text null | |
| `dietary_preference` | string null | |
| `notes` | text null | |
| `responded_at` | timestamp null | |
| `ip_address`, `user_agent` | string null | Submission metadata |

### `checkins`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id`, `guest_id` | FK | Unique pair — duplicate guard |
| `checked_in_by` | FK users null | Officer |
| `method` | enum | `qr`, `manual` |
| `checked_in_at` | timestamp | Indexed with `event_id` |
| `notes` | string null | |
| `ip_address` | string(45) null | |

---

## Guestbook

### `guestbook_messages`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `guest_id` | FK null | Nullable for imported messages |
| `sender_name` | string | Snapshot of the guest name |
| `message` | text | 3–1000 characters |
| `emoji` | string(16) null | |
| `visibility` | enum | `public`, `private` |
| `status` | enum | `pending`, `approved`, `rejected`, `hidden` |
| `is_pinned` | boolean | |
| `reviewed_by` | FK users null | |
| `reviewed_at` | timestamp null | |
| `ip_address` | string(45) null | |
| `deleted_at` | timestamp null | Soft delete |

Indexes: `(event_id, status, visibility)`, `(event_id, is_pinned)`.

### `guestbook_favorites`

| Column | Type | Notes |
| --- | --- | --- |
| `user_id`, `guestbook_message_id` | FK | Unique pair |

---

## Media

### `media_albums`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `name` | string | |
| `description` | text null | |
| `cover_media_asset_id` | bigint null | Not a FK to avoid a circular constraint |
| `created_by` | FK users null | |

### `media_assets`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `media_album_id` | FK null | |
| `guest_id` | FK null | Set for guest uploads |
| `uploaded_by` | FK users null | Set for admin uploads |
| `path` | string | Public disk path |
| `thumbnail_path` | string null | |
| `original_name` | string | |
| `mime_type` | string(100) | |
| `size_bytes` | bigint unsigned | |
| `caption` | string null | |
| `status` | enum | `pending`, `approved`, `rejected` |
| `is_featured` | boolean | |
| `reviewed_by`, `reviewed_at` | FK/timestamp null | |
| `deleted_at` | timestamp null | Soft delete |

Indexes: `(event_id, status)`, `(event_id, is_featured)`.

### `media_album_items`

| Column | Type | Notes |
| --- | --- | --- |
| `media_album_id`, `media_asset_id` | FK | Unique pair |
| `sort_order` | smallint | |

---

## Seating

### `seating_tables`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `name` | string(60) | Unique with `event_id` |
| `capacity` | smallint unsigned | Default 8 |
| `zone` | string(60) null | |
| `notes` | string null | |

### `table_assignments`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id`, `seating_table_id`, `guest_id` | FK | |
| `seat_number` | smallint null | Unique with `seating_table_id` |
| — | — | Unique `(event_id, guest_id)` |

---

## Interactions

### `song_requests`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id`, `guest_id` | FK | |
| `song_title` | string | |
| `artist`, `note` | string null | |
| `status` | enum | `pending`, `approved`, `played`, `rejected` |

Index: `(event_id, status)`.

### `polls`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `question` | string | |
| `description` | text null | |
| `is_active` | boolean | |
| `show_results` | boolean | |
| `closes_at` | datetime null | |
| `created_by` | FK users null | |

Index: `(event_id, is_active)`.

### `poll_options`

| Column | Type | Notes |
| --- | --- | --- |
| `poll_id` | FK | |
| `label` | string | |
| `sort_order` | smallint | |

### `poll_responses`

| Column | Type | Notes |
| --- | --- | --- |
| `poll_id`, `poll_option_id`, `guest_id` | FK | Unique `(poll_id, guest_id)` |

### `games`

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | |
| `title` | string | |
| `description` | text null | |
| `type` | enum | `quiz` |
| `is_active` | boolean | |
| `created_by` | FK users null | |

Index: `(event_id, is_active)`.

### `game_questions`

| Column | Type | Notes |
| --- | --- | --- |
| `game_id` | FK | |
| `question` | string | |
| `sort_order` | smallint | |

### `game_options`

| Column | Type | Notes |
| --- | --- | --- |
| `game_question_id` | FK | |
| `label` | string | |
| `is_correct` | boolean | |
| `sort_order` | smallint | |

### `game_attempts`

| Column | Type | Notes |
| --- | --- | --- |
| `game_id`, `guest_id` | FK | Unique pair |
| `score` | smallint unsigned | |
| `total_questions` | smallint unsigned | |
| `completed_at` | timestamp null | |

### `game_answers`

| Column | Type | Notes |
| --- | --- | --- |
| `game_attempt_id` | FK | |
| `game_question_id` | FK | Unique with attempt |
| `game_option_id` | FK null | |
| `is_correct` | boolean | |

---

## Activity and system

### `event_activities`

Lightweight event timeline shown to owners.

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | FK | Indexed with `created_at` |
| `user_id`, `guest_id` | FK null | Actor |
| `type` | string(60) | e.g. `guest.created`, `checkin` |
| `description` | string | Human-readable |
| `metadata` | json null | |

### `audit_logs`

Security/audit trail.

| Column | Type | Notes |
| --- | --- | --- |
| `user_id`, `event_id` | FK null | |
| `action` | string(60) | Indexed, e.g. `auth.login` |
| `target_type`, `target_id` | string/bigint null | Polymorphic-ish target |
| `description` | string | |
| `metadata` | json null | |
| `ip_address`, `user_agent` | string null | |

Index: `(event_id, created_at)`.

### `app_settings`

Global key/value settings edited by the superadmin.

| Column | Type | Notes |
| --- | --- | --- |
| `key` | string(100) unique | |
| `value` | text null | |

Seeded keys: `app_name`, `support_email`, `default_timezone`, `guestbook_auto_approve`.

### `notifications`

Standard Laravel notifications table (UUID primary key, morph `notifiable`). Reserved for future
queued notifications; the demo does not send any.
