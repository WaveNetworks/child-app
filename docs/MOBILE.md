# Mobile build (child-app spec 05)

This app can ship as an iOS/Android app built from the SAME `views/*.php` — no second
implementation. The engine lives in **wave-networks-core**; this repo owns only the config.

## What this repo provides (the scaffold)
- `m/js/env.js` — your app's domain/name/slug. **The only file that names your app.** Edit it.
- `include/mobile/view_map.php` — which screens ship on mobile (`mobile`), tab/menu labels, icons.
- `scripts/release-mobile.sh` — thin wrapper: sets config, calls core's pipeline.
- `app/index.php` — calls `wn_mobile_fragment_emit()` under `?mobile=1` (already wired).
- `views/template.php` — has `#content-dynamic` (the mobile shell renders screens there).
- `.gitignore` — the vendored engine (everything in `m/` except `m/js/env.js`) is build output.

## What core provides (consumed, never forked)
Bearer device-token auth + CORS, `wn_split_view`, `build_mobile.php`, `build_shell.php`,
`release-mobile.sh`, and the runtime engine (`assets/mobile/js/*` vendored into your `m/`).

## Prerequisites this app must have
- `assets/scss/custom.scss` theme (compiled per build). ✓
- A brand tile SVG → set `BRAND_TILE` in the wrapper (copied to `m/assets/img/app-tile.svg`).
- `bootstrap-icons` in devDependencies (the topnav/tab icons). Add it: `npm i -D bootstrap-icons`.
- Self-hosted brand fonts in `assets/fonts/*.woff2` + `app-fonts.css` (CSP blocks Google Fonts
  on device). Optional but recommended.

## Build
```bash
npm i                       # sass, bootstrap, bootstrap-icons
scripts/release-mobile.sh 0.1.0            # build the bundle into m/ (+ sync <app>-cordova)
scripts/release-mobile.sh 0.1.0 --push     # …and push the shell repo for the build service
```

## The shell repo (`<app>-cordova`)
A thin Cordova shell whose `www/` is GENERATED (rsynced from `m/`). Hand-authored: `config.xml`
(app id, pinned plugins, `ios-CFBundleVersion` — bump every upload), `resources/` icon+splash
(from your tile via `cordova-res`). A cloud build service (Appflow) watches it.
