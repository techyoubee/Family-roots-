# Family Roots

A PHP + MySQL web application for documenting family ancestry, registering a
family, and applying to join a family you already belong to. Every person,
parent link and membership decision is recorded explicitly: the app never
invents ancestors and never confirms a relationship on its own.

No Composer dependencies — plain PHP 7.3+, MySQL 8 and any Apache/PHP setup.

## Requirements

- PHP 7.3 or newer with `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`
- MySQL 8.0.16+ (the migrations use JSON columns and enforced `CHECK`
  constraints)
- Apache with `mod_php` (this repository is configured for AppServ 9.x on
  Windows) or the PHP built-in server
- Writable `storage/` (logs, cache, sessions)

## Quick start

From the project folder:

```powershell
# 1. Create the schema, the least-privilege app account, .env, and demo data.
php database/install.php --db-pass=YOUR_MYSQL_ROOT_PASSWORD

# 2. Open the site (AppServ/Apache example).
#    APP_PRETTY_URLS=false is required on installs without mod_rewrite.
http://127.0.0.1/family-root/public/index.php?r=/
```

The installer is idempotent. Re-running it:

- skips migrations already recorded in `schema_migrations`
- reuses `DB_PASS` from an existing `.env` (the live site keeps working)
- skips demo seeding when the demo family already exists
- regenerates nothing unless you pass `--fresh` (which drops the schema,
  the app account and `.env` — irreversible)

Useful flags: `--no-seed`, `--seed-only`, `--no-test-db`, `--app-pass=...`.

## Deployment

`DEPLOYMENT.md` covers putting this on a real host: copying files with `public/`
as the document root, creating the database (`--no-seed`), production `.env`
values, an Apache vhost (`deploy/apache-family-roots.conf`), PHP/MySQL host
settings, backups (`deploy/backup-db.sh`) and a post-deploy checklist.

The development tuning described in [Troubleshooting](#troubleshooting) is
**not** server-ready (binlog off, `performance_schema` off, relaxed fsync); the
deployment guide lists what to change back.

## Demo accounts

The seed creates these addresses with the password `FamilyRoots!Demo2026`:

| Account | Role |
| --- | --- |
| `platform.admin@family-roots.local` | platform administrator (read-only oversight area) |
| `founder@bh-kiru.example` | founder/admin of BH KIRU |
| `moderator@bh-kiru.example` | BH KIRU member |
| `founder@amani-family.example` | founder/admin of AMANI Family |
| `rina.aman@example.test`, `noor.hakim@example.test` | ordinary members |

Ancestor records in the seed are fictional samples used to exercise the tree,
the matching engine and the review queue.

## Features

- **Accounts** — registration, login, e-mail confirmation, password reset,
  profile/password settings, account deletion request, notifications.
- **Families** — register a family, edit its profile, membership roles
  (founder/admin/member/viewer), family tree view.
- **Ancestry records** — add ancestors and parent/spouse relationships. A
  person's `record_status` is `confirmed`, `proposed`, `unknown` or
  `disputed`; each parent link is `confirmed`, `proposed` or `disputed`.
- **Membership requests** — claims compared against the recorded ancestry
  (names, dates, places, evidence notes), an automated strong-match score, and
  a human decision (approve/decline/request more information/appeal) on the
  family's own review screen.
- **Ancestral Journey** — a read-only, cinematic walk through the recorded
  ancestry (intro → animation → interactive tree → timeline). Progress is
  stored per user and family in `ancestral_journeys`; nothing is invented.
- **Platform administration** — read-only oversight for
  `is_platform_admin = 1`: all accounts, all membership requests, all
  families. Membership decisions stay on each family's review screen.
  Regenerating a confirmation link for an unverified account is the only
  write action, and it is audited.

## Working without outbound e-mail

Set `MAIL_TRANSPORT=log` (the default for local development). Messages are
written to `storage/logs/mail-YYYY-MM-DD.log` instead of being sent.

Because of that, the verification flow is usable without a mail server:

- `/verify-email` shows the current confirmation link in log mode.
- `/admin/accounts` has a **Resend link** button for accounts awaiting
  confirmation; the generated URL is displayed to the administrator.

Switch to `MAIL_TRANSPORT=mail` on a host with a configured MTA.

## Database and migrations

SQL files live in `database/migrations/` and are applied in filename order.
Applied versions are recorded in `schema_migrations`.

The application account (`family_roots_app` by default) is deliberately
least-privileged: it has data rights on `family_roots` and DDL rights only on
the throwaway `family_roots_test` schema. Migrations therefore run through the
installer with an administrator account, never from the web app:

```powershell
php database/install.php --db-pass=YOUR_MYSQL_ROOT_PASSWORD
```

If a migration is not applied yet, the app keeps working and degrades
gracefully (for example, Journey progress is simply not stored).
`/health` reports the number of applied migrations.

## Tests

The suite builds its own schema (`family_roots_test`), so it never touches the
live data:

```powershell
php tests/run.php --db-user=family_roots_app --db-pass=APP_ACCOUNT_PASSWORD
```

Options: `--filter=Name` (run one case), `--keep` (skip the schema rebuild),
`--db-host=`, `--db-user=`, `--db-pass=` or `FAMILY_ROOTS_INSTALL_PASS`.

Cases: `AuthTest`, `CoreTest`, `CspTest`, `FamilyTest`, `MembershipTest`,
`SchemaTest`, `SeedDataTest`. `CspTest` fails if a view reintroduces inline
styles, inline event handlers or inline script blocks that the strict
Content-Security-Policy would silently block. The runner exits non-zero on
failure.

## Security notes

- All SQL goes through prepared statements; identifiers are never built from
  user input.
- CSRF tokens on every state-changing form, session rotation on login,
  login rate limiting, and per-action limits for registration, verification
  resends, password resets and family creation.
- Strict Content-Security-Policy with `style-src 'self'` and
  `script-src 'self'`: no inline `style=""` attributes, no inline `<script>`
  blocks and no inline event handlers in views.
- Private families are invisible to outsiders (404, no leaking records), and the
  public directory shows aggregate counts rather than ancestor records.
- Application logs never contain passwords or database credentials. With
  `MAIL_TRANSPORT=log`, outgoing messages (including confirmation links) are
  written to `storage/logs/mail-*.log` on purpose, so treat that directory as
  sensitive.
- `.env` is git-ignored and holds the only copy of the app DB password.

## Project layout

```
app/            Controllers, Core (router, DB, auth, session, CSRF, view),
                Http/Middleware, Models, Services, Views
config/         app, db, mail and security configuration
database/       installer + numbered SQL migrations
deploy/         production vhost, env template and backup script
public/         front controller (index.php) and assets
routes/         web.php route table
storage/        logs, cache, sessions, private files (git-ignored)
tests/          dependency-free test runner and cases
```

## Troubleshooting

- **Clean URLs 404** — this install has `AllowOverride None`, so keep
  `APP_PRETTY_URLS=false`; every link then uses `index.php?r=/path`.
- **"Please confirm your email address before continuing"** — the account can
  browse, but actions such as creating a family or applying to one need a
  confirmed address. Open `/verify-email` (log mode shows the link) or have a
  platform administrator generate one from `/admin/accounts`.
- **A migration will not apply from the web app** — expected: the app account
  has no `CREATE` on the live schema. Run `database/install.php` as an
  administrator instead.
- **Pages time out with `Maximum execution time of 30 seconds exceeded`** — on
  a small development machine this is usually MySQL I/O starvation, not a slow
  query: the device itself is pausing between reads. Check
  `storage/logs/app-YYYY-MM-DD.log` for `Slow database query` or
  `Slow database connection` lines, which record the duration, the statement
  and the route (never the values). Compare with
  `C:\AppServ\MySQL\data\mysql-slow.log`. The usual culprits are a tiny
  `innodb_buffer_pool_size`, `innodb_flush_method=unbuffered` and an fsync per
  commit; the development defaults in this README assume a single 4 GB machine.
  Close large browsers/Electron apps and add `C:\AppServ` to the antivirus
  exclusions so the database files are not rescanned on every write. If the
  first request after an idle period is still slow, check that opcache is
  loaded (`php -v` should mention `Zend OPcache`); without it every request
  recompiles every PHP file. Then check what MySQL is reserving:
  `SHOW ENGINE PERFORMANCE_SCHEMA STATUS` on MySQL 8 reported 379 MB here,
  which is what Windows was paging out on a 3.8 GB box. The development
  `my.ini` therefore sets `performance_schema=0`, `max_connections=30`,
  `tmp_table_size=32M`, `max_heap_table_size=32M`,
  `myisam_sort_buffer_size=32M`, `table_open_cache=400`,
  `table_definition_cache=400` and `skip-log-bin`, which cut the server's
  private memory from 448 MB to 137 MB. Every one of those needs a restart and
  every one is a development-only trade-off: no performance telemetry, no
  binary-log point-in-time recovery, and fewer simultaneous connections.
  Revert from `my.ini.bak-2026-11-17` for anything that is not a local dev box.
  If the first request after an idle period is still slow because Windows has
  trimmed the Apache/PHP/MySQL working sets, you can optionally keep them warm:
  right-click `scripts\register-keep-warm.bat` and run as Administrator to
  request `/health` every 3 minutes (dev-only workaround; one line per run goes
  to `storage/logs/keep-warm.log`). Remove it with
  `scripts\unregister-keep-warm.bat`. This does not change the application and
  is not needed on a normal production host.
