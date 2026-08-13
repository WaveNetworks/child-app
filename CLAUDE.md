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
2. Update $child_db_version or $child_shard_version in BOTH
   include/common.php AND include/common_api.php — they each carry their own
   copy, and when they drift the lower one silently stops migrating (web
   requests migrate, API requests don't, or vice versa).

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
