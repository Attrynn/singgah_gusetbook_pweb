# Testing and Verification

Four layers, each answering a different question. All of them are runnable today.

| Layer | Command | Question it answers |
| --- | --- | --- |
| Feature tests | `php artisan test` | Does the business logic and authorization behave correctly? |
| Blade lint | `php bin/blade_lint.php` | Does every view compile to valid PHP? |
| HTTP smoke | `./bin/smoke.sh` | Does every GET route actually render on a real server? |
| Build | `npm run build` | Do the CSS/JS bundles compile? |

---

## 1. Feature tests

```bash
php artisan test
php artisan test --compact          # one-line summary
php artisan test --filter=Checkin   # a single file
```

Current state: **54 tests, 144 assertions, all passing.**

Environment: `phpunit.xml` forces `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:`, `CACHE_STORE=array`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`,
`BCRYPT_ROUNDS=4`. Tests use `RefreshDatabase`, so each test runs against a clean schema. No MySQL
server is required.

> Constraint: migrations must stay SQLite-compatible. No `fullText`, no `->after()`, no
> `ENGINE=` clauses, no spatial indexes.

### Coverage map

| File | Tests | Covers |
| --- | --- | --- |
| `AuthenticationTest` | 6 | login page, role-based redirects, bad credentials, inactive user, logout |
| `AuthorizationTest` | 7 | guest redirects, owner↔admin separation, inactive session invalidation, owner without event |
| `GuestInvitationTest` | 7 | valid token render, unknown/revoked/expired 404, cross-event isolation for games and polls, admin preview |
| `RsvpTest` | 5 | successful RSVP, declined forces 0 attendance, deadline enforcement, draft-event rejection, attendee cap |
| `CheckinTest` | 5 | QR check-in, duplicate prevention, wrong-event detection, invitation token rejected as QR, manual check-in |
| `GuestbookTest` | 5 | wish submission, minimum length, disabled guestbook, admin approval, live wall filtering |
| `InteractionTest` | 5 | song request, single poll vote, closed poll rejection, quiz scoring + single attempt, draft-event block |
| `MediaUploadTest` | 5 | guest upload pending, non-image rejected, gallery-disabled block, admin auto-approve, moderation |
| `AdminCrudTest` | 7 | event create with schedules, guest create issues tokens, guest CSV export, report CSV, status change, settings, owner denied |
| `ExampleTest` (Feature) | 1 | home page renders |
| `ExampleTest` (Unit) | 1 | framework sanity check |

### Helpers

`tests/Concerns/BuildsWedding.php` provides:

```php
$this->makeEvent(['status' => 'live']);
$this->makeGuest($event, ['max_attendees' => 4]);
$this->invitationToken($guest);   // raw invitation token, issuing it if needed
$this->qrToken($guest);           // raw QR token
```

`UserFactory` has `superadmin()`, `owner()`, `inactive()` states. The default state is an active
`owner` — tests that need an admin must say `superadmin()`.

> Why: `User::factory()->create()` does not hydrate database column defaults, so `role` and
> `is_active` are set explicitly in the factory. Without that, every authenticated test hit the
> `active` middleware and failed with "Akun Anda dinonaktifkan."

### What is not unit-tested

Stated honestly:

- No browser/E2E suite (no Dusk, no Playwright). Interactive behaviour — camera scanning, Alpine
  toggles, Chart.js rendering — is verified manually and by the HTTP smoke test, not automatically.
- No test asserts PDF *content*; only that CSV endpoints stream and that the PDF route responds.
- No load/performance tests.
- No mail-sending tests, because no mail is sent by the application.
- Seating, reports and audit-log screens have route-level smoke coverage but no dedicated feature
  assertions.

---

## 2. Blade lint

```bash
php bin/blade_lint.php
# ALL 77 VIEWS CLEAN
```

The script clears the view cache, compiles every `resources/views/**/*.blade.php`, resolves the
compiled path with `$compiler->getCompiledPath($path)`, and runs `php -l` on the result. It exits
non-zero on the first broken view and prints the offending file and error.

This catches the class of bug that route smoke tests also catch but with a much slower loop:
unbalanced `@if`/`@endif`, a directive glued to a preceding word (`selected@endif`), and invalid
PHP emitted by a Blade expression.

> Note: `Blade::compile()` returns `void` in this Laravel version. Reading the compiled file is the
> only reliable way to lint the real output.

---

## 3. HTTP smoke test

```bash
php artisan serve --host=127.0.0.1 --port=8123 &
./bin/smoke.sh
# BASE=http://127.0.0.1:8000 ./bin/smoke.sh   # different port
```

What it does:

1. Logs in as `admin@vowspace.test` and requests every resolvable admin GET route.
2. Logs in as `raka@vowspace.test` and requests every owner GET route.
3. Requests every guest and live-wall route using a real seeded token.
4. Requests `/invite/invalid-token` and expects `404`.

Failures printed:

- `FAIL <code> <name> <url>` — 4xx/5xx.
- `AUTH-REDIRECT ... -> /login` — a protected route bounced to login while authenticated.
- `LOGIN-FAILED` — credentials or session handling broke.

`bin/smoke_urls.php` builds the URL list from the live router and resolves parameters from the
database (`event`, `guest`, `poll`, `game`, `message`, `table`, `album`, `log`, …). It appends
`?event_id=` to export/report endpoints, which legitimately require one.

Exit status is not aggregated: the script prints failures and ends with `== done ==`. Read the output;
do not assume silence means success. A clean run prints only the section headers and
`invalid-token -> 404`.

### Write-path verification

The smoke script covers GET only. Write paths were verified against a real server with a
cookie-jar + CSRF session:

| Action | Observed |
| --- | --- |
| RSVP submit | `302` redirect back |
| Guestbook submit | `302` redirect back |
| Song request | `302` redirect back |
| Poll vote | `302` redirect back |
| Game page | `200` |
| Admin QR scan (`POST /admin/checkins/scan`) | `200` `{"status":"success", …}` |
| Admin login | `302` |

The same paths are also asserted in the feature suite, which is the durable regression guard.

---

## 4. Build

```bash
npm run build
```

Compiles `resources/css/app.css` (Tailwind v4) and `resources/js/app.js` into `public/build` with a
manifest. A missing manifest surfaces immediately as a 500 on every page, so run this before any
demo.

---

## Recommended pre-merge checklist

```bash
php artisan test --compact
php bin/blade_lint.php
npm run build
php artisan serve --port=8123 &  ./bin/smoke.sh
```

Then reset the demo data if the smoke run mutated it:

```bash
php artisan migrate:fresh --seed
```

---

## Debugging recipes

| Symptom | Command |
| --- | --- |
| Stale Blade output | `php artisan view:clear` |
| Stale config/routes | `php artisan optimize:clear` |
| View 500 with no message | `tail -50 storage/logs/laravel.log`, then `php bin/blade_lint.php` |
| "Undefined constant X" in a view | an Alpine expression used `{{ }}`; switch to `x-text` / `x-bind:` |
| "must return a relationship instance" | a model method returning a value was called as a property |
| Wrong model injected into a controller | extra route parameter missing from the method signature — include it in URI order |
| Route 404 only in tests | route cache from a previous run — `php artisan route:clear` |
