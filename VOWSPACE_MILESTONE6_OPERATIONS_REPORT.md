# Milestone 6 — Event Operations & Staff Coordination

**Status:** Implemented and verified — 2026-11-11
**Baseline:** M5 complete at `1337f7b` (380 tests / 1278 assertions)
**Final:** 496 tests / 1546 assertions passing
**Working tree:** all M6 work is uncommitted (86 changed/untracked paths)

---

## 1. Outcome

M6 adds a day-of-event operations layer on top of the existing guest, RSVP, pass and
check-in system. It does not rebuild any M1–M5 capability: attendance metrics, guest
identity, passes and check-ins are consumed from `AttendanceService`, and all access
control flows through the existing `PermissionRegistry` / `PermissionService` /
`EventPolicy` stack.

Delivered:

| Area | Result |
| --- | --- |
| Operational lifecycle | Draft → Preparing → Ready → Active → Completed, plus Cancelled, with readiness gates, override-with-reason, closure review/snapshot and reopen |
| Staff coordination | Invite / accept / decline / suspend / reinstate / remove, per-member permission delegation bounded by actor authority |
| Stations | CRUD, status transitions, staff assignment, supervisor designation, coverage derivation |
| Tasks | Tasks, checklist, dependencies (cycle-safe), comments, visibility rules, overdue derivation |
| Incidents | Report, assign, escalate, resolve, close, reopen, full history timeline |
| Announcements | Server-side audience resolution, publish / withdraw / expire, read and acknowledgement as distinct facts, DB notifications |
| Readiness | Deterministic blocker/warning assessment feeding lifecycle gates |
| Dashboard | Aggregated operational summary + M5 attendance panel + activity feed + JSON live endpoint |
| Reports | Six report types with CSV export and explicit legacy-metric labelling |
| UI | 9 Blade screens, operations nav (tab + sidebar + sub-navigation), extended run-of-show forms |

---

## 2. Schema

Seven additive migrations (all verified up and down on a throwaway SQLite database at
`/tmp/opencode/m6-check.sqlite`; no destructive operations, no column drops):

1. `2026_11_10_001000_extend_event_staff_memberships_for_operations.php`
2. `2026_11_10_001100_create_event_station_tables.php`
3. `2026_11_10_001200_extend_run_of_show_for_operations.php`
4. `2026_11_10_001300_create_event_task_tables.php`
5. `2026_11_10_001400_create_event_incident_tables.php`
6. `2026_11_10_001500_create_event_announcement_tables.php`
7. `2026_11_10_001600_create_event_operations_table.php`

New tables: `event_operations`, `event_stations`, `event_station_assignments`,
`event_staff_permission_grants`, `event_tasks`, `event_task_checklist_items`,
`event_task_dependencies`, `event_task_comments`, `event_incidents`,
`event_incident_updates`, `event_announcements`, `event_announcement_recipients`,
`event_run_of_show_assignees`.

Extended tables: `event_staff_memberships` (invitation/suspension/removal metadata,
notes, expiry), `event_run_of_show_items` (station, dependency, parallel flag, status
metadata).

Every table is event-scoped; tenant isolation is `Organization → Event → event-owned
resource` and is enforced in services, controllers (`AssertsEventInOrganization`) and
tests.

### Models and enums

- 13 new Eloquent models, all with relationships wired and `Event` extended with
  `operation`, `stations`, `stationAssignments`, `tasks`, `incidents`, `announcements`.
- 12 new enums: `OperationsStatus`, `StationStatus`, `StationCategory`, `TaskPriority`,
  `TaskStatus`, `IncidentSeverity`, `IncidentStatus`, `IncidentCategory`,
  `AnnouncementStatus`, `AnnouncementPriority`, `AnnouncementAudience`,
  `ReadinessStatus` — each implements `label()` and `badge()`.
- 3 extended enums: `MembershipStatus` (Pending, Declined, Suspended, Removed, Expired),
  `EventStaffRole` (now 12 roles, adding OperationsCoordinator, StationSupervisor,
  GuestRelations, GeneralStaff, Observer), `RunOfShowStatus` (Skipped).

---

## 3. Permissions

21 new permissions added to `PermissionRegistry`, with role maps for all 12 event staff
roles and owner/admin inheritance:

```
operations.view    operations.manage    operations.close
staff.view         staff.invite         staff.manage        staff.permissions.manage
station.view       station.manage       station.assign
task.view          task.create          task.assign         task.manage
incident.view      incident.create      incident.manage     incident.resolve
announcement.view  announcement.manage  announcement.publish
```

`PermissionService` now evaluates delegated grants in `hasEventPermission()`, exposes
them through `effectiveEventPermissions()`, and guards delegation with
`mayDelegateEventPermission()` so an actor can never grant authority they do not hold.
`EventPolicy` exposes the matching abilities (`viewOperations`, `manageOperations`,
`closeOperations`, `viewStaff`, `inviteStaff`, `manageStaff`, `manageStaffPermissions`,
`viewStations`, `manageStations`, `assignStations`, `viewTasks`, `createTasks`,
`assignTasks`, `manageTasks`, `viewIncidents`, `reportIncidents`, `manageIncidents`,
`resolveIncidents`, `viewAnnouncements`, `manageAnnouncements`,
`publishAnnouncements`, `viewReports`, `exportReports`).

Every service transition writes through the existing `AuditService` (which also feeds
`EventActivity`); no parallel audit or notification system was introduced.

---

## 4. Business rules implemented

**Lifecycle.** The operational state machine is deliberately separate from the business
`EventStatus`. `ensure()` is idempotent. `Ready` and `Active` require readiness without
blockers; an override is accepted only with a non-empty reason and is audited.
`Completed` requires a clean closure review (no open serious incidents, no open critical
tasks, no incomplete run-sheet items) unless overridden with a reason; closure stores
notes and a JSON snapshot. `Completed → Active` reopen is restricted to close authority.
`Cancelled` is terminal.

**Staff.** Invitations target existing active workspace members and start `Pending`;
only the invitee may accept or decline. Suspension/removal cascades: station assignments
end, station supervisor references clear, run-of-show PIC/assignees clear, and open
assigned tasks become `Blocked` with a reason. Removal also deletes delegated grants.
Self-suspension and suspending non-active staff are rejected. Role and permission
delegation is bounded by the actor's own authority.

**Stations.** Slugs are unique per event. Status transitions follow an explicit map.
Stations with active staff or non-draft status cannot be deleted. Only active event staff
can be assigned. Coverage is derived (`no_staff` → `no_supervisor` → `ready`), never
stored as a mutable counter.

**Tasks.** Creation requires `task.create`; assigning others requires `task.assign`.
Blocking requires a reason. Completion requires every checklist item complete and every
dependency `Completed`, unless a manager overrides with a reason. Dependency cycles and
self-dependencies are rejected. Visibility hides unrelated tasks from general staff.
Overdue is derived from `due_at` plus non-terminal status. New tasks are rejected after
operational closure. Completed tasks are immutable for checklist changes.

**Incidents.** Only holders of `incident.manage` may be assignees. Progress requires the
assignee; resolution requires `incident.resolve`. Resolution notes are mandatory and
mandatory again before closing a High/Critical incident. Close requires `Resolved`.
Reopen clears resolution/closure timestamps and records history. Escalation cannot
downgrade a serious incident. Every step writes an `event_incident_updates` row.

**Announcements.** Recipients are resolved server-side from the audience
(`all_staff`, `station`, `selected_staff`, `profile`); client-supplied recipient lists
are ignored. Publishing persists recipients and sends `EventAnnouncementPublished`
database notifications. Published announcements are immutable and may only be withdrawn.
Read and acknowledgement are separate persisted timestamps; acknowledgement is rejected
when not required and for non-recipients. Due announcements expire.

**Readiness.** Deterministic item assessment (`Ready` / `Warning` / `NotReady` /
`NotApplicable`) covering event configuration, staff coverage, station readiness,
critical tasks, run-of-show completeness and check-in readiness (the last is
`NotApplicable` when no active passes exist).

**Metrics.** All dashboard and report figures are computed from persisted rows. The M5
`rsvps.attendance_count` is surfaced only as "Jumlah kehadiran RSVP (legacy)" and is
never presented as a headcount.

---

## 5. HTTP surface

`php artisan route:list --path=workspace` reports **50** routes named
`workspace.events.operations.*`, all resolving to controllers, plus
`workspace.events.run-of-show.status`.

| Group | Routes |
| --- | --- |
| Dashboard / lifecycle | index, live (JSON), transition, closure (GET), close, reopen |
| Staff | index, invite, accept, decline, suspend, reinstate, remove, permissions |
| Stations | index, store, update, destroy, status, assign, unassign, supervisor |
| Tasks | index, store, update, status, assign, checklist store/update/destroy, comments, dependencies store/destroy |
| Incidents | index, store, update, assign, status, escalate, resolve |
| Announcements | index, store, update, publish, withdraw, read, acknowledge |
| Reports | index, export (CSV) |
| Personal | my-work |

`my-work` is a staff console (stations, open tasks, run-sheet items, addressed
announcements) and the live endpoint exposes the same summary shape for controlled
polling, since no broadcasting infrastructure is installed.

---

## 6. UI

Nine new screens under `resources/views/workspace/events/operations/`:
`index`, `closure`, `my-work`, `staff`, `stations`, `tasks`, `incidents`,
`announcements`, `reports`.

- Event tabs gain an "Operasi" entry guarded by `viewOperations`, plus a secondary
  operations nav row rendered only on operations pages.
- Sidebar gains an "Operasi" item guarded by `@can('viewOperations', $workspaceEvent)`.
- Run-of-show create/edit forms and row actions now expose station, dependency,
  assignee multi-select, `allow_parallel`, and a status action limited to valid
  transitions.
- All controls submit to real routes; there are no placeholder or dead controls. The
  announcement edit control was reworked during verification from a no-op form into a
  real inline form with title, body, priority, expiry (event-timezone prefill) and an
  acknowledgement checkbox using the `hidden 0` + `checkbox 1` pair.

---

## 7. Test evidence (executed)

```
php artisan test --compact tests/Feature/M6
  -> 116 passed, 258 assertions

php artisan test --compact
  -> 496 passed, 1546 assertions
```

| File | Tests |
| --- | --- |
| `OperationsLifecycleTest` | 13 |
| `StaffCoordinationTest` | 16 |
| `StationOperationsTest` | 15 |
| `TaskCoordinationTest` | 15 |
| `IncidentManagementTest` | 14 |
| `AnnouncementBroadcastTest` | 14 |
| `OperationsReadinessAndReportingTest` | 12 |
| `OperationsHttpTest` | 17 |
| **Total M6** | **116** |

M1–M5 regression: all pre-existing tests still pass; total grew from 380 to 496 tests
and from 1278 to 1546 assertions.

Other executed checks:

```
php artisan view:cache           -> Blade templates cached successfully
php artisan route:list           -> 50 operations routes resolve
vendor/bin/pint --dirty          -> passed (no violations)
npm run build                    -> built successfully (app.css 121.55 kB)
```

---

## 8. Required edge-case matrix (40)

| # | Edge case | Verified by |
| --- | --- | --- |
| 1 | Draft may advance only to Preparing or Cancelled | `test_draft_can_move_to_preparing` |
| 2 | Skipping states (Draft → Active) is rejected | `test_invalid_transition_from_draft_to_active_is_rejected` |
| 3 | Readiness blockers prevent Ready | `test_ready_transition_is_blocked_when_readiness_has_blockers` |
| 4 | Override without a reason is rejected | `test_override_requires_a_reason` |
| 5 | Active requires full readiness without override | `test_active_requires_full_readiness_without_override` |
| 6 | A serious open incident blocks closure | `test_serious_open_incident_blocks_closure` |
| 7 | Cancelled is terminal | `test_cancelled_operation_is_terminal` |
| 8 | A user outside the workspace cannot be invited | `test_inviting_a_user_outside_the_workspace_is_rejected` |
| 9 | Inviting an already-active role is rejected | `test_inviting_an_already_active_role_is_rejected` |
| 10 | Only the invitee may accept | `test_only_the_invitee_can_accept_an_invitation` |
| 11 | Self-suspension is rejected | `test_a_staff_member_cannot_suspend_their_own_membership` |
| 12 | Only active staff can be suspended | `test_only_active_staff_can_be_suspended` |
| 13 | Suspension releases stations, PIC and tasks | `test_suspending_a_staff_member_releases_all_operational_responsibilities` |
| 14 | Removal deletes delegated grants | `test_removing_staff_revokes_membership_and_deletes_permission_grants` |
| 15 | Delegation beyond actor authority is rejected | `test_permissions_cannot_be_delegated_beyond_the_actor_authority` |
| 16 | Station slugs are unique per event | `test_station_names_generate_unique_slugs_within_an_event` |
| 17 | Unknown station category is rejected | `test_unknown_station_category_is_rejected` |
| 18 | Supervisor must be active staff of the same event | `test_supervisor_must_be_an_active_member_of_the_same_event` |
| 19 | Invalid station status transition is rejected | `test_invalid_station_status_transition_is_rejected` |
| 20 | Station with active staff cannot be deleted | `test_station_with_active_staff_cannot_be_deleted` |
| 21 | Inactive staff cannot be assigned to a station | `test_inactive_staff_cannot_be_assigned_to_a_station` |
| 22 | Task creation requires `task.create` | `test_creating_a_task_requires_the_create_permission` |
| 23 | Assigning others requires `task.assign` | `test_staff_without_assign_permission_cannot_assign_work_to_others` |
| 24 | Blocking a task requires a reason | `test_blocking_a_task_requires_a_reason` |
| 25 | Incomplete checklist blocks completion | `test_completion_is_blocked_by_incomplete_checklist_items` |
| 26 | Unfinished dependencies block completion | `test_completion_is_blocked_by_unfinished_dependencies` |
| 27 | Override without a reason is rejected | `test_override_without_a_reason_is_rejected` |
| 28 | Dependency cycles are rejected | `test_dependency_cycles_are_rejected` |
| 29 | New tasks are rejected after closure | `test_new_tasks_are_rejected_after_operational_closure` |
| 30 | Incident assignee must hold `incident.manage` | `test_incident_can_only_be_assigned_to_a_manager` |
| 31 | Unassigned staff cannot progress an incident | `test_unassigned_staff_cannot_progress_an_incident` |
| 32 | Resolving requires `incident.resolve` | `test_assigned_staff_without_resolve_permission_cannot_resolve` |
| 33 | An incident must be resolved before closure | `test_incident_must_be_resolved_before_it_can_be_closed` |
| 34 | Serious incidents need notes before closure | `test_serious_incident_requires_resolution_notes_before_closure` |
| 35 | Station audience requires at least one station | `test_station_audience_requires_at_least_one_station` |
| 36 | Selected-staff audience rejects foreign memberships | `test_selected_staff_audience_rejects_foreign_memberships` |
| 37 | Published announcements are immutable | `test_published_announcement_is_immutable` |
| 38 | A foreign event is not reachable (404) | `test_event_from_another_organization_is_not_accessible` |
| 39 | Foreign-event membership is not found | `test_membership_from_another_event_is_not_found` |
| 40 | Reports isolate another organization's event | `test_event_from_another_organization_is_isolated_in_reports` |

Additional automated coverage beyond the required 40 includes: permission-denied paths
for every group, audience resolution for all four audience types, read vs acknowledgement
separation, expiry, withdraw, published-announcement edit rejection, escalation downgrade
prevention, reopen timestamp clearing, coverage progression, task visibility, overdue
derivation, readiness `NotApplicable`, M5 attendance reuse, report header/row integrity,
CSV header emission, export permission, and unauthenticated access redirects.

---

## 9. Deliberate limitations

1. **Staff invitations target existing active workspace members.** Onboarding a person
   with no workspace account still uses the existing `workspace_invitations` flow; M6
   does not add a second invitation channel.
2. **No realtime broadcasting.** No broadcast driver is installed, so the dashboard is
   server-rendered and the `operations/live` JSON endpoint supports controlled polling.
3. **In-app notifications only.** Announcement delivery uses Laravel's `notifications`
   table via `toDatabase`; no email/SMS/push provider is claimed.
4. **`docs/PERMISSIONS.md` predates M3–M6** and still describes the M2 permission state.
   The authoritative matrix is `app/Authorization/PermissionRegistry.php` plus this
   report; the doc was left untouched rather than partially rewritten.
5. **Tests run on SQLite in-memory, development runs on MySQL.** All M6 queries avoid
   engine-specific SQL (no `DATE_FORMAT`, no raw date functions), and the suite passes on
   SQLite.

---

## 10. Files

New PHP: 13 models, 12 enums, 9 services, 7 controllers, 1 controller concern, 1
notification, 7 migrations, 8 test files + 1 fixture trait.

Modified PHP: `PermissionRegistry`, `PermissionService`, `EventPolicy`, `Event`,
`EventStaffMembership`, `EventRunOfShowItem`, `RunOfShowService`,
`WorkspaceRunOfShowController`, `routes/workspace-events.php`, run-of-show form requests,
`MembershipStatus`, `EventStaffRole`, `RunOfShowStatus`.

New Blade: 9 operations views. Modified Blade: `event-tabs` partial, `owner` layout,
`run-of-show` view and `run-of-show-items` partial.

---

## 11. Reproduce

```bash
cd /home/attrynn/vowspace
php artisan test --compact tests/Feature/M6
php artisan test --compact
php artisan view:cache
vendor/bin/pint --dirty --format agent
npm run build
php artisan route:list --path=workspace | grep operations | wc -l   # 50
```
