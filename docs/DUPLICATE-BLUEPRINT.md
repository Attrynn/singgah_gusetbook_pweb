# Duplicate Blueprint

**Milestone 3 — Event Workspace, Setup & Operations**
Status: Implemented — 2026-11-10

---

## 1. Purpose

An existing event can be duplicated to seed a new one. Duplication copies **structural and
identity configuration only**; it **never** copies personal data, transactional data,
tokens, media, or user assignments. This enables workspaces to turn successful events into
blueprints without risking cross-event data leaks.

Model / Service: `App\Services\EventBlueprintService`
Controller: `App\Http\Controllers\Workspace\WorkspaceEventDuplicateController`
Route: `POST /workspace/{organization}/events/{event}/duplicate` (`workspace.events.duplicate`)

Related reading: [EVENT-WORKSPACE.md](EVENT-WORKSPACE.md),
[OPERATIONAL-PLAYBOOK.md](OPERATIONAL-PLAYBOOK.md), [EVENT-TEAM.md](EVENT-TEAM.md).

---

## 2. Duplicate flow

Duplication is invoked from two entry points:

1. **Standalone duplicate route**: `POST workspace.events.duplicate`. Validates `name`,
   `event_date`, and boolean copy flags (`setup`, `checklist`, `phases`, `sessions`,
   `schedule`, `run_of_show`). Redirects to the new event's overview.
2. **Event create form**: passing `duplicate_source_event_id` in
   `StoreWorkspaceEventRequest`. `WorkspaceEventController::duplicateFromSource()` runs
   `EventBlueprintService::duplicate()`, then applies the overrides supplied in the
   creation form (couple names, venue, times, etc.).

Both entry points require `EventPolicy::update` on the source event (and superadmin bypass).

---

## 3. The allowlist — `allowedCopyMap()`

`EventBlueprintService::allowedCopyMap()` is the **static authority** on what is copied and
what is excluded. It returns four arrays:

| Bucket | Keys / tables | Meaning |
| --- | --- | --- |
| `identity` | `name`, `groom_name`, `bride_name`, `couple_description`, `description`, `venue_name`, `venue_address`, `google_maps_url`, `event_date`, `start_time`, `end_time`, `timezone` | Base fields on the `events` table |
| `structure` | `primary_venue`, `setup_config`, `checklist`, `phases`, `sessions`, `event_schedules`, `run_of_show_items` | Relational tables that can be copied |
| `playbook_refs` | `playbook_id`, `playbook_version_id` | Foreign keys retained on the new setup config |
| `excluded` | `guests`, `guest_tokens`, `invitations`, `rsvps`, `checkins`, `media`, `guestbook_messages`, `gifts`, `event_user`, `event_owner_memberships`, `event_staff_memberships`, `audit_logs`, `published_at`, `archived_at` | Data that is **strictly excluded** |

---

## 4. Copy rules per entity

| Entity | Copied? | Details / transformation |
| --- | --- | --- |
| Event row | ✅ | `name` defaults to `"{source->name} (Salinan)"`; `status` is forced to **Draft**; `organization_id` is forced to the source's org; `created_by` is the acting user; `published_at` and `archived_at` are **not** copied |
| Primary venue | ✅ | Copied from `source->primaryVenue()`; sets `is_primary = true` |
| Setup config | ✅ | `playbook_id`, `playbook_version_id`, `config_snapshot` copied; `source_event_id` is set to the source event's id; `setup_status` is reset to **InProgress**; `primary_pic_staff_membership_id` is set to **null** |
| Checklist items | ✅ | `label`, `description`, `required`, `sequence`, `source_key` copied; `completed = false`, `completed_at = null` |
| Phases | ✅ | Copied to new `event_phases` rows. Old→new ID mapping is built in memory |
| Sessions | ✅ | Copied to new `event_sessions` rows; `phase_id` remapped via the phase map |
| Public schedules | ✅ | `title`, `description`, `start_time`, `end_time`, `sort_order` copied; `status` reset to `Planned` |
| Run of show | ✅ | `title`, `internal_description`, `planned_start`, `planned_end`, `sequence` copied; `phase_id` and `event_session_id` remapped; `actual_start`, `actual_end`, `internal_notes`, `pic_staff_membership_id`, `updated_by` are set to **null**; `status` is reset to **Pending**; `created_by` is the acting user |
| Team memberships | ❌ | **Never copied.** `event_staff_memberships` rows are not touched; the new event starts with an empty staff roster |
| Owners / couple | ❌ | **Never copied.** `event_owner_memberships` and legacy `event_user` are untouched |
| Guests & tokens | ❌ | **Never copied.** |
| RSVPs, check-ins, messages, media, gifts | ❌ | **Never copied.** |
| Audit logs | ❌ | Not copied. A single `event.duplicated` audit row is emitted |

Structural copy is **on by default**; caller options (`setup`, `checklist`, `phases`,
`sessions`, `schedule`, `run_of_show`) can disable individual sections (`shouldCopy()`).

---

## 5. Atomicity

The entire duplicate process runs inside a **single `DB::transaction`**:

```php
return DB::transaction(function () use ($source, $options, $actor): Event {
    $event = Event::create([...]);
    $this->copyPrimaryVenue($source, $event);
    $this->copySetupConfig($source, $event, $options);
    $this->copyChecklist($source, $event, $options);
    $phaseMap = $this->copyPhases($source, $event, $options);
    $sessionMap = $this->copySessions($source, $event, $options, $phaseMap);
    $this->copySchedules($source, $event, $options);
    $this->copyRunOfShow($source, $event, $options, $phaseMap, $sessionMap, $actor);
    $this->audit->record('event.duplicated', ...);
    return $event->fresh([...]);
});
```

If an insert fails anywhere in the sequence, the transaction rolls back completely: you
never get a partial event without its sessions or phases.

---

## 6. Tenant constraints (same org only)

Cross-tenant duplication is **forbidden by construction**:

1. **Routing constraint**: the controller verifies `event.organization_id === organization->id`
   and aborts 404 otherwise (`assertEventInOrg()`).
2. **Service constraint**: `EventBlueprintService::duplicate()` explicitly sets
   `'organization_id' => $source->organization_id`. It does not accept an
   `organization_id` override in `$options`.
3. If `$source->organization_id` is missing (un-backfilled legacy event), the service aborts
   with 422 ("Event sumber belum terhubung ke workspace.").
4. The actor must have `EVENT_UPDATE` permission on the source event (or superadmin bypass),
   otherwise the service aborts 422.

An event can therefore **only be duplicated within the same workspace**.

---

## 7. Recovery

| Situation | Result |
| --- | --- |
| Source event belongs to another org | 404 (tenant binding) |
| Source event has no org | 422 with explanation |
| Actor lacks permission | 422 / 403 |
| Failure mid-copy | Full transaction rollback; source event untouched; no orphaned rows |

Because duplication is read-from-source / write-new inside a transaction, the source event is
never mutated. Retrying the operation is completely safe.
