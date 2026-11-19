# Family Roots — real hosting + mobile store packages

This covers two **separate** jobs. Do them in this order:

1. **Phase A** — put the PHP/MySQL web app on a real host (required first).
2. **Phase B** — wrap that hosted site as mobile apps for Google Play and the
   Apple App Store.
3. **Phase C** — submit the packages to the stores.

## Read this before you spend money

- The app is a **server-rendered PHP/MySQL website**. Nothing runs on the phone
  except a web view. The mobile apps load your hosted HTTPS site.
- **Google Play**: doable here, but you need a Google developer account
  (about **$25 one-time**) and an Android signing key.
- **Apple App Store**: an `.ipa` **cannot be built on this Windows machine** —
  it needs **macOS + Xcode** and a paid Apple developer account (about
  **$99/year**). I can prepare everything; you (or a Mac/cloud-Mac) do the final
  Xcode build.
- A "thin wrapper around a website" can be **rejected by Apple** under App Store
  Review Guideline 4.2 (minimum functionality). Adding native features later
  (push notifications, offline caching, biometric unlock) reduces that risk.
  Google Play is more permissive but still requires a real, useful app.
- The app's strict CSP (`script-src 'self'`, `style-src 'self'`) is fine: the web
  view loads your own domain, so `self` is your domain. No CSP changes needed.

## Phase A — the real host (order this)

Suggested minimum for a small family app with MySQL 8:

| Item | Recommendation |
| --- | --- |
| OS | **Ubuntu 24.04 LTS** (ships MySQL 8.0 and PHP 8.3) |
| CPU / RAM | **2 vCPU / 4 GB RAM** (2 GB is the bare minimum; 4 GB avoids the swapping we saw locally) |
| Disk | 40 GB SSD |
| Access | SSH as root (needed to run the installer and configure Apache) |
| Extras | A domain you control + DNS access, for HTTPS |

Providers to compare (check current prices yourself): **Hetzner CX22**,
**DigitalOcean 2 GB droplet**, **Linode Nanode 2 GB**, **Vultr**. A managed
MySQL service is easier but costs more; this app only needs one small database.

Then follow `DEPLOYMENT.md` exactly:

1. Copy the project under `/var/www/family-roots/` with only `public/` served.
2. Run `php database/install.php --db-user=root --db-pass='...' --no-seed
   --no-test-db` on the host.
3. Set `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_PRETTY_URLS=1`,
   `SESSION_SECURE=1`, `MAIL_TRANSPORT=mail`.
4. Install Apache + PHP extensions, enable the shipped vhost, get HTTPS with
   Let's Encrypt, and reload PHP-FPM so opcache picks up the code.
5. Schedule `deploy/backup-db.sh` in cron and store dumps **off the host**.
6. Work through the verification checklist at the end of `DEPLOYMENT.md`.

**Do not copy the local `my.ini` tuning to the server** — those are dev-only
trade-offs (binlog off, `performance_schema` off, relaxed fsync).

## Phase B — build the store wrappers

See `mobile/README.md` for the exact commands. Short version:

```bash
cd mobile
npm install
# edit capacitor.config.json: replace REPLACE-WITH-YOUR-DOMAIN with your real HTTPS domain
npx cap add android
npx cap sync
```

- **Android**: install a JDK (17+) and the Android SDK (Android Studio), set
  `ANDROID_HOME`, then `npx cap open android` and build a **signed AAB** in
  Android Studio.
- **iOS**: on a Mac, `npx cap add ios && npx cap sync && npx cap open ios`, then
  Xcode → *Archive* → *Distribute to App Store* to produce the `.ipa`.

## Phase C — submit to the stores

Use the templates in `mobile/store/`:

- `LISTINGS.md` — text and assets each store asks for.
- `PRIVACY-POLICY.md` — **template only**; fill in your real details. You must
  host it at a public URL before submitting.
- `ASSETS.md` — icon, screenshot and feature-graphic sizes.

Fees and review are external to this repository. Budget time for store review
and for at least one rejection/resubmission cycle with Apple.
