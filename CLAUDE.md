# child-app — CLAUDE.md

## CRITICAL: This repo is a template — always clone it first
**Never create a child app directory from scratch.** To build a new child app:
```
git clone https://github.com/WaveNetworks/child-app.git your-app-name/
```
Then modify the cloned files — don't recreate them. The template provides
auth pages, include/common.php, template.php, JS assets, SCSS pipeline,
Docker config, installer, and the full directory structure.

## What this repo is
Example child application for the Wave Networks ecosystem. Deployed at
`public_html/child-app/` as a sibling of `public_html/admin/`. Demonstrates the
complete pattern for building apps on top of the admin core: own databases,
own shards, branded auth pages, custom theme pipeline, Docker dev environment.

External developers clone this repo, rename it, and use it as their starting point.

## Relationship to admin core
Admin core (`../admin/`, repo: WaveNetworks/wave-networks-core) provides: auth,
sessions, shard routing, email queue, notifications, theming, branding, user management.

Bootstrap chain: `child-app/include/common.php` -> `admin/include/common.php`

## Path reference (critical)
```
auth/*.php          -> include: ../include/common_auth.php
app/index.php       -> include: ../include/common.php
api/index.php       -> include: ../include/common_api.php
views/*.php         -> included BY app/index.php (never directly)
include/common.php  -> include: ../../admin/include/common.php (core)
```

## Three database layers
1. **Admin main DB** (`$db`) — READ ONLY from child
2. **Child main DB** (`$child_db`) — `child_db_query()`, `child_db_query_prepared()`
3. **Child shard DBs** — `child_prime_shard()`, `child_db_query_shard()`, `child_db_query_shard_prepared()`

Use `db_query_shard()` ONLY for reading admin shard data (user_profile).

## Migration rules
Dir: `db_migrations/main/` and `db_migrations/shard/` — decimal versioning (1.0, 1.1, 2.0).

EVERY migration requires BOTH steps:
1. Create the SQL file in `db_migrations/main/` or `db_migrations/shard/`
2. Update `$child_db_version` or `$child_shard_version` in `include/common.php`
   AND `include/common_api.php` (both files must match!)

THIS STEP IS FREQUENTLY FORGOTTEN — DO NOT SKIP IT.
See [CLAUDE-development.md](CLAUDE-development.md) for DDL/DML templates and detailed rules.

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

## Topic files

| File | Contents |
|------|----------|
| [CLAUDE-architecture.md](CLAUDE-architecture.md) | UI components, folder layout, template shell, JS modules, apiPost/modalView patterns |
| [CLAUDE-development.md](CLAUDE-development.md) | Migration templates, action files, modal system, live search, toast, page nav, canvas, theme registration, env vars |
| [CLAUDE-docker.md](CLAUDE-docker.md) | Docker services, build/rebuild, database naming, config auto-generation, environment variables |
| [CLAUDE-schema.md](CLAUDE-schema.md) | Full database schema (main + shard), history/activity logging, GDPR/privacy, consent, all action names |
