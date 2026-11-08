# Event Team

**Milestone 3 — Event Workspace, Setup & Operations**
Status: Implemented — 2026-11-10

---

## 1. Purpose

The **event team** is the set of workspace members assigned operational roles on a single
event. M3 adds a workspace-facing team surface at
`/workspace/{organization}/events/{event}/team` that assigns existing workspace members
directly — no invitation flow — and designates one **primary PIC**.

Model: `App\Models\EventStaffMembership` (reused from Milestone 2)
Service: `App\Services\EventTeamService`
Controller: `App\Http\Controllers\Workspace\WorkspaceEventTeamController`

Related reading: [IDENTITY.md](IDENTITY.md) (the membership layers),
[PERMISSIONS.md](PERMISSIONS.md) (the staff permission matrix),
[EVENT-WORKSPACE.md](EVENT-WORKSPACE.md) (tabs and access).

---

## 2. Reuse of `event_staff_memberships`

M3 does **not** introduce a new team table. It reuses the Milestone 2
`event_staff_memberships` table:

| Column | Type / constraint |
| --- | --- |
| `id` | bigint PK |
| `event_id` | FK → `events`, `cascadeOnDelete` |
| `user_id` | FK → `users`, `cascadeOnDelete` |
| `role` | string (`EventStaffRole`) |
| `status` | string, default `active` (`active` \| `revoked`) |
| `assigned_by` | FK nullable → `users`, `nullOnDelete` |
| `revoked_at` | timestamp nullable |

Keys: **unique** `(event_id, user_id, role)`; indexes on `user_id` and `event_id`. M3 adds
one relation to the model — `runOfShowItems()` (items where this membership is the PIC) —
and nothing else. See [MILESTONE-2-MIGRATION.md](MILESTONE-2-MIGRATION.md) §2.3.

---

## 3. Direct assignment of active workspace members

`EventTeamService::assign(Event $event, User $target, EventStaffRole $role, User $actor)`
performs three guards before writing:

1. **Actor authority** — `assertActorMayManageTeam()` requires
   `PermissionService::hasEventPermission($actor, $event, event.team.manage)`, else 403.
2. **Target is an active org member** — `assertTargetIsActiveOrgMember()` requires the event
   to have an `organization_id` and the target to hold an **active**
   `organization_membership` for that organization, else 422.
3. **Target can access the event** — `assertTargetCanAccessEvent()` requires
   `User::canAccessEvent($event)`, else 422.

It then `updateOrCreate`s on `(event_id, user_id, role)` with `status = active`,
`revoked_at = null`, `assigned_by = actor`, inside a transaction, and records a
`team.assigned` audit row.

The team page's **candidate list** is the organization's **active** memberships whose user
is not already assigned an active role on the event. There is no email invitation and no
new user creation — the target must already be a workspace member.

---

## 4. The seven `EventStaffRole` values

`App\Enums\EventStaffRole` (unchanged from Milestone 2):

| Value | Label |
| --- | --- |
| `event_manager` | Event Manager |
| `guest_manager` | Guest Manager |
| `checkin_supervisor` | Check-in Supervisor |
| `checkin_staff` | Check-in Staff |
| `seating_usher` | Seating Usher |
| `media_curator` | Media Curator |
| `gift_steward` | Gift Steward |

---

## 5. Multi-role

Because the unique key is `(event_id, user_id, role)`, **one user may hold multiple roles on
the same event** (e.g. Check-in Supervisor *and* Seating Usher). Each role is a separate
row and contributes its own permission set; effective event permissions are the **union** of
all active roles (see [PERMISSIONS.md](PERMISSIONS.md) §4).

The team controller's `update()` supports changing a row's role or user: when either
changes it **removes** the old assignment and **assigns** the new one, so the service keeps
a single active row per `(event, user, role)`.

---

## 6. Primary PIC

The **primary PIC** (penanggung jawab utama) is stored on the event's setup config:

- `event_setup_configs.primary_pic_staff_membership_id` — nullable FK →
  `event_staff_memberships`, `nullOnDelete`.
- `EventSetupConfig::primaryPic()` is the `belongsTo` relation; the overview and portfolio
  read `setupConfig.primaryPic.user`.

`EventTeamService::setPrimaryPic(Event $event, ?EventStaffMembership $membership, User
$actor)`:

- Requires `event.team.manage` (403 otherwise).
- If a membership is given, it must be an **active** staff membership of the **same event**,
  else 422 ("Primary PIC harus anggota tim aktif dari event ini.").
- Passing `null` clears the PIC.
- Creates the setup-config row on demand (with `setup_status = not_started`) if the event has
  none, then updates the FK and records a `team.primary_pic` audit row.

The team page's PIC selector lists only **active** memberships; the controller validates
`membership_id` with `Rule::exists('event_staff_memberships', 'id')->where('event_id',
$event->id)`.

---

## 7. Revocation behaviour

`EventTeamService::remove(Event $event, EventStaffMembership $membership, User $actor)`:

1. Requires `event.team.manage`.
2. Aborts 422 if the membership does not belong to the event.
3. Inside a transaction, sets `status = revoked` and `revoked_at = now()`.
4. **Nulls the PIC**: any `event_setup_configs` row for the event whose
   `primary_pic_staff_membership_id` equals this membership is updated to `null` — a revoked
   membership is never left dangling as the primary PIC.
5. Records a `team.revoked` audit row.

Revocation is **state-based**: the row is retained for audit, and the role's permissions are
lost immediately (permissions are evaluated live on every request). There is **no
un-revoke** — re-granting requires a new assignment. The team page shows revoked rows with a
muted status badge and no edit/revoke controls.

---

## 8. Permission restrictions

| Concern | Rule |
| --- | --- |
| Managing the team | `event.team.manage` via `EventPolicy::manageTeam` (blocked when the event is archived) |
| Workspace Owner/Admin | Inherit `event.team.manage` inside their organization |
| Event Manager (staff) | Holds `event.team.manage` |
| Other staff roles | Do **not** hold `event.team.manage` — they can view the roster but not change it |
| Event Owner (couple) | Holds **no** registry permission; a couple membership grants event *access*, not team management |
| `event.archive` | **Never** granted to any staff role |

Two distinct "owner" concepts must not be conflated:

- A **Workspace Owner** (organization membership) inherits every event permission, including
  `event.team.manage`.
- An **Event Owner** (couple, `event_owner_memberships`) is **not** automatically event staff
  and does **not** receive operational permissions. Holding couple ownership does not unlock
  team management. See [IDENTITY.md](IDENTITY.md) §5.

The team view renders a read-only notice when the user lacks `manageTeam`, and the
assignment/PIC forms are hidden.

---

## 9. Audit actions

| Action | Emitted by |
| --- | --- |
| `team.assigned` | `EventTeamService::assign` |
| `team.revoked` | `EventTeamService::remove` |
| `team.primary_pic` | `EventTeamService::setPrimaryPic` |

---

## 10. Intentionally out of scope for M3

- **Shift Management** — no per-shift scheduling for staff.
- **Control Room** — no live operational console.
- **Invitation-based team onboarding** — M3 assigns existing active members only; the
  Milestone 2 staff-invitation flow is unchanged and separate.
- No team **capacity enforcement** — playbook `team_roles` quantities are informational; the
  service does not block over-assignment.
