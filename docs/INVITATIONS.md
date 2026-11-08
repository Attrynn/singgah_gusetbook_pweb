# Invitations (Staff & Couple)

**Milestone 2 — Identity, Membership & Permission Foundation**
Status: Implemented — 2026-11-09

---

## 1. Purpose

How a Workspace Owner/Admin brings people into a workspace. There are two invitation types,
both backed by the `workspace_invitations` table:

| Type | Enum | Acceptance effect | Expiry |
| --- | --- | --- | --- |
| **Staff** | `WorkspaceInvitationType::Staff` | Creates/re-activates an `organization_memberships` row **and** active `event_staff_memberships` rows | 7 days |
| **Couple** | `WorkspaceInvitationType::Couple` | Creates an active `event_owner_memberships` row (groom/bride/partner). **No** org membership | 30 days |

Related reading: [IDENTITY.md](IDENTITY.md) (model), [PERMISSIONS.md](PERMISSIONS.md) (roles),
[WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) (workspaces).

---

## 2. Routes

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET | `/invitations/{token}` | `invitations.workspace.accept` | `throttle:30,1` |
| POST | `/invitations/{token}/accept` | `invitations.workspace.accept.store` | `auth`, `throttle:10,1` |
| POST | `/invitations/{token}/register` | `invitations.workspace.accept.register` | `guest`, `throttle:5,1` |
| POST | `/workspace/{organization}/invitations` | `workspace.invitations.store` | `auth`, `auth.session`, `active`, `org.context`, `org.member` |
| POST | `/workspace/{organization}/couple-invitations` | `workspace.couple-invitations.store` | same as above |
| POST | `/workspace/{organization}/invitations/{invitation}/revoke` | `workspace.invitations.revoke` | same as above |
| POST | `/workspace/{organization}/invitations/{invitation}/resend` | `workspace.invitations.resend` | same as above |

The workspace management group is defined in `routes/web.php`; every route behind it is
`org.member`-gated. Creation/revocation/resend additionally call
`$this->authorize('inviteMembers', $organization)` (`OrganizationPolicy`), which requires
`organization.invite_members` (Workspace Owner or Admin).

---

## 3. Creating an invitation

`WorkspaceInvitationService` owns creation. Both paths pre-validate, generate the token, write
the row, record an audit entry, and queue the mail.

### 3.1 Staff invitation — `createStaffInvitation()`

Inputs: organization, inviter, email, `OrganizationRole`, `event_assignments[]`
(each `['event_id' => int, 'role' => EventStaffRole value]`).

Validation (`validateEventAssignments`):

- every assignment must carry an `event_id`;
- the event must exist **in the organization** (otherwise 422);
- each `role` must be a valid `EventStaffRole` (otherwise 422).

Stored: `type = staff`, `organization_role` = the requested org role, `event_assignments` =
the normalized list, `expires_at = now()->addDays(7)`, `status = pending`.
Audit: `invitation.staff.created`.

### 3.2 Couple invitation — `createCoupleInvitation()`

Inputs: organization, inviter, email, `event_id`, `EventOwnerRole` (`groom`/`bride`/`partner`).

Validation: the event must belong to the organization (`firstOrFail` → 404 on the controller
path). Stored: `type = couple`, `organization_role = null`, `event_assignments =
[['event_id' => …, 'role_label' => …]]`, `expires_at = now()->addDays(30)`,
`status = pending`. Audit: `invitation.couple.created`.

### 3.3 Privilege ceiling

The controller enforces that **only an active Workspace Owner or a platform admin can grant
the Owner role**:

- `storeStaffInvitation` and `updateMember` call `canGrantOwner()` before persisting an
  `organization_role = owner`;
- `canGrantOwner()` returns true only for `isSuperadmin()`, `isPlatformSuperadmin()`, or an
  active `isWorkspaceOwner($org)`.

A Workspace Admin holds `organization.invite_members`, so it *can* issue invitations — but
any attempt to set `organization_role = owner` returns 403 (asserted by
`StaffInvitationTest::test_admin_cannot_grant_owner_role`). The ceiling does not attempt to
rank every role; it specifically guards the apex (Owner) role.

### 3.4 Stored value is authoritative

Acceptance never reads role/assignment data from the request. The stored invitation's
`organization_role` / `event_assignments` are used verbatim, so posting
`organization_role=owner` at accept time is ignored (asserted by
`SecurityInvitationTest::test_acceptance_uses_stored_invitation_role`).

---

## 4. Email delivery

```php
Mail::to($email)->queue(new WorkspaceInvitationMail($invitation, $rawToken));
```

- **Mailer:** the application's configured mailer (`MAIL_MAILER`, default `smtp` in
  production per [DEPLOY.md](DEPLOY.md)). `WorkspaceInvitationMail` implements
  `ShouldQueue`, so it is queued (`Mail::to(...)->queue(...)`).
- **Tests never send mail:** all M2 tests call `Mail::fake()` in `setUp()`. Tests read the
  raw token back from `Mail::queued(WorkspaceInvitationMail::class)` — the token is only ever
  exposed via the mailable, never stored in plaintext.
- **No raw token in logs.** `WorkspaceInvitation` hides `token_hash`
  (`protected $hidden`), and the token is passed straight to the mailable for the view and
  never written to the database or logs. The raw value is 64 random characters and is shown
  only in the email.

---

## 5. Accepting an invitation

### 5.1 Token resolution

`WorkspaceInvitationService::acceptInvitation($rawToken, $user)`:

1. `hash('sha256', $rawToken)` and look up `workspace_invitations.token_hash` with
   `lockForUpdate()` — unknown token → 404.
2. Status must be `pending`, otherwise **422** ("Undangan ini sudah tidak dapat digunakan.").
3. `isExpired()` (past `expires_at`) → mark `expired` and abort **422**
   ("Undangan ini sudah kedaluwarsa.").
4. **Email match:** `strtolower($invitation->email)` must equal
   `strtolower($user->email)` → otherwise **403**
   ("Email akun Anda tidak sesuai dengan target undangan ini.").
5. Inside a transaction: activate the membership and mark the invitation `accepted`
   (`accepted_at`, `accepted_by`).

Audit: `invitation.staff.accepted` or `invitation.couple.accepted`.

### 5.2 Accept as an existing account

`POST /invitations/{token}/accept` requires `auth`. The controller calls the service and sets
`active_organization_id` in the session, then redirects home. The existing account is reused;
no new user is created. For a **couple** invitation, the user's existing account is reused
even if the same email owns other events (asserted by `CoupleInvitationTest`).

### 5.3 Accept as a new account

`POST /invitations/{token}/register` is `guest`-only. `WorkspaceInvitationController::register()`:

- loads the invitation by token hash (404 if unknown);
- rejects unusable invitations (422);
- **refuses to create an account if one already exists for the invitation email** — it
  redirects to `login` with an "email already exists" error instead;
- validates only `name`, `password`, `password_confirmation`;
- **takes the email from the invitation, never from the request** (a posted
  `email=attacker@…` is ignored — asserted by
  `StaffInvitationTest::test_new_account_registers_via_invitation_and_ignores_posted_email`);
- creates the user with `role = owner`, `is_active = true`, logs them in, regenerates the
  session, calls `acceptInvitation()`, and sets `active_organization_id`.

No new organization is created by accepting a staff or couple invitation.

### 5.4 GET `/invitations/{token}` page states

The show action computes a `state` for the acceptance view:

| State | Meaning |
| --- | --- |
| `unusable` | Not pending, or expired (expired rows are marked `expired` on view) |
| `login_required` | Pending but the visitor is not authenticated |
| `email_mismatch` | Authenticated, but the account email differs from the invitation |
| `ready` | Pending, authenticated, email matches |

An unknown token returns HTTP 404.

---

## 6. What acceptance writes

| Invitation | Writes | Does **not** write |
| --- | --- | --- |
| Staff | `organization_memberships` (upsert; `invited_by`, `accepted_at`), `event_staff_memberships` per assignment (`updateOrCreate` on `event_id,user_id,role`) | `event_owner_memberships` |
| Couple | `event_owner_memberships` (`updateOrCreate` on `event_id,user_id`, with `role_label`, `accepted_at`, `invited_by`) | `organization_memberships`, `event_staff_memberships` |

Staff acceptance re-activates an existing non-active membership if one exists, otherwise
creates a new active one. Couple acceptance is deliberately org-membership-free: a couple
member is an event owner, not a workspace member (asserted by
`CoupleInvitationTest::test_couple_acceptance_creates_no_org_membership_or_staff_membership`).

---

## 7. Resend, revoke, expiry

| Action | Precondition | Effect |
| --- | --- | --- |
| **Resend** | status is `pending` or `expired` (not `revoked`/`accepted`) | Generates a **new token**, resets `status = pending` and `expires_at = now()->addDays(7)`, re-queues mail. The old token immediately stops resolving (404) |
| **Revoke** | status is `pending` | Sets `status = revoked`, `revoked_at = now()`. Token unusable (422) |
| **Expire** | status is `pending` and `expires_at` is past | Sets `status = expired` |
| **Auto-expire** | — | `WorkspaceInvitationService::expireStaleInvitations()` marks pending+past rows expired; run by `php artisan vowspace:expire-invitations` and by the daily scheduler (`routes/console.php`) |

`WorkspaceMemberController` guards cross-organization access for revoke/resend by rejecting an
invitation whose `organization_id` does not match the route organization (404).

Revocation and resend are audited (`invitation.revoked`, `invitation.resent`).

---

## 8. Token security

- **Generation:** `Str::random(64)` — 64 random characters.
- **At rest:** only `sha256(raw)` is stored, in `token_hash` (unique). The raw token is never
  persisted.
- **Single use:** once accepted, `status = accepted` and the token cannot be reused (422).
- **Expiry:** `expires_at` is enforced on both accept (422) and the GET page (`unusable`).
- **Revocation:** revoked tokens are rejected (422) and the GET page shows `unusable`.
- **Resend rotates** the token (the previous hash row is overwritten, so the old link dies).
- **Enumeration resistance:** random/unknown tokens return 404 on GET and 404/422 on POST, and
  responses never leak `token_hash` or `organization_id` (asserted by
  `SecurityInvitationTest`).
- **Rate limiting:** 30/min on the GET page, 10/min on accept, 5/min on register.

---

## 9. Couple account claim

Couple invitations let a partner claim access to an event they belong to:

- **Roles:** `groom`, `bride`, `partner` (`EventOwnerRole`).
- **One role per event per user:** `event_owner_memberships` is unique on
  `(event_id, user_id)`; acceptance uses `updateOrCreate`.
- **No workspace membership:** accepting a couple invitation creates no
  `organization_memberships` row, so the couple gets event access without workspace
  administration.
- **No operational permissions:** couple membership grants event access, not event-staff
  permissions (see [PERMISSIONS.md](PERMISSIONS.md) §4.3).
- **Account reuse:** if an account already exists for the invitation email, it is reused —
  one account can be a couple owner on **multiple** events (asserted by
  `CoupleInvitationTest::test_single_user_can_be_couple_owner_on_multiple_events`).
- **Event-bound:** the invitation only ever grants the specific event stored in
  `event_assignments`; no sibling event is touched.
- **Revocation:** setting the membership `status = revoked` cuts event access immediately.

---

## 10. Known limitations

- **No email verification** (see [IDENTITY.md](IDENTITY.md) §8). Email match is enforced on
  acceptance, but the address itself is not verified.
- **No staff/owner membership un-revoke.** Revoked staff/owner memberships and revoked
  invitations cannot be reactivated; a fresh invitation is required.
- **Invitation emails require a working mailer / queue.** With `Mail::fake()` in tests there
  is no delivery; in production `MAIL_MAILER` must be configured (see
  [DEPLOY.md](DEPLOY.md)). The raw token is available only to the recipient's email.
