# child-app — CLAUDE.md

## What this repo is
Example child application for the Wave Networks ecosystem. Deployed at
public_html/child-app/ as a sibling of public_html/admin/. Demonstrates the
complete pattern for building apps on top of the admin core: own databases,
own shards, branded auth pages, custom theme pipeline, Docker dev environment.

External developers clone this repo, rename it (e.g. /payments/, /helpdesk/),
and use it as their starting point. They never PR back to this repo.

## UI Component Reference
`views/ui-guide.php` is a living style guide showcasing every Bootstrap 5
component rendered with the active theme. When building new views, reference
this file for correct markup patterns: Typography, Buttons, Cards, Forms,
Tables, Modals (AJAX via modalView()), Tabs, Alerts, Badges, Breadcrumbs,
Dropdowns, List Groups, Pagination, Progress, Spinners, Toasts, Accordions,
Offcanvas, Tooltips/Popovers, and Nav variants. Access at ?page=ui-guide.

## Relationship to admin core
Admin core (`../admin/`) provides: auth, sessions, shard routing, email queue,
notifications, theming, branding, and all user management. This child app
reuses all of that while adding its own databases, views, actions, and theme.

Bootstrap chain: child-app/include/common.php → admin/include/common.php
Admin handles its own config, $db, helpers, migrations, and session guard.
Child app then opens its own $child_db and runs its own migrations.

## Folder layout (critical)
The repo root is child-app/ — deployed into public_html/child-app/.
Admin core is a sibling at public_html/admin/ (separate repo).
Both are served from the same webroot (public_html/).

## Path reference (critical — all paths relative to repo root)
child-app/auth/*.php          → include: ../include/common_auth.php
child-app/app/index.php       → include: ../include/common.php
child-app/api/index.php       → include: ../include/common_api.php
child-app/views/*.php         → included BY child-app/app/index.php (never directly)
child-app/include/common.php  → include: ../../admin/include/common.php (core)

## Three database layers
1. Admin main DB ($db) — READ ONLY from child. Auth tables, user table, etc.
2. Child main DB ($child_db) — child app's own tables (items, app_settings)
3. Child shard DBs — per-user tables (user_history, childapp_user_prefs)

Child DB functions: child_db_query(), child_db_query_prepared(),
child_prime_shard(), child_db_query_shard(), child_db_query_shard_prepared()

## Migration rules
Migration dir: db_migrations/main/ and db_migrations/shard/
Decimal versioning: 1.0, 1.1, 2.0, etc.

EVERY migration requires BOTH steps:
1. Create the SQL file in db_migrations/main/ or db_migrations/shard/
2. Update $child_db_version or $child_shard_version in include/migration_versions.php —
   the ONE declaration. common.php, common_api.php and common_auth.php all include it;
   never re-declare them in a bootstrap (per-bootstrap copies drifted, and the lower
   one silently stopped migrating). The loop is core's wn_child_migrate(): it STOPS at
   the first failed file (ledger stays at the last success; apiSchemaAudit shows
   migration_failure) and retries it on the next request.

THIS STEP IS FREQUENTLY FORGOTTEN — DO NOT SKIP IT.

Versions are single-decimal floats: (float)"1.10" == 1.1 and collides with
1.1.sql, so after 1.9 the next version is 2.0.

Never edit an already-applied migration to fix a schema mistake — the runner
records the version and will not re-run it, so the file and the live schema
drift apart with nothing to reconcile them. Roll FORWARD with a new version
(CREATE TABLE IF NOT EXISTS / DROP TABLE IF EXISTS) instead.

## Action files
Same pattern as admin. Auto-included via glob in common.php / common_api.php.

Authenticated: include/actions/memberActions/yourFeatureActions.php
API/public:    include/actions/apiActions/yourFeatureActions.php

Two invocation methods:
1. Plain form POST: <form method="post"> on any view page
2. AJAX: POST to api/index.php via apiPost() from bs-init.js

WRONG:
- Creating new endpoint files in api/
- Pointing <form action=""> at api/index.php

## Template shell (views/template.php)
The main HTML shell includes all structural UI regions:
- Sidebar: collapsible nav with icon+text links, item count badge (.sidebar-badge)
- Topnav: live search (desktop + mobile), notification bell, color mode toggle,
  theme switcher, user dropdown, right panel trigger (vertical dots, rightmost icon)
- Right panel: offcanvas-end with tabbed content (General, Preferences, About)
- Footer: sticky to bottom, Terms/Privacy links, copyright text
- Canvas: #bgCanvas for gamified/animated backgrounds
- Toast: reads #toast-data div, shows Bootstrap toasts via toast.js
- Large modal: #largeModal for AJAX content via modalView()

## JavaScript files loaded by template
Admin shared: bs-init.js (apiPost helper), sidebar.js, color-mode.js, notifications.js
Child-app: modal.js, theme.js, toast.js, search.js, page-nav.js, bg-canvas.js

### apiPost() pattern (from bs-init.js)
apiPost('actionName', {key: 'value'}, function(response) {
    // response = {error, success, info, warning, results: $data}
    // So search results are at response.results.results
});

### modalView() pattern (from modal.js)
modalView('modal=snippet_name&param=value')
reloadModalThisForm('formId', 'modal=snippet_name&action=doThing')

## SCSS theme pipeline
assets/scss/custom.scss → imports _variables.scss → bootstrap → _bootswatch.scss
_bootswatch.scss contains a glass() mixin for glassmorphism effects.
All components have dark mode (base) + light mode ([data-bs-theme="light"]) styles.

Build: npm run build:css | npm run watch | npm run build:themes

Themed components in _bootswatch.scss:
- Cards, tables, forms, modals, offcanvas, sidebar, navbar
- DataTables controls (search input, pagination, length select)
- Dashboard hero, breadcrumbs, D3 chart containers
- Live search bar (.nav-search, .nav-search-mobile)
- Footer (.app-footer)
- Scrollbars, tooltips, dropdowns

## Third-party libraries (CDN-loaded in views)
- D3.js v7 — dashboard.php (line/area chart, donut chart)
- jQuery 3.7.1 — examples.php (DataTables dependency)
- DataTables 2.3.7 + Responsive 3.0.4 (Bootstrap 5) — examples.php

## Active coding rules

DO:
- Escape out of PHP for HTML — never echo HTML from within PHP strings
- Use <?= $var ?> shorthand, always h() for user-supplied data
- Use sanitize($val, SQL) for DB values
- Flat if($_POST['action'] == 'x') blocks in action files
- Collect validation errors in $errs array before setting $_SESSION['error']
- Always set $_SESSION['success'] when an action succeeds
- Use child_db_query() / child_db_query_prepared() for child's main DB
- Use child_db_query_shard() for child's shard DBs
- Use db_query_shard() ONLY for reading admin's shard data (user_profile)
- Asset paths: ../../admin/assets/ for admin shared assets
- __DIR__-based paths for all includes

DO NOT:
- Write to admin's DB tables (user, device, api_key, etc.)
- Create a new $db PDO connection — use the global $db from admin
- Edit vendor/ ever
- Store credentials anywhere except config/config.php (gitignored)
- Create new API endpoint files — use action files instead
- Edit existing migration files — always create a new version
- Skip h() or sanitize() for any output
- Set $_SESSION['error'] on first validation failure (collect all errors first)

## Template pattern
<?php if ($someCondition) { ?>
<div class="something"><?= h($userData['field']) ?></div>
<?php } ?>

## Action file pattern
if ($_POST['action'] == 'doThing') {
    $errs = array();
    if (!$_SESSION['user_id']) { $errs['auth'] = 'Login required.'; }
    if (!$_POST['title'])      { $errs['title'] = 'Title required.'; }
    if (count($errs) <= 0) {
        child_db_query_prepared("INSERT INTO items ...", [...]);
        $_SESSION['success'] = 'Done.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

## Docker
docker compose up — starts 8 services (app, 3 admin DBs, 3 child DBs, mailhog).
Source code is volume-mounted: edit PHP → refresh browser → changes are live.
Admin repo must be checked out as sibling: ../admin/

## Deployment traps that have already cost real time

Every item here caused a real, days-long outage on a provisioned app. They are
invisible to `docs/spec-coverage.md`, because a coverage manifest verifies that
code EXISTS — a static property of the repo. None of them are theoretical.

### opcache: a shipped fix can silently not run
The shared host runs `opcache.validate_timestamps=0`. Bytecode is compiled once and
cached in FPM shared memory **forever**, ignoring mtime. Brand-NEW files compile
fresh, but **MODIFIED files keep serving the old build after rsync** — a correct
fix sits on disk and does nothing.

Symptom: "I deployed the fix and it still does the old thing." Suspect this FIRST.
On one app a `pac_is_treasurer()` load-order fix deployed green while FPM kept
executing the broken bytecode, so the settings screen went on silently discarding
the owner's typed input for a day after it was fixed.

Fix: ship `opcache-bust.php` at the repo root plus "Inject OPcache bust token"
(before rsync) and "Reset OPcache" (after) steps in `deploy.yml`. The token lives
only in the `OPCACHE_BUST_TOKEN` Actions secret and is substituted into the
staging copy, so it never lands in git. **Set the secret BEFORE the first push** —
otherwise the endpoint compiles carrying its placeholder and is then stale and
unreachable with the real token.

### Helper load order: define shared helpers in `include/common/`, never in an action file
`include/common.php` globs `common/*.php` **before** `actions/**`, and action files
run their flat `if ($_POST['action'] …)` blocks at include time. So a helper defined
in one action file **does not exist yet** when an earlier-globbed action file runs.

On one app `pac_is_treasurer()` lived in `treasurerActions.php`, which globs after
`memberConsoleActions.php`. Every staff write guarded by
`function_exists('pac_is_treasurer')` short-circuited to "Staff access required."
and silently persisted nothing — settings, site content and agent drafts, all dead,
for anyone including an admin. It fails closed and looks like a permissions problem.

**Rule: any helper more than one file uses goes in `include/common/`.**

### Cron on the shared host is a SHELL command, and it must run EVERY MINUTE
There is no URL endpoint — `cron/cron.php` is CLI-only (`php_sapi_name()` guard)
and `cron/.htaccess` denies the directory. Hitting the path over HTTP returns 403.

ONE entry covers core and every app on the account, because admin's runner globs
`../../*/cron/cron.php`:

    /usr/bin/php83 <home>/public_html/admin/cron/cron.php

Never add `-l` (lint mode: syntax-checks and exits without running anything).
**Every minute, not every five:** each `minutes/{N}/` directory gates on
`$minute % N === 0`, so a 5-minute cron that is not aligned to :00/:05/:10 silently
never fires ANY `minutes/5` job. The tell is output with `Running minute/1/…` and
no `Running minute/5/…`.

### An app is not installed when its files are on the host
Provisioning stops at "deployed". Anything that must run once on the box —
seeding reference data, the first publish of a read seam — has nothing running it,
so the app lands half-installed and looks fine. `tools/install.php` is that step;
wire it into `deploy.yml` over the SSH connection the deploy already holds. It is
idempotent, so running it on every deploy is correct.

### Verify the running app, not the repo
Run `scripts/acceptance.sh <base-url>` after deploy. It catches scaffold text
reaching production, unsubstituted placeholders, an `og:image` that 404s, and a
root page with no way to sign in. On one app all four were live simultaneously
while the manifest read 239/255 — including `views/dashboard.php` still being the
scaffold placeholder, because no coverage row named a general dashboard and so
nothing measured the first screen every user saw.
