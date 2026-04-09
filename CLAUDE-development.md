# child-app -- Development

## Migration Rules (Detailed)

Dir: `db_migrations/main/` and `db_migrations/shard/` -- decimal versioning (1.0, 1.1, 2.0).

**Current targets:**
- Child main: 1.1
- Child shard: 1.4

**Naming:** `{version}.sql` (e.g., `1.0.sql`, `1.1.sql`)

**Version tracking:** `db_version` table (managed by admin's migration infrastructure).

**Execution:** `child_check_and_migrate()` runs on every page load. Compares current DB version to target; runs pending migrations sequentially.

EVERY migration requires BOTH steps:
1. Create the SQL file in `db_migrations/main/` or `db_migrations/shard/`
2. Update `$child_db_version` or `$child_shard_version` in `include/common.php`

Never edit existing migration files -- always create a new version.

### DDL Template (new table)

```sql
-- ElevateHER Learning Suite -- Main DB v{VERSION}
-- {Description of what this migration adds}
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `my_table` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `data_json` LONGTEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### DML Template (alter/insert)

```sql
-- ElevateHER Learning Suite -- Shard DB v{VERSION}
-- {Description of what this migration changes}

ALTER TABLE `existing_table` ADD COLUMN `new_col` VARCHAR(50) NULL AFTER `existing_col`;
ALTER TABLE `existing_table` ADD INDEX `idx_new_col` (`new_col`);
```

### Migration Version Targets by Bootstrap

| Bootstrap | Main Target | Shard Target | Why |
|-----------|-------------|--------------|-----|
| common_auth.php | 1.0 | 1.0 | Auth pages need only base schema |
| common_api.php | 1.1 | 1.2 | API needs cache + snapshots |
| common.php | 1.1 | 1.4 | Full app needs all tables |

## Action Files

Action files live in `include/actions/memberActions/` (12 files) and `include/actions/apiActions/` (4 files). They are auto-included via glob by the bootstrap files.

### Action Dispatch Pattern

```php
// In any action file (auto-included via glob):
if ($_REQUEST['action'] === 'myAction') {
    // validate inputs
    // perform operation
    // set $_SESSION['success'] or $_SESSION['error']
    // redirect (member) or set $results['data'] (API)
}
```

Flat `if` blocks -- no registry, no routing. This mirrors admin's dispatch pattern and cannot be replaced without forking admin.

**Member actions** (session auth via common.php's guard): set `$_SESSION['success']`/`$_SESSION['error']` and redirect.

**API actions** (self-check auth): set `$results['data']` for JSON response.

### Template Pattern (member action)

```php
if ($_REQUEST['action'] === 'saveItem') {
    $errs = [];
    $title = sanitize($_POST['title'] ?? '', SQL);
    $desc  = sanitize($_POST['description'] ?? '', SQL);

    if (empty($title)) $errs[] = 'Title is required.';
    if (strlen($title) > 255) $errs[] = 'Title too long.';

    if (!empty($errs)) {
        $_SESSION['error'] = implode('<br>', $errs);
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    child_db_query_prepared("INSERT INTO items (user_id, title, description) VALUES (?, ?, ?)",
        [$_SESSION['user_id'], $title, $desc]);

    $_SESSION['success'] = 'Item saved.';
    header('Location: ../app/index.php?page=items');
    exit;
}
```

### Template Pattern (API action)

```php
if ($_REQUEST['action'] === 'getItems') {
    $page = intval($_POST['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $items = child_db_query_prepared(
        "SELECT * FROM items WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$_SESSION['user_id'], $limit, $offset]
    );

    $results['data'] = ['items' => $items, 'page' => $page];
}
```

## Modal System

### Adding a New Modal

1. Create snippet: `snippets/modal_my_thing.php`
2. Register in `app/modal_controller.php` (add to the switch/if block)
3. Call from JS: `modalView({modal: 'my_thing', id: 123})`

Modals are HTML fragments loaded via AJAX into Bootstrap 5's modal container.

### Validation Persistence in Modals

When a modal form has validation errors, use `reloadModalThisForm()` to re-render the modal with the form data preserved:

```javascript
reloadModalThisForm('myFormId', {extra: 'vars'})
```

This re-fetches the modal HTML from `modal_controller.php` with the current form data sent as POST parameters, so the snippet can repopulate fields and display error messages.

## Flash Message / Toast Pattern

```php
// Set in action handler:
$_SESSION['success'] = 'Item saved.';
$_SESSION['error'] = 'Validation failed.';

// Read in view (via toast.js or inline alert):
// toast.js reads #toast-data div attributes on page load
// SPA nav reads toast from JSON response
```

Profile page uses form-specific keys: `profile_success`, `password_error`, `prefs_success`, etc.

### Toast JS API

```javascript
showToast('success', 'Item saved!', {autohide: true, delay: 5000})
// Types: success, danger, warning, info
// Session flash: reads #toast-data div attributes on DOMContentLoaded
```

## Live Search Implementation

`search.js` (115 lines):
- 300ms debounce, minimum 2 characters
- Calls `apiPost('liveSearch')` (via admin's bs-init.js)
- Renders dropdown with title, date, status badge
- Binds both `#navSearchInput` (desktop) and `#navSearchInputMobile`

Server side: `searchActions.php` queries items by title/description (limit 8).

## Page-Level Navigation

`page-nav.js` (94 lines):
- Desktop (>=992px): inline sidebar toggle, state persisted in localStorage
- Mobile (<992px): overlay drawer with backdrop
- 150ms debounced resize for breakpoint crossing
- Storage key: `wn_page_nav_state`

## Canvas Background

`bg-canvas.js` (194 lines):
- Dark mode: deep indigo/violet nebula with colored glows
- Light mode: grey-white with faint color washes
- 80px grid with directional arrows at intersections
- `MutationObserver` on `data-bs-theme` for live theme switching

`water-ripples.js` (209 lines):
- Radial ripple rings from lotus center (phase-based)
- 3 overlaid caustic stripe gradients
- Background-size breathing animation
- Parallax sway via `lps.canvas._windVector`

## Theme Registration

`themeRegistration.php` auto-discovers themes:
- Scans `assets/themes/*/custom.css` via glob
- Registers each with admin core via `register_theme(slug, name, metadata)`
- Metadata includes `sidebar_mode: 'glass'`, `created_by_app: 'elevateher'`

### Theme Resolution (appFunctions.php)

`get_app_theme_css_url()` fallback chain:
1. Custom DSWA theme (`assets/themes/dswa/custom.css`)
2. Glassmorphism theme (`assets/themes/glassmorphism/custom.css`)
3. Admin's Bootswatch theme system

Cache-busted via `?v=` with `filemtime()`.

### SCSS Build

```bash
npm run build:css    # Production (compressed) -- builds glass + dswa
npm run watch        # Development (expanded, watching)
```

Entry point: `assets/themes/{theme}/custom.scss` imports `_variables.scss`, Bootstrap, then `_bootswatch.scss`.

**Compiled CSS is git-tracked.** The deploy pipeline does not run `npm build`, so compiled output must be committed.

## Cron Jobs

### Daily (cron/days/)

| File | Purpose |
|------|---------|
| `cohort_snapshot.php` | Capture daily aggregate progress snapshot for cohort timeline |
| `framework_sync.php` | Auto-sync framework data if `auto_sync` enabled |
| `cache_cleanup.php` | Remove expired cache entries from app_cache |

### Weekly (cron/weeks/)

| File | Purpose |
|------|---------|
| `inactive_check.php` | Flag inactive sellers, send notifications |

## Notification Categories

Registered in `notificationRegistration.php`:

| Category | Trigger |
|----------|---------|
| `coach_linked` | Coach-seller relationship created |
| `coach_note` | Coach shares a note |
| `seller_linked` | Seller accepts invite |
| `seller_inactive` | Weekly inactivity check |
| `assessment_complete` | Assessment finished |
| `framework_updated` | Framework data synced |

## Environment Variables (Docker)

When `config/config.php` does not exist, the bootstrap files fall back to environment variables. See [CLAUDE-docker.md](CLAUDE-docker.md) for the full variable table.

Key config sections loaded from env: child DB connections, shard configs, AI vendor keys, framework source, app settings.
