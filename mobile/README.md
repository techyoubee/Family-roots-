# Mobile wrappers (Capacitor)

These are thin native shells that open the **hosted** Family Roots site. The
PHP/MySQL app stays on your Linux VPS; no PHP runs on the phone.

> Build the web host first — see `../MOBILE.md` Phase A and `../DEPLOYMENT.md`.
> The apps below will not work until your site is live over HTTPS.

## Configure once

1. `cd mobile`
2. `npm install`
3. Edit `capacitor.config.json`:
   - replace **both** `REPLACE-WITH-YOUR-DOMAIN` values with your real domain
     (e.g. `family.example.com`), with no `https://` in `allowNavigation`
   - change `appId` if `com.familyroots.app` is not yours

## Android (can be prepared on this Windows box)

Needs a **JDK 17+** and the **Android SDK**. The easiest route is Android
Studio, which installs both; then set `ANDROID_HOME` to the SDK path.

```bash
cd mobile
npx cap add android      # once
npx cap sync             # after any config change
npx cap open android     # opens Android Studio
```

In Android Studio: **Build → Generate Signed Bundle / APK → Android App
Bundle**. Create a signing keystore and **back it up** — losing it means you
can never update the app on Google Play. The keystore is git-ignored.

## iOS (requires macOS + Xcode)

Cannot be produced on Windows. On a Mac:

```bash
cd mobile
npm install
npx cap add ios
npx cap sync
npx cap open ios
```

In Xcode: select a development team, then **Product → Archive → Distribute
App → App Store Connect → Upload**. Apple requires a paid developer account.

## Notes

- If the CLI hangs on first run, disable telemetry:
  `set CAPACITOR_CLI_DISABLE_TELEMETRY=true` (Windows) or
  `export CAPACITOR_CLI_DISABLE_TELEMETRY=true` (Linux/macOS).
- `mobile/.gitignore` excludes `node_modules/`, generated `android/`/`ios/`
  projects, and all signing material. Never commit signing keys.
- If users tap external links, they currently open inside the web view. To
  open them in the phone's browser instead, add `@capacitor/browser` and a
  click handler later — that would also need a small CSP-compatible JS asset.
- After changing `capacitor.config.json`, always run `npx cap sync` again.
