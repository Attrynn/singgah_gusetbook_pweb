# Schedule Architecture

**Milestone 3 — Event Workspace, Setup & Operations**
Status: Implemented — 2026-11-10

---

## 1. Purpose

Milestone 3 splits the event's schedule into two surfaces with **different audiences**:

- The **public schedule** (`event_schedules`) — guest-visible, managed on the workspace tab
  `Jadwal publik`.
- The **internal run of show** (`event_run_of_show_items`) — staff-only, managed on the
  workspace tab `Run of show`.

The public schedule is the legacy surface and is **unchanged** by M3; the run of show is a
new internal-only table that replaces the owner timeline for operational planning.

Related reading: [EVENT-WORKSPACE.md](EVENT-WORKSPACE.md) (tabs),
[EVENT-TEAM.md](EVENT-TEAM.md) (PIC), [MILESTONE-3-MIGRATION.md](MILESTONE-3-MIGRATION.md).

---

## 2. Public schedule — `event_schedules` (unchanged)

This table predates M3 and is not modified. Columns: `id`, `event_id`, `title`,
`description`, `start_time`, `end_time`, `status` (`ScheduleStatus::Planned` /
`Ongoing` / `Done`), `sort_order`. `start_time`/`end_time` are stored as UTC datetimes
(parsed from event-local wall-clock input via `EventService::toUtc()`).

| Route name | Method | URI | Ability |
| --- | --- | --- | --- |
| `workspace.events.schedule.index` | GET | `events/{event}/schedule` | `manageSchedule` |
| `workspace.events.schedule.store` | POST | `events/{event}/schedule` | `manageSchedule` |
| `workspace.events.schedule.update` | PUT | `events/{event}/schedule/{schedule}` | `manageSchedule` |
| `workspace.events.schedule.destroy` | DELETE | `events/{event}/schedule/{schedule}` | `manageSchedule` |

The schedule view renders only fields guests see (`title`, `description`, wall-clock times in
the event timezone). It never shows internal notes, PIC, or planned-vs-actual split.

---

## 3. Internal run of show — `event_run_of_show_items` (new)

Created by migration `2026_11_10_000700_create_event_run_of_show_items_table.php`. Columns:

| Column | Type / constraint | Meaning |
| --- | --- | --- |
| `id` | bigint PK | |
| `event_id` | FK → `events`, `cascadeOnDelete` | |
| `event_session_id` | FK nullable → `event_sessions`, `nullOnDelete` | optional multi-day grouping |
| `phase_id` | FK nullable → `event_phases`, `nullOnDelete` | optional phase grouping |
| `title` | string | |
| `internal_description` | text nullable | staff-only notes |
| `planned_start` / `planned_end` | datetime nullable | planned wall-clock instant (UTC) |
| `actual_start` / `actual_end` | datetime nullable | actual wall-clock instant (UTC) |
| `status` | string default `pending` indexed | `RunOfShowStatus` |
| `sequence` | unsignedSmallInt default 0 | ordering |
| `pic_staff_membership_id` | FK nullable → `event_staff_memberships`, `nullOnDelete` | PIC |
| `internal_notes` | text nullable | |
| `created_by` / `updated_by` | FK nullable → `users`, `nullOnDelete` | audit trail |

Relations: `event`, `session`, `phase`, `pic`, `creator`, `updater`.

| Route name | Method | URI | Ability |
| --- | --- | --- | --- |
| `workspace.events.run-of-show.index` | GET | `events/{event}/run-of-show` | `viewRunOfShow` |
| `workspace.events.run-of-show.store` | POST | `events/{event}/run-of-show` | `manageRunOfShow` |
| `workspace.events.run-of-show.update` | PUT | `events/{event}/run-of-show/{item}` | `manageRunOfShow` |
| `workspace.events.run-of-show.destroy` | DELETE | `events/{event}/run-of-show/{item}` | `manageRunOfShow` |

---

## 4. Data separation and audience access

The run-of-show page (`resources/views/workspace/events/run-of-show.blade.php`) opens with an
explicit notice: "Internal. Halaman ini berisi jadwal teknis, waktu aktual, PIC, dan catatan
internal. Data di sini tidak pernah ditampilkan ke tamu — gunakan Jadwal publik untuk
informasi tamu."

| Surface | Audience | What it shows |
| --- | --- | --- |
| Public schedule | Guests (via invitation) | `title`, `description`, wall-clock times |
| Run of show | Workspace members (with the right role) | title, planned/actual times, PIC, internal notes, status |

The public schedule is rendered to guests through the existing invitation flow; the run of
show is **never** surfaced to guests. A workspace member without `viewRunOfShow` sees no
link to it.

---

## 5. Planned vs actual, and status transitions

Each item has both planned and actual time columns, plus a `status`:

| Status | Label | Badge |
| --- | --- | --- |
| `pending` | Pending | neutral |
| `in_progress` | In Progress | info |
| `completed` | Completed | success |
| `delayed` | Delayed | warning |
| `cancelled` | Cancelled | muted |

`RunOfShowService::resolveStatus()` accepts an already-resolved enum, defaults to `pending`,
and throws a `ValidationException` for unknown values. There is **no explicit transition
matrix** — any valid `RunOfShowStatus` value is accepted on create/update. The UI exposes
status as a select; the service validates the value against the enum.

Planned vs actual are independent columns: `planned_*` are set at creation/edit time,
`actual_*` are filled during the event. The timeline view renders both, grouped by session
(and a "Tanpa sesi" group for items without a session).

---

## 6. Primary PIC on run-of-show items

`RunOfShowService::resolvePic($event, $membershipId)` requires an **active**
`event_staff_memberships` row for the same event, else 422 ("PIC harus anggota tim aktif
dari event ini."). The PIC is stored on each item's `pic_staff_membership_id`; the team
page's PIC selector lists active memberships and the item form offers a PIC dropdown.

---

## 7. Timezone handling

All datetimes are stored as **UTC instants**. Input arrives as event-local wall-clock values
(`*_local` keys) and is converted before storage:

```php
return Carbon::parse((string) $value, $timezone)->utc();
```

where `$timezone = $event->timezoneName()` (IANA, falling back to `config('app.timezone')`).

- **Public schedule**: `WorkspaceEventScheduleController::payload()` converts `start_time`/
  `end_time` from the event timezone to UTC before insert/update.
- **Run of show**: `RunOfShowService::toUtc()` does the same conversion for every
  `*_local` field.
- **Sessions**: `event_sessions.timezone` defaults to `Asia/Jakarta`; sessions carry their own
  `start_time`/`end_time` (datetime) and `session_date` (date).

Display converts back to the event timezone via `Carbon::copy()->setTimezone($event->timezoneName())`
in the views. Multi-day events therefore keep per-session dates and times in the event
timezone while the DB stores absolute UTC instants.

---

## 8. Multi-day sessions

`event_sessions` is the per-day structure that the run of show groups by. An event can have
multiple sessions across multiple days; each session can optionally belong to a phase, and
each run-of-show item optionally belongs to a session and/or phase. Items without a session
render under a "Tanpa sesi" group. This lets a multi-day wedding keep day-by-day structure
while the run of show remains a single ordered timeline.

---

## 9. Intentionally out of scope for M3

- **Shift Management** — no per-shift staffing schedule.
- **Control Room** — no live operational console.
- The legacy owner **timeline** page (`/owner/timeline`, `TimelineController`) is unchanged and
  still renders the public schedule; the workspace Run of Show is the internal-only
  counterpart.
