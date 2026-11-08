# Milestone 3 Migration Guide

**Milestone 3 — Event Portfolio, Workspace, Setup Wizard, Operational Playbook & Team Assignment**  
Status: Implemented — 2026-11-10

---

## 1. Summary

Milestone 3 delivers operational readiness for organizations managing multiple wedding events on VOWSPACE:

- **Operational Playbooks** — reusable operational blueprints (platform defaults and organization-customized) with snapshot-based versioning.
- **Event Setup Wizard** — step-by-step onboarding pipeline evaluating backend readiness without auto-publishing.
- **Event Workspace & Portfolio** — tenant-isolated workspace routing (`/workspace/{organization}/events...`) with lifecycle grouping, search, filters, and summary metrics.
- **Team Assignment** — direct assignment of active workspace members to events, reusing `event_staff_memberships`, with primary PIC and Run of Show item PIC support.
- **Public Schedule vs Internal Run of Show** — strict separation between guest-facing `event_schedules` and internal-only `event_run_of_show_items`.
- **Duplicate Blueprint** — safe duplication of structural data into a new Draft event within the same organization, with zero personal, transactional, or credential data leakage.

**Everything is additive and non-destructive.** No Milestone 3 migration drops a table, renames an enum, removes a column, or mutates historical data. In particular:

- `events.status` values (`draft`, `published`, `live`, `completed`, `archived`) are preserved without renaming.
- `event_schedules` remains the single source of truth for public (guest-facing) schedules.
- `event_user`, `event_owner_memberships`, and existing `event_staff_memberships` rows are untouched.
- Legacy events (including those in the Legacy Workspace) remain valid with nullable M3 columns.

---

## 2. The Seven Additive Migrations

All filenames live under `database/migrations/` with the `2026_11_10_` prefix. They must run in sequence due to foreign key dependencies.

```
playbooks
   └── playbook_versions
          └── event_setup_configs (FKs to playbooks, playbook_versions, events, event_staff_memberships)
                 ├── event_setup_checklist_items (FK to events)
                 ├── event_phases (FK to events)
                 │      └── event_sessions (FK to events, event_phases)
                 │             └── event_run_of_show_items (FK to events, event_sessions, event_phases, event_staff_memberships)
```

### 2.1 `2026_11_10_000100_create_playbooks_table.php`

Stores operational templates. `organization_id = NULL` denotes a platform-wide default template.

| Column | Type | Attributes |
| --- | --- | --- |
| `id` | bigIncrements | Primary key |
| `organization_id` | foreignId | Nullable, FK → `organizations.id`, `nullOnDelete` |
| `name` | string | Playbook title |
| `slug` | string | Unique across the table |
| `description` | text | Nullable summary |
| `event_type` | string | Nullable category (e.g. `wedding`, `traditional`) |
| `days` | unsignedTinyInteger | Default `1` (1-30) |
| `status` | string | Default `'active'`, indexed (`active` \| `inactive`) |
| `created_by` | foreignId | Nullable, FK → `users.id`, `nullOnDelete` |
| `timestamps` | timestamp | `created_at`, `updated_at` |
| Indexes | `slug` (unique), `status`, composite `(organization_id, status)` |

### 2.2 `2026_11_10_000200_create_playbook_versions_table.php`

Stores immutable configuration snapshots for playbooks. Whenever an organization updates a playbook's configuration, a new version is inserted (`version = max + 1`) rather than overwriting historical records.

| Column | Type | Attributes |
| --- | --- | --- |
| `id` | bigIncrements | Primary key |
| `playbook_id` | foreignId | FK → `playbooks.id`, `cascadeOnDelete` |
| `version` | unsignedInteger | Default `1` |
| `config` | json | The 9 configuration keys |
| `notes` | text | Nullable change notes |
| `timestamps` | timestamp | `created_at`, `updated_at` |
| Indexes | composite unique `(playbook_id, version)` |

### 2.3 `2026_11_10_000300_create_event_setup_configs_table.php`

Attaches a setup configuration to an event. An event has at most one setup config row (`event_id` is unique).

| Column | Type | Attributes |
| --- | --- | --- |
| `id` | bigIncrements | Primary key |
| `event_id` | foreignId | Unique, FK → `events.id`, `cascadeOnDelete` |
| `playbook_id` | foreignId | Nullable, FK → `playbooks.id`, `nullOnDelete` |
| `playbook_version_id` | foreignId | Nullable, FK → `playbook_versions.id`, `nullOnDelete` |
| `source_event_id` | foreignId | Nullable, FK → `events.id`, `nullOnDelete` |
| `config_snapshot` | json | Nullable frozen configuration snapshot |
| `setup_status` | string | Default `'not_started'`, indexed (`not_started`, `in_progress`, `ready_for_review`, `ready`) |
| `primary_pic_staff_membership_id` | foreignId | Nullable, FK → `event_staff_memberships.id`, `nullOnDelete` |
| `timestamps` | timestamp | `created_at`, `updated_at` |

### 2.4 `2026_11_10_000400_create_event_setup_checklist_items_table.php`

Checklist items derived from playbook configuration or populated blank. Readiness is derived exclusively from these rows.

| Column | Type | Attributes |
| --- | --- | --- |
| `id` | bigIncrements | Primary key |
| `event_id` | foreignId | FK → `events.id`, `cascadeOnDelete` |
| `label` | string | Item label |
| `description` | text | Nullable detail |
| `required` | boolean | Default `false` |
| `sequence` | unsignedSmallInteger | Default `0` |
| `completed` | boolean | Default `false` |
| `completed_at` | timestamp | Nullable |
| `source_key` | string | Nullable template identifier |
| `timestamps` | timestamp | `created_at`, `updated_at` |
| Indexes | composite `(event_id, sequence)` |

### 2.5 `2026_11_10_000500_create_event_phases_table.php`

Defines high-level operational phases (e.g. Preparation, Ceremony, Reception, Closing).

| Column | Type | Attributes |
| --- | --- | --- |
| `id` | bigIncrements | Primary key |
| `event_id` | foreignId | FK → `events.id`, `cascadeOnDelete` |
| `name` | string | Phase title |
| `sequence` | unsignedSmallInteger | Default `0` |
| `timestamps` | timestamp | `created_at`, `updated_at` |
| Indexes | composite `(event_id, sequence)` |

### 2.6 `2026_11_10_000600_create_event_sessions_table.php`

Supports multi-day and multi-session weddings. Datetimes are stored in UTC; display uses the event's timezone.

| Column | Type | Attributes |
| --- | --- | --- |
| `id` | bigIncrements | Primary key |
| `event_id` | foreignId | FK → `events.id`, `cascadeOnDelete` |
| `phase_id` | foreignId | Nullable, FK → `event_phases.id`, `nullOnDelete` |
| `name` | string | Session title (e.g. "Akad Nikah", "Resepsi Malam") |
| `session_date` | date | Wall-clock session date |
| `start_time` | dateTime | Nullable, UTC |
| `end_time` | dateTime | Nullable, UTC |
| `timezone` | string | Default `'Asia/Jakarta'` |
| `sequence` | unsignedSmallInteger | Default `0` |
| `timestamps` | timestamp | `created_at`, `updated_at` |
| Indexes | composite `(event_id, session_date)` |

### 2.7 `2026_11_10_000700_create_event_run_of_show_items_table.php`

Internal operational timeline. Strictly isolated from guest-facing endpoints.

| Column | Type | Attributes |
| --- | --- | --- |
| `id` | bigIncrements | Primary key |
| `event_id` | foreignId | FK → `events.id`, `cascadeOnDelete` |
| `event_session_id` | foreignId | Nullable, FK → `event_sessions.id`, `nullOnDelete` |
| `phase_id` | foreignId | Nullable, FK → `event_phases.id`, `nullOnDelete` |
| `title` | string | Activity title |
| `internal_description` | text | Nullable internal instructions |
| `planned_start` | dateTime | Nullable, UTC |
| `planned_end` | dateTime | Nullable, UTC |
| `actual_start` | dateTime | Nullable, UTC |
| `actual_end` | dateTime | Nullable, UTC |
| `status` | string | Default `'pending'`, indexed (`pending`, `in_progress`, `completed`, `delayed`, `cancelled`) |
| `sequence` | unsignedSmallInteger | Default `0` |
| `pic_staff_membership_id` | foreignId | Nullable, FK → `event_staff_memberships.id`, `nullOnDelete` |
| `internal_notes` | text | Nullable notes (never exposed publicly) |
| `created_by` | foreignId | Nullable, FK → `users.id`, `nullOnDelete` |
| `updated_by` | foreignId | Nullable, FK → `users.id`, `nullOnDelete` |
| `timestamps` | timestamp | `created_at`, `updated_at` |
| Indexes | `status`, composite `(event_id, sequence)` |

---

## 3. Platform Playbook Seeder

Milestone 3 introduces `PlatformPlaybookSeeder`, registered in `DatabaseSeeder`:

```bash
php artisan db:seed --class=PlatformPlaybookSeeder
```

The seeder is **idempotent** (`firstOrCreate` keyed on `slug`). It creates five platform-wide defaults (`organization_id = NULL`, `status = 'active'`), each with an initial `PlaybookVersion` (v1) containing the full 9-key configuration schema:

1. `akad-resepsi` — Pernikahan Akad dan Resepsi (1 day, 2 sessions)
2. `satu-sesi` — Pernikahan Satu Sesi (1 day, 1 session)
3. `adat-rangkaian` — Pernikahan Adat dengan Beberapa Rangkaian (2 days, 3 sessions)
4. `multi-pintu` — Resepsi dengan Beberapa Pintu Masuk (1 day, multi-gate reception)
5. `multi-day` — Pernikahan Multi-day (3 days, 4 sessions)

---

## 4. Legacy Event Compatibility

Per confirmed architectural decision #8:

- **No automatic backfill** is performed on existing events.
- All new foreign keys and setup relations on `events` are nullable.
- When an existing/legacy event is opened in the new Event Workspace:
  - Setup readiness displays as **"Belum Dikonfigurasi"** (`setup_status = not_started`).
  - The Event Overview renders clean empty states for phases, sessions, team roster, and Run of Show.
  - Existing `event_schedules` rows continue to be served on public guest views without disruption.
  - The workspace manager may optionally run the Setup Wizard at any time to attach a playbook or configure the event manually.
- Historical data (guests, RSVPs, check-ins, media, guestbook messages, gift transactions) remains completely untouched.

---

## 5. Deployment Sequence

### Pre-Deployment Checklist

1. Verify environment PHP version (>= 8.2; production runs PHP 8.5).
2. Take a standard full database snapshot before running migrations.
3. Confirm that the application is in maintenance mode if deploying under live traffic (recommended during schema updates).

### Execution

```bash
# 1. Enter maintenance mode
php artisan down --render="errors::503" --secret="<deploy-secret>"

# 2. Pull latest code
git fetch origin
git checkout master
git pull --ff-only origin master

# 3. Install composer dependencies (no dev in production)
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Run the additive migrations
php artisan migrate --force

# 5. Seed platform playbooks (idempotent)
php artisan db:seed --class=PlatformPlaybookSeeder --force

# 6. Cache framework configuration, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Exit maintenance mode
php artisan up
```

---

## 6. Verification & Health Checks

After deployment, verify the following:

```bash
# Check that all 7 new migrations are recorded as ran
php artisan migrate:status | grep 2026_11_10

# Verify platform playbooks are present (expect 5)
php artisan tinker --execute 'echo \App\Models\Playbook::whereNull("organization_id")->count();'

# Verify workspace routes are compiled and registered
php artisan route:list --path=workspace/ --name=workspace.events

# Verify test suite pass on deployment target (if test runner is configured)
php artisan test --filter=M3 --compact
```

---

## 7. Rollback & Recovery Procedures

Because all Milestone 3 migrations are purely additive and use proper `down()` implementations, rollback can be executed in exact reverse order:

```bash
# Roll back the 7 Milestone 3 migrations
php artisan migrate:rollback --step=7
```

### Rollback Risk Assessment

- **If no new Milestone 3 records have been created:** Rollback is clean, instantaneous, and zero-risk. Legacy data is unaffected.
- **If events have started using Playbooks, Sessions, or Run of Show:**
  - Rolling back will drop the 7 new tables (`CASCADE` foreign keys drop child rows).
  - Existing `events` and `event_schedules` rows **will remain intact**.
  - Any Run of Show entries, playbook definitions, or session structures created since deployment **will be permanently removed**.
  - Therefore, prefer a forward-fix over rolling back migrations if users have already entered live data.
