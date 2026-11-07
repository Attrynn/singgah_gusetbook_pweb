# VOWSPACE — Security, Privacy & Backend Remediation Report

Branch: `master`. Audited source: commit `e837ae9` plus this working tree.
No credential values, tokens or private URLs appear in this report.

## 1. Executive summary

The bounded remediation cycle is complete for the confirmed media-privacy, session-revocation,
email-verification, QR-lifecycle, tenant-cover, album-deletion, audit-durability and purge findings.
108 automated tests pass (332 assertions). Guest media is now private by default and delivered
only through a signed endpoint that re-checks access on every request.

Two release-critical items remain open and are **not** claimed as resolved: privileged-account MFA
(no package approved) and four Symfony packages at 8.1.10 with published advisories patched in
8.1.11 (dependency change requires approval). MySQL-specific verification was blocked because no
server was reachable.

## 2. What was preserved

- Composite tenant foreign keys from the previous cycle (guests, RSVP, check-in, tokens,
  invitations, guestbook, song requests, seating, media).
- Check-in transaction + row locking, duplicate prevention, guest/event revalidation.
- High-entropy tokens with SHA-256 lookup hash and encrypted ciphertext; regeneration revokes prior
  tokens.
- Private staging for new guest uploads (now extended to the full lifecycle).
- Role middleware, active-account middleware, rate limiters, CSV formula sanitisation, existing
  validation, Midnight Slate & Champagne design system.

## 3. Findings resolved

| ID | Result | Evidence |
| --- | --- | --- |
| VS-SEC-001 | Partial (source-side) | `.env` untracked; `.gitattributes` export-ignore; archive inspection pending |
| VS-SEC-002 | Resolved | `auth.session` enforced; stale session hash rejected |
| VS-PRV-001 | Resolved | Signed delivery; pending/demoted/archived all denied |
| VS-PRV-002 | Resolved | No publication copy step remains |
| VS-PRV-003 | Resolved | Original, thumbnail and medium deleted |
| VS-PRV-004 | Resolved | Event purge inventories and verifies every object |
| VS-PRV-005 | Resolved | `vowspace:migrate-legacy-media` with dry-run + hash verification |
| VS-SEC-003 | Resolved | Header/pixel guard before GD decode |
| VS-SEC-004 | Resolved | Checked writes; deletion fails closed |
| VS-ID-001 | Resolved | Pending email, hashed single-use token, expiry, notices |
| VS-ID-002 | Resolved | QR TTL 30 days bounded by event end + 3 days |
| VS-LOG-001 | Resolved | Check-in audit is transactional and fail-closed |
| VS-PRV-006 | Partial | Audit IP/UA anonymised after 90 days; backup retention undecided |
| VS-BE-001 | Resolved | Album deletion detaches media and audits critically |
| VS-DB-001 | Resolved | Composite album-cover FK with preflight |
| VS-DOC-001 | Resolved | Counts regenerated from commands |

## 4. Changes by area

**Security** — `auth.session` on authenticated groups; `SecurityHeaders` middleware (nosniff,
frame options, referrer policy, permissions policy, HSTS when secure, report-only CSP);
`no-referrer` + `no-store` on invitation pages; inactive owners rejected on assignment.

**Privacy** — Approved media no longer implies a public object. Invitation pages keep tokens out
of caches and referrers. Audit identifiers are minimised after a configurable window.

**Storage** — Private-by-default originals and derivatives; signed `/media/{asset}` delivery;
derivative writes checked; rejection/demotion withdrawal; deletion is physical-first; purge is
verified and idempotent; legacy migration command.

**Database** — New migrations: album-cover tenant integrity and pending-email columns. Existing
composite constraints verified, not duplicated.

**Backend** — Check-in audit durability; QR lifecycle tied to event dates; album deletion
consistency; purge covers gallery objects.

**User experience** — Profile screens show the pending email and state that the login email has not
changed; pass page refuses expired passes; gallery markup uses signed URLs.

**Deployment** — `.gitattributes` release exclusions; environment template documents
`MEDIA_PRIVATE_DISK` and delivery TTL.

## 5. Test results (actual)

| Check | Command | Result |
| --- | --- | --- |
| PHP syntax | `php -l` across 210 files | PASS |
| Test suite | `php artisan test --compact` | 108 passed, 332 assertions |
| Blade lint | `php bin/blade_lint.php` | 84 views clean |
| Composer validation | `composer validate --no-check-publish` | Valid |
| Platform requirements | `composer check-platform-reqs` | All satisfied (PHP 8.5.12) |
| Dependency audit | `composer audit` | **7 advisories / 4 packages** (Symfony 8.1.10) |
| NPM audit | `npm audit --omit=dev` | 0 vulnerabilities |
| Frontend build | `npm run build` | Built successfully |
| Routes | `php artisan route:list --json` | 145 routes |
| Migrations | `php artisan migrate:status` | **BLOCKED** — MySQL refused on 127.0.0.1:3307 |
| Code style | `vendor/bin/pint --dirty` | Passed |

## 6. Remaining risks

1. **Symfony advisories (High, external decision).** Upgrade to ≥ 8.1.11 and re-run audit/tests.
2. **MFA for superadmins (High, not implemented).** Requires an approved TOTP package and a
   controlled enrolment/recovery rollout.
3. **MySQL verification (Medium, blocked).** SQLite proves logic, not concurrency or engine-specific
   DDL. Run migrations and the check-in/seating concurrency checks on the production engine.
4. **Poll/game tenant keys (Medium, partial).** Service-layer ownership is enforced and tested; the
   composite database keys are deferred.
5. **Media retention policy (Medium decision).** Backup expiry and check-in IP retention need an
   authorised policy decision.
6. **Derivative processing is synchronous.** Bounded by the pre-decode guard, but it is still
   request-time work.
7. **Legacy migration on real data.** Automated tests use fake disks; run `--dry-run` on a
   production snapshot first.

## 7. Documentation produced/updated

`VOWSPACE_PRIVACY_DATA_INVENTORY.md`, `VOWSPACE_TENANT_INTEGRITY_REPORT.md`,
`VOWSPACE_STORAGE_LIFECYCLE_REPORT.md`, `VOWSPACE_SECURITY_FINDINGS_MATRIX.csv`,
`VOWSPACE_REGRESSION_TEST_REPORT.md`, `VOWSPACE_DEPLOYMENT_SECURITY_CHECKLIST.md`,
`VOWSPACE_REMEDIATION_REGISTER.md`, `VOWSPACE_RELEASE_READINESS_REPORT.md`, this report,
`PROGRESS.md`, `docs/TESTING.md`.

## 8. Closure declaration

**VOWSPACE SECURITY & BACKEND REMEDIATION — CLOSED** for the confirmed in-scope findings above.

Closure is bounded by the accepted risks in section 6. Reopen security work only for a
reproducible vulnerability, a confirmed regression, a new dependency advisory affecting deployed
components, or a material architecture/threat-model change. Do not start another broad audit as the
next development step.
