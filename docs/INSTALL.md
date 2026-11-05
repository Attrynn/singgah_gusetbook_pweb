# Installation

Two supported paths: a standard MySQL/MariaDB service (the common case) and a **user-space MariaDB**
for machines where you cannot use `sudo`. Both end at the same place.

---

## Requirements

| Component | Version | Notes |
| --- | --- | --- |
| PHP | 8.3+ (tested on 8.5) | `php -v` |
| Composer | 2.x | |
| Node.js | 20+ (tested on 26) | for Vite |
| Database | MySQL 8 or MariaDB 10.6+ | SQLite is fine for `php artisan test` only |

Required PHP extensions:

```
openssl pdo pdo_mysql mbstring tokenizer xml ctype json bcmath fileinfo curl gd intl zip
```

`gd` is required — the QR **PNG** renderer writes the matrix with `imagepng()`. `imagick` is **not**
needed.

Verify:

```bash
php -m | grep -E 'gd|pdo_mysql|mbstring|fileinfo|intl|zip'
```

---

## 1. Get the code and dependencies

```bash
cd /path/to
git clone <repository> vowspace
cd vowspace

composer install
npm install
```

---

## 2. Create the database

### Option A — system MariaDB/MySQL

```sql
CREATE DATABASE vowspace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vowspace'@'127.0.0.1' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON vowspace.* TO 'vowspace'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### Option B — user-space MariaDB (no sudo)

Useful in sandboxes. Data and socket live under your home directory.

```bash
mkdir -p ~/.local/share/vowspace-mysql /tmp/opencode/vowspace-mysql-run
mariadb-install-db --no-defaults \
  --datadir="$HOME/.local/share/vowspace-mysql" \
  --auth-root-authentication-method=normal

nohup mariadbd --no-defaults \
  --datadir="$HOME/.local/share/vowspace-mysql" \
  --socket=/tmp/opencode/vowspace-mysql-run/mysql.sock \
  --port=3307 --bind-address=127.0.0.1 \
  --pid-file=/tmp/opencode/vowspace-mysql-run/mysql.pid \
  --log-error=/tmp/opencode/vowspace-mysql-run/error.log \
  > /tmp/opencode/vowspace-mysql-run/out.log 2>&1 &

# wait for the socket, then create the schema
mariadb --socket=/tmp/opencode/vowspace-mysql-run/mysql.sock -u root <<'SQL'
CREATE DATABASE vowspace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vowspace'@'127.0.0.1' IDENTIFIED BY 'vowspace_secret';
GRANT ALL PRIVILEGES ON vowspace.* TO 'vowspace'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

Stop it later with `mariadb-admin --socket=... shutdown` or `kill "$(cat .../mysql.pid)"`.

---

## 3. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
APP_NAME=VOWSPACE
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306          # 3307 for the user-space instance
DB_DATABASE=vowspace
DB_USERNAME=vowspace
DB_PASSWORD=change-me

FILESYSTEM_DISK=public
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

`.env.example` ships with `DB_PORT=3307` and an empty password because that matches the user-space
setup. Change it for a standard install.

> `APP_URL` matters: uploaded media URLs are built from it. Set it to the real host in production.

---

## 4. Migrate, seed, link storage, build assets

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

`migrate --seed` runs `DatabaseSeeder`, which runs `DemoSeeder`:

- 4 users — 1 superadmin, 3 owners
- 2 events (`Raka & Alya` live, `Dimas & Nadia` in another state)
- 38 guests with invitation + QR tokens, categories and groups
- RSVPs, check-ins, guestbook messages, media rows, seating tables and assignments
- polls, a quiz with questions/options, attempts and song requests
- app settings and audit log entries

Demo credentials — **all passwords are `password`**:

| Role | Email |
| --- | --- |
| Superadmin | `admin@vowspace.test` |
| Owner (groom, event 1) | `raka@vowspace.test` |
| Owner (bride, event 1) | `alya@vowspace.test` |
| Owner (event 2) | `dimas@vowspace.test` |

---

## 5. Run

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

- `http://127.0.0.1:8000/` — public home
- `http://127.0.0.1:8000/login` — admin/owner sign-in
- `http://127.0.0.1:8000/admin` — superadmin console
- `http://127.0.0.1:8000/owner` — couple space
- `http://127.0.0.1:8000/e/raka-alya` — public event page
- `http://127.0.0.1:8000/wall/raka-alya` — live wall

To get a guest link, open `/admin/invitations` and copy one, or print it:

```bash
php artisan tinker --execute="
  \$g = App\Models\Guest::first();
  echo app(App\Services\InvitationService::class)->rawToken(\$g);
"
```

---

## 6. Asset development

```bash
npm run dev      # Vite dev server with HMR
npm run build    # production build into public/build
```

The design system lives in `resources/css/app.css` (Tailwind v4 `@utility` declarations) and the
client bootstrap in `resources/js/app.js` (Alpine, Chart.js, Flatpickr, SweetAlert2, html5-qrcode).

---

## 7. Verify the installation

```bash
php artisan test            # 54 tests
php bin/blade_lint.php      # compiles + lints all 77 views
./bin/smoke.sh              # real HTTP sweep, needs the server on :8123
```

`bin/smoke.sh` defaults to `http://127.0.0.1:8123`; either serve on that port or pass
`BASE=http://127.0.0.1:8000 ./bin/smoke.sh`.

---

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `could not find driver` | `pdo_mysql` missing — install `php-mysql`. |
| `SQLSTATE[HY000] [2002] Connection refused` | Database not running, or wrong `DB_PORT`. For the user-space instance use `3307`. |
| Images 404 after upload | `php artisan storage:link` not run, or `APP_URL` wrong. |
| QR PNG is blank | `gd` missing — install `php-gd`. |
| `Vite manifest not found` | Run `npm run build` (or `npm run dev`). |
| 419 on every form | Stale session — clear cookies, `php artisan config:clear`. |
| Views show old markup | `php artisan view:clear`. |
| Owner area returns 403 | The owner has no event attached — attach one from `/admin/events/{event}`. |
