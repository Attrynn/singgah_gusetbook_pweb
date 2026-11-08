# Event Setup Wizard

**Milestone 3 — Event Workspace, Setup & Operations**
Status: Implemented — 2026-11-10

---

## 1. Purpose

Every workspace event has an **8-step setup wizard** at
`/workspace/{organization}/events/{event}/setup`. It captures the structural definition of
an event — blueprint, identity, date/time, structure, venues, and team composition — and
tracks setup readiness. It is the primary way a workspace Owner/Admin turns a Draft event
into a prepared one.

The wizard is **draft-friendly**: it persists configuration as the user goes and can be
resumed at any step. Crucially, finishing the wizard **never publishes the event**.

Related reading: [EVENT-WORKSPACE.md](EVENT-WORKSPACE.md) (lifecycle and portfolio),
[OPERATIONAL-PLAYBOOK.md](OPERATIONAL-PLAYBOOK.md) (blueprints),
[EVENT-TEAM.md](EVENT-TEAM.md) (the team step),
[MILESTONE-3-MIGRATION.md](MILESTONE-3-MIGRATION.md) (the underlying tables).

---

## 2. Entry, access and resume

| Route name | Method | URI | Ability |
| --- | --- | --- | --- |
| `workspace.events.setup` | GET | `events/{event}/setup` | `manageSetup` |
| `workspace.events.setup.save` | PUT | `events/{event}/setup` | `manageSetup` |
| `workspace.events.setup.finalize` | POST | `events/{event}/setup/finalize` | `manageSetup` |

`WorkspaceEventController::setup()` renders `workspace.events.setup` and passes
`step = (int) request()->query('step', 1)`. **Resume** is simply navigating back to the
setup URL: the page reloads the persisted config (phases, sessions, venues, checklist,
applied blueprint) and the step indicator can jump to any step. The step value is clamped
to `1..8` in the view.

The wizard is a **single HTML form** (`PUT workspace.events.setup.save`) with all eight
sections in the DOM; Alpine (`x-show`) reveals one section at a time and a hidden `step`
input records the current step. The **finalize** action is a **separate POST form** so its
`POST` method does not collide with the save form's `PUT`.

---

## 3. The eight steps

`resources/views/workspace/events/setup.blade.php` defines the step metadata:

| # | Label | What it captures |
| --- | --- | --- |
| 1 | Blueprint | Start blank, or pick a platform/workspace playbook version |
| 2 | Identitas | Event name, groom/bride names, description |
| 3 | Tanggal & waktu | Event date, timezone, start/end time |
| 4 | Struktur & sesi | Phases and per-day sessions |
| 5 | Venue | Venue name/address with a single primary venue |
| 6 | Pratinjau operasional | Read-only summary of structure, venues, checklist |
| 7 | Komposisi tim | Staff-role catalogue and current team (assignment happens on the Team tab) |
| 8 | Tinjau & simpan | Review + review/finalize action |

Step 6 (preview) and step 8 (review) are **read-only** — they contain no inputs and trigger
no writes beyond the form's normal save.

---

## 4. Required vs optional fields, per-step validation

Validation lives in `App\Http\Requests\Workspace\SaveEventSetupRequest` (the save) and
`StoreWorkspaceEventRequest` (initial creation). All sections are **nullable**: a partial
save is valid, and the wizard can save with only some steps filled.

| Step | Field | Rules |
| --- | --- | --- |
| 1 | `playbook_version_id` | `nullable`, `integer`, `exists:playbook_versions,id` |
| 2 | `name` | validated by the create/update request, not re-validated on setup save; shown from the event |
| 3 | (date/time/timezone) | captured on event create/edit, shown from the event |
| 4 | `phases` | `nullable`, `array` |
| 4 | `phases.*.name` | `required_with:phases`, `string`, `max:255` |
| 4 | `phases.*.sequence` | `nullable`, `integer`, `min:0` |
| 4 | `sessions` | `nullable`, `array` |
| 4 | `sessions.*.name` | `required_with:sessions`, `string`, `max:255` |
| 4 | `sessions.*.session_date` | `required_with:sessions`, `date` |
| 4 | `sessions.*.start_time` / `end_time` | `nullable`, `date` |
| 4 | `sessions.*.phase_index` | `nullable`, `integer`, `min:0` |
| 5 | `venues` | `nullable`, `array` |
| 5 | `venues.*.name` | `required_with:venues`, `string`, `max:255` |
| 5 | `venues.*.address` | `nullable`, `string`, `max:2000` |
| 5 | `venues.*.is_primary` | `nullable`, `boolean` |
| 6 | `checklist` | `nullable`, `array` |
| 6 | `checklist.*.label` | `required_with:checklist`, `string`, `max:255` |
| 6 | `checklist.*.required` / `completed` | `nullable`, `boolean` |
| 6 | `checklist.*.source_key` | `nullable`, `string`, `max:255` |

The event **identity** fields (steps 2–3) are required when the event is first created:
`StoreWorkspaceEventRequest` requires `name`, `event_date`, `venue_name`, `venue_address`,
and `timezone`, and validates that `end_time` is after `start_time`. The wizard does not
re-validate these; it edits the existing event.

`authorize()` on every workspace request **double-checks tenant binding** before returning
the ability result: it aborts 404 if `event.organization_id !== organization->id`, then
returns `can('manageSetup', $event)` (or the step's matching ability).

---

## 5. Draft persistence

`WorkspaceEventController::saveSetup()` orchestrates the save:

1. **Apply blueprint** — if `playbook_version_id` is supplied and resolvable within the
   route organization (or a platform template), `EventSetupService::applyPlaybook()` runs.
   It snapshots the version's config into `event_setup_configs.config_snapshot` and
   materialises **phases** and **checklist items** (and sessions, if the config carries a
   `sessions` key) from the config. It **never** copies staff users or guests.
2. **Update config** — `EventSetupService::updateConfig()` merges the submitted sections
   (`phases`, `checklist`, `sessions`, `public_schedule`, `run_of_show`, `team_roles`,
   `vendor_categories`, `reception`, `invitation_defaults`, `guestbook_defaults`) into the
   `config_snapshot` JSON on the single setup-config row (created on demand).
3. **Checklist completion** — each submitted checklist item carrying a `source_key` is
   passed to `completeChecklistItem()`, which toggles `completed`/`completed_at` and writes
   an `event.setup.checklist` audit row if the item exists.
4. **Sessions** — if `sessions` is present, `syncSessions()` **replaces** the event's
   `event_sessions` rows from wizard input (delete + recreate, in a transaction).

The save returns a redirect-back with a status flash ("Konfigurasi setup disimpan.").

> Persistence note: manual phase and venue input is kept in the config snapshot. **Phase
> rows** (`event_phases`) are materialised when a **blueprint is applied**, and **sessions**
> are materialised on every save. This is the behaviour asserted by
> `tests/Feature/M3/EventSetupWizardTest::test_setup_save_persists_phases_checklist_and_sessions`.

---

## 6. Finalization

`POST workspace.events.setup.finalize` → `EventSetupService::finalize()`. It runs
`evaluate()` and persists the result:

- If the checklist is **Ready**, `setup_status = ready`.
- Otherwise, `setup_status = in_progress`.

Finalize **never changes `events.status`**. It emits an `event.setup.finalized` audit row
with the computed status and percent, then redirects to the event overview. The wizard's
step-8 copy states this explicitly: "Menandai siap **tidak** mempublikasikan acara."

### Readiness evaluation (`EventSetupService::evaluate`)

| Condition | Resulting `SetupStatus` |
| --- | --- |
| No checklist items exist | `not_started` |
| All **required** items complete (or, if none are required, all items complete) | `ready` |
| Anything else | `in_progress` |

It also returns `items`, `completed`, `total`, and `percent`
(`round(completed / total * 100)`, or `0` when there are no items).

> `SetupStatus::ReadyForReview` exists in the enum (with label/badge) but the finalize flow
> currently sets only `ready` or `in_progress`; `ready_for_review` is reserved and not
> written by the wizard.

---

## 7. Failure handling

| Situation | Result |
| --- | --- |
| Event belongs to another organization | 404 (tenant binding) |
| User lacks `manageSetup` (or event archived) | 403 via policy |
| Invalid payload (e.g. phase/session name missing) | 302 back with validation errors; no snapshot change for that section |
| `playbook_version_id` not resolvable in this org/platform | 404 |
| Finalize with incomplete required items | `setup_status` stays `in_progress`; event stays Draft |
| Any exception mid-save | The section's DB writes roll back (each service write is transactional) |

Recovery: because everything is stored on the single setup-config row plus the event's
structure rows, a failed save leaves the previous state intact and the wizard can simply be
re-submitted.
