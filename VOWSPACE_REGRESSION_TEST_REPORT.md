# VOWSPACE — Regression Test Report

Environment: PHP 8.5.12 (CLI), Laravel 13.40.0, PHPUnit 12, Node build via Vite.
Test database: SQLite `:memory:` (`phpunit.xml`), `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`.

## 1. Executed checks

| # | Command | Result | Evidence |
| --- | --- | --- | --- |
| 1 | `php -l` over `app config database routes tests` | PASS | 210 files, no syntax errors |
| 2 | `php artisan test --compact` | PASS | 108 tests, 332 assertions, 1.96s |
| 3 | `php bin/blade_lint.php` | PASS | `ALL 84 VIEWS CLEAN` |
| 4 | `composer validate --no-check-publish` | PASS | `./composer.json is valid` |
| 5 | `composer check-platform-reqs` | PASS | all requirements satisfied |
| 6 | `composer audit` | FAIL | 7 advisories / 4 packages (see §4) |
| 7 | `npm audit --omit=dev` | PASS | `found 0 vulnerabilities` |
| 8 | `npm run build` | PASS | Vite built in 291ms |
| 9 | `php artisan route:list --json` | PASS | 145 routes |
| 10 | `vendor/bin/pint --dirty` | PASS | style clean |
| 11 | `php artisan migrate:status` | BLOCKED | MySQL refused on 127.0.0.1:3307 |

## 2. Coverage added this cycle

`tests/Feature/ProductionHardeningTest.php` (17 tests):

- Signed media delivery: unsigned request forbidden, signed request OK.
- Pending media is not deliverable even with a signature.
- Demotion revokes access immediately for an already-issued URL.
- Archiving the event revokes access.
- Album cover must belong to the same event (database rejection).
- Album deletion detaches media and preserves the photo row.
- QR pass expires after the event grace period and does not resolve.
- Check-in writes a durable audit row inside the transaction.
- Email change stays pending; verification applies it; token is single-use; expired token rejected.
- Admin/owner route groups enforce `auth.session`; a stale session password hash is rejected.
- Purge anonymises old audit identifiers.
- Event purge removes original, thumbnail and medium objects.
- Legacy public media migrates to private storage; dry-run changes nothing.

Updated: `MediaUploadTest` now asserts private storage and no public copy; the existing derivative
tests run on the private disk.

## 3. What these tests actually prove

- Application behaviour under a single SQLite connection, including constraint definitions.
- Storage behaviour through Laravel fake disks (not S3 and not real filesystem permissions).
- Transactional audit coupling for check-in and email change.

## 4. Failing check detail — dependency audit

`composer audit` reports 7 advisories affecting 4 packages, all locked at 8.1.10:

| Package | Advisories | Patched |
| --- | --- | --- |
| `symfony/http-foundation` | PKSA-24ph-xkrh-y79j, PKSA-pr6q-2w5s-kzzf, PKSA-nxjs-s6hn-vb6m, PKSA-b2gg-vk1g-6d5q | ≥ 8.1.11 |
| `symfony/http-kernel` | PKSA-3gxv-7xhq-mgmz | ≥ 8.1.11 |
| `symfony/routing` | PKSA-y8c5-1b7f-r1gj | ≥ 8.1.11 |
| `symfony/yaml` | PKSA-nktx-f1n1-b4yd | ≥ 8.1.11 |

No dependency was modified because that requires explicit approval. The application should not be
promoted to production until these are patched and the suite is re-run.

## 5. Checks not executed (explicitly BLOCKED / not claimed)

| Check | Reason |
| --- | --- |
| `php artisan migrate:status` against MySQL | No MySQL server at 127.0.0.1:3307 |
| MySQL foreign-key/lock/concurrency matrix | Same |
| S3-compatible integration test | No bucket/credentials in this environment |
| Browser/E2E workflows | No Dusk/Playwright suite in the project |
| Real backup/restore drill | Infrastructure not available here |
| Real `git archive` secret inspection | Requires committing the working tree first |

None of the above is reported as passed.
