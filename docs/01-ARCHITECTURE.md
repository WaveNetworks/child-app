# Architecture Overview

> **RE-ENGINEERING NOTES:**
> 1. ~~**Migration version targets differ across bootstrap files**~~ — **BY DESIGN.** The three bootstraps intentionally use different targets (auth: 1.0/1.0, API: 1.1/1.2, app: 1.1/1.4). Auth pages need only the base schema to function during first-run. Moving to a single constant in `definition.php` would break auth pages by forcing the full migration suite. **Do not consolidate.**
> 2. **Glob-based auto-include** — All `*.php` files in `include/common/`, `include/actions/memberActions/`, and `include/actions/apiActions/` are auto-included via glob on every request. In practice this is fine: PHP function definitions are order-independent, and no action file has cross-file top-level side effects. This mirrors admin's pattern and **cannot be changed** without forking admin.

---

## Bootstrap Chain

```
Auth pages:     auth/*.php → include/common_auth.php → admin/include/common_auth.php
App pages:      app/index.php → include/common.php → admin/include/common.php
API endpoints:  api/index.php → include/common_api.php → admin/include/common_api.php
```

Each bootstrap file:
1. Loads admin core (session, `$db`, admin helpers)
2. Loads `config/config.php` or falls back to environment variables
3. Opens child `$child_db` (PDO)
4. Runs child migrations (`child_check_and_migrate()`)
5. Globs helper and action files

## Request Flow

```
Browser → .htaccess (security + routing)
  ├── auth/*.php           → common_auth.php (no session guard)
  ├── app/index.php        → common.php (session guard) → ?page= router → views/*.php
  ├── api/index.php        → common_api.php (JSON) → action handlers → JSON response
  └── app/modal_controller.php → common.php → snippets/*.php (HTML fragment)
```

SPA navigation (spa-nav.js) intercepts `a[href*="index.php?page="]` clicks and loads views via AJAX with `reloadView=true`, receiving JSON `{html, title, page, toast}`.

## Database Layers

| Layer | Connection | Scope | Access from child |
|-------|-----------|-------|-------------------|
| Admin main | `$db` | Users, auth, notifications, feedback | Read-only |
| Admin shard 1 | via admin helpers | Per-user profiles | Read-only |
| Admin shard 2 | via admin helpers | Per-user profiles | Read-only |
| Child main | `$child_db` | Items, app_cache, invite_links, framework_versions, framework_data, cohort_snapshots | Read/write |
| Child shard 1 | `child_prime_shard('shard1')` | assessment_instances, assessment_sessions, coach_relationships, content_progress, user_history, childapp_user_prefs, progress_snapshots | Read/write |
| Child shard 2 | `child_prime_shard('shard2')` | Same schema as shard 1 | Read/write |

## Directory Structure

```
{child-app}/
├── api/                    # Single JSON endpoint (index.php)
├── app/                    # App shell (index.php) + modal controller
├── assets/
│   ├── js/                 # Child-app JS (8 files)
│   ├── scss/               # SCSS theme sources
│   └── themes/             # Compiled themes (glassmorphism/)
├── auth/                   # Login, register, OAuth, SAML, 2FA, consent
├── config/                 # config.php (gitignored), config.sample.php
├── cron/
│   ├── days/               # Daily: cohort_snapshot, framework_sync, cache_cleanup
│   └── weeks/              # Weekly: inactive_check
├── db_migrations/
│   ├── main/               # Child main DB migrations
│   ├── shard/              # Child shard DB migrations
│   └── seeds/              # dev_seed.sql
├── docker/                 # Dockerfile support (entrypoint, apache.conf, init SQL)
├── include/
│   ├── common.php          # Auth bootstrap
│   ├── common_auth.php     # Auth-page bootstrap (no session guard)
│   ├── common_api.php      # API bootstrap (JSON)
│   ├── definition.php      # Constants + request vars
│   ├── common/             # 19 helper files (auto-included)
│   └── actions/
│       ├── memberActions/  # 12 authenticated action files
│       └── apiActions/     # 4 API action files
├── install/                # First-run installer
├── snippets/               # Modal HTML templates (3 files)
├── views/                  # Page views (11 files)
├── .htaccess               # Security + routing
├── Dockerfile
├── docker-compose.yml
└── package.json            # SCSS build scripts
```

## Key Conventions

- **Action dispatch**: Form POST `action=handlerName` or AJAX `apiPost('handlerName', data)`
- **Error pattern**: Collect in `$errs[]` array, batch-set `$_SESSION['error']` before redirect
- **Success pattern**: `$_SESSION['success'] = 'message'` then redirect
- **HTML escaping**: `h($val)` wrapper for `htmlspecialchars()`
- **SQL escaping**: `sanitize($val, SQL)` for inline queries; prepared statements preferred
- **Template convention**: Escape out of PHP for HTML blocks; use `<?= $var ?>` shorthand
