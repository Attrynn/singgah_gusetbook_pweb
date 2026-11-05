# Deployment

Target: a single Linux host running nginx + PHP-FPM + MySQL/MariaDB. Nothing in the app requires a
queue worker, a WebSocket server, or a cron job, because no queued jobs or scheduled tasks are
registered. That is a deliberate scope decision, not an oversight — see
[Known limitations](../README.md#known-limitations).

---

## 1. Server packages

```bash
# Debian/Ubuntu example
sudo apt install nginx mysql-server \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-gd php8.3-intl php8.3-zip php8.3-bcmath
```

Node.js is only needed at build time. You can build assets locally/CI and ship `public/build`.

---

## 2. Filesystem layout and permissions

```bash
sudo mkdir -p /var/www/vowspace
sudo chown -R deploy:www-data /var/www/vowspace
cd /var/www/vowspace
```

The web server user needs write access to:

```
storage/
bootstrap/cache/
```

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

Do **not** grant write access to `app/`, `config/`, `routes/`, `public/`.

---

## 3. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Production values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vowspace.example.com

LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vowspace
DB_USERNAME=vowspace
DB_PASSWORD=<strong-password>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=false
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@vowspace.example.com
```

`APP_DEBUG=false` is mandatory. Debug pages leak environment variables and query bindings.

---

## 4. Build and migrate

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan storage:link
```

Seed only for a demo/staging deployment:

```bash
php artisan migrate:fresh --seed --force   # DESTROYS DATA
```

### Cache warm-up

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Re-run these after any config, route or view change. To roll back during debugging:

```bash
php artisan optimize:clear
```

---

## 5. nginx

```nginx
server {
    listen 80;
    server_name vowspace.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name vowspace.example.com;

    root /var/www/vowspace/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/vowspace.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/vowspace.example.com/privkey.pem;

    client_max_body_size 12M;   # guest photo uploads are capped at 5 MB each

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|webp|svg|css|js|woff2)$ {
        expires 30d;
        access_log off;
    }
}
```

`client_max_body_size` must be at least as large as the largest accepted upload plus multipart
overhead. PHP's `upload_max_filesize` and `post_max_size` must match.

---

## 6. Database

Create a dedicated user with only the privileges it needs:

```sql
CREATE DATABASE vowspace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vowspace'@'127.0.0.1' IDENTIFIED BY '<strong-password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP
  ON vowspace.* TO 'vowspace'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Backups:

```bash
mysqldump --single-transaction --routines --triggers vowspace \
  | gzip > /var/backups/vowspace-$(date +%F).sql.gz
```

Back up `storage/app/public` alongside the database — media rows are useless without the files.

---

## 7. Media storage

Default is the local `public` disk (`storage/app/public`), exposed through the `public/storage`
symlink. For multi-host or container deployments, switch to S3:

```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
AWS_URL=https://cdn.example.com
```

The service layer uses `Storage::disk(config('filesystems.default'))`-style access through
`MediaService`, so no code change is required. There is no thumbnail pipeline; originals are served.

---

## 8. Security checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS enforced, `SESSION_SECURE_COOKIE=true`
- [ ] Strong `APP_KEY` (32 bytes, generated, never committed)
- [ ] Database user limited to one schema
- [ ] `storage/` and `bootstrap/cache/` writable, everything else read-only
- [ ] `.env` not web-accessible (it lives above `public/`)
- [ ] `php artisan storage:link` present; no directory listing on `/storage`
- [ ] Rate limiters active: `guest-actions`, `guestbook`, `guest-uploads`, `guest-poll`, plus the
      5-attempt login throttle in `LoginController`
- [ ] Rotate demo accounts or run `php artisan migrate:fresh --seed` only on staging
- [ ] Backups for database **and** media verified by a restore drill

---

## 9. Zero-downtime-ish release

```bash
cd /var/www/vowspace
git fetch && git checkout <tag>

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache

sudo systemctl reload php8.3-fpm
```

Migrations are additive where possible. Two known constraints if you must roll back a deploy:

- `migrate:rollback` drops tables; take a backup first.
- Cached routes/config must be cleared (`php artisan optimize:clear`) before reverting code.

---

## 10. Health and monitoring

- `GET /up` — Laravel health endpoint, returns `200` when the app boots.
- Application log: `storage/logs/laravel.log` (single channel by default).
- Audit trail: `/admin/audit-logs`, table `audit_logs`.
- Suggested checks: HTTP 200 on `/up`, DB connectivity, disk space on `storage/app/public`,
  and a synthetic login every few minutes.

---

## 11. What is intentionally not included

Stated plainly so nobody assumes it exists:

- No queue worker or scheduler is required or configured.
- No WebSocket/broadcast server; the live wall polls `GET /wall/{slug}/messages`.
- No email provider integration beyond Laravel's standard mailer config.
- No CDN, image resizing, or thumbnail generation.
- No 2FA, SSO, or social login.
- No multi-tenant subdomain routing; tenancy is row-level via `event_id`.
