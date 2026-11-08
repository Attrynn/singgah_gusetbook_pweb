# Operational Playbook

**Milestone 3 — Event Workspace, Setup & Operations**
Status: Implemented — 2026-11-10

---

## 1. Purpose

A **playbook** is a reusable, versioned definition of how an event is structured: its
phases, checklist, public schedule, run-of-show template, team-role requirements, and
defaults. Applying a playbook to an event seeds that structure in one step. Playbooks are
the building block behind the wizard's **Blueprint** step and the workspace **Playbook**
tab.

Model: `App\Models\Playbook` / `App\Models\PlaybookVersion`
Service: `App\Services\PlaybookService`
Controller: `App\Http\Controllers\Workspace\WorkspacePlaybookController`

Related reading: [EVENT-SETUP-WIZARD.md](EVENT-SETUP-WIZARD.md) (how a blueprint is applied),
[MILESTONE-3-MIGRATION.md](MILESTONE-3-MIGRATION.md) (`playbooks` / `playbook_versions`).

---

## 2. Platform playbooks vs organization playbooks

Both live in the **same `playbooks` table**. The only difference is `organization_id`:

| | Platform playbook | Organization playbook |
| --- | --- | --- |
| `organization_id` | `NULL` | the owning organization's id |
| `Playbook::isPlatform()` | `true` | `false` |
| Scope helpers | `scopePlatform()` | `scopeForOrganization($org)` |
| Editable in a workspace? | **No** — read-only (`update`/`deactivate` abort 404) | Yes, by an org admin |
| Visible to | every workspace | only its owning workspace |
| Readable in `show` | any authorized user | must belong to the route organization, else 404 |
| Duplicable into an org? | Yes (`duplicate` copies a platform template into the workspace) | Yes |

`PlaybookService::platformTemplates()` returns active platform playbooks;
`forOrganization($org)` returns the organization's active playbooks. Both eager-load
`latestVersion` and are ordered by name. The create form and the wizard blueprints step
concatenate the two.

---

## 3. Config structure — the 9 snapshot keys

Every playbook version carries a `config` JSON object. `PlaybookService::CONFIG_KEYS`
defines the canonical key set, and `normalizeConfig()` guarantees each key exists and is an
**array** (missing keys default to `[]`; unknown keys are dropped; a non-array value throws
a `ValidationException`).

| # | Key | Contents (as used by the seeded templates and setup service) |
| --- | --- | --- |
| 1 | `phases` | `[{ name, sequence }]` — event phases |
| 2 | `checklist` | `[{ key/source_key, label, required }]` — readiness items |
| 3 | `public_schedule` | `[{ title, description }]` — guest-visible schedule template |
| 4 | `run_of_show` | `[{ title, description }]` — internal run-of-show template |
| 5 | `team_roles` | `[{ role, label, qty }]` — staff-role requirements |
| 6 | `vendor_categories` | `[ "catering", "dekorasi", ... ]` |
| 7 | `reception` | `{ doors, seating, welcome_drink }` |
| 8 | `invitation_defaults` | `{ digital, rsvp, personal_url }` |
| 9 | `guestbook_defaults` | `{ enabled, moderation }` |

> Note: `vendor_categories` and the `*_defaults` keys are stored as configuration for future
> modules. M3 does **not** create vendor, invitation, or guestbook records from them.

The setup service maps these keys as follows:
`phases` → `event_phases`; `checklist` → `event_setup_checklist_items`;
`sessions` → `event_sessions`; `run_of_show` / `public_schedule` / the remaining keys are
kept in `event_setup_configs.config_snapshot`.

---

## 4. Versioning

Playbooks are **immutable by version**:

- `PlaybookService::create()` creates the playbook **and version 1** in one transaction.
- `PlaybookService::update()` updates only the playbook's metadata (`name`, `description`,
  `event_type`, `days`, `status`, and `slug` if changed). If `config` is present, it does
  **not** mutate the existing version — it calls `createVersion()`, which appends a new
  `playbook_versions` row with `version = max(version) + 1`.
- `playbook_versions` has a `unique(playbook_id, version)` constraint.
- `Playbook::latestVersion()` is a `hasOne(...)->latestOfMany('version')` relation.

Audit actions: `playbook.created`, `playbook.updated`, `playbook.duplicated`,
`playbook.deactivated`, `playbook.version_created`.

---

## 5. Event config snapshot immutability

When a blueprint is applied, `EventSetupService::applyPlaybook()` writes the chosen
version's config into **`event_setup_configs.config_snapshot`** and records the
`playbook_id` + `playbook_version_id` on the same row. This snapshot is the event's own
copy:

- Later edits to the playbook (which create new versions) **do not** change any event's
  `config_snapshot`.
- Re-applying a different version overwrites the snapshot with that version's config (and
  rebuilds phases/checklist). `applyPlaybook()` calls `syncStructure()`, which deletes the
  event's existing checklist items and phases before recreating them from the new config.

This is how an event stays faithful to the exact configuration it was created from even as
playbooks evolve.

---

## 6. Reuse

| Route name | Action | Notes |
| --- | --- | --- |
| `workspace.playbooks.index` | List | platform + organization playbooks, grouped |
| `workspace.playbooks.show` | View | versions (newest first) + latest version |
| `workspace.playbooks.store` | Create | org-owned playbook + version 1 |
| `workspace.playbooks.update` | Edit | metadata update; config edit → new version |
| `workspace.playbooks.duplicate` | Duplicate | copies the **latest version** config into a new org playbook, or into the same owning org for an org playbook |
| `workspace.playbooks.deactivate` | Deactivate | sets `status = inactive` (soft; row retained) |

Playbooks are also consumed from the **event create form** and the **wizard blueprint step**
(the latter resolves `playbook_version_id` only within the route org or platform scope).
`deactivate` only flips status — it does not delete, and existing events keep their snapshot.

---

## 7. Permissions

| Identifier | Where enforced |
| --- | --- |
| `playbook.view` | Defined in `PermissionRegistry`; present in the Owner/Admin sets |
| `playbook.manage` | `OrganizationPolicy::managePlaybooks`, consumed by every workspace playbook route |

`OrganizationPolicy::managePlaybooks($user, $org)` returns `true` for legacy Superadmin and
Platform Superadmin, otherwise defers to `PermissionService::hasOrgPermission($user, $org,
playbook.manage)`. Because Workspace Owner and Admin both carry `playbook.manage` (Admin is
Owner minus `organization.manage_roles`), both can manage their workspace's playbooks.
**Event staff roles do not receive playbook permissions.**

> The `playbook.view` identifier is defined and included in the Owner/Admin sets but is not
> consumed by a dedicated check — all playbook routes go through `managePlaybooks`.

---

## 8. The five seeded platform templates

`database/seeders/PlatformPlaybookSeeder` seeds five platform templates (organization_id
`null`, `event_type = wedding`). It is idempotent: playbooks are keyed by slug and version 1
is created only when missing. It is registered in `DatabaseSeeder`.

| # | Name | Slug | Days | Phases |
| --- | --- | --- | --- | --- |
| 1 | Pernikahan Akad dan Resepsi | `akad-resepsi` | 1 | Persiapan, Akad Nikah, Resepsi |
| 2 | Pernikahan Satu Sesi | `satu-sesi` | 1 | Persiapan, Acara Inti |
| 3 | Pernikahan Adat dengan Beberapa Rangkaian | `adat-rangkaian` | 1 | Persiapan Adat, Prosesi Adat, Akad Nikah, Resepsi |
| 4 | Resepsi dengan Beberapa Pintu Masuk | `multi-pintu` | 1 | Persiapan, Resepsi |
| 5 | Pernikahan Multi-day | `multi-day` | 2 | Hari Pertama, Hari Kedua |

Each template's config includes all nine keys. `team_roles` is generated from
`TEAM_ROLE_QUANTITIES` and mirrors the seven `EventStaffRole` values:

| Role | Default qty |
| --- | --- |
| `event_manager` | 1 |
| `guest_manager` | 1 |
| `checkin_supervisor` | 1 |
| `checkin_staff` | 2 |
| `seating_usher` | 2 |
| `media_curator` | 1 |
| `gift_steward` | 1 |

Seed with:

```bash
php artisan db:seed --class=PlatformPlaybookSeeder
# or, as part of a full seed:
php artisan db:seed
```

---

## 9. Intentionally out of scope for M3

- **Vendor Management** — `vendor_categories` is configuration only; no vendor records/UI.
- **Invitation Studio** — `invitation_defaults` is stored, not applied to invitations.
- **Task Management** — `checklist` seeds readiness items, not assignable tasks.
- No playbook **scheduling**, marketplace, or cross-organization sharing beyond
  platform→organization duplication.
