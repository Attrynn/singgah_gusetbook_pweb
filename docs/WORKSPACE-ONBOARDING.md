# Workspace Onboarding

**Milestone 2 — Identity, Membership & Permission Foundation**
Status: Implemented — 2026-11-09

---

## 1. Purpose

How a person turns into a workspace owner, and how an existing account creates extra
workspaces. Both paths are backed by real routes, real validation, and a real database
transaction — nothing here is a mock.

Related reading: [IDENTITY.md](IDENTITY.md) (the model), [PERMISSIONS.md](PERMISSIONS.md)
(what an Owner can do), [INVITATIONS.md](INVITATIONS.md) (inviting others in).

---

## 2. The registration journey

A brand-new visitor at `/register` becomes the **Owner of a brand-new workspace**:

```
GET  /register                     → registration form
POST /register  (register.store)   → register + workspace + owner membership → /owner
```

Route definitions (`routes/web.php`):

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET | `/register` | `register` | `guest` |
| POST | `/register` | `register.store` | `guest`, `throttle:5,1` |

`RegisterController::store()` validates and delegates to
`WorkspaceRegistrationService::register()`, then `Auth::login()` + session regeneration, and
redirects to `owner.dashboard` with a status flash.

### What gets written

`WorkspaceRegistrationService::register()` runs a single `DB::transaction` creating:

1. a `users` row (`role = owner`, `is_active = true`, hashed password);
2. an `organizations` row (`status = 'active'`, unique slug);
3. an `organization_memberships` row (`role = owner`, `status = active`,
   `accepted_at = now()`).

The user is therefore a Workspace Owner immediately, and `User::isWorkspaceOwner($org)` is
true. No `event_owner_memberships` or `event_staff_memberships` rows are created — those are
per-event and come later (see [INVITATIONS.md](INVITATIONS.md)).

---

## 3. Validation & security

`RegisterController::store()` accepts **only** four fields; privileged fields are never read
from the request:

| Field | Rules |
| --- | --- |
| `name` | `required`, `string`, `max:255` |
| `email` | `required`, `email:rfc,dns`, `max:255`, `unique:users,email` |
| `password` | `required`, `confirmed`, `Password::min(8)->letters()->numbers()` |
| `workspace_name` | `required`, `string`, `max:255` |

Hardened by design:

- **`platform_role`, `role`, and `organization_id` sent in the body are ignored.** The
  service hard-codes `role = UserRole::Owner` and never sets `platform_role`. This is
  asserted by `WorkspaceRegistrationTest` and `SecurityInvitationTest`
  (`test_register_store_cannot_elevate_platform_role_or_role`).
- **Email is lower-cased and trimmed** before the uniqueness check and insert.
- **Duplicate email** fails validation and creates nothing (no orphan organization).
- **The workspace slug** is derived with `Str::slug()` (fallback `workspace`) and made unique
  with a numeric suffix (`gadun-org`, `gadun-org-2`, …).
- **Rate limiting:** the registration POST is throttled at 5 requests/minute.
- **DNS validation:** `email:rfc,dns` performs a real DNS lookup (tests call
  `Validator::fakeDnsLookups()` to stay deterministic).
- **Session fixation:** the session is regenerated after login.

### Privileged-field payload (rejected by construction)

```json
{
  "name": "New User",
  "email": "platform@example.com",
  "password": "Password1",
  "password_confirmation": "Password1",
  "workspace_name": "Platform Org",
  "platform_role": "superadmin",
  "role": "superadmin",
  "organization_id": 999
}
```

Result: the user is created with `role = owner`, `platform_role = null`.

---

## 4. Atomicity

`register()` is wrapped in `DB::transaction`. If the user, organization, or membership insert
fails for any reason, the whole thing rolls back — you never get a user without a workspace or
a workspace without an owner.

Validation happens **before** the transaction, so a failed validation writes nothing at all
(asserted by `test_failed_validation_writes_nothing`).

---

## 5. Creating an additional workspace (existing account)

An already-authenticated user can create more workspaces without creating a new account:

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET | `/workspace/create` | `workspace.create` | `auth`, `auth.session`, `active` |
| POST | `/workspace/create` | `workspace.store` | `auth`, `auth.session`, `active` |

`WorkspaceController::store()` validates `workspace_name` and calls
`WorkspaceRegistrationService::createWorkspaceForExistingUser()`, which — again inside a
transaction — creates a new `organizations` row and an `organization_memberships` row with
`role = owner`. **No new `users` row is created**; the same global identity now owns two
workspaces. This is asserted by
`test_authenticated_user_can_create_additional_workspace_without_new_user`.

---

## 6. Legacy Workspace handling

Events that existed before tenancy are associated with a single organization:

- Name: **Legacy Workspace**, slug `legacy-workspace` (from `config('vowspace.organization')`),
  status `active`.

Rules:

- **Legacy Workspace is never auto-assigned to a registrant.** New registrants always get a
  freshly created organization (their `workspace_name`). The slug generator only avoids
  *slug* collisions; it never joins an existing org.
- Legacy membership is deliberately empty. As [TENANCY.md](TENANCY.md) states, the
  `event_user` pivot (groom/bride/partner) is event-scoped ownership and **must not** be
  auto-converted into organization memberships.
- The backfill command `php artisan vowspace:backfill-legacy-workspace` (idempotent,
  `--dry-run` supported) assigns **existing** events lacking an `organization_id` to Legacy
  Workspace. It does **not** create memberships.
- `EventService::create()` uses `Organization::firstOrCreate()` with the legacy slug only as
  a fallback when no organization is supplied; every new event always ends up with an
  `organization_id`. See [MILESTONE-2-MIGRATION.md](MILESTONE-2-MIGRATION.md).

---

## 7. Failure / recovery notes

| Situation | Result |
| --- | --- |
| Validation fails (weak password, duplicate email, missing name) | 302 back with errors; **nothing written** |
| Duplicate email on registration | `email` error; no second user, no org |
| Email already registered when accepting an invitation | Redirect to `login` with an "email already exists" error (see [INVITATIONS.md](INVITATIONS.md)) |
| Any DB error mid-registration | Transaction rolls back — no partial user/org |
| Authenticated user visits `/register` | Redirected to `owner.dashboard` (form is `guest`-only) |
| Unauthenticated user visits `/workspace/create` | Redirected to `login` |
| Inactive user with a live session | Logged out and session invalidated by the `active` middleware |

Recovery guidance:

- If a user reports "my workspace is missing", check `organization_memberships` for that
  `user_id`. A missing row means the transaction rolled back.
- Slug collisions never overwrite: a second "Gadun Org" becomes `gadun-org-2`.
- There is **no self-service delete** for a workspace in this milestone.

---

## 8. Known limitations

- **No email verification.** `App\Models\User` does not implement `MustVerifyEmail`, and no
  verification route or middleware exists. `email_verified_at` is not set at registration
  and is not enforced. Both registration and invitation acceptance trust the submitted email.
  See [IDENTITY.md](IDENTITY.md) §8.
- **No password reset flow** is part of this milestone; users are created with the password
  they supply.
- **No `Organization::users()->attach()` path.** Creating memberships that way is broken
  (see [IDENTITY.md](IDENTITY.md) §8); always go through the services or
  `OrganizationMembership::create()`.
- **Owner routes are still gated by the legacy `event_user` pivot**, so a freshly registered
  Owner reaches `/owner` only once an event is attached to them; with no events,
  `ResolvesOwnerEvent` returns 403. Workspace management happens under `/workspace/{org}`,
  which requires an active membership.
