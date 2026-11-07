# VOWSPACE — Tenant Integrity Report

## 1. Model

`events` is the tenancy anchor. Every event-scoped child carries `event_id`; the database must
reject a child whose parent belongs to another event. Application scoping alone is not treated as
authorization.

## 2. Constraints verified / added

Migration `2026_11_06_000200_enforce_tenant_integrity.php` (pre-existing, verified):

| Child | Parent | Key |
| --- | --- | --- |
| `guests` | `guest_categories` | `(event_id, guest_category_id)` |
| `guests` | `guest_groups` | `(event_id, guest_group_id)` |
| `rsvps`, `checkins`, `guest_tokens`, `invitations`, `guestbook_messages`, `song_requests` | `guests` | `(event_id, guest_id)` |
| `table_assignments` | `guests`, `seating_tables` | `(event_id, guest_id)`, `(event_id, seating_table_id)` |
| `media_assets` | `media_albums`, `guests` | `(event_id, media_album_id)`, `(event_id, guest_id)` |
| `poll_responses` | `poll_options` | `(poll_id, poll_option_id)` |

Migration `2026_11_06_000500_album_cover_tenant_integrity.php` (added this cycle):

- Preflight counts covers whose asset belongs to another event, clears them and logs the count.
- Adds `UNIQUE (event_id, id)` on `media_assets` and
  `FOREIGN KEY (event_id, cover_media_asset_id) → media_assets (event_id, id)`.
- The redundant single-column cover key is dropped where the driver supports it (not SQLite).
- Service-level ownership checks remain in `MediaService::attachToAlbum` and delete paths.

Preflight behaviour of the tenant migration: mismatched `event_id` values are aligned from the
authoritative parent, and any row that cannot be reconciled aborts the migration instead of being
silently deleted.

## 3. Verified application-level controls

- `ResolvesOwnerEvent` resolves the event from the owner's own relation and re-resolves on each
  request; a stale session id falls back to an owned event, never to an arbitrary event.
- `Admin\EventController::syncOwners` supports an empty array (removes all owners), rejects
  non-Owner roles and now also rejects inactive accounts.
- Guest endpoints resolve guest and event from the token, not from user input.
- Media album attachment rejects cross-event assets at the service layer in addition to the
  request rule.

## 4. Test coverage

- `ProductionHardeningTest::test_album_cover_must_belong_to_the_same_event` proves the database
  rejects a cross-event cover.
- `ProductionHardeningTest::test_album_deletion_detaches_media_without_losing_photos` proves album
  deletion preserves photos and clears membership.
- `MasterSecurityRemediationTest::test_sync_owners_removes_all_owners_when_empty` and
  `..._rejects_non_owner_user` cover owner synchronisation.
- Existing suites cover cross-event tokens, polls and games.

## 5. Remaining gap — PARTIAL

`polls`, `games`, `game_questions`, `game_options` and `game_attempts` are event-scoped through
`event_id`/parent chains but do not all have composite `(event_id, …)` foreign keys. Current
services validate ownership (`PollService`, `GameService`) and tests cover cross-event access, so
this is recorded as **PARTIAL — service-enforced, database constraint not added** rather than
claimed as complete. Adding composite keys there requires a preflight on existing data and a
separate migration; it is deferred to the next schema change, not silently assumed.

## 6. Engine limitation

The automated suite runs on SQLite in-memory. SQLite verifies constraint definitions and
single-connection behaviour, but it does **not** prove MySQL concurrency, row-lock or isolation
behaviour. MySQL at `127.0.0.1:3307` was not running in this environment, so
`php artisan migrate:status` could not execute. Production-engine verification is BLOCKED pending a
reachable MySQL instance.
