# VOWSPACE

**Beyond the Guestbook. A Complete Wedding Experience.**

VOWSPACE is a production-oriented wedding management ecosystem built with Laravel. It serves three
distinct audiences from one database: a **Superadmin** operations console, a **read-focused Owner**
space for the couple, and a **secure, token-based Guest** experience — plus a public **Live Wall**
for projected wishes.

The visual identity is a **cold minimalist soft neumorphism** palette (`#F1F5F9`, `#F8FAFC`,
`#E9EFF6`, `#DCE5EF`, `#253447`, `#64748B`, `#94A3B8`, `#8EB4D3`, `#A5C7D4`, `#DCEAF5`, `#DFEFED`,
`#5DAD91`, `#D5AD70`, `#D88C96`). Admin screens are desktop-first; Owner and Guest screens are
mobile-native with a maximum shell width of 480px.

---

## Stack

| Layer | Choice |
| --- | --- |
| Runtime | PHP 8.5 (compatible with `^8.3`) |
| Framework | Laravel 13.40 |
| Database | MySQL 8 / MariaDB 10.6+ (SQLite supported for the test suite) |
| Views | Blade + 12 reusable `x-ui` components + 5 layouts |
| Styling | Tailwind CSS v4 (`@utility`-based design system) |
| Client | Alpine.js, Chart.js, Flatpickr, SweetAlert2, html5-qrcode |
| Build | Vite 8 |
| QR | `simplesoftwareio/simple-qrcode` (SVG) + a GD-based PNG renderer |
| PDF | `barryvdh/laravel-dompdf` |
| Tests | PHPUnit 12 (`php artisan test`) |

---

## Feature map

### Superadmin (desktop-first, `/admin`)

- Dashboard with event, guest, RSVP, check-in and interaction metrics.
- Event CRUD: couple profile, venue, schedules, feature flags (RSVP / guestbook / gallery / gift /
  live wall), status workflow (`draft → published → live → completed → archived`), gift accounts.
- Owner account management (create, edit, activate/deactivate).
- Guest management: CRUD, CSV import, CSV export, bulk delete, token regeneration.
- Guest categories and groups per event.
- Invitations: personal links, QR SVG, WhatsApp deep link, share tracking, admin preview.
- RSVP management and CSV export; manual RSVP overrides.
- Check-in: camera QR scanner, JSON scan API with duplicate prevention, manual check-in.
- Guestbook moderation (approve / reject / hide / pending), pinning, CSV export.
- Media gallery moderation, albums, featured photos, event uploads.
- Seating: tables, capacity, zone, guest assignment and unassignment.
- Interactions: song requests, polls (options, activation, closing), quizzes (questions, options,
  correct answers, activation).
- Reports: per-event summary, RSVP breakdown, check-in timeline, poll/game results, CSV exports,
  PDF report.
- App settings, audit log viewer, own profile and password.

### Owner (mobile-native, `/owner`)

Read-focused personal space scoped to the owner's event(s):

- Dashboard with live counts, latest wishes, latest photos, next schedule and recent activity.
- Attendance overview, wishes feed with favourites, memories/gallery, timeline, interactions
  results, wedding story, profile.

### Guest (mobile-native, `/invite/{token}`)

No login. The opaque token is resolved server-side; every query is scoped to the token's event.

- Personal invitation with countdown, schedule, story, live-wall link.
- RSVP form (status, attendance count, companions, dietary notes).
- Guestbook wishes (public or private) with emoji reactions.
- Photo upload to the event gallery (moderated).
- Digital pass with QR code and downloadable PDF.
- Gift accounts (bank transfer, QRIS, gift address).
- Song requests, polls, and a quiz game with scoring.
- Public event landing page (`/e/{slug}`) and Live Wall (`/wall/{slug}`).

---

## Security model

- Guest tokens are stored as a **SHA-256 hash** for lookup plus a Laravel-encrypted ciphertext so the
  original link/QR can be re-displayed to the couple without ever storing it in plaintext.
- Tokens carry an `expires_at`, `revoked_at` and `last_used_at`; regenerating a token revokes the
  previous one.
- `ResolveGuestToken` middleware resolves `{token}` and injects the guest/event into the request;
  controllers never accept an `event_id` from the guest.
- All admin routes are protected by `auth`, `active` and `role:superadmin`; owner routes by
  `auth`, `active` and `role:owner`. Owners can only read events they are attached to.
- CSRF protection is active on every web POST; guest write actions are additionally rate limited
  (`guest-actions`, `guestbook`, `guest-uploads`, `guest-poll`).
- QR check-in is idempotent: a database unique key plus a row lock prevents double check-in.
- Uploads are validated by MIME type and size before being stored on the `public` disk.
- Every sensitive action writes an `audit_logs` row (actor, event, IP, user agent, metadata).

---

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate

# configure DB_* in .env, then:
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

Demo accounts (all passwords are `password`):

| Role | Email |
| --- | --- |
| Superadmin | `admin@vowspace.test` |
| Owner (groom) | `raka@vowspace.test` |
| Owner (bride) | `alya@vowspace.test` |
| Owner (second event) | `dimas@vowspace.test` |

See [docs/INSTALL.md](docs/INSTALL.md) for a step-by-step local setup (including a user-space
MariaDB instance), and [docs/DEMO.md](docs/DEMO.md) for a guided tour.

---

## Verification

```bash
php artisan test                 # 80 feature tests, 227 assertions, all passing
php bin/blade_lint.php           # compiles + PHP-lints every Blade view (77 views)
./bin/smoke.sh                   # real HTTP smoke test against `php artisan serve`
npm run build                    # Vite production build
```

The smoke script logs in as each role and requests every resolvable GET route, then verifies the
guest token flow. It expects the app to be serving on `http://127.0.0.1:8123` (override with
`BASE=...`).

---

## Project layout

```
app/
  Enums/           13 domain enums (statuses, token types, roles…)
  Http/
    Controllers/   Auth, Admin, Owner, Guest, LiveWall
    Middleware/    EnsureActiveUser, EnsureRole, ResolveGuestToken
    Requests/      Form Requests for every write action
  Models/          33 Eloquent models
  Policies/        Event, Guest, GuestbookMessage, MediaAsset
  Services/        Audit, Checkin, Event, Game, Guest, Guestbook, Invitation,
                   Media, Poll, Qr, Report, Rsvp, Seating
database/
  migrations/      12 migrations, 32 domain tables
  seeders/         DemoSeeder (2 events, 38 guests, RSVP/check-in/wishes/media/polls/games)
  factories/       User, Event, Guest, GuestbookMessage
docs/              ERD, database dictionary, roles, journeys, install, deploy, demo, testing
resources/
  css/app.css      Tailwind v4 design system
  js/app.js        Alpine + Chart.js + Flatpickr + SweetAlert + QR helpers
  views/           77 Blade views (admin, owner, guest, live-wall, layouts, components)
routes/
  web.php          Auth + home
  admin.php        142 routes total across the three role files
  owner.php
  guest.php
bin/               blade_lint.php, smoke.sh, smoke_urls.php
```

---

## Documentation

- [docs/ERD.md](docs/ERD.md) — entity relationship diagram
- [docs/DATABASE.md](docs/DATABASE.md) — table dictionary
- [docs/ROLES.md](docs/ROLES.md) — role and permission matrix
- [docs/USER-JOURNEY.md](docs/USER-JOURNEY.md) — end-to-end journeys
- [docs/INSTALL.md](docs/INSTALL.md) — local installation
- [docs/DEPLOY.md](docs/DEPLOY.md) — production deployment
- [docs/DEMO.md](docs/DEMO.md) — demo walkthrough
- [docs/TESTING.md](docs/TESTING.md) — test and verification strategy
- [PROGRESS.md](PROGRESS.md) — delivery status and known limitations

---

## Known limitations

These are deliberate, documented boundaries rather than hidden gaps:

- **Live Wall is poll-based**, not WebSocket-based. The wall refreshes via `fetch` every few seconds.
- **Email delivery is not wired** to a provider. Mail is configured through Laravel's mailer; the
  demo uses the `log` driver. Invitations are shared through WhatsApp links and copy-to-clipboard.
- **Uploads use the local `public` disk.** Object storage (S3) works by changing `FILESYSTEM_DISK`,
  but no CDN/thumbnail pipeline is included.
- **PDF report** is generated with Dompdf and contains the summary tables, not charts.
- **QR PNG generation uses GD** because the reference environment lacks Imagick; SVG output uses
  SimpleQrCode.
- **No 2FA, no social login.** Authentication is email + password with rate limiting.
