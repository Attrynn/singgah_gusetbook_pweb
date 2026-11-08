# Event Workspace Architecture

**Milestone 3 — Event Workspace, Setup & Operations**
Status: Implemented — 2026-11-10

---

## 1. Purpose

Milestone 3 introduces the **workspace-managed event**: a workspace Owner or Admin
configures an event through an org-scoped surface under
`/workspace/{organization}/events...`, without touching the platform-superadmin
`/admin` area. This document describes the event portfolio, the per-event workspace
tabs, the event lifecycle, and how *setup readiness* is kept separate from
*publishing*.

This document covers the architecture. The wizard is documented in
[EVENT-SETUP-WIZARD.md](EVENT-SETUP-WIZARD.md); the playbook system in
[OPERATIONAL-PLAYBOOK.md](OPERATIONAL-PLAYBOOK.md); the team in [EVENT-TEAM.md](EVENT-TEAM.md);
the schedule split in [SCHEDULE-ARCHITECTURE.md](SCHEDULE-ARCHITECTURE.md); duplication in
[DUPLICATE-BLUEPRINT.md](DUPLICATE-BLUEPRINT.md); and the schema changes in
[MILESTONE-3-MIGRATION.md](MILESTONE-3-MIGRATION.md).

---

## 2. Organization context and routing

Every M3 workspace event route is registered in `routes/workspace-events.php` (required
from `routes/web.php`) and is bound to a route-model organization:

```php
Route::prefix('workspace/{organization}')->name('workspace.')
    ->middleware(['auth', 'auth.session', 'active', 'org.context', 'org.member'])
    ->group(function (): void { /* ... */ });
```

| Middleware | Effect |
| --- | --- |
| `auth`, `auth.session` | Authenticated session required |
| `active` | Inactive users are logged out (`EnsureActiveUser`) |
| `org.context` | Shares the route-bound `organization` with views |
| `org.member` | Requires an **active** membership in the organization (superadmin/platform-admin bypass; otherwise 403) |

**Tenant binding is enforced in the controller, not only in routing.** Every event
handler calls a private `assertEventInOrg()` that aborts with **404** when the route
event does not belong to the route organization:

```php
abort_unless((int) $event->organization_id === (int) $organization->id, 404);
```

The same rule applies to team memberships, schedules, run-of-show items, playbook
versions, and duplicates. An event in another workspace is therefore indistinguishable
from a missing one (no cross-tenant disclosure).

### 2.1 Workspace routes vs superadmin routes

| Surface | Prefix | Names | Gate | Controller |
| --- | --- | --- | --- | --- |
| Workspace owner/admin | `/workspace/{organization}/events` | `workspace.events.*` | `auth` + active org membership + policies | `App\Http\Controllers\Workspace\*` |
| Platform superadmin | `/admin/events` | `admin.events.*` | `role:superadmin` middleware | `App\Http\Controllers\Admin\EventController` |

`/admin/events` is the platform-superadmin surface and is **not** part of M3: it remains
guarded by the legacy `role:superadmin` middleware (`routes/admin.php`). M3 adds the
workspace surface alongside it and does not remove or reroute the admin surface.

---

## 3. Event portfolio

`GET /workspace/{organization}/events` → `workspace.events.index`
(`WorkspaceEventController::index`). Authorized by `EventPolicy::viewAny`.

### 3.1 Listing, search and filter

| Capability | Implementation |
| --- | --- |
| Search (`search`) | `LIKE` across `name`, `groom_name`, `bride_name`, `venue_name` |
| Status filter (`status`) | Exact match, only when the value is a valid `EventStatus` case |
| Date range (`date_from` / `date_to`) | `whereDate` on `event_date` |
| Sort (`sort`) | One of `event_date`, `name`, `status`, `created_at` (invalid falls back to `event_date`) |
| Direction (`direction`) | `asc` or `desc` (default `desc`) |
| Pagination | `paginate(12)->withQueryString()` |

Eager-loaded relations: `organization`, `primaryVenue`, `setupConfig.primaryPic.user`.

### 3.2 Grouping

The current page of results is grouped **client-side by lifecycle stage** in
`resources/views/workspace/events/index.blade.php`. Groups render only when non-empty:

| Status value | Group label | Hint |
| --- | --- | --- |
| `draft` | Draft | Belum dipublikasikan |
| `published` | Akan datang | Sudah dipublikasikan |
| `live` | Berlangsung | Sedang berjalan |
| `completed` | Selesai | Sudah selesai |
| `archived` | Diarsipkan | Disimpan sebagai arsip |

Each event card shows the event name, couple names, the stage label badge, the date, the
primary venue, the primary PIC (or "Manajer acara belum ditetapkan"), and the setup
readiness badge.

### 3.3 Summary

`summary()` computes workspace-wide counts (independent of the active filter):

| Key | Meaning |
| --- | --- |
| `total` | All events in the organization |
| `draft` | `status = draft` |
| `upcoming` | `status = published` |
| `in_progress` | `status = live` |
| `completed` | `status = completed` |
| `archived` | `status = archived` |
| `setup_incomplete` | Events with no `setupConfig` whose `setup_status` is `ready` |
| `no_manager` | Events with no `primary_pic_staff_membership_id` on their setup config |

These are rendered as stat cards at the top of the portfolio. A legacy event with no
setup config shows the readiness badge **"Belum dikonfigurasi"** rather than an error.

---

## 4. Event workspace tabs

`resources/views/workspace/events/partials/event-tabs.blade.php` renders the shared
navigation strip for a single event. **Each tab is rendered only when the acting user
passes the matching `EventPolicy` ability**, so a staff member never sees a link they
cannot open.

| Tab | Route name | Shown when |
| --- | --- | --- |
| Ringkasan | `workspace.events.show` | always (event view) |
| Persiapan | `workspace.events.setup` | `manageSetup` |
| Tim | `workspace.events.team.index` | `manageTeam` |
| Jadwal publik | `workspace.events.schedule.index` | `manageSchedule` |
| Run of show | `workspace.events.run-of-show.index` | `viewRunOfShow` |
| Playbook | `workspace.playbooks.show` / `.index` | `managePlaybooks` |

The **overview** page (`workspace.events.show`) additionally surfaces: event identity, the
setup readiness card (percent + completed/total), the primary PIC, up to five team members,
a public-schedule count, and a run-of-show summary
(`total`/`pending`/`in_progress`/`completed`). Lifecycle action buttons are rendered only
for transitions the user is allowed to perform.

---

## 5. Lifecycle

The canonical lifecycle values are unchanged from earlier milestones. `EventStatus`
(`app/Enums/EventStatus.php`) has five cases; the UI maps them to human stage labels via
`stageLabel()`:

| Enum case | Stored value | UI label (`stageLabel()`) | Public? |
| --- | --- | --- | --- |
| `Draft` | `draft` | Draft | no |
| `Published` | `published` | **Upcoming** (Akan datang) | yes |
| `Live` | `live` | **In Progress** (Berlangsung) | yes |
| `Completed` | `completed` | Completed (Selesai) | yes |
| `Archived` | `archived` | Archived (Diarsipkan) | no |

`EventStatus::label()` returns the raw enum word (e.g. "Published"); the workspace UI uses
`stageLabel()` so the friendlier wording appears consistently. No enum rename was performed.

### 5.1 Centralized transitions

`App\Services\EventLifecycleService` is the **single source of truth** for status changes.
The **free-form `status` field was removed from the create/edit forms**: creation always
defaults to **Draft** (`StoreWorkspaceEventRequest` has no `status` rule;
`WorkspaceEventController::update()` explicitly `unset()`s it). Status changes only happen
through the transition service.

Allowed transitions (`EventLifecycleService::$transitions`):

| From | Allowed to |
| --- | --- |
| Draft | Published, Archived |
| Published | Draft, Live, Archived |
| Live | Completed, Archived |
| Completed | Archived |
| Archived | Published |

`transition()` throws a `ValidationException` for an illegal target, and on success writes
the payload inside a transaction and records a **critical** audit row
(`event.status_changed`) with `from`/`to`.

### 5.2 Delivery paths

| Action | Route | Authorization |
| --- | --- | --- |
| Set status | `POST workspace.events.status` | `publish` when target is Published, `archive` when Archived, else `update` |
| Archive | `POST workspace.events.archive` | `archive` |
| Unarchive | `POST workspace.events.unarchive` | `unarchive` (targets Published) |

The status endpoint validates `status` against the `EventStatus` enum, then authorizes the
**specific target** before calling the lifecycle service. Archive/unarchive are dedicated
endpoints that share the same service.

---

## 6. Setup readiness vs lifecycle

Readiness and publishing are deliberately **separate concerns**:

- **`SetupStatus`** (`not_started` | `in_progress` | `ready_for_review` | `ready`) lives on
  `event_setup_configs.setup_status` and is owned by `EventSetupService`.
- **`EventStatus`** (`draft` | … | `archived`) lives on `events.status` and is owned by
  `EventLifecycleService`.

`EventSetupService::finalize()` **never touches `events.status`**. Finalizing an event whose
required checklist items are complete sets `setup_status = ready`; if they are incomplete it
stays `in_progress`. Publishing is a separate, explicit lifecycle action from the overview
page. (See [EVENT-SETUP-WIZARD.md](EVENT-SETUP-WIZARD.md) §6 for the finalize contract.)

---

## 7. Access rules

Event actions are authorized by `App\Policies\EventPolicy`, which delegates to
`PermissionService::hasEventPermission()` and grants a full bypass to legacy
`UserRole::Superadmin` and `PlatformRole::Superadmin` (`allowsPermission()`).

| `EventPolicy` ability | Permission identifier | Notes |
| --- | --- | --- |
| `viewAny` | `organization.manage_events` (via `hasOrgPermission`) | superadmin bypass |
| `create` | `organization.manage_events` | superadmin bypass |
| `view` | — (`User::canAccessEvent`) | membership-based access |
| `update` | `event.update` | |
| `delete` | `event.delete` | |
| `publish` | `event.update` | |
| `manageSetup` | `event.setup.manage` | blocked when archived |
| `manageTeam` | `event.team.manage` | blocked when archived |
| `manageSchedule` | `event.schedule.manage` | blocked when archived |
| `viewRunOfShow` | `event.run_of_show.view` | not blocked when archived |
| `manageRunOfShow` | `event.run_of_show.manage` | blocked when archived |
| `archive` | `event.archive` | not already archived |
| `unarchive` | `event.archive` | only when archived |

**Effective permission resolution** (from [PERMISSIONS.md](PERMISSIONS.md) §4): the union of
the platform bypass, org inheritance (an active Workspace Owner/Admin inherits every event
permission inside their organization), and every **active** `EventStaffRole` on the event.
An archived event blocks all mutating abilities (`manageSetup`, `manageTeam`,
`manageSchedule`, `manageRunOfShow`, `archive`) with a 403.

M3 **added** eight identifiers to `PermissionRegistry` — `event.setup.manage`,
`event.team.manage`, `event.schedule.manage`, `event.run_of_show.view`,
`event.run_of_show.manage`, `event.archive`, `playbook.view`, `playbook.manage` — and
granted them to Workspace Owner and Workspace Admin. Event staff roles receive the
run-of-show/setup/team/schedule subset appropriate to their role but **never**
`event.archive`. See [EVENT-TEAM.md](EVENT-TEAM.md) §6 and [PERMISSIONS.md](PERMISSIONS.md).

---

## 8. Intentionally out of scope for M3

The following are **not** implemented by M3 and remain untouched:

- **Household / guest-side surfaces** — guest actions are still gated by event status and
  feature flags, not the M3 permissions.
- **Invitation Studio** — existing invitation views are unchanged.
- **Vendor Management** — playbook `vendor_categories` are stored as configuration only;
  no vendor CRUD/UI exists.
- **Task Management** — the setup checklist is a readiness tracker, not a task board.
- **Shift Management** and **Control Room** — no scheduling/control surfaces are added.
- **Couple Space** — the owner/couple surfaces and the couple memberships remain as in
  Milestone 2.
- **Memory** — the media/memory surfaces are unchanged.
- **Legacy cleanup** — no Milestone 1/2 tables, columns, or rows are dropped or rewritten.
- The legacy owner **timeline** page (`/owner/timeline`) is unchanged and still renders the
  public schedule; the workspace Run of Show is the internal-only counterpart.
