# ElevateHER — CLAUDE.md

## What this is
Child application for the Wave Networks ecosystem. Deployed at `public_html/elevateher/`,
a sibling of `public_html/admin/`. The admin core provides auth, sessions, sharding, email,
notifications, theming, and branding. This app adds its own databases, views, actions, and theme.

Bootstrap chain: `elevateher/include/common.php` → `admin/include/common.php`

## Path reference
```
auth/*.php          → include: ../include/common_auth.php
app/index.php       → include: ../include/common.php
api/index.php       → include: ../include/common_api.php
views/*.php         → included BY app/index.php (never directly)
include/common.php  → include: ../../admin/include/common.php
```

## Three database layers
1. **Admin main DB** (`$db`) — READ ONLY from child.
2. **Child main DB** (`$child_db`) — `child_db_query()`, `child_db_query_prepared()`.
3. **Child shard DBs** — `child_prime_shard()`, `child_db_query_shard()`, `child_db_query_shard_prepared()`.

Use `db_query_shard()` only for reading admin shard data (e.g. `user_profile`).

## Migration rules
Directories: `db_migrations/main/` and `db_migrations/shard/`, decimal versioning (1.0, 1.1, 2.0).

EVERY migration requires BOTH steps:
1. Create the SQL file in `db_migrations/main/` or `db_migrations/shard/`.
2. Bump `$child_db_version` or `$child_shard_version` in `include/common.php`.

Never edit an existing migration file — always create a new version.

## Coding rules

**DO:**
- Escape out of PHP for HTML — never echo HTML from PHP strings.
- Use `<?= $var ?>` shorthand, always wrapping user-supplied data in `h()`.
- Use `sanitize($val, SQL)` for values going into raw SQL.
- Write flat `if ($_POST['action'] == 'x')` blocks in action files.
- Collect errors in an `$errs` array, then set `$_SESSION['error']` once.
- Set `$_SESSION['success']` when an action succeeds.
- Reference admin shared assets via `../../admin/assets/`.
- Use `__DIR__`-based paths for all includes.

**DO NOT:**
- Write to admin's DB tables (`user`, `device`, `api_key`, etc.).
- Create a new `$db` PDO connection — reuse the global from admin.
- Edit `vendor/`, ever.
- Store credentials anywhere except `config/config.php` (gitignored).
- Create new API endpoint files — add action files instead.
- Skip `h()` on any output, or `sanitize()` on any raw-SQL value.
- Set `$_SESSION['error']` on the first validation failure — collect them all first.

## Template pattern
```php
<?php if ($someCondition) { ?>
<div class="something"><?= h($userData['field']) ?></div>
<?php } ?>
```

## Action file pattern
```php
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
```

## Action dispatch
- Authenticated: `include/actions/memberActions/yourFeatureActions.php` (auto-included via glob).
- API / public: `include/actions/apiActions/yourFeatureActions.php`.
- Form POST: `<form method="post">` on any view page.
- AJAX: `apiPost('actionName', {data}, callback)` → `api/index.php`.

## Docker
Source is **bind-mounted** — edit any file and refresh, no rebuild needed.
`docker compose up -d` starts app (port 8085), db, and mailhog (port 8025).
Rebuild only when changing `Dockerfile`, `apache.conf`, `entrypoint.sh`, or PHP extensions.
Test credentials: `admin@elevateher.test` / `Admin123!`

## Detailed reference docs
Read these on demand when working in the relevant area:

| Doc | When to read |
|-----|-------------|
| [01-ARCHITECTURE.md](docs/01-ARCHITECTURE.md) | Bootstrap chains, request flow, directory structure |
| [02-BOOTSTRAP-AND-CONFIG.md](docs/02-BOOTSTRAP-AND-CONFIG.md) | Config loading, env var fallbacks, common.php internals |
| [03-FRAMEWORK-SYSTEM.md](docs/03-FRAMEWORK-SYSTEM.md) | Framework data sync, content catalog, versioning |
| [04-ASSESSMENT-ENGINE.md](docs/04-ASSESSMENT-ENGINE.md) | Assessment instances, scoring, question matrix, AI backstop |
| [05-CONTENT-SYSTEM.md](docs/05-CONTENT-SYSTEM.md) | Content state machine, progress tracking, recommendations |
| [06-COACH-AND-TEAM.md](docs/06-COACH-AND-TEAM.md) | Coach relationships, team views, role-based access |
| [07-ACTIONS-REFERENCE.md](docs/07-ACTIONS-REFERENCE.md) | Complete list of all action handlers by file |
| [08-VIEWS-AND-UI.md](docs/08-VIEWS-AND-UI.md) | Page views, modal system, template shell regions |
| [09-JAVASCRIPT.md](docs/09-JAVASCRIPT.md) | All JS modules, apiPost/modalView patterns, game loop architecture |
| [10-THEME-AND-SCSS.md](docs/10-THEME-AND-SCSS.md) | Theme system, SCSS pipeline, build commands, theme registration |
| [11-AUTH-AND-PRIVACY.md](docs/11-AUTH-AND-PRIVACY.md) | Auth flow, GDPR privacy page, consent, data export, account deletion |
| [12-DOCKER-AND-DEPLOY.md](docs/12-DOCKER-AND-DEPLOY.md) | Docker services, volumes, env vars, entrypoint, deploy script |
| [13-DATABASE-SCHEMA.md](docs/13-DATABASE-SCHEMA.md) | Full table schemas for main and shard DBs |

## Cron system

Entry point: `cron/cron.php`, run via crontab every minute:
```
* * * * * /usr/bin/php /path/to/app/cron/cron.php >> /tmp/app_cron.log 2>&1
```

**Schedule layout:**
| Directory | When |
|---|---|
| `cron/minutes/{N}/*.php` | Every N minutes (e.g. `minutes/5/` runs at :00 :05 :10 …) |
| `cron/days/*.php` | Every day |
| `cron/weeks/*.php` | Every Monday |
| `cron/months/*.php` | 1st of each month |

**Adding a job:**
- Drop a `.php` file in the appropriate directory.
- Do NOT `require_once common.php` — `cron.php` bootstraps it first, so all globals are available.
- Use `child_db_query_prepared()` / `child_db_query_shard_prepared()` for DB access.

**Rules:**
- `cron/.htaccess` blocks web access to the entire `cron/` tree — never remove it.
- Never do HTTP-only work (headers, sessions, cookies) inside cron jobs.
- Keep jobs idempotent where possible.
