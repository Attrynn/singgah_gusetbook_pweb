# Milestone 2 Migration Guide

**Milestone 2 — Identity, Membership & Permission Foundation**
Status: Implemented — 2026-11-09

---

## 1. Summary

Milestone 2 adds platform identities, workspace memberships, event staff/owner memberships,
and workspace invitations. It ships as **five additive migrations** plus one **data-migration
command**.

**Everything is additive and non-destructive.** No Milestone 2 migration drops a table or
deletes data. In particular, the legacy `event_user` pivot is **retained** and is not
modified — the new `event_owner_memberships` table is populated *from* it without touching it.

---

## 2. The five migrations

All filenames are under `database/migrations/` with the `2026_11_09_` prefix.

### 2.1 `2026_11_09_000100_add_platform_role_to_users_table.php`

Adds an explicit platform authority column, separate from the customer `role` enum.

| Change | Detail |
| --- | --- |
| Add column | `users.platform_role` `string`, **nullable**, indexed, `after('role')` |
| `down()` | Drops the index, then the column |

`NULL` means "no platform authority". Values: `superadmin`, `admin`. Public registration
never sets this column (see [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md)).

### 2.2 `2026_11_09_000200_expand_organization_memberships.php`

Widens the existing Milestone 1 memberships table and adds traceability columns.

| Change | Detail |
| --- | --- |
| Alter `role` | `enum('admin','member')` → `string` (default `member`); now also allows `owner`. SQLite does not enforce ENUM, so validation is at the app level |
| Alter `status` | `enum('active','inactive')` → `string` (default `active`); new lifecycle `active` \| `suspended` \| `revoked` |
| Add `invited_by` | `foreignId` nullable, `after('status')`, FK → `users`, `nullOnDelete` |
| Add `accepted_at` | `timestamp` nullable, `after('invited_by')` |
| `down()` | Drops FK + `invited_by` + `accepted_at`; re-narrows `role` to `enum('admin','member')` and `status` to `enum('active','inactive')` |

The Milestone 1 default value `inactive` has no semantic weight and is treated as equivalent
to `suspended` going forward.

### 2.3 `2026_11_09_000300_create_event_staff_memberships_table.php`

New table for event-scoped operational roles (multi-role per user per event).

| Column | Type / constraint |
| --- | --- |
| `id` | bigint PK |
| `event_id` | FK → `events`, `cascadeOnDelete` |
| `user_id` | FK → `users`, `cascadeOnDelete` |
| `role` | string (`event_manager`, `guest_manager`, `checkin_supervisor`, `checkin_staff`, `seating_usher`, `media_curator`, `gift_steward`) |
| `status` | string, default `active`, indexed (`active` \| `revoked`) |
| `assigned_by` | FK nullable → `users`, `nullOnDelete` |
| `revoked_at` | timestamp nullable |
| `created_at`, `updated_at` | timestamps |

Keys: **unique** `(event_id, user_id, role)`; indexes on `user_id` and `event_id`.
`down()` drops the table.

### 2.4 `2026_11_09_000400_create_event_owner_memberships_table.php`

New canonical table for the groom/bride/partner relationship (replaces the *reading* of the
legacy pivot, which stays in place).

| Column | Type / constraint |
| --- | --- |
| `id` | bigint PK |
| `event_id` | FK → `events`, `cascadeOnDelete` |
| `user_id` | FK → `users`, `cascadeOnDelete` |
| `role_label` | string (`groom` \| `bride` \| `partner`) |
| `status` | string, default `active`, indexed (`active` \| `revoked`) |
| `invited_by` | FK nullable → `users`, `nullOnDelete` |
| `accepted_at` | timestamp nullable |
| `revoked_at` | timestamp nullable |
| `created_at`, `updated_at` | timestamps |

Keys: **unique** `(event_id, user_id)` (one couple role per user per event); indexes on
`user_id` and `event_id`. `down()` drops the table.

### 2.5 `2026_11_09_000500_create_workspace_invitations_table.php`

New table for staff and couple invitations.

| Column | Type / constraint |
| --- | --- |
| `id` | bigint PK |
| `organization_id` | FK → `organizations`, `cascadeOnDelete` |
| `invited_by` | FK → `users`, `cascadeOnDelete` |
| `email` | string (recipient; not necessarily a registered user) |
| `type` | string (`staff` \| `couple`) |
| `organization_role` | string nullable (null for couple invitations) |
| `event_assignments` | json nullable (`[{event_id, role}]` for staff; `[{event_id, role_label}]` for couple) |
| `token_hash` | string, **unique** (SHA-256 of the raw token) |
| `status` | string, default `pending`, indexed (`pending` \| `accepted` \| `expired` \| `revoked`) |
| `expires_at` | timestamp |
| `accepted_at`, `revoked_at` | timestamps nullable |
| `accepted_by` | FK nullable → `users`, `nullOnDelete` |
| `created_at`, `updated_at` | timestamps |

Keys: indexes on `(email, status)` and `(organization_id, status)`. `down()` drops the table.

---

## 3. Data migration: `event_user` → `event_owner_memberships`

Command:

```bash
php artisan vowspace:migrate-event-owner-memberships
```

Options:

| Option | Effect |
| --- | --- |
| `--dry-run` | Reports what **would** be migrated; writes nothing |
| `--force` | Re-processes rows even if a matching `event_owner_memberships` row already exists |

Behaviour:

- Reads all `event_user` rows via the raw query builder to avoid Eloquent pivot
  observers/events.
- Maps each `role_label` through `EventUserRole` → `EventOwnerRole`
  (`groom` → `groom`, `bride` → `bride`, `partner` → `partner`).
- Skips rows whose `(event_id, user_id)` already has an owner-membership row (idempotency)
  unless `--force`.
- Uses `updateOrInsert` on `(event_id, user_id)` inside a transaction per row, copying
  `created_at` / `updated_at` and setting `status = active`, `invited_by = null`.
- Unrecognised `role_label` values are **not** migrated; they are reported as exceptions and
  the command exits non-zero only if *every* row failed and none migrated.
- Prints counts: migrated, skipped (already exists), errors.

**Idempotent:** a second run reports all rows as skipped and changes nothing.

---

## 4. Preservation guarantees

The migration is proven not to be lossy by `tests/Feature/M2/LegacyMigrationTest.php`:

- **`event_user` is retained.** Row count is unchanged before/after; every legacy row remains
  queryable by `(event_id, user_id)`.
- **Identifiers are unchanged.** The set of `event_id`s and `user_id`s in
  `event_owner_memberships` matches the source `event_user` rows exactly.
- **Roles are preserved** (`groom`/`bride`/`partner`) with `status = active`.
- **Access is preserved.** Migrated users still satisfy `PermissionService::isEventOwner()`
  and `User::canAccessEvent()`.
- **No workspaces are mass-created.** `organization_memberships` count is unchanged by the
  command (from the pre-migration baseline) — the data migration never fabricates org owners.
- **Bad rows do not corrupt good rows.** An unrecognised `role_label` is skipped and its
  legacy row is left intact.
- **`--dry-run` writes nothing** (counts on both tables unchanged).

---

## 5. Deployment order

```bash
# 1. Back up the database (and storage/app/public) before any schema change.
mysqldump --single-transaction --routines --triggers vowspace | gzip > backup.sql.gz

# 2. Apply the five additive migrations.
php artisan migrate --force

# 3. (If not already done for Milestone 1) assign legacy events to the Legacy Workspace.
php artisan vowspace:backfill-legacy-workspace --dry-run
php artisan vowspace:backfill-legacy-workspace

# 4. Migrate legacy couple ownership into event_owner_memberships.
php artisan vowspace:migrate-event-owner-memberships --dry-run
php artisan vowspace:migrate-event-owner-memberships

# 5. Expire stale invitations (also runs daily via the scheduler).
php artisan vowspace:expire-invitations
```

Notes:

- Steps 3 and 4 are independent and idempotent; run them in either order. Both support
  `--dry-run` for a safe preview.
- No code path depends on step 4 completing before the app can boot — `canAccessEvent()` falls
  back to the `event_user` pivot for events without an organization, and accepts either
  membership. The migration is a forward-compatibility enrichment, not a prerequisite.
- Optionally re-warm caches after deploy:
  `php artisan config:cache && php artisan route:cache && php artisan view:cache`.

---

## 6. Rollback considerations

Rollback is **lossy for Milestone 2 data**, so treat it as a last resort and back up first.

| Rolled-back migration | Consequence |
| --- | --- |
| `create_workspace_invitations_table` | Drops all staff/couple invitations (pending + history) |
| `create_event_owner_memberships_table` | Drops the couple-membership rows. **Legacy `event_user` rows survive**, so couple access via the pivot is intact |
| `create_event_staff_memberships_table` | Drops all event-staff assignments |
| `expand_organization_memberships` | Drops `invited_by` + `accepted_at` and re-narrows `role`/`status`. On MySQL, re-narrowing to `enum('admin','member')` / `enum('active','inactive')` **cannot represent** `owner` or `suspended`/`revoked` — rows holding those values must be fixed first or the DDL fails |
| `add_platform_role_to_users_table` | Drops the column and **loses** all `superadmin`/`admin` platform assignments |

Guidance:

- `php artisan migrate:rollback` drops these tables. Take a database backup before rolling
  back, as with any DDL change (see [DEPLOY.md](DEPLOY.md) §9).
- Before rolling back `expand_organization_memberships`, back up membership rows:
  ```sql
  SELECT organization_id, user_id, role, status FROM organization_memberships;
  ```
- Before rolling back `add_platform_role_to_users_table`, back up platform roles:
  ```sql
  SELECT id, email, platform_role FROM users WHERE platform_role IS NOT NULL;
  ```
- Reverting application code after a rollback requires clearing cached config/routes:
  `php artisan optimize:clear`.

---

## 7. What is intentionally unchanged

- **`event_user` is not dropped.** It remains the pivot for `User::events()` /
  `ResolvesOwnerEvent`, and the data migration copies from it rather than moving it.
- **`events.organization_id` stays nullable.** Existing docs describe the future `NOT NULL`
  step in [TENANCY.md](TENANCY.md) §9.
- **Legacy `users.role` stays.** `platform_role` is added alongside it, not replacing it.

See [DATABASE.md](DATABASE.md) for the wider table dictionary and
[ERD.md](ERD.md) for the baseline relationship map.
