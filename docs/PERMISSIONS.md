# Roles & Permission Matrix

**Milestone 2 — Identity, Membership & Permission Foundation**
Status: Implemented — 2026-11-09

---

## 1. Where permissions live

- **Definitions:** `App\Authorization\PermissionRegistry` — string-identifier constants
  plus the role → permission-set maps.
- **Evaluation:** `App\Services\PermissionService` — `hasOrgPermission()` and
  `hasEventPermission()`, with the platform-superadmin bypass and org-owner/admin
  inheritance.
- **Enforcement points:** `App\Policies\OrganizationPolicy` (workspace actions) and
  `App\Policies\EventPolicy` / `App\Policies\GuestPolicy` (event actions), plus the
  workspace controllers.

Identifiers are strings, not enums, so they can be persisted, logged, and asserted in tests.

---

## 2. Role catalog

| Role | Enum / source | Scope | Where it is stored |
| --- | --- | --- | --- |
| Platform Superadmin | `PlatformRole::Superadmin` | Global | `users.platform_role` |
| Platform Admin | `PlatformRole::Admin` | Global | `users.platform_role` |
| Legacy Superadmin | `UserRole::Superadmin` | Global | `users.role` |
| Workspace Owner | `OrganizationRole::Owner` | One organization | `organization_memberships.role` |
| Workspace Admin | `OrganizationRole::Admin` | One organization | `organization_memberships.role` |
| Basic Member | `OrganizationRole::Member` | One organization | `organization_memberships.role` |
| Event staff (×7) | `EventStaffRole` | One event | `event_staff_memberships.role` |
| Event Owner (couple) | `EventOwnerRole` (`groom`, `bride`, `partner`) | One event | `event_owner_memberships.role_label` |
| Guest | opaque token | One event | `guest_tokens` / `guests` |

`OrganizationRole` carries a hierarchy helper `isAtLeast()` (Owner > Admin > Member) used by
`User::isWorkspaceAdmin()`.

---

## 3. Workspace (organization) permissions

Evaluated by `PermissionService::hasOrgPermission()`. Each `OrganizationRole` maps to a set
from `PermissionRegistry`:

| | Platform Super/Admin | Workspace Owner | Workspace Admin | Basic Member |
| --- | --- | --- | --- | --- |
| `organization.view` | ✅ | ✅ | ✅ | ✅ |
| `organization.update` | ✅ | ✅ | ✅ | ❌ |
| `organization.manage_members` | ✅ | ✅ | ✅ | ❌ |
| `organization.invite_members` | ✅ | ✅ | ✅ | ❌ |
| `organization.manage_roles` | ✅ | ✅ | ❌ | ❌ |
| `organization.manage_events` | ✅ | ✅ | ✅ | ❌ |

Legend: ✅ granted · ❌ denied.

Mapping rules (`PermissionService::hasOrgPermission`):

- Platform Superadmin / Platform Admin and legacy Superadmin → always `true`.
- Owner → `PermissionRegistry::forWorkspaceOwner()`.
- Admin → `PermissionRegistry::forWorkspaceAdmin()` = Owner's set **minus**
  `organization.manage_roles`.
- Any other role (Member) → only `organization.view`.
- Inactive membership (suspended/revoked) or no membership → `false`.

`OrganizationPolicy` exposes `view`, `update`, `manageMembers`, `inviteMembers`,
`manageRoles`. The workspace member controllers additionally check
`organization.manage_members`, `organization.invite_members`, and
`organization.manage_roles` directly.

---

## 4. Event permissions

Evaluated by `PermissionService::hasEventPermission()`. A user's effective permissions on an
event are the **union** of:

1. the platform bypass, if any;
2. org inheritance, if the event belongs to an org and the user is an active Owner/Admin of
   that org;
3. every **active** `EventStaffRole` the user holds on that event.

### 4.1 Event staff permission matrix

Legend: ✅ granted · ❌ denied.

| Permission | Event&nbsp;Manager | Guest&nbsp;Manager | Check-in Supervisor | Check-in Staff | Seating Usher | Media Curator | Gift Steward |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `event.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `event.update` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `event.delete` | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `event.manage_settings` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `guest.view` | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `guest.create` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `guest.update` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `guest.delete` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `guest.import` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `guest.export` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `checkin.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| `checkin.scan` | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `checkin.create` | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `checkin.correct` | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `checkin.report` | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `seating.view` | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ |
| `seating.manage` | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `media.view` | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| `media.moderate` | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| `media.manage_albums` | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| `media.publish` | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| `gift.view` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| `gift.manage` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| `reports.view` | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `reports.export` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

Naming note: the seven `EventStaffRole` cases are `event_manager`, `guest_manager`,
`checkin_supervisor`, `checkin_staff`, `seating_usher`, `media_curator`, `gift_steward`.

### 4.2 Workspace Owner / Admin inheritance

When the event has an `organization_id` and the user is an active **Workspace Owner** or
**Workspace Admin**, `hasEventPermission()` delegates to `hasOrgPermission()`. Because the
Owner/Admin permission sets contain the same event-scoped identifiers (`event.*`, `guest.*`,
`checkin.*`, `seating.*`, `media.*`, `gift.*`, `reports.*`), an active Owner/Admin inherits
**every** event permission within their organization. Only `organization.manage_roles`
distinguishes Admin from Owner, and it is org-scoped (not an event permission).

### 4.3 Event Owner (couple) and Guest

- **Event Owner / couple** holds **no** `PermissionRegistry` identifiers. Membership grants
  *event access* (`PermissionService::isEventOwner()`, `User::canAccessEvent()`), not
  operational permissions. A couple member who is only an `event_owner_membership` is denied
  every permission in the matrix above (e.g. `guest.create` returns `false`), and is denied
  `hasOrgPermission(..., organization.manage_members)`.
- **Guest** is not modelled in `PermissionRegistry`. Guest capabilities are token-scoped and
  gated by event status + feature flags (`rsvp_enabled`, `guestbook_enabled`,
  `gallery_enabled`, `gift_enabled`, `live_wall_enabled`). See [ROLES.md](ROLES.md).

---

## 5. Full permission identifier list

### Platform scope

| Identifier | Purpose |
| --- | --- |
| `platform.access` | Reach platform surfaces |
| `platform.manage_users` | Manage platform users |
| `platform.manage_organizations` | Manage organizations |
| `platform.manage_settings` | Manage global app settings |

### Organization scope

| Identifier | Purpose |
| --- | --- |
| `organization.view` | View the workspace |
| `organization.update` | Edit workspace settings |
| `organization.manage_members` | Add/remove workspace members |
| `organization.invite_members` | Issue staff/couple invitations |
| `organization.manage_roles` | Change member roles (Owner-only) |
| `organization.manage_events` | Manage the workspace's events |

### Event scope

`event.view`, `event.update`, `event.delete`, `event.manage_settings`.

### Guest scope

`guest.view`, `guest.create`, `guest.update`, `guest.delete`, `guest.import`, `guest.export`.

### Check-in scope

`checkin.view`, `checkin.scan`, `checkin.create`, `checkin.correct`, `checkin.report`.

### Seating scope

`seating.view`, `seating.manage`.

### Media scope

`media.view`, `media.moderate`, `media.manage_albums`, `media.publish`.

### Gift scope

`gift.view`, `gift.manage`.

### Reports scope

`reports.view`, `reports.export`.

---

## 6. Denial & inheritance rules

**Inheritance**

1. Platform Superadmin / Platform Admin / legacy Superadmin → allow everything.
2. Workspace Owner / Admin → full event permissions inside their organization.
3. Multiple active `EventStaffRole`s on one event → permissions are **unioned**.
4. No other role inherits upward: a Basic Member's org permission is only
   `organization.view`, and couple/guest status grants no registry permission.

**Denial**

1. Only an **active** membership counts. `organization_memberships.status` =
   `suspended` or `revoked` is denied (even though the row exists).
2. Only an **active** `event_staff_memberships` / `event_owner_memberships` row counts;
   `revoked` is denied.
3. Event permissions are scoped to **one event**. A staff row on event A grants nothing on
   event B, even within the same organization.
4. Cross-tenant access is denied: a member of org A has no permissions on events in org B.
5. **Privilege ceiling (owner role).** Granting the Workspace **Owner** role — via
   `workspace.members.update` or a staff invitation — requires the actor to be an active
   Workspace Owner, a platform superadmin, or a platform admin (`canGrantOwner()`). A
   Workspace Admin is forbidden from creating/promoting Owners, even though it holds
   `organization.invite_members` and `organization.manage_members`.
6. **Last active owner protection.** Demoting, suspending, or revoking the only active
   Workspace Owner aborts with HTTP 422 ("Workspace harus memiliki minimal satu Workspace
   Owner aktif."). See §8.

---

## 7. Revocation semantics

| Object | Status change | Effect |
| --- | --- | --- |
| `organization_memberships` | `revoked` | Access denied everywhere for that workspace; row is kept for audit |
| `organization_memberships` | `suspended` | Temporarily denied; `restore()` returns it to `active` |
| `organization_memberships` | `active` | Restored by `WorkspaceMembershipService::restore()` |
| `event_staff_memberships` | `revoked` | Loses that role's permissions **immediately** (checked live on every request) |
| `event_owner_memberships` | `revoked` | Loses couple event access immediately |
| `workspace_invitations` | `revoked` | Token unusable (`422`); see [INVITATIONS.md](INVITATIONS.md) |
| `workspace_invitations` | `expired` | Token unusable (`422`) |

Revocation is state-based: there is no cached permission snapshot, so a status change takes
effect on the next request. There is **no** un-revoke for staff/owner memberships or
invitations; re-granting requires issuing a new assignment/invitation.

Every membership transition is audited (see §9).

---

## 8. Ownership continuity (`WorkspaceMembershipService`)

`changeRole`, `revoke`, and `suspend` all run inside a `DB::transaction` and lock the target
membership row (`lockForUpdate`). Before changing an **active Owner** away from Owner (or
removing them), the service counts the workspace's active owners under lock and aborts if
there is only one. Transferring ownership while two owners exist is atomic; the workspace
always retains at least one active Owner.

---

## 9. Audit actions

`AuditService` records, with the actor and a description:

| Action | Emitted by |
| --- | --- |
| `membership.role_changed` | `WorkspaceMembershipService::changeRole` |
| `membership.revoked` | `WorkspaceMembershipService::revoke` |
| `membership.suspended` | `WorkspaceMembershipService::suspend` |
| `membership.restored` | `WorkspaceMembershipService::restore` |
| `invitation.staff.created` | `WorkspaceInvitationService::createStaffInvitation` |
| `invitation.couple.created` | `WorkspaceInvitationService::createCoupleInvitation` |
| `invitation.staff.accepted` / `invitation.couple.accepted` | `WorkspaceInvitationService::acceptInvitation` |
| `invitation.revoked` | `WorkspaceInvitationService::revokeInvitation` |
| `invitation.resent` | `WorkspaceInvitationService::resendInvitation` |

---

## 10. Defined but NOT yet wired (important)

Some identifiers exist in `PermissionRegistry` and appear in role permission sets, but **no
module currently calls a check for them**. They are reserved for future route/module work and
should not be assumed to be enforced.

### 10.1 Identifiers with no consuming check

| Identifier(s) | Status |
| --- | --- |
| `platform.*` (`access`, `manage_users`, `manage_organizations`, `manage_settings`) | Defined; no caller. Platform access is currently gated by `users.role` / `platform_role`, not these identifiers |
| `organization.manage_events` | Defined; present in Owner/Admin sets; no consuming check |
| `event.view` | In every role set; **no policy checks it** (`EventPolicy::view` uses `canAccessEvent`) |
| `guest.import`, `guest.export` | In Owner/Admin/EventManager/GuestManager sets; **no consuming check** — the import/export routes are admin-only behind `role:superadmin` |
| `checkin.*` (`view`, `scan`, `create`, `correct`, `report`) | Defined; **no consuming check** — check-in routes are admin-only behind `role:superadmin` |
| `seating.*` (`view`, `manage`) | Defined; **no consuming check** — seating routes are admin-only |
| `media.view`, `media.manage_albums`, `media.publish` | Defined; only `media.moderate` is consumed (via `EventPolicy::moderate`) |
| `gift.*` (`view`, `manage`) | Defined (Gift Steward / Owner / EventManager); **no consuming check** — gift management routes are admin-only |
| `reports.*` (`view`, `export`) | Defined; **no consuming check** — report routes are admin-only |

### 10.2 Identifiers that *are* consumed

| Identifier | Consumed by |
| --- | --- |
| `organization.view` / `update` / `manage_members` / `invite_members` / `manage_roles` | `OrganizationPolicy`, `WorkspaceMemberController` |
| `event.update` / `event.delete` / `event.manage_settings` | `EventPolicy` |
| `guest.view` / `guest.create` / `guest.update` / `guest.delete` | `GuestPolicy`, `EventPolicy` |
| `media.moderate` | `EventPolicy::moderate` |

### 10.3 The bigger caveat

`EventPolicy` and `GuestPolicy` are currently **not invoked by any route**. The admin
controllers are gated by the `role:superadmin` middleware instead of calling
`authorize()`. Consequently:

- The event-staff permission model is fully implemented and **unit-tested at the service
  level** (`PermissionService`, `EventStaffMembershipTest`, `EventOwnerMembershipTest`,
  `PermissionRegistryTest`), but no event-staff-facing route evaluates it yet.
- Only the **organization** permission set is wired to live routes today, through
  `organization_memberships` + `OrganizationPolicy` + the `workspace/*` routes.

When staff-facing routes are added, they must call
`PermissionService::hasEventPermission()` (or a policy that does) to make this matrix
binding. Until then, treat §4.1 as the *intended* contract, not an enforced one.

---

## 11. Middleware reference

| Alias | Class | Behaviour |
| --- | --- | --- |
| `role` | `EnsureRole` | 403 if `users.role` is not in the allowed list; logs out inactive users |
| `active` | `EnsureActiveUser` | Logs out and invalidates the session when `is_active` is false |
| `org.context` | `ResolveOrganizationContext` | Shares the route-bound `organization` with views |
| `org.member` | `EnsureOrganizationMembership` | Requires an **active** membership (superadmin/platform-admin bypass); 403 otherwise |

Workspace routes combine `auth`, `auth.session`, `active`, `org.context`, `org.member`.
See [TENANCY.md](TENANCY.md) and [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md).
