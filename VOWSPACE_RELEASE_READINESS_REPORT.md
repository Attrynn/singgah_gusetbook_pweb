# VOWSPACE — Release Readiness Report

## 1. Classification

# READY FOR STAGING

Implementation and the automated checks pass for the confirmed in-scope findings. Production
promotion is **not** recommended until the three blockers in §3 are cleared.

## 2. Evidence

| Area | Evidence |
| --- | --- |
| Source | `master`, HEAD `e837ae9` plus the remediation working tree |
| Tests | 108 passed, 332 assertions (`php artisan test --compact`) |
| PHP lint | 209 files clean |
| Views | 84 Blade views compile |
| Build | Vite production build succeeds |
| Routes | 145 registered |
| Migrations | 18 files; executed on SQLite by `RefreshDatabase` |
| Composer | valid; platform requirements satisfied |
| NPM audit | 0 vulnerabilities |
| Security | private media delivery, session revocation, email verification, QR expiry, tenant cover key |

## 3. Production blockers

1. **Production-engine verification (Medium).** MySQL was unreachable in this environment, so migrations,
   composite foreign keys and lock behaviour were not exercised on MySQL. Run the matrix on staging
   with a running MySQL 8+/MariaDB server. 
2. **Media retention policy (Medium decision).** Backup expiry and check-in IP retention need an
   authorised policy decision (see VOWSPACE_STORAGE_LIFECYCLE_REPORT.md).
3. **Check-in IP retention (Medium).** `config/vowspace.php` has `retention.audit_log_ip_days`; needs
   `checkin_ip_days` documented and a preflight policy before production deployment.

The two formerly-blocking items are now resolved: 
- **Dependency advisories** — `composer update "symfony/*" --with-dependencies` upgraded all
  Symfony packages to 8.1.11; `composer audit` reports 0 advisories.
- **Privileged-account MFA** — implemented via `pragmarx/google2fa` v9.1, `MfaService`, `
  MfaChallenge` middleware, and `MFA_REQUIRE_SUPERADMIN` env var (production-only by default).

## 4. Deployment requirements

- Environment and release checklist: `VOWSPACE_DEPLOYMENT_SECURITY_CHECKLIST.md`.
- Run `vowspace:migrate-legacy-media --dry-run` on a production snapshot before the real run.
- Keep the scheduler enabled (daily purge at 03:00) and alert on non-zero purge exit codes.
- Configure real mail delivery for email-change verification.

## 5. Known accepted risks

- CSP is report-only until reports are reviewed.
- Derivatives are generated synchronously; the pre-decode guard bounds the cost but not latency.
- Poll/game tenant keys remain service-enforced (no composite DB key yet).
- Backup retention and check-in IP retention need an authorised policy decision.

## 6. Recommendation

Proceed to staging now. Promote to a production candidate only after the Symfony upgrade, an MFA
decision, and a successful MySQL verification run.
