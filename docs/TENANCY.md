# Organization Tenancy Architecture

**Milestone 1 — Organization & Multi-Tenant Foundation**
Status: Implemented — 2026-11-08

---

## 1. Purpose

Establishes a data-ownership boundary above events, enabling future workspace
isolation without disrupting existing authentication, event ownership, or guest
access mechanisms.

---

## 2. Entity Model

```
Organization (1) ──< (N) Events ──< (N) Event-owned data
                                        (guests, rsvp, checkins, guestbook,
                                         media, albums, seating, gifts,
                                         polls, games, songs, audits)

Organization (1) ──< (N) OrganizationMemberships ──> (N) Users
```

### Separation of concerns

| Relationship | Table | Purpose |
|---|---|---|
| User global identity | `users` | Account, role, auth |
| User <-> Event (couple) | `event_user` | groom/bride/partner role on a specific event |
| User <-> Organization | `organization_memberships` | Foundation for workspace access |
| Organization <-> Event | `events.organization_id` | Tenant ownership of the event and all its data |

These three relationships are **distinct and must not be conflated**.

---

## 3. Organization

Model: `App\Models\Organization`  Table: `organizations`

- `id` bigint PK
- `name` varchar(255)
- `slug` varchar(255) UNIQUE
- `status` enum('active','archived') DEFAULT 'active'
- `created_at`, `updated_at`, `deleted_at`

---

## 4. OrganizationMembership

Model: `App\Models\OrganizationMembership`  Table: `organization_memberships`

- `organization_id` FK -> organizations (CASCADE DELETE)
- `user_id` FK -> users (CASCADE DELETE)
- `role` enum('admin','member') DEFAULT 'member'
- `status` enum('active','inactive') DEFAULT 'active'
- UNIQUE(organization_id, user_id)

**Memberships are intentionally empty for Legacy Workspace.** The `event_user`
pivot (groom/bride/partner) is event-scoped ownership and must not be
auto-converted to organization memberships.

---

## 5. Event -> Organization

Column: `events.organization_id BIGINT UNSIGNED NULL`
FK: `events_organization_id_foreign` -> `organizations.id` ON DELETE SET NULL
INDEX: `events_organization_id_index`

Column is nullable to allow safe multi-step migration (backfill first, add NOT
NULL constraint in a future migration after full validation).

---

## 6. Legacy Workspace

All events that existed before organization support are assigned to:

- **Name:** Legacy Workspace
- **Slug:** legacy-workspace
- **Status:** active

One organization for all legacy events — not one per couple or event owner.

Backfill command: `php artisan vowspace:backfill-legacy-workspace`
- Supports `--dry-run` for safe preview without data modification.
- Idempotent: safe to re-run; will not create duplicate organizations or
  re-assign already-assigned events.

---

## 7. How Tenant Scope Is Applied

### New events
`EventService::create()` calls `resolveLegacyOrganizationId()` which uses
`Organization::firstOrCreate()` with the Legacy Workspace slug. Every new event
always receives an `organization_id`.

### User::canAccessEvent(Event|int)
1. Superadmin -> always allowed (global platform access).
2. Event has `organization_id` -> user must be an active org member via
   `organization_memberships`.
3. Event has no `organization_id` (not yet backfilled) -> fallback to
   `event_user` pivot check.

### Owner routes
`ResolvesOwnerEvent::ownerEvent()` gates on `$user->events()` (event_user
pivot). Organization membership and event-user ownership are two separate
layers — both must apply.

### Superadmin routes
All admin routes are behind `role:superadmin`. Superadmin bypasses both
organization membership and event-user checks, preserving existing
platform-administrative access.

---

## 8. What Is NOT Enforced Yet

- No middleware injects an organization context automatically.
- Owner routes still route via `event_user` pivot, not organization membership.
- `User::eventIds()` still returns events from the `event_user` pivot.
- No UI for workspace creation, membership management, or workspace switching.

Deferred to the next milestone (identity and permission system).

---

## 9. Migration Guide

### First deployment with organization support

```bash
# 1. Backup the database before running migrations.
# 2. Run the new migrations (organizations, organization_memberships, events column):
php artisan migrate

# 3. Dry-run the backfill to verify the events that will be assigned:
php artisan vowspace:backfill-legacy-workspace --dry-run

# 4. Apply the backfill:
php artisan vowspace:backfill-legacy-workspace

# 5. Verify: no events without organization_id should remain:
#    SELECT COUNT(*) FROM events WHERE organization_id IS NULL;
```

### Applying NOT NULL constraint (future step)

After verifying that all events have an `organization_id` in all environments:

```bash
# Create a new migration:
php artisan make:migration enforce_organization_id_not_null_on_events --table=events

# In the migration:
# $table->foreignId('organization_id')->nullable(false)->change();
```

Only apply when:
- All events have been backfilled.
- No code path creates an event without `organization_id`.
- The change has been validated in a staging environment.

### Rollback risk

Rolling back the `add_organization_id_to_events_table` migration after events
have been assigned to Legacy Workspace will drop the `organization_id` column
and lose the tenant assignment data. If rollback is needed, back up the
`organization_id` values before rolling back:

```sql
SELECT id, organization_id FROM events WHERE organization_id IS NOT NULL;
```
