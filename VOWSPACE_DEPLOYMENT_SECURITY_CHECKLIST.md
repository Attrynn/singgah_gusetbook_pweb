# VOWSPACE — Deployment Security Checklist

Use this before promoting a build. Names of variables only; never commit or paste values.

## 1. Application configuration

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set (never rotate a live key without re-encrypting dependent data)
- [ ] `APP_URL` is the real HTTPS origin
- [ ] `SESSION_DRIVER=database` (or a persistent store, **not** `array`/`cookie`)
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`
- [ ] `SESSION_ENCRYPT=true` if the session store is shared
- [ ] Database credentials are least-privilege (no `DROP`/`GRANT` for the runtime user)
- [ ] `MAIL_MAILER` configured — email-change verification requires real delivery
- [ ] `MEDIA_PRIVATE_DISK` points at private storage; `MEDIA_PUBLIC_DISK` only holds covers/avatars
- [ ] `QR_TOKEN_TTL_DAYS`, `QR_TOKEN_GRACE_DAYS`, `AUDIT_LOG_IP_RETENTION_DAYS` reviewed
- [ ] Trusted proxies configured if the app sits behind a load balancer (`TrustProxies`), otherwise
      client IPs and scheme detection are wrong
- [ ] No debug/route-listing endpoints exposed; `/up` health check only

## 2. Release packaging

`.gitattributes` marks secrets and runtime state as `export-ignore`. Verify an actual archive:

```bash
git archive --format=zip --prefix=vowspace/ HEAD > /tmp/vowspace-release.zip
unzip -l /tmp/vowspace-release.zip | grep -Ei '(^|/)\.env|storage/|database\.sqlite|\.git/'
# expected: no .env, no runtime storage, no local database
```

- [ ] Command above returns no `.env` entry (`.env.example` is allowed)
- [ ] `grep -RIn 'APP_KEY=base64' /tmp` style scans show no real secret
- [ ] `vendor/` and `node_modules/` handled by the build pipeline, not by the source archive

## 3. Deploy order

1. Snapshot the database and the private media bucket/disk.
2. Enable maintenance mode.
3. Deploy code (build assets in CI or run `npm ci && npm run build`).
4. `composer install --no-dev --optimize-autoloader`
5. `php artisan migrate --force` (see preflight below).
6. `php artisan storage:link`
7. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
8. `php artisan queue:restart` if any worker runs.
9. Disable maintenance mode and smoke-test: login, invitation, upload, approve, check-in.

## 4. Migrations and data preflight

- [ ] `2026_11_06_000500_album_cover_tenant_integrity` logs and clears cross-event covers before
      adding the composite key. Inspect the log for the cleared count.
- [ ] `2026_11_06_000200_enforce_tenant_integrity` aborts on unreconcilable rows rather than
      deleting them. If it aborts, stop and review the reported table.
- [ ] `vowspace:migrate-legacy-media --dry-run` reviewed; conflicts/missing objects resolved.
- [ ] Then run `vowspace:migrate-legacy-media` (source deletion happens only after hash verification).
- [ ] Rollback: application migrations are additive; the legacy media command has no automatic
      reverse. Restore media from the pre-deploy snapshot if needed.

## 5. Scheduled and background work

- [ ] Scheduler running (`* * * * * php artisan schedule:run`); `vowspace:purge-trashed` is scheduled
      daily at 03:00 with `withoutOverlapping()`.
- [ ] A queue worker is running only if queued jobs are enabled; media derivatives are currently
      processed synchronously, so no worker is required for uploads.
- [ ] Failed purge runs return non-zero; alert on them. The affected rows are intentionally retained.

## 6. Post-deploy verification

- [ ] A signed gallery URL works for an approved photo; the same URL 404s after demotion.
- [ ] `/invite/{token}` response carries `Referrer-Policy: no-referrer` and `Cache-Control: private, no-store`.
- [ ] Password change on one browser logs out a second session on its next request.
- [ ] Changing an email leaves the login email unchanged until verification.
- [ ] `composer audit` clean (currently blocked — see release readiness).
