# Milestone 8 — Venue Management, Floor Plans & Seating

Status: **implementation complete, verification passed** (660 backend tests, 50 frontend tests, build and Blade lint clean)
Baseline: Milestone 7 release `28295bc` (tag `v7.0`), branch `master`
Stack: Laravel 13.40.0 · PHP 8.5.12 · Node 26.12.1 · Vite 8 · Alpine 3.17.5 · Tailwind 4 · Konva 10.9.1

---

## 1. Outcome

Milestone 8 adds a spatial planning layer to the event workspace without replacing any
existing domain:

| Capability | Delivered |
| --- | --- |
| Organization-scoped venue catalog | `venues` + `venue_spaces`, reusable across events, linked to `event_venues` by nullable `venue_id` |
| Event floor plans | `floor_plans`, each owning an ordered chain of versions |
| Freeform 2D canvas editor | Konva-based editor with object library, drag/resize/rotate, multi-select, groups, layers, zoom/pan, grid, snapping, undo/redo, copy/paste, keyboard shortcuts, autosave |
| Tables and chairs | Individual chair objects, automatic seat generation around round/rectangular tables, manual chair edits, capacity-vs-chair warnings, safe regeneration |
| Zones | Structured zone types plus rect/polygon geometry; identity is never colour-only |
| Seating | Version-aware assignments using M5 party identity and named individuals, with party headcount placeholders |
| Versioning | Draft → Published → Archived; published versions immutable; edits create a new draft; historical assignments retained |
| M6/M7 spatial links | Polymorphic `spatial_references`; no destructive change to M6/M7 tables |
| Security | Event-centric tenancy checks on every query, mutation and endpoint; new permissions through the existing registry |
| Audit | Publish, version creation, archive, seating changes, venue/floor-plan changes, spatial links |

Out of scope and not attempted: 3D/BIM/CAD, realtime multiplayer, vendor marketplace rebuild,
guest/staff/vendor domain replacement, AI floor-plan generation.

---

## 2. Architecture overview

```
HTTP (Blade + Alpine)                    Domain services                     Persistence
─────────────────────                    ───────────────                     ───────────
WorkspaceVenueController        ──▶  VenueService                 ──▶  venues, venue_spaces
WorkspaceFloorPlanController    ──▶  FloorPlanService             ──▶  floor_plans
WorkspaceFloorPlanVersionCtrl   ──▶  FloorPlanVersionService      ──▶  floor_plan_versions
                                       FloorPlanDocumentValidator
                                       FloorPlanValidationService
                                ──▶  SeatingAssignmentService     ──▶  seating_assignments
                                ──▶  SpatialReferenceService      ──▶  spatial_references
```

Design rules applied:

1. **Event is the security boundary.** Every service method receives the `Event` and derives
   `organization_id` from it; no endpoint accepts a tenant id from the client.
2. **The canvas is untrusted input.** The browser posts a JSON document; the backend re-validates
   every identifier, coordinate, object type, property bag and reference before persisting.
3. **Versions are the unit of immutability.** A `floor_plan_version` row is an immutable snapshot
   once published. Editing always targets a draft.
4. **No domain duplication.** Guests remain `guests` + `guest_party_memberships`; operations
   remain M6 records; vendors remain M7 records. M8 stores only spatial context and layout data.
5. **Derived counters are persisted.** `object_count`, `table_count`, `chair_count`,
   `zone_count`, `seat_capacity` are computed on save so listings never parse the document.
6. **Optimistic concurrency.** Each version carries `lock_version`; saves with a stale value are
   rejected with HTTP 409.

Service responsibilities:

- `VenueService` — organization-scoped venue and space CRUD, archive guards.
- `FloorPlanService` — plan CRUD, unique names per event, venue/space link resolution, delete guard.
- `FloorPlanVersionService` — draft creation/cloning, document save, publish, archive, stats, lock version.
- `FloorPlanDocumentValidator` — authoritative JSON schema, normalization and hard limits.
- `FloorPlanValidationService` — advisory layout issues (errors block publishing; warnings do not).
- `SeatingAssignmentService` — assignment, capacity, duplicates, seat occupancy, release, review flags.
- `SpatialReferenceService` — polymorphic M6/M7 links and review flags.

---

## 3. Database schema

Three additive migrations; no existing column is removed or renamed.

### 3.1 `venues` (organization scope)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `organization_id` | FK → organizations | cascade delete |
| `name` | varchar(120) | unique per organization |
| `description`, `address` | text nullable | plain text |
| `google_maps_url` | varchar(2048) nullable | https only |
| `latitude`, `longitude` | decimal(10,7) nullable | range checked |
| `timezone` | varchar(64) nullable | |
| `status` | varchar(20) | `active` / `archived` |
| `archived_at` | timestamp nullable | |
| `created_by` | FK → users nullable | null on delete |

Unique keys: `(organization_id, name)`, `(organization_id, id)` (tenant-safe composite reference).

### 3.2 `venue_spaces` (venue scope)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `organization_id` | FK → organizations | cascade delete |
| `venue_id` | composite FK `(organization_id, venue_id)` → `venues(organization_id, id)` | prevents cross-tenant space linkage |
| `name` | varchar(120) | unique per venue |
| `type` | varchar(30) | `VenueSpaceType` |
| `floor_label` | varchar(60) nullable | |
| `description` | text nullable | |
| `capacity` | unsigned int nullable | 0–100000 |
| `sort_order` | unsigned smallint | default 0 |
| `status`, `archived_at` | | same lifecycle as venues |

Unique keys: `(venue_id, name)`, `(organization_id, id)`.

### 3.3 `event_venues` (additive link only)

Adds nullable `venue_id` → `venues.id` (`nullOnDelete`) plus an index. The legacy event-level
`venue_name` / `venue_address` columns remain the operational anchor; M8 never rewrites them.

### 3.4 `floor_plans`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `organization_id` | FK → organizations | cascade delete |
| `event_id` | FK → events | cascade delete |
| `venue_id`, `venue_space_id` | nullable FK | null on delete |
| `name` | varchar(120) | unique per event |
| `description` | text nullable | |
| `status` | varchar(20) | `active` / `archived` |
| `archived_at`, `created_by` | | |

Unique keys: `(event_id, name)`, `(event_id, id)` (used by version FK).

### 3.5 `floor_plan_versions`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `organization_id` | FK → organizations | cascade delete |
| `event_id`, `floor_plan_id` | composite FK `(event_id, floor_plan_id)` → `floor_plans(event_id, id)` | cascade delete |
| `version_number` | unsigned int | unique per plan |
| `status` | varchar(20) | `draft` / `published` / `archived` |
| `schema_version` | unsigned smallint | default 1 |
| canvas columns | width/height/grid/snap/background | denormalized from document |
| `document` | json | validated canvas document |
| derived counters | object/table/chair/zone/seat_capacity | recomputed on save |
| `lock_version` | unsigned int | optimistic concurrency |
| `created_by`, `published_by`, `published_at`, `archived_by`, `archived_at` | | audit attribution |

Unique keys: `(floor_plan_id, version_number)`, `(event_id, id)` (used by assignment/reference FKs).

### 3.6 `seating_assignments`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `organization_id` | FK → organizations | cascade delete |
| `event_id`, `floor_plan_version_id` | composite FK → `floor_plan_versions(event_id, id)` | cascade delete |
| `floor_plan_id` | bigint | denormalized for reporting |
| `table_object_id` | varchar(64) | stable document object id |
| `seat_object_id` | varchar(64) nullable | stable chair id |
| `guest_id` | composite FK `(event_id, guest_id)` → `guests(event_id, id)` | M5 party |
| `guest_party_membership_id` | composite FK `(event_id, id)` → `guest_party_memberships(event_id, id)` | M5 named individual |
| `headcount` | unsigned smallint | 1 for individuals; 1–100 for placeholders |
| `status` | varchar(20) | `active` / `released` / `needs_review` |
| `assigned_by`, `assigned_at`, `released_at`, `release_reason` | | audit trail |

Indexes cover version/table/status, version/seat/status, event/member/status, event/guest/status.
`guest_party_memberships` gains a unique `(event_id, id)` key so the composite FK is valid on MySQL
and SQLite.

### 3.7 `spatial_references`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `organization_id` | FK → organizations | cascade delete |
| `event_id`, `floor_plan_version_id` | composite FK → `floor_plan_versions(event_id, id)` | cascade delete |
| `floor_plan_id` | bigint | |
| `object_id` | varchar(64) | document object |
| `object_type` | varchar(30) | `FloorPlanObjectType` value |
| `referenceable_type` | varchar(60) | FQCN of the M6/M7 model |
| `referenceable_id` | bigint | target row |
| `label` | varchar(160) nullable | human hint |
| `status` | varchar(20) | `active` / `needs_review` |
| `created_by` | FK → users nullable | |

Unique `(referenceable_type, referenceable_id)` — one spatial anchor per M6/M7 record.
Indexes on `(floor_plan_version_id, object_id)` and `(event_id, status)`.

**Migration verification:** applied, rolled back and re-applied on MariaDB 13.0.2 (MySQL protocol)
and SQLite. All generated foreign-key names are ≤ 43 characters.

---

## 4. Canvas document schema

Each `floor_plan_versions.document` is a JSON object validated by
`App\Services\FloorPlanDocumentValidator` (`schema_version = 1`):

```json
{
  "schema_version": 1,
  "canvas": {
    "width": 2000,
    "height": 1500,
    "unit": "cm",
    "grid_size": 50,
    "grid_visible": true,
    "snap_enabled": true,
    "background_color": "#ffffff"
  },
  "objects": [
    {
      "id": "obj_a1b2c3d4",
      "type": "round_table",
      "x": 100,
      "y": 100,
      "width": 180,
      "height": 180,
      "rotation": 0,
      "z_index": 0,
      "visible": true,
      "locked": false,
      "label": "Meja 1",
      "group_id": null,
      "parent_id": null,
      "properties": {
        "capacity": 10,
        "table_number": "1",
        "color": "#f5f5f5",
        "table_id": null,
        "seat_index": 0,
        "seat_spacing": 30,
        "generated": true
      }
    }
  ],
  "zones": [
    {
      "id": "zone_a1b2c3d4",
      "type": "vip",
      "name": "Area VIP",
      "color": "#ffcc00",
      "description": null,
      "visible": true,
      "capacity": 50,
      "geometry": { "kind": "rect", "x": 0, "y": 0, "width": 500, "height": 400 }
    }
  ]
}
```

Zone geometry also supports polygons:

```json
{ "kind": "polygon", "points": [[0,0], [500,0], [500,400]] }
```

Object types (34) are grouped as seating (chair, round/rectangle/square/VIP/family/custom table),
venue (stage, dance floor, entrance, exit, door, wall, pillar, partition, staircase, aisle),
operational (buffet, catering, photo booth, guest reception, registration desk, vendor station,
staff station, sound area, lighting area, restricted area) and general shapes (rectangle, circle,
ellipse, line, polygon, text label, custom shape).

Zone types (14): main reception, VIP, family, guest, stage, dining, buffet, photography, vendor,
restricted, entrance, emergency exit, ceremony, other. Zone identity is the structured `type`; colour
is decoration only.

Server-side normalization on every save:

- object ids match `^[A-Za-z0-9_-]{8,64}$`; ids are unique across objects **and** zones;
- numeric coordinates rounded to 2 decimals; rotation normalized into `[0, 360)`;
- labels/descriptions stripped of HTML and truncated;
- property keys must match `^[a-z][a-z0-9_]{0,39}$`, max 30 keys, depth ≤ 3, list ≤ 50 items,
  string values ≤ 500 chars;
- table `capacity` must be an integer 0–200; chair `seat_index` 0–500;
- every chair `properties.table_id` must reference a table present in the same document;
- canvas unit is forced to `cm`.

Hard limits: 2 MB payload, 2,000 objects, 200 zones, coordinates ±100,000, canvas 100–100,000 per
axis, grid 1–1,000.

---

## 5. Coordinate system and units

- Unit: **centimetres** (`unit: "cm"`), enforced by the validator.
- Origin: **top-left** of the canvas.
- Axes: **+x right**, **+y down** (screen convention).
- Object position `(x, y)` is the **top-left of the unrotated bounding box**; `width`/`height` are
  the unrotated extents; `rotation` is **degrees clockwise** about the object centre.
- Zones use absolute world geometry (`rect` x/y/width/height or polygon points).
- Chairs generated around a table are ordinary objects with absolute world coordinates and
  `properties.table_id` / `properties.seat_index` linking them to the parent table.
- **Viewport zoom and pan are never persisted.** They are editor UI state only, so two users with
  different screen sizes load the same layout.
- All persisted values are world units; screen pixels exist only inside the Konva stage transform.

This keeps the document portable and lets the backend validate geometry without knowing anything
about the browser.

---

## 6. Canvas engine choice

**Konva.js 10.9.1**, bundled by Vite and self-hosted.

Why Konva:

- Mature 2D scene graph with layers, groups, transformers, hit detection and drag events out of the box.
- No framework runtime requirement (works with the existing Blade + Alpine stack; no React/Vue added).
- Fine-grained control over world-vs-screen transforms, which is required for a centimetre canvas.
- Small enough to bundle locally, satisfying the CSP (`script-src 'self'`; no CDN).
- Pure-JS ESM modules can be unit-tested without a browser DOM for document/geometry/seat logic.

Alternatives rejected: raw Canvas (too much interaction plumbing), Fabric.js (heavier, weaker
grouping/transformer ergonomics for this use case), SVG/DOM (poor performance at 2,000 objects),
React Flow (wrong paradigm and would add React).

The editor is split so that **all layout logic is framework-free ESM** (`document.js`, `geometry.js`,
`commands.js`, `seats.js`, `serializer.js`), and only `stage.js` / `index.js` touch Konva and Alpine.
That separation is what makes the pure-logic tests possible.

---

## 7. Versioning lifecycle

```
create plan ──▶ v1 draft ──save──▶ v1 draft ──publish──▶ v1 published (immutable)
                                                             │
                                            create next draft│ (clone published document)
                                                             ▼
                                                          v2 draft ──publish──▶ v2 published
```

Rules enforced by `FloorPlanVersionService`:

1. A plan always starts with a blank draft (`version_number = 1`, empty objects/zones).
2. Only **one active draft** per plan; creating another is rejected with a validation error.
3. `saveDocument` rejects non-draft versions — published and archived layouts are immutable.
4. `publish` requires `publishFloorPlans`; it runs the layout validator and blocks on any
   `severity = error`. Warnings/info never block.
5. Publishing stamps `status`, `published_by`, `published_at` and increments `lock_version`.
6. `createNextDraft` clones the latest **published** document into a new draft with the next
   version number. Historical versions remain queryable.
7. Archiving is idempotent and stamps `archived_by` / `archived_at`.
8. A plan with any published version cannot be deleted; archive it instead.
9. Assignments and spatial references point at a specific `floor_plan_version_id`, so history is
   retained when a new draft is created. Objects removed from a draft cause their assignments and
   references to be flagged `needs_review` on the next save rather than silently deleted.

Concurrency: the client sends the `lock_version` it loaded. A mismatch returns **HTTP 409** and the
client must reload; last-write-wins is never silent.

---

## 8. Seating model (M5 integration)

M5 identity model (unchanged):

- `guests` = an invitation **party** with a quota (`max_attendees`).
- `guest_party_memberships` = a **named individual** inside that party (passes, check-in attribution).
- Unidentified quota slots are deliberately not rows.

M8 seating therefore supports two shapes:

| Shape | Stored as | Use |
| --- | --- | --- |
| Named individual | `guest_id` + `guest_party_membership_id`, `headcount = 1` | Usher knows exactly who sits where |
| Party placeholder | `guest_id` + `guest_party_membership_id = null` + `headcount` | Party invited as a quota, names not collected |

Validation performed by `SeatingAssignmentService`:

- the version must be a draft;
- the table id must exist and be a table object; capacity must be > 0;
- the seat id, when supplied, must exist, be a chair, and belong to the chosen table;
- the guest must belong to the same event; the member must belong to that guest;
- placeholder `headcount` must not exceed the party's `max_attendees`;
- the sum of active `headcount` for the table must not exceed table capacity;
- the same individual (or the same party placeholder) cannot have two active assignments in the event;
- a seat cannot hold two active assignments.

Release is soft: `status = released`, `released_at`, optional `release_reason`. Rows are never
deleted, so historical seating remains auditable. `tableSummary()` returns capacity / assigned /
remaining / chair count per table for the editor and API.

Regeneration safety: chairs are real objects. The editor distinguishes generated chairs
(`properties.generated = true`) from manually moved ones and asks before overwriting manual edits;
the backend never regenerates chairs on its own.

---

## 9. Tenant isolation

- Routes are nested under `workspace/{organization}/events/{event}` with `auth`, `auth.session`,
  `active`, `org.context`, `org.member`.
- `AssertsEventInOrganization` rejects an event whose `organization_id` does not match the route
  organization (404) before any policy or query runs.
- Controllers re-assert the event/plan/version chain (`event_id`, `floor_plan_id`) and abort 404 on
  mismatch, so a valid id from another event cannot be used through a different event URL.
- Services derive `organization_id` from the event, never from request input.
- Venue and space lookups are constrained by `organization_id`; a foreign id resolves to 404.
- Seating targets (`guest`, `guest_party_membership`) are constrained by `event_id`.
- Spatial reference targets are constrained by `event_id` and the target type must be in the
  server-side registry.
- Composite foreign keys `(event_id, id)` / `(organization_id, id)` make cross-tenant linkage
  impossible at the database level, not just in application code.
- Page routes authorize through `EventPolicy`, which delegates to
  `PermissionService::hasEventPermission()`; superadmin/platform-superadmin bypass is explicit.

---

## 10. Permissions

Added to `PermissionRegistry` (single registry, no parallel ACL):

| Permission | Purpose |
| --- | --- |
| `venue.view` | View the venue catalog |
| `venue.manage` | Create/update/archive venues and spaces |
| `venue.floor_plan.view` | View plans, versions, validation and seating |
| `venue.floor_plan.manage` | Create/update plans, edit draft documents, archive plans, manage spatial links |
| `venue.floor_plan.publish` | Publish a draft |
| `venue.floor_plan.delete` | Delete a plan without published history |
| `venue.floor_plan.version` | Create and archive versions |
| `seating.view` | View seating (existing constant, now consumed) |
| `seating.assign` | Assign/release seating |
| `seating.manage` | Broader seating management ability |

Role mapping highlights:

- **Workspace Owner/Admin:** all M8 permissions.
- **Event Manager:** all M8 permissions.
- **Operations Coordinator / Station Supervisor / General Staff / Observer:** view-only.
- **Guest Manager / Check-in Supervisor / Check-in Staff:** view venues, floor plans and seating.
- **Seating Usher:** `seating.view`, `seating.manage`, `seating.assign` plus view-only layout.
- **Guest Relations / Media Curator / Gift Steward:** no floor-plan access.

Delegatable to event staff: `venue.view`, `venue.floor_plan.view`, `seating.view`,
`seating.assign`. Manage/publish/delete/version abilities are **not** delegatable.

`EventPolicy` exposes `viewVenues`, `manageVenues`, `viewFloorPlans`, `manageFloorPlans`,
`publishFloorPlans`, `deleteFloorPlans`, `manageFloorPlanVersions`, `viewSeating`, `assignSeating`,
`manageSeating`. Mutating abilities are additionally blocked when the event is archived.

---

## 11. M5–M7 integration contracts

### M5 — Guests

- Read-only use of `guests` and `guest_party_memberships`; no guest rows are created or mutated.
- `guest_party_memberships` gains only a unique `(event_id, id)` index to support the composite FK.
- Seating never fabricates individuals for unidentified quota slots; it stores a headcount placeholder.
- Deleting a guest or member cascades their seating assignments (FK), preserving referential integrity.

### M6 — Operations

Spatial links are supported for: station, task, run-of-show item, incident, staff membership.
`SpatialReferenceService::TARGETS` is the single allow-list; unknown types are rejected.
M6 tables and columns are untouched; the reference is additive context ("where on the layout"),
and the M6 record remains authoritative for its own data.

### M7 — Vendors

Spatial links are supported for: vendor engagement, vendor schedule reference, vendor logistics
requirement. Same additive contract: no M7 column changes, no duplication of vendor records.

### Cross-cutting

- Every reference is scoped to the event and version.
- Removing an object from a draft flags the reference `needs_review` on the next save.
- Re-linking the same M6/M7 record updates the existing reference (one spatial anchor per record).

---

## 12. HTTP API

All routes live inside the existing `workspace/{organization}/events/{event}` group.

### Venues (7)

| Method | Path | Name |
| --- | --- | --- |
| GET | `/venues` | `workspace.events.venues.index` |
| POST | `/venues` | `workspace.events.venues.store` |
| PUT | `/venues/{venue}` | `workspace.events.venues.update` |
| POST | `/venues/{venue}/archive` | `workspace.events.venues.archive` |
| POST | `/venues/{venue}/spaces` | `workspace.events.venues.spaces.store` |
| PUT | `/venues/{venue}/spaces/{space}` | `workspace.events.venues.spaces.update` |
| POST | `/venues/{venue}/spaces/{space}/archive` | `workspace.events.venues.spaces.archive` |

### Floor plans (18)

| Method | Path | Name |
| --- | --- | --- |
| GET | `/floor-plans` | `workspace.events.floor-plans.index` |
| POST | `/floor-plans` | `workspace.events.floor-plans.store` |
| GET | `/floor-plans/{floorPlan}` | `workspace.events.floor-plans.show` |
| PUT | `/floor-plans/{floorPlan}` | `workspace.events.floor-plans.update` |
| DELETE | `/floor-plans/{floorPlan}` | `workspace.events.floor-plans.destroy` |
| POST | `/floor-plans/{floorPlan}/archive` | `workspace.events.floor-plans.archive` |
| GET | `/floor-plans/{floorPlan}/editor` | `workspace.events.floor-plans.editor` |
| POST | `/floor-plans/{floorPlan}/versions` | `workspace.events.floor-plans.versions.store` |
| GET | `/floor-plans/{floorPlan}/versions/{version}` | `workspace.events.floor-plans.versions.show` (JSON) |
| PUT | `/floor-plans/{floorPlan}/versions/{version}/document` | `…versions.document` (JSON) |
| POST | `/floor-plans/{floorPlan}/versions/{version}/publish` | `…versions.publish` |
| POST | `/floor-plans/{floorPlan}/versions/{version}/archive` | `…versions.archive` |
| GET | `/floor-plans/{floorPlan}/versions/{version}/validate` | `…versions.validate` (JSON) |
| GET | `/floor-plans/{floorPlan}/versions/{version}/seating` | `…seating.index` (JSON) |
| POST | `/floor-plans/{floorPlan}/versions/{version}/seating` | `…seating.store` (JSON) |
| DELETE | `/floor-plans/{floorPlan}/versions/{version}/seating/{assignment}` | `…seating.destroy` (JSON) |
| POST | `/floor-plans/{floorPlan}/versions/{version}/spatial-references` | `…spatial-references.store` (JSON) |
| DELETE | `/floor-plans/{floorPlan}/versions/{version}/spatial-references/{spatialReference}` | `…spatial-references.destroy` (JSON) |

JSON responses:

- save → `{ saved, lock_version, stats{object_count,table_count,chair_count,zone_count,seat_capacity}, saved_at }`
- publish → `{ published, version{…} }`; blocking validation → 422 `errors.document[]`
- validate → `{ issues[{severity,code,message,object_id}], counts{error,warning,info} }`
- seating index → `{ tables{objectId:{capacity,assigned,remaining,chairs}}, assignments[] }`
- seating store → 201 `{ assignment, tables }`
- spatial store → 201 `{ reference }`

---

## 13. Validation and limits

Backend is authoritative; the canvas client is never trusted.

| Layer | Enforced |
| --- | --- |
| Request | Laravel validation on every controller action (types, ranges, enums, URL scheme) |
| Document | `FloorPlanDocumentValidator` (schema, ids, types, geometry, properties, limits) |
| Layout | `FloorPlanValidationService` (outside canvas, capacity mismatch, over-capacity, duplicates, missing objects, overlaps, restricted zones) |
| Seating | `SeatingAssignmentService` (capacity, duplicates, seat occupancy, M5 ownership, quota) |
| References | `SpatialReferenceService` (object existence, allow-listed target type, event ownership) |
| Tenancy | route/controller/service assertions + composite FKs |
| Concurrency | `lock_version` → HTTP 409 |

Severity contract: `error` blocks publishing; `warning` and `info` are advisory. A clean layout
returns a single `layout.valid` info issue.

---

## 14. Frontend and UI

The M8 frontend is server-rendered Blade plus Alpine components. There is no SPA router and no
second state store: the canvas document lives in `resources/js/editor/document.js`, and the
Alpine component in `resources/js/editor/index.js` is the only coordinator. Konva is imported
from the bundled `resources/js/editor.js` entry (self-hosted, no CDN, CSP nonce preserved).

### 14.1 Pages

| Route name | View | Purpose |
| --- | --- | --- |
| `workspace.events.venues.index` | `workspace/events/venues/index.blade.php` | Organization venue catalog and venue-space CRUD, archive/restore |
| `workspace.events.floor-plans.index` | `workspace/events/floor-plans/index.blade.php` | Plan list, create, open editor / history |
| `workspace.events.floor-plans.show` | `workspace/events/floor-plans/show.blade.php` | Version history, publish, archive, delete, spatial references, seating summary |
| `workspace.events.floor-plans.editor` | `workspace/events/floor-plans/editor.blade.php` | Full-canvas floor-plan editor |

### 14.2 Editor layout

| Region | Contents |
| --- | --- |
| Toolbar | Back link, undo/redo, zoom out / zoom label / reset / zoom in / fit-to-screen, grid toggle, snap toggle, save-state badge, validate, publish, mobile rail toggles |
| Left rail | `Perpustakaan` (search + 4 categories: seating, venue, operational, general) and `Lapisan` (objects and zones with visibility and lock toggles) |
| Canvas | Konva stage with separate grid, zone, object and overlay layers; marquee selection; transformer handles |
| Right rail | `Properti`, `Seating`, `Validasi` |
| Status bar | Object / table / chair / zone counts, total capacity, selection count, pan hint, zoom percentage |
| Modals | Chair generation, publish confirmation, destructive confirm, toast |

### 14.3 Canvas interaction inventory

| Capability | Implementation |
| --- | --- |
| Blank canvas | New plan starts with an empty document (`Document` defaults), grid drawn from `canvas.grid_size` |
| Object library | 35 typed objects across 4 categories; click inserts at viewport centre, drag-and-drop inserts at the drop point |
| Drag | Konva `dragmove` writes back world `x`/`y`; dragging one group member moves the whole selection by the same delta |
| Resize | Konva transformer, scale reset to 1 after transform and converted into world `width`/`height` |
| Rotate | Transformer rotation normalised into `[0, 360)` on write-back and in the serializer |
| Multi-select | Shift-click toggles membership; shift-drag draws a marquee; `Ctrl/Cmd+A` selects all visible objects |
| Groups | Logical `group_id`; clicking a member selects every visible member, ungroup clears the id for the selection |
| Layers | Per-object `z_index`, `visible`, `locked`; front/back ordering actions |
| Zoom / pan | Wheel zoom anchored at the pointer, clamp `0.1–4`; pan with space-drag or middle-drag; zoom never persisted |
| Grid / snapping | Grid toggle and snap toggle; `snapToGrid` applied on insert, drag and keyboard nudge |
| Undo / redo | Snapshot `History` (depth 100) with labelled operations |
| Copy / paste / duplicate | In-memory clipboard, paste offsets by one grid step, copies never inherit `group_id` |
| Keyboard | Shortcuts below; ignored while focus is in an input, textarea, select or contenteditable |
| Autosave | 1.2 s debounce after any mutation, `PUT` with `lock_version`, explicit save states |

### 14.4 Keyboard shortcuts

| Shortcut | Action |
| --- | --- |
| `Ctrl/Cmd+Z` / `Ctrl/Cmd+Shift+Z` | Undo / redo |
| `Ctrl/Cmd+Y` | Redo |
| `Ctrl/Cmd+C` / `Ctrl/Cmd+V` | Copy / paste |
| `Ctrl/Cmd+D` | Duplicate |
| `Ctrl/Cmd+A` | Select all visible |
| `Delete` / `Backspace` | Delete selection |
| Arrow keys / `Shift`+arrows | Nudge 1 cm / 10 cm |
| `Space` (hold) + drag | Pan canvas |
| `Escape` | Cancel zone draw → close publish → close chair modal → clear selection |
| `Enter` | Finish polygon zone |

### 14.5 Properties panel

Objects expose label, position, size, rotation, colour, lock and visibility; tables additionally
expose table number, shape, capacity, seat spacing and start angle; chairs expose seat number and
parent table; zones expose name, structured type, colour, capacity, visibility and geometry.
Every field is disabled in read-only mode.

### 14.6 Seating panel

Table chips show `assigned/capacity`; the selected table shows an occupancy meter, its current
assignments, a guest search and a guest list. A party with named M5 members expands to individual
members; placing a member stores `guest_party_membership_id`, placing the party stores a
`guest_id` + `headcount` placeholder. Assignments can be released individually.

### 14.7 Validation and publish

`Validasi` calls the backend validation endpoint and renders issues grouped by severity; clicking
an issue focuses the offending object. The publish modal summarises the version and blocks the
action while any `error`-severity issue remains. Chair generation opens a modal for capacity,
spacing and start angle, and offers regenerate-all versus keep-manual-chairs.

### 14.8 Save states and read-only mode

| State | UI |
| --- | --- |
| `idle` | Neutral badge |
| `unsaved` | "Belum tersimpan" |
| `saving` | Spinner + "Menyimpan…" |
| `saved` | "Tersimpan" with timestamp |
| `error` | Error badge, retry on next change |
| `conflict` | 409 from a stale `lock_version`; asks the user to reload |

Read-only mode (`!manageFloorPlans` or non-draft version) disables every mutating control, hides
publish, disables seating placement unless `seating.assign` is present, and still renders the
document. There is no `beforeunload` guard yet: the badge shows `unsaved` but navigation away
with a pending debounce is not blocked (see limitations).

### 14.9 Responsive layout and accessibility

Left and right rails collapse behind toolbar buttons below `lg`; the canvas fills the remaining
space. Interactive icons carry `aria-label`/`title`; tabs are buttons with active state; modals
close on backdrop click and `Escape`; focus is not trapped (see limitations).

### 14.10 Styling

Editor styles live in the `app.css` "Floor plan editor" section (`fp-editor-shell`, `fp-toolbar`,
`fp-rail`, `fp-canvas-host`, `fp-status`, `fp-modal`, `fp-library-*`, `fp-layer-*`, `fp-seat-*`,
`fp-validation`). All copy is Indonesian, matching the rest of the workspace.

---

## 15. Testing evidence

### 15.1 Commands and results

| Check | Command | Result |
| --- | --- | --- |
| Backend full suite | `php artisan test --compact` | **660 passed**, 2,071 assertions |
| M8 backend suite | `php artisan test tests/Feature/M8 --compact` | **80 passed**, 189 assertions |
| Frontend unit suite | `npm test` (Vitest 5.1.0) | **50 passed**, 5 files |
| Blade lint | `php bin/blade_lint.php` | **ALL 137 VIEWS CLEAN** |
| View compilation | `php artisan view:cache` | cached successfully |
| Production build | `npm run build` | `editor` entry 247.44 kB (73.27 kB gzip); manifest contains `resources/js/editor.js` |
| Migrations | SQLite + MariaDB 13.0.2, `up → rollback → re-apply` | all FK names ≤ 43 chars, both engines clean |
| Formatting | `vendor/bin/pint --dirty --format agent` | passed |

### 15.2 Backend coverage (80 tests)

| File | Focus |
| --- | --- |
| `VenueManagementTest` | Venue and venue-space CRUD, organization isolation, archive, permissions |
| `FloorPlanVersionTest` | Draft creation, save, publish immutability, archive, lock-version conflict, stats |
| `FloorPlanDocumentValidationTest` | Schema, ids, object types, geometry, polygon normalisation, payload size, property limits |
| `SeatingAssignmentTest` | Individual and party assignment, duplicates, seat occupancy, capacity, quota, release, review flags |
| `SpatialReferenceTest` | Allow-listed targets, M6/M7 object existence, event ownership, duplicate links |
| `SpatialSecurityTest` | Cross-organization and cross-event access, read-only staff, publish permission separation |
| `FloorPlanPageTest` | Index/show/editor rendering, owner edit mode, view-only staff read-only mode, forbidden without view permission, cross-event 404 |

### 15.3 Frontend coverage (50 tests)

| File | Focus |
| --- | --- |
| `document.test.js` | Object add/remove, ids, ordering, zone geometry, validation |
| `geometry.test.js` | Rotation, bounding boxes, grid snapping, viewport round-trip and fit |
| `commands.test.js` | Undo/redo stack, depth limit, labels |
| `seats.test.js` | Round and rectangular chair generation, counts, seat indices, spacing |
| `serializer.test.js` | Backend JSON shape, polygon points as arrays, rotation normalisation, round-trip |

Regression safety: no pre-M8 test was deleted, skipped or weakened. The full suite grew from
653 to 660 tests because of the new M8 cases; all previously passing tests still pass.

### 15.4 Defects caught during final verification

| Defect | Fix |
| --- | --- |
| `screenToWorld` was not the inverse of `worldToScreen` when `zoom !== 1` | Corrected to `(screen - pan) / zoom`; round-trip test now passes |
| `geometry.js` carried dead, broken duplicate chair/normalise helpers superseded by `seats.js` | Removed; single implementation remains |
| Editor view called the Alpine helper `formatZoom()` as a PHP function | Replaced with `x-text="formatZoom()"` |
| Alpine `:class` bindings were placed on Blade component tags, where Blade evaluated them as PHP | Moved each binding to a wrapping `<span>`; 6 sites fixed |

These were runtime defects that static Blade lint and pure unit tests could not catch; the editor
render tests in `FloorPlanPageTest` are the regression guard.

---

## 16. Limitations and extension points

### 16.1 Limitations (honest)

| Limitation | Impact |
| --- | --- |
| No browser-level E2E test | Canvas pointer interaction (drag, transformer, marquee) is covered by pure-module tests and page render tests, not by automated DOM events. Manual browser smoke testing is still required before release. |
| No realtime collaboration | A single editor is assumed. Concurrent writes are detected via `lock_version` → HTTP 409, but there is no live presence, locking lease or merge. |
| Undo history is in-memory only | Reloading the page discards undo/redo; history depth is 100 operations. |
| No unload guard | Navigating away inside the 1.2 s autosave debounce can lose the last change; the save badge is the only signal. |
| Viewport is not persisted | Zoom and pan are intentionally per-session; reopening a plan always fits the document. |
| Document size cap | The validated JSON document is capped at 2 MB. Very large venues (roughly 500+ chairs plus zones) can approach it; the editor does not yet warn before the limit. |
| Legacy seating coexists | M5 `table_assignments` and its admin UI are untouched. M8 seating lives in `seating_assignments`; legacy assignments are not migrated or mirrored. |
| Party placeholders are not M5 rows | An unidentified quota slot stores `guest_id` + `headcount`; it only becomes an individual assignment when a named membership is chosen. |
| Static spatial target allow-list | Adding a new M6/M7 target type requires a code change in `SpatialReferenceService`. |
| Zones are advisory | Zone geometry drives capacity and restricted-area warnings; it does not enforce collision, pathfinding or object containment. |
| Publish is synchronous and all-or-nothing | No scheduled publishing, no approval workflow, no partial publish. |
| No print/export | There is no PDF/image export of a published plan. |
| Accessibility is partial | Keyboard covers selection, movement, deletion and history; there is no screen-reader spatial description and no focus trap in modals. |
| `docs/PERMISSIONS.md` is stale | It predates M6. M8 permissions are documented in section 10 of this report only. |
| Bundle size | Konva makes the `editor` entry ~247 kB (73 kB gzip); it is loaded only on the editor page. |

### 16.2 Extension points

| Extension | Where to extend |
| --- | --- |
| New object type | `FloorPlanObjectType` enum → `resources/js/editor/types.js` → renderer in `stage.js` → validator allow-list |
| New zone type | `FloorPlanZoneType` enum → `types.js` `ZONE_TYPES` → validation rules |
| New M6/M7 spatial target | `SpatialReferenceService` target registry plus the allow-list test in `SpatialReferenceTest` |
| New layout rule | `FloorPlanValidationService` issue codes; severity determines publish blocking |
| New canvas tool | Add an Alpine action in `index.js` and a stage mode in `stage.js`; keep document mutation inside `_mutate` so history and autosave stay correct |
| New permission | `PermissionRegistry` constant + role matrix + `EventPolicy` ability |
| Version lifecycle hooks | `FloorPlanVersionService` transaction boundaries (publish/archive already emit audit records) |
| Export / print | Serialize the published document and render it through the same Konva stage in a read-only mode |

---

## Appendix A — Acceptance checklist

Legend: **Met** = implemented and evidenced; **Partial** = implemented with a documented gap;
**Not met** = not delivered.

| # | Acceptance criterion | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Venue management (organization-scoped) with venue spaces | Met | Section 3.1–3.2, `VenueManagementTest`, venues page |
| 2 | Event-centric tenancy on every query, mutation, policy, endpoint | Met | Section 9, `SpatialSecurityTest`, composite FKs, service assertions |
| 3 | Real interactive canvas: blank canvas, library, drag, resize, rotate | Met | Section 14.3, `stage.js`, editor page render test |
| 4 | Multi-select, group, layers, zoom, pan, grid, snapping | Met | Section 14.3; group is a logical `group_id` |
| 5 | Undo/redo, copy/paste, keyboard shortcuts, autosave | Met | Section 14.3–14.4, `commands.test.js`; history is per-session |
| 6 | World coordinates independent of screen pixels, documented | Met | Section 5; viewport transforms unit-tested |
| 7 | Tables, individual chairs, automatic seat generation | Met | Section 8, `seats.test.js`, chair modal |
| 8 | Manual chair edits and safe regeneration | Met | `generated: false` chairs preserved by "keep manual" mode |
| 9 | Capacity-vs-actual-chair warnings | Met | `capacityMismatch` in editor, backend capacity rules, validation issues |
| 10 | Zones with structured type and geometry, not colour-only | Met | Section 4, `FloorPlanZoneType`, rect/polygon geometry |
| 11 | Seating uses actual M5 guest identity (individual vs party) | Met | Section 8; individual via `guest_party_memberships.id`, party via `guest_id` + headcount |
| 12 | Draft / Published / Archived with immutable published versions | Met | Section 7, `FloorPlanVersionTest` |
| 13 | New edits to a published plan create a new draft | Met | Published writes are rejected (`saveDocument` guard); the plan page offers **Buat Draf Baru**, which clones the published document into the next draft (`createNextDraft`), tested in `FloorPlanVersionTest` |
| 14 | Version-aware references and retained historical assignments | Met | `seating_assignments.floor_plan_version_id`, version-scoped reads |
| 15 | Additive spatial links to M6/M7 without destructive schema change | Met | Section 3.7, `SpatialReferenceTest`; no M6/M7 table altered |
| 16 | Backend validation of geometry, seating, capacity, references, payload, object types | Met | Section 13, `FloorPlanDocumentValidationTest` |
| 17 | New permissions via existing registry; separate abilities | Met | Section 10, `PermissionRegistry`, `EventPolicy` |
| 18 | Audit logging for publish, version creation, archive, seating changes | Met | `floor_plan.version.*`, `seating.assignment.*` audit records |
| 19 | Automated backend, feature, security, frontend, regression tests | Partial | 80 backend + 50 frontend tests; no browser-level E2E (section 16.1) |
| 20 | Prior tests preserved (none deleted or skipped) | Met | Full suite 660 passing, up from 653 |
| 21 | Architecture, database, schema, coordinates, canvas choice, versioning, seating, isolation, permissions, contracts, API, validation, testing, limitations documented | Met | Sections 1–16 |
| 22 | Out-of-scope items not attempted | Met | 3D/BIM/CAD, realtime multiplayer, marketplace rebuild, AI generation absent |
