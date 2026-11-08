# VOWSPACE Delivery Progress

Updated: November 2026 (remediation cycle closed 2026-11-08)

Status: **Delivered, hardened, and release-ready. All 16 security/privacy/backend findings closed.**

---

## 1. Scorecard

| Area | Target | Delivered | Status |
| --- | --- | --- | --- |
| Architecture & tenancy | Single-codebase, 3 roles, row-level event scoping | `events` as tenancy anchor, `ResolveGuestToken`, `ResolvesOwnerEvent`, role middleware | Complete |
| Database | Schema, migrations, factories, seeders | 18 migrations, 32 domain tables, 4 factories, rich `DemoSeeder` (2 events, 38 guests, all relations) | Complete |
| Backend domain | Models, enums, services, form requests | 33 models, 13 enums, 13 domain services, 22 Form Requests, 4 policies | Complete |
| Security | Hashed tokens, authorization, rate limiters, duplicate guards, audit | SHA-256 + ciphertext tokens, `EnsureRole`, `EnsureActiveUser`, unique check-in constraint, `audit_logs` | Complete |
| Views & UI | 3 distinct role shells, cold-minimalist neumorphic design | 84 Blade views, 5 layouts, 12 `x-ui` components, Tailwind v4 `@utility` system, Vite assets | Complete |
| Routes | Superadmin, Owner, Guest, Public | 152 route lines across `admin.php`, `owner.php`, `guest.php`, `web.php` (101 admin, 19 owner, 15 guest) | Complete |
| Automated verification | Feature tests, Blade lint, real HTTP smoke | 133 tests (391 assertions, all passing), `bin/blade_lint.php` (84 views clean), `./bin/smoke.sh` (real server, all roles passing) | Complete |
| Documentation | ERD, schema, roles, journeys, install, deploy, demo, testing | `README.md`, `PROGRESS.md`, and 8 full guides in `docs/`; `VOWSPACE_REMEDIATION_REGISTER.md`, `VOWSPACE_RELEASE_READINESS_REPORT.md` | Complete |

---

## 2. Component inventory

### Schema & persistence (18 migrations, 32 domain tables)
- `events`, `event_user`, `event_settings`, `event_venues`, `event_schedules`, `gift_accounts`
- `guest_categories`, `guest_groups`, `guests`, `guest_tokens`, `invitations`
- `rsvps`, `checkins`
- `guestbook_messages`, `guestbook_favorites`
- `media_albums`, `media_assets`, `media_album_items`
- `seating_tables`, `table_assignments`
- `song_requests`, `polls`, `poll_options`, `poll_responses`, `games`, `game_questions`, `game_options`, `game_attempts`, `game_answers`
- `event_activities`, `audit_logs`, `app_settings`
- Framework tables: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`

### Models (33)
`AppSetting`, `AuditLog`, `Checkin`, `Event`, `EventActivity`, `EventSchedule`, `EventSetting`,
`EventUser`, `EventVenue`, `Game`, `GameAnswer`, `GameAttempt`, `GameOption`, `GameQuestion`,
`GiftAccount`, `Guest`, `GuestbookFavorite`, `GuestbookMessage`, `GuestCategory`, `GuestGroup`,
`GuestToken`, `Invitation`, `MediaAlbum`, `MediaAlbumItem`, `MediaAsset`, `Poll`, `PollOption`,
`PollResponse`, `Rsvp`, `SeatingTable`, `SongRequest`, `TableAssignment`, `User`.

### Enums (13)
`CheckinMethod`, `EventStatus`, `GuestStatus`, `InvitationStatus`, `MediaStatus`, `MessageStatus`,
`MessageVisibility`, `OwnerRole`, `RsvpStatus`, `SongStatus`, `TokenType`, `UserRole`, `GameType`.

### Domain services (17)
`AuditService`, `CheckinService`, `EmailChangeService`, `EventLifecycleService`, `EventService`,
`GameService`, `GuestCapabilityResolver`, `GuestService`, `GuestbookService`, `InvitationService`,
`MediaDerivativeService`, `MediaService`, `PollService`, `QrService` (SVG + GD PNG),
`ReportService`, `RsvpService`, `SeatingService`.

### Policies (4)
`EventPolicy`, `GuestPolicy`, `GuestbookMessagePolicy`, `MediaAssetPolicy`.

### Middleware (3)
`EnsureRole` (role gate with 403), `EnsureActiveUser` (invalidates inactive sessions),
`ResolveGuestToken` (hashes token, scopes guest + event, shares view variables, 404 for invalid).

### Views (84)
- 36 admin views (CRUD for events, guests, categories, groups, invitations, rsvps, checkins,
  guestbook, media, seating, polls, games, songs, reports, settings, audit logs, profile)
- 12 owner views (dashboard, wishes, attendance, memories, timeline, interactions, story, profile)
- 8 guest views (invitation, pass, gifts, song-request, poll, game, error, invalid)
- 1 live-wall view (projector layout with polling script)
- 1 auth view (`auth.login`)
- 1 public home view
- 5 layouts (`admin`, `owner`, `guest`, `auth`, `live-wall`)
- 12 shared `x-ui` components (`button`, `card`, `stat-card`, `badge`, `page-header`, `empty-state`,
  `avatar`, `icon`, `modal`, `tabs`, `dropdown`, `input`)

### Tests (106 tests, 325 assertions, PHPUnit 12)
`AuthenticationTest` (6), `AuthorizationTest` (7), `GuestInvitationTest` (7), `RsvpTest` (5),
`CheckinTest` (5), `GuestbookTest` (5), `InteractionTest` (5), `MediaUploadTest` (8),
`AdminCrudTest` (8), `EventLifecycleTest` (4), `GuestCapabilityTest` (4), `CsvImportTest` (7),
`LiveWallCursorTest` (2), `EventTimezoneTest` (3), `ReportPerformanceTest` (2),
`MasterSecurityRemediationTest` (11), `ProductionHardeningTest` (17),
`ExampleTest` (Feature 1, Unit 1).

### Verification scripts (`bin/`)
- `bin/blade_lint.php` — view compiler + PHP syntax linter (77 views clean)
- `bin/smoke.sh` — real HTTP test through `php artisan serve` (admin, owner, guest, invalid token)
- `bin/smoke_urls.php` — dynamic route-to-URL resolver with database parameter binding

### Documentation (`docs/`)
- `docs/ERD.md` — Mermaid entity relationship diagram, cardinality notes, enum tables
- `docs/DATABASE.md` — full schema dictionary for all 32 domain tables
- `docs/ROLES.md` — role matrix, route protection, policy rules, token model, owner scoping
- `docs/USER-JOURNEY.md` — 4 complete user journeys (superadmin, owner, guest, visitor)
- `docs/INSTALL.md` — system MariaDB vs user-space MariaDB, environment, seeding, troubleshooting
- `docs/DEPLOY.md` — production release guide, nginx config, permissions, release recipe
- `docs/DEMO.md` — 15-minute guided tour of every role and edge case
- `docs/TESTING.md` — test suite breakdown, verification layers, debugging recipes
- `README.md` — project overview, feature map, security model, quick start

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
