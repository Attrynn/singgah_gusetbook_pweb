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

1. **Dependency advisories (High).** Four Symfony packages at 8.1.10 carry 7 published advisories;
   patched in 8.1.11. Requires approval to run `composer update` and re-verify.
2. **Privileged-account MFA (High).** Not implemented. Requires an approved TOTP package plus a
   controlled enrolment/recovery rollout. Do not expose superadmin accounts to the public internet
   without it.
3. **Production-engine verification (Medium).** MySQL was unreachable, so migrations, composite
   foreign keys and lock behaviour were not exercised on MySQL. Run the matrix on staging.

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
