# Wave Networks Child App — CLAUDE.md

## What this is

A reference scaffold for child applications in the Wave Networks
ecosystem. Deployed at `public_html/{CHILD_APP_SLUG}/` as a sibling
of `public_html/admin/`. Admin core (`wave-networks-core`) provides
auth, sessions, sharding, email, notifications, theming, branding.
This scaffold adds child-app-specific bootstrap, config, and
boilerplate.

Bootstrap chain:
`{child-app}/include/common.php` → `admin/include/common.php`

## Scaffolding a new child app from this repo

1. Create the new repo (e.g. `WaveNetworks/myapp`) and copy this
   scaffold's contents into it.
2. Set the env identity:
   - `CHILD_APP_SLUG=myapp` (lowercase, URL-safe — drives DB
     namespacing, URL paths, theme paths, cost tracking)
   - `CHILD_APP_NAME="My App"` (display name shown in onboarding etc.)
3. Set in `.github/workflows/deploy.yml`:
   - `env.CHILD_APP_SLUG: myapp`
4. Set repo secrets via `gh secret set`:
   - `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `CROSS_REPO_PAT`
5. Set in `docker-compose.yml` `.env`:
   - same `CHILD_APP_SLUG=myapp` and `CHILD_APP_NAME=...`
6. Decide which scaffold files you actually need. Some files in
   `include/common/` (`questionMatrixHelpers.php`,
   `instanceFileHelpers.php`, `scoringEngine.php`,
   `contentHelpers.php`, etc.) are inherited from the original
   ElevateHER assessment engine and most child apps will delete them.
   Strip what you don't need before first run.

## Path reference

```
auth/*.php          → include: ../include/common_auth.php
app/index.php       → include: ../include/common.php
api/index.php       → include: ../include/common_api.php
views/*.php         → included BY app/index.php (never directly)
include/common.php  → include: ../../admin/include/common.php
```

## Three database layers

1. **Admin main DB** (`$db`) — READ ONLY from child
2. **Child main DB** (`$child_db`) — `child_db_query()`, `child_db_query_prepared()`
3. **Child shard DBs** — `child_prime_shard()`, `child_db_query_shard()`, `child_db_query_shard_prepared()`

Use `db_query_shard()` ONLY for reading admin shard data (user_profile).

DB names default to `{CHILD_APP_SLUG}_main`, `{CHILD_APP_SLUG}_shard_1`,
`{CHILD_APP_SLUG}_shard_2` (and `{CHILD_APP_SLUG}_admin_main` etc for
admin tables) so each child app on shared infra has its own
namespace automatically.

## Migration rules

Dir: `db_migrations/main/` and `db_migrations/shard/` — decimal versioning (1.0, 1.1, 2.0).

EVERY migration requires BOTH steps:
1. Create the SQL file in `db_migrations/main/` or `db_migrations/shard/`
2. Update `$child_db_version` or `$child_shard_version` in `include/common.php`

## Coding rules

**DO:**
- Escape out of PHP for HTML — never echo HTML from PHP strings
- `<?= $var ?>` shorthand, always `h()` for user-supplied data
- `sanitize($val, SQL)` for DB values
- Flat `if($_POST['action'] == 'x')` blocks in action files
- Collect errors in `$errs` array before setting `$_SESSION['error']`
- Always set `$_SESSION['success']` when an action succeeds
- Asset paths: `../../admin/assets/` for admin shared assets
- `__DIR__`-based paths for all includes

**DO NOT:**
- Write to admin's DB tables (user, device, api_key, etc.)
- Create a new `$db` PDO connection — use the global from admin
- Edit `vendor/` ever
- Store credentials anywhere except `config/config.php` (gitignored)
- Create new API endpoint files — use action files instead
- Edit existing migration files — always create a new version
- Skip `h()` or `sanitize()` for any output
- Set `$_SESSION['error']` on first validation failure (collect all first)
- Hardcode the child-app slug, DB name, or URL prefix anywhere — read
  `CHILD_APP_SLUG` / `CHILD_APP_NAME` constants instead so the
  scaffold stays reusable.

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

- Authenticated: `include/actions/memberActions/yourFeatureActions.php` (auto-included via glob)
- API/public: `include/actions/apiActions/yourFeatureActions.php`
- Form POST: `<form method="post">` on any view page
- AJAX: `apiPost('actionName', {data}, callback)` → `api/index.php`

## Docker

Source is **bind-mounted** — edit any file and refresh, no rebuild needed.
`docker compose up -d` starts app (port 8085), db, mailhog (port 8025).
Only rebuild when changing Dockerfile/apache.conf/entrypoint.sh/PHP extensions.

## Cron system

Entry point: `cron/cron.php` — run via crontab every minute:
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
- Create a `.php` file in the appropriate directory.
- Do NOT call `require_once common.php` — `cron.php` bootstraps it first; all globals are available.
- Use `child_db_query_prepared()` / `child_db_query_shard_prepared()` for DB access.

**Rules:**
- `cron/.htaccess` blocks web access to the entire `cron/` tree — never remove it.
- Never do HTTP-only work (headers, sessions) inside cron jobs.
- Keep jobs idempotent where possible.
