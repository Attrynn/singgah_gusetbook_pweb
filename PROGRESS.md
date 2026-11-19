# VOWSPACE Delivery Progress

Updated: November 2026

Status: **Delivered, hardened, and release-ready. All security/privacy/backend findings closed.**

---

## Milestone 10 — Legacy Cleanup & System Consolidation

Milestone 10 consolidated the codebase and removed accumulated legacy surface:

- **Removed the dead MFA subsystem** — deleted `MfaChallenge` / `MfaService` and dropped the
  `pragmarx/google2fa` dependency; nothing in routes, controllers or views referenced it.
- **Removed duplicate documentation** — deleted the redundant `CLAUDE.md` and the superseded
  `VOWSPACE_*.md` reports, leaving this file, `README.md` and `docs/` as the single source of truth.
- **Data integrity fixes** — added the missing database indexes and backfilled orphan events into
  the Legacy Workspace.
- **Extended tenant integrity** — database-enforced `event` scoping now uses composite
  `(event_id, id)` foreign keys across the M5–M9 tables.
- **Retained but deprecated** — the legacy M1 admin seating surface and the `event_user` pivot are
  intentionally kept for backward compatibility and marked `@deprecated`; the canonical replacements
  are the Milestone 8 floor-plan seating and `event_owner_memberships`.

All changes were additive or cleanup-safe; no destructive migrations were applied.

---

## 1. Scorecard

| Area | Target | Delivered | Status |
| --- | --- | --- | --- |
| Architecture & tenancy | Single-codebase, 3 roles, row-level event scoping | `events` as tenancy anchor, `ResolveGuestToken`, `ResolvesOwnerEvent`, role middleware | Complete |
| Database | Schema, migrations, factories, seeders | 58 migrations, 90 domain tables, 18 factories, rich `DemoSeeder` | Complete |
| Backend domain | Models, enums, services, form requests | 91 models, 71 enums, 63 domain services, 52 Form Requests, 7 policies | Complete |
| Security | Hashed tokens, authorization, rate limiters, duplicate guards, audit | SHA-256 + ciphertext tokens, `EnsureRole`, `EnsureActiveUser`, unique check-in constraint, `audit_logs` | Complete |
| Views & UI | Role shells, cold-minimalist neumorphic design | 145 Blade views, 6 layouts, 13 `x-ui` components, Tailwind v4 `@utility` system, Vite assets | Complete |
| Routes | Superadmin, Owner, Guest, Workspace, Vendor portal, Public | 366 route registrations across `admin.php` (68), `owner.php` (42), `guest.php` (22), `web.php` (21), `workspace-events.php` (131), `workspace-vendors.php` (72), `vendor-portal.php` (10) | Complete |
| Automated verification | Feature tests, Blade lint, real HTTP smoke | 733 PHPUnit tests (2307 assertions, all passing) + 56 Vitest tests in 6 files, `bin/blade_lint.php` (145 views clean), `./bin/smoke.sh` (real server, all roles passing) | Complete |
| Documentation | ERD, schema, roles, journeys, install, deploy, demo, testing | `README.md`, `PROGRESS.md`, and 21 documents in `docs/` | Complete |

---

## 2. Component inventory

### Schema & persistence (58 migrations, 90 domain tables)
The domain schema is anchored on `events`. Grouped by area:
- **Core & tenancy**: `events`, `event_user` (legacy), `event_owner_memberships`, `event_settings`,
  `event_venues`, `event_schedules`, `event_phases`, `event_sessions`, `event_setup_configs`,
  `event_setup_checklist_items`
- **Guests & invitations**: `guests`, `guest_categories`, `guest_groups`, `guest_tokens`, `guest_passes`,
  `guest_party_memberships`, `invitations`, `rsvps`, `checkins`
- **Guestbook, media & memory**: `guestbook_messages`, `guestbook_favorites`, `media_albums`,
  `media_assets`, `memory_collections`, `memory_collection_items`,
  `memory_consents`, `memory_export_jobs`, `event_memory_settings`
- **Interactions**: `song_requests`, `polls`, `poll_options`, `poll_responses`, `games`,
  `game_questions`, `game_options`, `game_attempts`, `game_answers`
- **Operations & people**: `event_tasks`, `event_task_checklist_items`, `event_task_comments`,
  `event_task_dependencies`, `event_staff_memberships`, `event_staff_permission_grants`,
  `event_stations`, `event_station_assignments`, `event_run_of_show_items`,
  `event_run_of_show_assignees`, `event_incidents`, `event_incident_updates`, `event_announcements`,
  `event_announcement_recipients`, `event_operations`, `playbooks`, `playbook_versions`,
  `event_activities`
- **Vendors**: `vendors`, `vendor_categories`, `vendor_contacts`, `vendor_services`,
  `vendor_service_packages`, `event_vendor_requirements`, `event_vendor_requirement_candidates`,
  `event_vendor_proposals`, `event_vendor_engagements`, `event_vendor_engagement_revisions`,
  `event_vendor_change_requests`, `event_vendor_communications`, `event_vendor_deliverables`,
  `event_vendor_deliverable_submissions`, `event_vendor_documents`, `event_vendor_issues`,
  `event_vendor_logistics_requirements`, `event_vendor_performance_reviews`,
  `event_vendor_schedule_references`, `vendor_portal_memberships`, `vendor_portal_engagement_access`
- **Venue & floor plan**: `venues`, `venue_spaces`, `floor_plans`, `floor_plan_versions`,
  `seating_tables`, `seating_assignments`, `table_assignments`, `spatial_references`
- **Platform**: `organizations`, `organization_memberships`, `workspace_invitations`, `audit_logs`,
  `app_settings`, `gift_accounts`, `users`
- Framework tables: `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
  `job_batches`, `failed_jobs`, `notifications`

### Models (91)
`AppSetting`, `AuditLog`, `Checkin`, `Event`, `EventActivity`, `EventAnnouncement`,
`EventAnnouncementRecipient`, `EventIncident`, `EventIncidentUpdate`, `EventMemorySettings`,
`EventOperation`, `EventOwnerMembership`, `EventPhase`, `EventRunOfShowAssignee`,
`EventRunOfShowItem`, `EventSchedule`, `EventSession`, `EventSetting`, `EventSetupChecklistItem`,
`EventSetupConfig`, `EventStaffMembership`, `EventStaffPermissionGrant`, `EventStation`,
`EventStationAssignment`, `EventTask`, `EventTaskChecklistItem`, `EventTaskComment`,
`EventTaskDependency`, `EventUser`, `EventVendorChangeRequest`, `EventVendorCommunication`,
`EventVendorDeliverable`, `EventVendorDeliverableSubmission`, `EventVendorDocument`,
`EventVendorEngagement`, `EventVendorEngagementRevision`, `EventVendorIssue`,
`EventVendorLogisticsRequirement`, `EventVendorPerformanceReview`, `EventVendorProposal`,
`EventVendorRequirement`, `EventVendorRequirementCandidate`, `EventVendorScheduleReference`,
`EventVenue`, `FloorPlan`, `FloorPlanVersion`, `Game`, `GameAnswer`, `GameAttempt`, `GameOption`,
`GameQuestion`, `GiftAccount`, `Guest`, `GuestbookFavorite`, `GuestbookMessage`, `GuestCategory`,
`GuestGroup`, `GuestPartyMembership`, `GuestPass`, `GuestToken`, `Invitation`, `MediaAlbum`,
`MediaAsset`, `MemoryCollection`, `MemoryCollectionItem`, `MemoryConsent`, `MemoryExportJob`,
`Organization`, `OrganizationMembership`, `Playbook`, `PlaybookVersion`, `Poll`, `PollOption`,
`PollResponse`, `Rsvp`, `SeatingAssignment`, `SeatingTable`, `SongRequest`, `SpatialReference`,
`TableAssignment`, `User`, `Vendor`, `VendorCategory`, `VendorContact`,
`VendorPortalEngagementAccess`, `VendorPortalMembership`, `VendorService`, `VendorServicePackage`,
`Venue`, `VenueSpace`, `WorkspaceInvitation`.

### Enums (71)
`AnnouncementAudience`, `AnnouncementPriority`, `AnnouncementStatus`, `CheckinMethod`,
`EventOwnerRole`, `EventStaffRole`, `EventStatus`, `EventUserRole`, `FloorPlanObjectType`,
`FloorPlanStatus`, `FloorPlanVersionStatus`, `FloorPlanZoneType`, `GuestPartyStatus`, `GuestStatus`,
`IncidentCategory`, `IncidentSeverity`, `IncidentStatus`, `InvitationStatus`, `MediaStatus`,
`MembershipStatus`, `MemoryExportStatus`, `MemoryItemType`, `MemoryState`, `MessageStatus`,
`MessageVisibility`, `OperationsStatus`, `OrganizationMembershipStatus`, `OrganizationRole`,
`PlatformRole`, `PlaybookStatus`, `ReadinessStatus`, `RsvpStatus`, `RunOfShowStatus`,
`ScheduleStatus`, `SeatingAssignmentStatus`, `SetupStatus`, `SongStatus`, `SpatialReferenceStatus`,
`StationCategory`, `StationStatus`, `TaskPriority`, `TaskStatus`, `TokenType`, `UserRole`,
`VendorArrivalStatus`, `VendorCandidateState`, `VendorChangeRequestStatus`,
`VendorCommunicationDirection`, `VendorCommunicationType`, `VendorCommunicationVisibility`,
`VendorContactPurpose`, `VendorDeliverableStatus`, `VendorDocumentReviewStatus`, `VendorDocumentType`,
`VendorDocumentVisibility`, `VendorEngagementStatus`, `VendorIssueSeverity`, `VendorIssueStatus`,
`VendorLogisticsResponsibility`, `VendorLogisticsStatus`, `VendorLogisticsType`, `VendorPortalStatus`,
`VendorProposalStatus`, `VendorRequirementStatus`, `VendorStatus`, `VendorSubmissionStatus`,
`VendorType`, `VenueSpaceType`, `VenueStatus`, `WorkspaceInvitationStatus`, `WorkspaceInvitationType`.

### Domain services (63)
`AnnouncementService`, `AttendanceService`, `AuditService`, `CheckinService`, `EmailChangeService`,
`EventBlueprintService`, `EventLifecycleService`, `EventService`, `EventSetupService`,
`EventTeamService`, `FloorPlanDocumentValidator`, `FloorPlanService`, `FloorPlanValidationService`,
`FloorPlanVersionService`, `GameService`, `GuestbookService`, `GuestCapabilityResolver`,
`GuestLegacyMigrationService`, `GuestPartyService`, `GuestPassService`, `GuestService`,
`IncidentService`, `InvitationService`, `MediaDerivativeService`, `MediaService`,
`MemoryCollectionService`, `MemoryContributionService`, `MemoryExportService`, `MemoryFeedService`,
`MemoryMediaSanitizer`, `MemoryModerationService`, `MemorySettingsService`,
`OperationsDashboardService`, `OperationsLifecycleService`, `OperationsReadinessService`,
`OperationsReportService`, `PermissionService`, `PlaybookService`, `PollService`, `QrService`
(SVG + GD PNG), `ReportService`, `RsvpService`, `RunOfShowService`, `SeatingAssignmentService`,
`SeatingService`, `SpatialReferenceService`, `StaffOperationsService`, `StationService`,
`TaskService`, `TenantContextService`, `VendorCoordinationService`, `VendorDeliverableService`,
`VendorDirectoryService`, `VendorDocumentService`, `VendorEngagementService`,
`VendorLogisticsService`, `VendorPortalAccessService`, `VendorReportService`, `VendorSourcingService`,
`VenueService`, `WorkspaceInvitationService`, `WorkspaceMembershipService`,
`WorkspaceRegistrationService`.

### Policies (7)
`EventPolicy`, `GuestbookMessagePolicy`, `GuestPartyMembershipPolicy`, `GuestPassPolicy`,
`GuestPolicy`, `MediaAssetPolicy`, `OrganizationPolicy`.

### Middleware (6)
`EnsureActiveUser` (invalidates inactive sessions), `EnsureOrganizationMembership`,
`EnsureRole` (role gate with 403), `ResolveGuestToken` (hashes token, scopes guest + event, shares
view variables, 404 for invalid), `ResolveOrganizationContext`, `SecurityHeaders`.

### Views (145)
- 36 admin views (events, owners, guests, categories, groups, invitations, rsvps, checkins,
  guestbook, media, seating, interactions, reports, settings, audit logs, profile)
- 46 workspace views (event workspace, setup wizard, team, vendors, operations, memory)
- 17 owner views (dashboard, wishes, attendance, memories, timeline, interactions, story, profile)
- 9 guest views (invitation, pass, gifts, song-request, poll, game, error, invalid)
- 3 vendor-portal views
- 3 auth views
- 8 error views
- 1 live-wall view (projector layout with polling script)
- 1 public memory view
- 1 mail view
- 1 public home view
- 6 layouts (`admin`, `auth`, `guest`, `memory-public`, `owner`, `vendor-portal`)
- 13 shared `x-ui` components (`avatar`, `badge`, `button`, `empty-state`, `flash`, `icon`,
  `input`, `modal`, `page-header`, `select`, `stat-card`, `textarea`, `theme-switcher`)

### Tests (733 PHPUnit tests / 2307 assertions, PHPUnit 12; 56 Vitest tests in 6 files)
76 PHPUnit test classes under `tests/Unit` and `tests/Feature` (see `docs/TESTING.md` for the
per-file breakdown), plus 6 Vitest client test files under `tests/js/`.

### Verification scripts (`bin/`)
- `bin/blade_lint.php` — view compiler + PHP syntax linter (145 views clean)
- `bin/smoke.sh` — real HTTP test through `php artisan serve` (admin, owner, guest, invalid token)
- `bin/smoke_urls.php` — dynamic route-to-URL resolver with database parameter binding

### Documentation (`docs/`)
- `docs/ERD.md` — Mermaid entity relationship diagram, cardinality notes, enum tables
- `docs/DATABASE.md` — full schema dictionary for all 90 domain tables
- `docs/ROLES.md` — role matrix, route protection, policy rules, token model, owner scoping
- `docs/PERMISSIONS.md` — permission and authorization reference
- `docs/IDENTITY.md` — identity model (users, owners, memberships)
- `docs/TENANCY.md` — event/organization tenancy and isolation
- `docs/EVENT-WORKSPACE.md`, `docs/EVENT-TEAM.md`,
  `docs/EVENT-SETUP-WIZARD.md`, `docs/WORKSPACE-ONBOARDING.md`, `docs/INVITATIONS.md`,
  `docs/SCHEDULE-ARCHITECTURE.md`, `docs/OPERATIONAL-PLAYBOOK.md`, `docs/DUPLICATE-BLUEPRINT.md`
  — milestone feature documentation
- `docs/MILESTONE-2-MIGRATION.md`, `docs/MILESTONE-3-MIGRATION.md` — retained historical migration
  notes
- `docs/USER-JOURNEY.md` — end-to-end user journeys (superadmin, owner, guest, visitor)
- `docs/INSTALL.md` — system MariaDB vs user-space MariaDB, environment, seeding, troubleshooting
- `docs/DEPLOY.md` — production release guide, nginx config, permissions, release recipe
- `docs/DEMO.md` — 15-minute guided tour of every role and edge case
- `docs/TESTING.md` — test suite breakdown, verification layers, debugging recipes
- `README.md` — project overview, feature map, security model, quick start
- `PROGRESS.md` — this file; delivery status and known limitations

---

## 3. Notable bugs fixed during verification

These were real bugs discovered by the verification harness, diagnosed, and repaired:

1. **Route-parameter binding shift:**
   When a route declares an extra URI parameter that was not in the controller signature
   (e.g. `{token}` in `/invite/{token}/game/{game}`), positional dependency injection shifted when a
   `Request` or `FormRequest` was resolved first.
   *Fix:* Controller signatures now explicitly include `string $token` in URI order, e.g.
   `show(Request $request, string $token, Game $game)` and
   `vote(GuestPollRequest $request, string $token, Poll $poll)`.
2. **Alpine vs Blade component binding:**
   On Blade components, `:prop="expr"` is evaluated by Blade at compile time, not by Alpine in the
   browser. This caused `Undefined constant "scan"` and `Undefined constant "lastResult"` errors in
   the camera scanner view.
   *Fix:* Replaced `:class` and avatar bindings with `x-bind:class` and plain `x-text` DOM nodes.
3. **Model method called as relation:**
   `GameQuestion::correctOption()` returned a value rather than a relationship instance, but was
   accessed as a dynamic property `$question->correctOption` in the game review view.
   *Fix:* Removed the property access from the view and deleted the dead method.
4. **Missing view contract on guest invitation:**
   The guest invitation controller was missing several view variables expected by optional sections
   (`myPollResponse`, `allowsGuestActions`, etc.), and had an unguarded `$token` access in the share
   block during admin preview.
   *Fix:* Supplied the complete view contract and guarded the share block behind `$shareUrl`.
5. **Event create schedule rows:**
   The event create view attempted to iterate `$schedules` which was null on `create()`.
   *Fix:* Controller passes `'schedules' => []` and the view falls back to `old('schedules', [])`.
6. **UserFactory default active state:**
   `User::factory()->create()` does not hydrate database column defaults in SQLite testing, leaving
   `is_active = null` and causing every authenticated test to be rejected by the `active` middleware.
   *Fix:* `UserFactory` explicitly sets `'is_active' => true` and `'role' => 'owner'`.

---

## 4. Documented limitations

Deliberate scope boundaries, not hidden omissions (also documented in `README.md`):

1. **Live Wall is poll-based**, not WebSocket/broadcast-based. It refreshes every few seconds via
   `fetch('/wall/{slug}/messages?since_id=...')`.
2. **Email delivery is not wired to a third-party transactional provider.** Mail is configured via
   Laravel's standard mailer; the demo uses the `log` driver. Invitations are shared via WhatsApp
   deep links and clipboard copy.
3. **Media uploads use the local `public` disk.** S3 works via `FILESYSTEM_DISK=s3`, but no CDN or
   on-the-fly image resizing pipeline is included.
4. **PDF export uses Dompdf** and formats tabular data; it does not render JavaScript charts into
   the PDF.
5. **QR PNG renderer uses PHP GD** (BaconQrCode matrix + `imagepng`) because the target environment
   does not have `imagick`. SVG rendering uses `simplesoftwareio/simple-qrcode`.
6. **No 2FA, SSO, or social login.** Authentication is email + password with rate limiting and
   session invalidation for inactive accounts.
7. **No automated browser/E2E suite** (no Dusk/Playwright); client behaviour is verified via
   `bin/smoke.sh` and manual testing.
