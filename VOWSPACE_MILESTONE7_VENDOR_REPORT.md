# Milestone 7 — Vendor & Service Provider Management

**Status:** Implemented and verified — 2026-11-12
**Baseline:** M6 complete at `7bb922c` (496 tests / 1546 assertions)
**Final:** 580 tests / 1882 assertions passing
**Working tree:** all M7 work is uncommitted (107 changed/untracked paths)

---

## 1. Outcome

M7 adds an organization-scoped vendor management layer on top of the existing
workspace, event, permission and audit stack. It does not rebuild M1–M6
capabilities: organization membership, event access, policy checks and audit
logging are consumed from the existing `PermissionRegistry`, `PermissionService`,
`OrganizationPolicy`, `EventPolicy` and `AuditService`.

Delivered:

| Area | Result |
| --- | --- |
| Directory | Organization-scoped vendors, categories, contacts, services and packages with archive/restore and soft deactivation of referenced records |
| Catalog | Service and package CRUD; package edits bump the catalog version while engagement snapshots stay immutable |
| Sourcing | Requirements, shortlist candidates, preferred-candidate switching, proposals, comparison and selection |
| Engagement lifecycle | Draft → Sourcing → Proposal received → Negotiation → Pending approval → Confirmed → In preparation → In progress → Completed, plus Cancelled, with confirmation gates, closure readiness, immutable revisions and cancellation reasons |
| Documents | Document types, visibility, version/supersedes metadata, review workflow, scoped download with audit logging |
| Deliverables | Deliverable lifecycle, submission history, review decisions, overdue derivation and task linking |
| Logistics & arrival | Schedule references tied to run-of-show items/stations, arrival state machine, logistics requirements and resolution notes |
| Coordination | Communications with internal/vendor visibility, issues, incident linking, change requests and performance reviews |
| Vendor portal | Hashed one-time invitations, membership/access grants, vendor-visible-only data, portal submissions, schedule/logistics confirmation and change requests |
| Reporting | Event vendor hub plus nine report types and CSV export with commercial gating |
| UI | Thirteen vendor screens plus a dedicated external portal layout, with event and organization navigation entries |

---

## 2. Schema

Five additive migrations, all verified through the test suite on SQLite and
compatible with the development MySQL database:

1. `2026_11_11_001000_create_vendor_directory_tables.php`
2. `2026_11_11_001100_create_event_vendor_sourcing_tables.php`
3. `2026_11_11_001200_create_event_vendor_engagement_tables.php`
4. `2026_11_11_001300_create_event_vendor_delivery_tables.php`
5. `2026_11_11_001400_create_vendor_portal_and_coordination_tables.php`

New tables (21):

```
vendor_categories              vendors                       vendor_contacts
vendor_services                vendor_service_packages

event_vendor_requirements      event_vendor_requirement_candidates
event_vendor_proposals

event_vendor_engagements       event_vendor_documents

event_vendor_deliverables      event_vendor_deliverable_submissions
event_vendor_schedule_references
event_vendor_logistics_requirements

vendor_portal_memberships      vendor_portal_engagement_access
event_vendor_change_requests   event_vendor_engagement_revisions
event_vendor_communications    event_vendor_issues
event_vendor_performance_reviews
```

No existing table is dropped or destructively altered. Tenant isolation is
`Organization → Vendor` for the directory and `Organization → Event →
event-owned vendor resource` for sourcing, engagements, delivery, coordination
and reporting. The isolation is enforced in services, controllers
(`AssertsEventInOrganization`) and feature tests.

### Models and enums

- 21 new Eloquent models, with relationships wired to organizations, events,
  vendors, engagements, users and existing M6 incidents/tasks where relevant.
- 23 new enums: `VendorStatus`, `VendorType`, `VendorContactPurpose`,
  `VendorRequirementStatus`, `VendorCandidateState`, `VendorProposalStatus`,
  `VendorEngagementStatus`, `VendorDocumentType`, `VendorDocumentVisibility`,
  `VendorDocumentReviewStatus`, `VendorDeliverableStatus`,
  `VendorSubmissionStatus`, `VendorArrivalStatus`, `VendorLogisticsType`,
  `VendorLogisticsResponsibility`, `VendorLogisticsStatus`,
  `VendorCommunicationType`, `VendorCommunicationDirection`,
  `VendorCommunicationVisibility`, `VendorIssueSeverity`, `VendorIssueStatus`,
  `VendorChangeRequestStatus`, `VendorPerformanceReview` (rating bounds are
  validated in the service).
- Every stateful enum exposes `label()`, `badge()`, `allowedTransitions()` and
  `canTransitionTo()` where a state machine applies.

---

## 3. Permissions

32 vendor permissions were added to `PermissionRegistry`, with role maps for
organization owners/admins and the existing event staff roles:

```
vendor.view                       vendor.create
vendor.update                     vendor.archive
vendor.contact.manage             vendor.catalog.manage

vendor.requirement.manage         vendor.proposal.view
vendor.proposal.manage            vendor.select

vendor.engagement.view            vendor.engagement.manage
vendor.engagement.close           vendor.agreement.view
vendor.agreement.manage           vendor.commercial.view
vendor.commercial.approve

vendor.deliverable.view           vendor.deliverable.review
vendor.logistics.manage           vendor.portal.manage
vendor.change.approve             vendor.performance.view
vendor.performance.review         vendor.report.view
vendor.report.export

vendor.portal.engagement.view     vendor.portal.document.view
vendor.portal.deliverable.submit  vendor.portal.communication.submit
vendor.portal.change_request.submit
vendor.portal.logistics.confirm
```

`OrganizationPolicy` exposes the organization-level abilities
(`viewVendors`, `createVendors`, `updateVendors`, `archiveVendors`,
`manageVendorContacts`, `manageVendorCatalog`). `EventPolicy` exposes the
event-level abilities (`viewVendors`, `viewVendorEngagements`,
`manageVendorEngagements`, `closeVendorEngagements`,
`viewVendorCommercial`, `approveVendorCommercial`, `viewVendorAgreements`,
`manageVendorAgreements`, `reviewVendorDeliverables`,
`manageVendorLogistics`, `manageVendorPortal`, `viewVendorPerformance`,
`reviewVendorPerformance`, `approveVendorChanges`, `viewVendorReports`,
`exportVendorReports`).

Every mutation writes through `AuditService` (`record` or `recordCritical` for
selection, status transitions, closure, change-request decisions and portal
access changes). No parallel audit or notification system was introduced.

---

## 4. Business rules implemented

**Directory and catalog.** Vendor slugs are unique per organization. Categories
are organization-scoped and cannot be deleted while vendors still reference
them. Contacts, services and packages referenced by engagements, proposals,
deliverables or change requests are deactivated instead of deleted so historical
records keep their catalog reference. Every package edit bumps `version`;
existing engagements keep the immutable `package_snapshot` captured at
engagement creation. Archiving sets `archived_at` and `status = archived`;
restoring returns the vendor to `active` and clears `archived_at`. Archiving
never deletes engagements.

**Sourcing.** Requirements belong to an event. Shortlist candidates are unique
per requirement; preferring a candidate clears the previous preferred flag.
Creating a proposal moves the requirement to `proposals_received`. Proposal
selection requires a non-empty reason, rejects expired or non-submitted
proposals, rejects every other submitted/under-evaluation proposal for the same
requirement, marks the winning candidate `selected` and creates a draft
engagement with amount and service/package/scope snapshots. Proposal history is
retained; unselected proposals remain visible to authorized users.

**Engagement lifecycle.** The engagement state machine is explicit and rejects
invalid transitions. `Confirmed` requires scope and a coordinator and records
the first immutable revision. Commercial terms (`agreed_amount`, currency and
scope snapshots) are locked after confirmation while notes remain editable.
Cancellation requires a reason and keeps deliverables. Completion is blocked
while deliverables are submitted or under review. Closure readiness blocks open
issues, pending change requests and unreviewed deliverables; it warns about
unaccepted deliverables and unapproved documents. Closing stores notes and a
JSON closure snapshot. Every confirmed commercial change appends a new revision
without mutating prior revisions.

**Documents.** Documents are event-scoped and support type, visibility,
effective/expiry dates, version and optional supersedes reference. Review
transitions are validated. Internal downloads are audit-logged. Vendor-visible
downloads are limited to documents whose visibility is `vendor_visible` or
`shared`, and portal access is checked against the granted engagement.

**Deliverables.** Deliverable status transitions are validated. Submissions
retain their history and review decisions; acceptance requires review
permission. Overdue is derived from `due_at` plus non-terminal status, never
stored as a mutable counter. A deliverable can link an existing M6 task, but a
task from another event is rejected.

**Logistics and arrival.** Schedule references are tied to a run-of-show item
and/or station and support arrival transitions (`expected → confirmed →
arrived → setup in progress → ready → departed`). Logistics requirements carry
type, responsibility and status; `fulfilled` and `unable` require a resolution
note. Open logistics counts are derived.

**Coordination.** Staff communications force an internal direction and validate
engagement-scoped deliverable/change-request links. Issues support severity and
status transitions, active-staff assignment, resolution notes, and linking an
existing M6 incident by reference without creating a second incident. Change
requests follow Draft → Submitted → decide/withdraw; approval applies amount,
scope and schedule changes to the engagement and appends an immutable revision.
Rejection requires notes. Commercial-key approvals require
`approveVendorCommercial`. Performance reviews are allowed only for
confirmed/active/completed engagements with a 1–5 rating.

**Portal.** Invitations use a 64-character random token; only its SHA-256 hash is
stored, with an expiry timestamp. Acceptance requires an authenticated user
whose email matches the invited email. Revoked or expired invitations are
rejected. A membership grants access only to explicitly granted engagements;
revoking an access row removes authorization immediately. Portal endpoints
never expose internal-only communications, internal notes, issues, performance
reviews or revisions. Portal submissions are server-controlled: the vendor can
submit deliverables, confirm schedule references and logistics requirements,
post communications and create change requests, but cannot set internal fields.

**Reporting.** The event vendor hub aggregates event-scoped counts and
organization-scoped vendor totals. Commercial totals and columns are omitted
unless `include_commercial` is requested and the actor holds
`viewVendorCommercial`. Nine report types are available: directory, engagements,
services, proposals, agreements, deliverables, logistics, issues and
performance. CSV export emits a BOM and a header row first, then the report
rows.

---

## 5. HTTP surface

`php artisan route:list` reports **82** vendor routes. Grouped by controller:

| Controller | Routes |
| --- | --- |
| `WorkspaceVendorDirectoryController` | 9 |
| `WorkspaceVendorCatalogController` | 13 |
| `WorkspaceEventVendorSourcingController` | 10 |
| `WorkspaceEventVendorEngagementController` | 8 |
| `WorkspaceEventVendorDocumentController` | 3 |
| `WorkspaceEventVendorDeliverableController` | 6 |
| `WorkspaceEventVendorLogisticsController` | 6 |
| `WorkspaceEventVendorCoordinationController` | 10 |
| `WorkspaceEventVendorController` (hub) | 1 |
| `WorkspaceEventVendorReportController` | 2 |
| `WorkspaceEventVendorPortalController` | 4 |
| `VendorPortalController` | 8 |
| `VendorPortalInvitationController` | 2 |
| **Total** | **82** |

The workspace routes are registered from `routes/workspace-vendors.php`; the
external portal routes are registered from `routes/vendor-portal.php`.

---

## 6. UI

Thirteen vendor screens plus a dedicated external portal layout:

- `resources/views/workspace/vendors/index.blade.php`
- `resources/views/workspace/vendors/create.blade.php`
- `resources/views/workspace/vendors/edit.blade.php`
- `resources/views/workspace/vendors/show.blade.php`
- `resources/views/workspace/vendor-categories/index.blade.php`
- `resources/views/workspace/events/vendors/index.blade.php`
- `resources/views/workspace/events/vendors/reports.blade.php`
- `resources/views/workspace/events/vendors/engagements.blade.php`
- `resources/views/workspace/events/vendors/engagement.blade.php`
- `resources/views/workspace/events/vendors/portal.blade.php`
- `resources/views/vendor-portal/index.blade.php`
- `resources/views/vendor-portal/show.blade.php`
- `resources/views/vendor-portal/invitation.blade.php`
- `resources/views/layouts/vendor-portal.blade.php`

Navigation integration:

- Event tabs gain a **Vendor** entry guarded by `viewVendors`, plus a vendor
  sub-navigation row for hub, engagements, portal and reports.
- The owner layout gains an organization-level **Vendor** item guarded by
  `viewVendors`, and an event-level vendor link under the event group.
- Every visible control submits to a real route. There are no placeholder or
  dead controls.
- All screens are responsive at 375px; tables scroll or become cards on mobile.
- Empty states use `x-ui.empty-state`; status pills use enum `label()`/`badge()`;
  money is formatted with `number_format($amount, 0, ',', '.')` and `Rp` for IDR.

---

## 7. Test evidence (executed)

```
php artisan test --compact tests/Feature/M7
  -> 84 passed, 336 assertions

php artisan test --compact
  -> 580 passed, 1882 assertions
```

| File | Tests |
| --- | --- |
| `VendorDirectoryTest` | 8 |
| `VendorCatalogTest` | 7 |
| `VendorSourcingTest` | 11 |
| `VendorEngagementLifecycleTest` | 7 |
| `VendorDeliverableTest` | 6 |
| `VendorLogisticsTest` | 6 |
| `VendorCoordinationTest` | 8 |
| `VendorReportingTest` | 6 |
| `VendorPortalTest` | 14 |
| `VendorSecurityTest` | 11 |
| **Total M7** | **84** |

M1–M6 regression: all pre-existing tests still pass; total grew from 496 to 580
tests and from 1546 to 1882 assertions.

Other executed checks:

```
php artisan view:cache           -> Blade templates cached successfully
php artisan route:list           -> 82 vendor routes resolve
vendor/bin/pint --dirty          -> passed (no violations)
npm run build                    -> built successfully
```

---

## 8. Edge-case coverage

| # | Edge case | Verified by |
| --- | --- | --- |
| 1 | Archiving keeps engagements and sets `archived_at` | `test_archiving_keeps_engagements_and_sets_archived_at` |
| 2 | Archived vendor can be restored | `test_archived_vendor_can_be_restored` |
| 3 | Category slugs are unique within an organization | `test_category_slugs_are_unique_within_an_organization` |
| 4 | Category with vendors cannot be deleted | `test_category_with_vendors_cannot_be_deleted` |
| 5 | Referenced contact is deactivated, not deleted | `test_contact_referenced_by_an_engagement_is_deactivated_instead_of_deleted` |
| 6 | Contacts can be created, updated and deleted | `test_contacts_can_be_created_updated_and_deleted` |
| 7 | Directory filters by query, category and status | `test_directory_filters_by_query_category_and_status` |
| 8 | Invalid vendor status enum is rejected | `test_invalid_vendor_status_enum_is_rejected` |
| 9 | Referenced service is deactivated, not deleted | `test_service_referenced_by_an_engagement_is_deactivated_instead_of_deleted` |
| 10 | Package edit bumps version without touching engagement snapshots | `test_updating_a_package_bumps_its_version_without_touching_engagement_snapshots` |
| 11 | Vendor create ignores client-supplied scope fields | `test_vendor_create_ignores_client_supplied_scope_fields` |
| 12 | Vendor from another organization is not visible | `test_vendor_from_another_organization_is_not_visible` |
| 13 | Cross-organization vendor is rejected during sourcing | `test_a_vendor_from_another_organization_is_rejected` |
| 14 | Duplicate shortlist candidate is rejected | `test_shortlist_candidate_is_added_and_duplicates_are_rejected` |
| 15 | Preferring a candidate clears the previous preferred candidate | `test_preferring_a_candidate_clears_the_previous_preferred_candidate` |
| 16 | Creating a proposal moves the requirement to proposals received | `test_creating_a_proposal_moves_the_requirement_to_proposals_received` |
| 17 | Expired proposal cannot be selected | `test_an_expired_proposal_cannot_be_selected` |
| 18 | Selection rejects the other submitted proposals | `test_selecting_a_proposal_rejects_the_other_submitted_proposals` |
| 19 | Selection creates a draft engagement with amount and snapshots | `test_selection_creates_a_draft_engagement_with_amount_and_snapshots` |
| 20 | Rejection history keeps every submission | `test_rejection_history_keeps_every_submission` |
| 21 | Proposal from another event is not found | `test_a_proposal_from_another_event_is_not_found` |
| 22 | Invalid engagement status transition is rejected | `test_invalid_status_transition_is_rejected` |
| 23 | Confirmation is blocked without scope and coordinator | `test_confirmation_is_blocked_without_scope_and_coordinator` |
| 24 | Confirmation records the first revision | `test_confirmation_succeeds_and_records_first_revision` |
| 25 | Commercial terms are locked after confirmation but notes stay editable | `test_commercial_terms_are_locked_after_confirmation_but_notes_stay_editable` |
| 26 | Cancelling requires a reason and keeps deliverables | `test_cancelling_requires_a_reason_and_keeps_deliverables` |
| 27 | Closure is blocked by open issues, pending changes and unreviewed deliverables | `test_closure_is_blocked_by_open_issues_pending_changes_and_unreviewed_deliverables` |
| 28 | Cross-event engagement returns not found | `test_cross_event_engagement_returns_not_found` |
| 29 | Deliverable can be submitted and accepted | `test_deliverable_can_be_submitted_and_accepted` |
| 30 | Deliverable from another engagement is not found | `test_deliverable_from_another_engagement_is_not_found` |
| 31 | Overdue helper only applies to open deliverables | `test_overdue_helper_only_applies_to_open_deliverables` |
| 32 | Linking a task from another event is rejected | `test_linking_a_task_from_another_event_is_rejected` |
| 33 | Schedule reference is created with a run-of-show item | `test_schedule_reference_is_created_with_run_of_show_item` |
| 34 | Arrival transitions set timestamps and invalid transitions are rejected | `test_arrival_transitions_set_timestamps_and_invalid_transitions_are_rejected` |
| 35 | Fulfilling logistics requires a resolution note | `test_fulfilling_logistics_requires_a_resolution_note` |
| 36 | Invalid logistics transition throws | `test_invalid_logistics_transition_throws` |
| 37 | Open logistics count reflects only open requirements | `test_open_logistics_count_reflects_only_open_requirements` |
| 38 | Vendor cannot confirm a schedule reference twice | `test_vendor_cannot_confirm_a_schedule_reference_twice` |
| 39 | Vendor can acknowledge a requested logistics requirement | `test_vendor_can_acknowledge_a_requested_logistics_requirement` |
| 40 | Station from another event is rejected | `test_station_from_another_event_is_rejected` |

Additional automated coverage beyond the required matrix includes: permission
denial for directory creation, requirement creation, engagement management,
deliverable review, reporting export and commercial approval; cross-organization
directory and document access; cross-event document, deliverable, proposal and
engagement 404s; internal-only communication storage and portal visibility;
portal invitation creation audit logging; expired invitation rejection;
mismatched-email invitation rejection; revoked membership losing access
immediately; portal submissions rejecting foreign engagements; portal endpoints
never exposing internal notes; change-request withdrawal; rejection notes;
approval applying amount/scope/schedule and preserving prior revisions;
performance-review permission and rating bounds; hub event scoping; commercial
totals omitted when not requested; CSV BOM and header emission; and report row
event scoping.

---

## 9. Deliberate limitations

1. **Portal invitations target existing authenticated users.** Acceptance
   requires a user account whose email matches the invited email; M7 does not
   add external account provisioning or a second invitation channel.
2. **Invitation delivery is link-based.** The one-time URL is returned in the
   session for the inviting staff member to copy. No email/SMS provider is
   claimed.
3. **No realtime broadcasting.** The hub and reports are server-rendered; no
   broadcast driver is installed.
4. **CSV reporting only.** The report screen and export cover CSV; no PDF/XLSX
   export is claimed.
5. **Tests run on SQLite in-memory, development runs on MySQL.** All M7 queries
   avoid engine-specific SQL.
6. **`docs/PERMISSIONS.md` predates M3–M7.** The authoritative matrix is
   `app/Authorization/PermissionRegistry.php` plus this report.

---

## 10. Files

New PHP: 23 enums, 21 models, 9 services, 13 controllers, 5 migrations,
10 feature test files and 1 fixture trait.

Modified PHP: `PermissionRegistry`, `OrganizationPolicy`, `EventPolicy`,
`Organization`, `Event`, `routes/web.php`.

New routes: `routes/workspace-vendors.php`, `routes/vendor-portal.php`.

New Blade: 13 vendor screens plus `layouts/vendor-portal.blade.php`.
Modified Blade: `workspace/events/partials/event-tabs.blade.php`,
`layouts/owner.blade.php`.

---

## 11. Reproduce

```bash
cd /home/attrynn/vowspace
php artisan test --compact tests/Feature/M7
php artisan test --compact
php artisan view:cache
vendor/bin/pint --dirty --format agent
npm run build
php artisan route:list | grep -c vendor   # 82
```
