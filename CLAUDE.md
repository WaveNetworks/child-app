# child-app — CLAUDE.md

## CRITICAL: This repo is a template — always clone it first
**Never create a child app directory from scratch.** To build a new child app:
```
git clone https://github.com/WaveNetworks/child-app.git your-app-name/
```
Then modify the cloned files — don't recreate them. The template provides
auth pages, include/common.php, template.php, JS assets, SCSS pipeline,
Docker config, installer, and the full directory structure. Building from
scratch will miss critical boilerplate and produce a broken app.

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
2. Update $child_db_version or $child_shard_version in include/common.php

THIS STEP IS FREQUENTLY FORGOTTEN — DO NOT SKIP IT.

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
- User dropdown: email, Profile link, Privacy & Data link, Logout
- Footer: sticky to bottom, Terms/Privacy links, admin badge (admin-only, links to admin panel), copyright text
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

## Modal system
`app/modal_controller.php` is an AJAX endpoint that routes `modal` param to snippet files.
It maps modal names to snippet paths in a `$modals` array:
- `item_detail` → `snippets/modal_item_detail.php`
- `confirm_delete` → `snippets/modal_confirm_delete.php`
- `example_form` → `snippets/modal_example_form.php`

The controller includes common.php first, so action files run before the snippet renders.
Returns 404 with error HTML if modal name is unknown.

### Opening a modal (JS)
`modalView('modal=snippet_name&param=value')` — POSTs params to modal_controller.php,
loads response HTML into `#modal-content` inside `#largeModal`. Shows spinner while loading.

### Form submission inside modals
`reloadModalThisForm('formId', 'modal=snippet_name&action=doThing')` — serializes the
form, appends extra params, POSTs to modal_controller.php. The action file runs (via
common.php include chain), sets `$errs`, then the snippet re-renders with validation
errors inline. Modal stays open. On success, the snippet can show a success message.

### Adding a new modal
1. Create `snippets/modal_your_name.php` — output modal-header, modal-body, modal-footer
2. Add entry to `$modals` array in `app/modal_controller.php`
3. Open it: `modalView('modal=your_name&any_param=value')`

### Validation persistence
Snippets read `$errs` array (set by action files) and `$_POST` values to re-populate
form fields and show `is-invalid` / `.invalid-feedback` on failed inputs. See
`modal_example_form.php` for the full pattern.

## Live search
`assets/js/search.js` binds to `#navSearchInput` (desktop) and `#navSearchInputMobile`.

- Debounce: 300ms after last keystroke
- Minimum query: 2 characters
- Calls `apiPost('liveSearch', {q: query})` → `apiActions/searchActions.php`
- Server searches `items` table: `title LIKE` and `description LIKE`, user-scoped, limit 8, ordered by `updated DESC`
- Response rendered as dropdown items with title, updated date, and status badge
- Dropdown closes on outside click or Escape, reopens on focus if text present

## Toast notifications
`assets/js/toast.js` provides `showToast(type, message, opts)`.

Types: `success`, `danger`, `warning`, `info`.
Options: `{ delay: ms, title: string }`. Default auto-hide: 5000ms.
Creates Bootstrap 5 toast in a fixed top-right container (`#toastContainer`).
Toasts self-remove from DOM after hidden.

### Session flash integration
Template outputs `<div id="toast-data" data-success="..." data-error="..." data-warning="..." data-info="...">`.
On DOMContentLoaded, toast.js reads each attribute and fires `showToast()` for non-empty values.
So `$_SESSION['success'] = 'Done.'` in PHP → appears as a success toast on next page load.

## Page-level navigation
`assets/js/page-nav.js` manages a secondary nav bar (`#pageNav` inside `#pageNavWrapper`).

- Desktop (>=992px): visible by default, toggle collapses inline. State saved in
  `localStorage` key `wn_page_nav_state` (`collapsed` | `expanded`).
- Mobile (<992px): hidden by default, toggle opens as overlay drawer with backdrop.
  Clicking backdrop or a nav link closes it.
- Resets state on breakpoint crossing (resize debounced 150ms).
- Elements: `#pageNavToggle`, `#pageNavWrapper`, `#pageNav`, `#pageNavBackdrop`.

## Canvas background
`assets/js/bg-canvas.js` renders a full-viewport background on `#bgCanvas`:
gradient + 80px grid + directional arrows at intersections + corner labels.
Dark mode: deep nebula gradients (indigo/violet/cyan/rose).
Light mode: grey-white with faint color washes.
Watches `data-bs-theme` attribute via MutationObserver and redraws on change.
Disable by removing `<canvas id="bgCanvas">` from template.php.
Replace `draw()` with custom rendering for gamified/animated backgrounds.

## Theme registration
`include/common/themeRegistration.php` runs on every request (idempotent).
If `has_custom_theme()` returns true (i.e. `assets/css/custom.css` exists), it calls
`register_theme('glassmorphism', 'Glassmorphism', 'child-app/assets/css/custom.css', [...])`.
This writes to admin's theme registry via `ON DUPLICATE KEY UPDATE`.

`get_app_theme_css_url()` in `appFunctions.php` resolves the active theme CSS:
- If `custom.css` exists and cookie `wn_theme` is `custom` or empty → returns local custom.css
- Otherwise falls back to admin's Bootswatch system via `get_theme_css_url()`

`has_custom_theme()` checks `file_exists(assets/css/custom.css)`.

## Environment variables (Docker)
When `config/config.php` is absent, `include/common.php` reads env vars:

| Env var | Default | Purpose |
|---------|---------|---------|
| `CHILD_DB_HOST` | `db_child` | Child main DB host |
| `CHILD_DB_NAME` | `childapp_main` | Child main DB name |
| `CHILD_DB_USER` | falls back to `DB_USER` then `root` | DB username |
| `CHILD_DB_PASS` | falls back to `DB_PASSWORD` then empty | DB password |
| `CHILD_DB_HOST_SHARD` | — | Child shard 1 host (enables shard1) |
| `CHILD_DB_NAME_SHARD` | `childapp_shard_1` | Child shard 1 DB name |
| `CHILD_DB_HOST_SHARD2` | — | Child shard 2 host (enables shard2) |
| `CHILD_DB_NAME_SHARD2` | `childapp_shard_2` | Child shard 2 DB name |

Shard user/pass reuse `CHILD_DB_USER`/`CHILD_DB_PASS`. Shards only created when their
host env var is set.

## Database schema

### Main DB (`$child_db`)

**db_version** — migration version tracking
| Column | Type | Notes |
|--------|------|-------|
| version_id | INT PK AUTO_INCREMENT | |
| version | DECIMAL(10,1) | Current schema version |

**app_settings** — app-wide key-value config
| Column | Type | Notes |
|--------|------|-------|
| setting_key | VARCHAR(100) PK | |
| setting_value | TEXT NULL | |
| updated | DATETIME | Auto-updates on change |

**items** — user-owned resources (example feature)
| Column | Type | Notes |
|--------|------|-------|
| item_id | INT UNSIGNED PK AUTO_INCREMENT | |
| user_id | INT UNSIGNED | Owner |
| title | VARCHAR(255) | |
| description | TEXT NULL | |
| status | ENUM('draft','active','archived') | Default 'draft' |
| created | DATETIME | |
| updated | DATETIME | Auto-updates |
Indexes: `idx_user_status` (user_id, status), `idx_updated` (updated)

### Shard DBs (per-user)

**db_version** — same as main

**user_history** — per-user activity log
| Column | Type | Notes |
|--------|------|-------|
| history_id | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| user_id | INT UNSIGNED | |
| event_type | VARCHAR(50) | e.g. 'item_created' |
| event_data | JSON NULL | Arbitrary context |
| ip_address | VARCHAR(45) NULL | |
| user_agent | VARCHAR(512) NULL | |
| created | DATETIME | |
Indexes: `idx_user_created` (user_id, created), `idx_event_type` (event_type)

**childapp_user_prefs** — per-user key-value preferences
| Column | Type | Notes |
|--------|------|-------|
| user_id | INT UNSIGNED | Composite PK |
| pref_key | VARCHAR(100) | Composite PK |
| pref_value | TEXT NULL | |
| updated | DATETIME | Auto-updates |

## History/activity logging
`include/common/historyFunctions.php` writes to `user_history` on child shards.

`log_user_history($user_id, $shard_id, $event_type, $event_data = [])` — inserts a row
with event type, JSON event data, IP, user agent, and timestamp. Called by item actions.

`get_user_history($user_id, $shard_id, $limit = 20)` — returns recent history rows
ordered by `created DESC`.

Tracked events (by existing action files): `item_created`, `item_updated`, `item_deleted`.

## GDPR Privacy & Data page
`views/privacy.php` — full GDPR management UI with 5 sections:
- Consent Preferences: ToS/Privacy Policy (read-only badges), Marketing Emails,
  Analytics Cookies, Marketing Cookies (toggle switches)
- Export Your Data: JSON/CSV format selector, download link when ready, "What's included" list
- Login History: table with date, browser, IP, method (with icons), success/failed badges
- Active Sessions: device list with browser icon, IP, last active, revoke buttons,
  "Sign out all others" bulk action
- Danger Zone — Account Deletion: optional reason textarea, type DELETE confirmation,
  30-day cooling-off period, cancel button when pending

Route: `index.php?page=privacy` (added to views array in app/index.php).
Accessible via user dropdown in topnav and a button on the Profile page (not in sidebar).

### Privacy action file
`include/actions/memberActions/privacyActions.php` — 7 actions:
- `updateConsent` — toggles consent for marketing_email, cookie_analytics, cookie_marketing.
  Only records changes (compares submitted vs current status).
- `requestDataExport` — builds export synchronously. Adds child-app-specific data
  (items, preferences, history) on top of admin's build_export_data() base.
- `downloadExport` (GET) — serves the export file as a download with correct MIME type.
- `requestAccountDeletion` — requires typing DELETE to confirm, 30-day cooling-off.
- `cancelAccountDeletion` — cancels pending deletion request.
- `revokeSession` — revokes a single device by device_id (user-scoped).
- `revokeAllSessions` — revokes all other devices except current.

All GDPR helper functions come from admin core (gdprFunctions.php,
loginHistoryFunctions.php, deviceFunctions.php) — loaded via common.php chain.

### Re-consent page
`auth/consent.php` — shown after login when user hasn't accepted latest
Terms of Service or Privacy Policy version. Uses child-app's auth template.
Action handled by admin's consentActions.php → acceptReconsent.

### Registration consent
`auth/register.php` — includes required checkbox: "I agree to the Terms of
Service and Privacy Policy". Validated in admin's loginActions.php register action.

## All action names

### memberActions/itemActions.php
- `saveItem` — Create or update an item (title, description, status). Logs `item_created` or `item_updated`.
- `deleteItem` — Delete an item by item_id. Logs `item_deleted`.
- `updateItemStatus` — Change item status inline. Logs `item_updated`.

### memberActions/profileActions.php
- `updateProfile` — Update personal info (first_name, last_name, email) on admin shard. Email uniqueness validated.
- `changePassword` — Change password with current password verification. Uses admin core's hash_password().
- `updateAppPreferences` — Save user prefs (items_per_page, default_status, email_on_item_change, bio) to shard.

### memberActions/exampleActions.php
- `sendFeedback` — Validate and process the feedback modal form (subject, message).

### memberActions/privacyActions.php
- `updateConsent` — Toggle optional consent preferences (marketing_email, cookie_analytics, cookie_marketing).
- `requestDataExport` — Build and save data export (JSON/CSV) with child app data included.
- `downloadExport` (GET) — Serve completed export file as download.
- `requestAccountDeletion` — Request account deletion with 30-day cooling-off. Requires typing DELETE.
- `cancelAccountDeletion` — Cancel pending deletion request.
- `revokeSession` — Revoke a single device session by device_id.
- `revokeAllSessions` — Revoke all other device sessions except current.

### apiActions/searchActions.php
- `liveSearch` — Search items by title/description for nav search bar. Returns up to 8 matches.

### apiActions/exampleApiActions.php
- `getItems` — Paginated item list via AJAX. Params: page, per_page, filter (status). Returns items, total, page count.
