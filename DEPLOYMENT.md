# Family Roots - production deployment guide

This is a **PHP + MySQL** application with no Composer dependencies and no
build step. Deployment is: copy the files, create the database, edit `.env`,
point the web server at `public/`, and configure HTTPS.

> This document describes deploying the **code that exists today**. It does not
> add features. Items the application does *not* implement are listed in
> [Not implemented](#not-implemented-do-not-assume) so nobody plans around
> them.

## What you need on the host

| Requirement | Notes |
| --- | --- |
| PHP 7.3+ (7.4/8.x recommended) | extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `json` |
| MySQL **8.0.16+** | migrations use JSON columns and enforced `CHECK` constraints. 5.7 will not work. |
| Apache with `mod_rewrite` | nginx works too, but this guide ships an Apache vhost. |
| HTTPS certificate | session cookies and HSTS assume HTTPS. |
| Write access to `storage/` | logs, sessions, cache. |
| `mysqldump` | the app has no export/backup feature; backups are external. |

Shared hosting is possible if it meets the MySQL version, allows a document root
outside the project root (or at least `public/`), and lets you run the installer
from SSH/cron. If it forces the document root to the project root, **do not
deploy**: `app/`, `config/`, `storage/` and `.env` would become web-reachable.

## 1. Copy the files

Put the whole project **outside** the web root, with `public/` as the only
served directory:

```
/var/www/family-roots/
├── app/  config/  database/  routes/  tests/  storage/
├── .env            <- created by the installer, never in public/
└── public/         <- DocumentRoot
```

Do not copy local artefacts:

- `.env` from your development machine (create a fresh one on the host)
- `storage/logs/*`, `storage/sessions/*`
- `.git/` if you clone on the server

`public/` must contain only `index.php`, `.htaccess` and `assets/`.

## 2. Create the database and `.env`

Run the installer **on the host, from the project root**, with a MySQL account
that may `CREATE DATABASE` / `CREATE USER` (usually `root`):

```bash
php database/install.php \
    --db-host=127.0.0.1 \
    --db-user=root \
    --db-pass='ROOT_PASSWORD' \
    --db-name=family_roots \
    --app-user=family_roots_app \
    --no-seed
```

- `--no-seed` is **required in production**. Without it, demo families and the
  documented demo accounts (password `FamilyRoots!Demo2026`) are created.
- The installer creates `family_roots_test` unless you pass `--no-test-db`.
  Skip it in production; it exists for the test suite.
- The app account is least-privileged: data rights on the app schema, DDL only
  on the test schema. Never give the web app `root`.
- `--fresh` **drops** the schema, the app account and `.env`. Never use it on a
  live install.
- Re-running without `--fresh` is safe: applied migrations are skipped and an
  existing `DB_PASS` is reused.

Then edit `.env`. The installer copies `.env.example`, which is a **development**
template - the values below must be changed:

| Key | Production value | Why |
| --- | --- | --- |
| `APP_ENV` | `production` | The login page shows the demo-account hint only when this is `local`. |
| `APP_DEBUG` | `false` | Must be false; otherwise error pages and PHP errors reveal internals. |
| `APP_PRETTY_URLS` | `1` | Requires the rewrite rules from step 3. Keep `0` if rewrite is unavailable. |
| `DB_PASS` | generated | Already written by the installer. Keep `.env` at mode `600`. |
| `SESSION_SECURE` | `1` | Session cookie must not travel over plain HTTP. |
| `MAIL_TRANSPORT` | `mail` | See [Email](#6-email-verification). `log` means no real email is sent. |
| `MAIL_FROM` | your address | Must be a mailbox your MTA is allowed to send from. |
| `AUDIT_ENABLED` | `true` | Membership/verification/account events go to `audit_logs`. |

Use `deploy/.env.production.example` as a starting point if you prefer to write
the file by hand instead of editing the generated one.

Notes on keys that do **not** do what you might expect:

- `APP_URL` is read into config but **not currently used** by any code path;
  URLs are derived from the incoming request. Setting it is harmless.
- `APP_KEY` is likewise **reserved, not used** by the current code (nothing is
  encrypted with it yet). Setting it is harmless and future-proof.
- Real OS environment variables take precedence over `.env`
  (`Env::load()` never overwrites an existing variable), so hosting panels that
  inject `DB_*`/`APP_*` work correctly.

## 3. Web server

Copy `deploy/apache-family-roots.conf` into your vhost directory, replace the
domain and paths, enable the site, then reload Apache. It provides:

- HTTP → HTTPS redirect
- `DocumentRoot .../public`
- the front-controller rewrite (so `.htaccess` is not needed; this install's
  local Apache has `AllowOverride None`)
- dotfile denial and no directory listing
- `display_errors=off` for PHP

Enable `mod_rewrite`, `mod_ssl` and `mod_headers`:

```bash
a2enmod rewrite ssl headers
a2ensite family-roots
apachectl configtest && systemctl reload apache2
```

`.htaccess` (shipped in `public/`) contains equivalent rewrite rules. If you
use it instead of vhost rules, the directory needs `AllowOverride All` — and
you must keep the `<FilesMatch "^\.">` denial, which `.htaccess` already has.

### Behind a TLS-terminating proxy

`Request::isSecure()` only checks `$_SERVER['HTTPS']`. It does **not** read
`X-Forwarded-Proto`. If HTTPS ends at a load balancer, the app will think the
request is plain HTTP, so `SESSION_SECURE` cookies and the automatic HSTS header
will not happen. Fix it at the web server:

```apache
SetEnvIf X-Forwarded-Proto https HTTPS=on
```

Only add this when a trusted proxy actually sets that header.

## 4. PHP settings (host)

```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
expose_php = Off
memory_limit = 128M
max_execution_time = 30

; opcache: enable, but do not re-check files on every request
opcache.enable = 1
opcache.validate_timestamps = 0
opcache.memory_consumption = 64
opcache.max_accelerated_files = 4000

; sessions: the app points save_path at storage/sessions itself
session.use_strict_mode = 1
session.use_only_cookies = 1
```

`opcache.validate_timestamps=0` means **you must reset opcache after every
deploy** (reload PHP-FPM, or `opcache_reset()`), otherwise old code keeps
running. That is the trade-off for maximum speed.

## 5. MySQL settings (host)

Do **not** copy the local development `my.ini`. This project's dev box uses
development-only trade-offs that are unsafe on a server:

| Dev setting | Production |
| --- | --- |
| `performance_schema=0` | `ON` (leave the default) |
| `log_bin` off | `ON` if you want point-in-time recovery |
| `innodb_flush_log_at_trx_commit=2` | `1` |
| `sync_binlog=1000` | `1` |
| `tmp_table_size=32M` | size to real temp-table usage |
| `max_connections=30` | size to your traffic |

Keep `innodb_buffer_pool_size` at roughly 50-70% of RAM on a dedicated
database host.

## 6. Email verification

Sign-up requires confirming an email address. `App\Services\Mailer` supports
exactly two transports:

- `log` - writes full messages to `storage/logs/mail-*.log` (development)
- `mail` - calls PHP's `mail()`

There is **no SMTP client** in the code. On a host that blocks `mail()`, set up
a local relay (`msmtp`, `postfix`, `sendmail`) so `mail()` works. A failed
`mail()` call is logged and the message still lands in the log file, so
verification does not silently vanish - but the user does not receive it.

Alternative (no MTA): keep `MAIL_TRANSPORT=log`, and have an administrator open
`/admin/accounts` and use **Resend link** for each unverified account. This is
manual, but it works.

## 7. Backups

The application has **no export, dump or restore feature**. Plan backups
externally:

- `deploy/backup-db.sh` runs `mysqldump --single-transaction` and keeps N days.
  Make it executable (`chmod +x deploy/backup-db.sh`) and schedule it with cron,
  e.g. nightly:
  `15 2 * * * APP_DIR=/var/www/family-roots /var/www/family-roots/deploy/backup-db.sh`
- Back up `.env` and `storage/` (logs contain verification links while
  `MAIL_TRANSPORT=log`) with the same schedule.
- Store copies **off the host**. A dump on the same disk is not a backup.
- Test a restore into a scratch database at least once.

## 8. After deploying - verification checklist

```bash
# 1. Health (should be 200 with database "ok")
curl -sS https://your-domain/health

# 2. Front page and login render
curl -sSI https://your-domain/ | head -n 1
curl -sSI https://your-domain/login | head -n 1

# 3. HTTPS redirect from HTTP
curl -sSI http://your-domain/ | grep -i '^location'

# 4. Security headers present (CSP, X-Frame-Options, ...)
curl -sSI https://your-domain/login | grep -iE 'content-security-policy|x-frame-options'
```

Then, in a browser:

- [ ] Register a new account and receive the confirmation email (or use the
      admin resend path if you are running without an MTA).
- [ ] Sign in, sign out.
- [ ] The login page does **not** show the demo-account hint (proves
      `APP_ENV=production`).
- [ ] `https://your-domain/.env` and `https://your-domain/storage/` return
      403/404.
- [ ] An invalid route shows a clean error page with no stack trace.
- [ ] `/health` shows the expected `migrations_applied` count.

## 9. Security checklist

- `.env` outside the web root, mode `600`, never committed.
- Document root is `public/`; `app/`, `config/`, `storage/`, `database/` and
  `tests/` are not reachable.
- `APP_DEBUG=false`.
- HTTPS only; `SESSION_SECURE=1`; HSTS appears once HTTPS is detected.
- Web app uses the least-privileged DB account, not `root`.
- MySQL not exposed publicly (`bind-address=127.0.0.1`).
- `storage/logs` treated as sensitive when `MAIL_TRANSPORT=log`.
- Remove `tests/` and `database/` from the host if you do not need to run
  migrations there (optional; they are outside the web root either way).
- Rotate the demo password/accounts: with `--no-seed` they never exist.

## Not implemented (do not assume)

These are referenced in configuration or history but have **no code path**, so
do not advertise or plan around them:

- **Data export / import** - no feature; use `mysqldump`.
- **SMTP** - only `mail()` and log transports exist.
- **Evidence file uploads** - `uploads.*` config exists but nothing reads it.
- **Living-person masking** - `privacy.living_person_masking` exists but is
  never consulted.
- **Corrections workflow UI** - not present.
- **Scheduled/automatic backups** - not part of the application;
  `deploy/backup-db.sh` must be scheduled by you (cron/Task Scheduler).
