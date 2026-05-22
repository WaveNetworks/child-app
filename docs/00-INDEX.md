# Child App Documentation

> Generated 2026-03-26 from full codebase audit. Updated with re-engineering notes.
> This codebase carries the ElevateHER assessment engine as its reference feature set;
> docs describe both the generic child-app scaffolding and the inherited engine.

## Document Index

| Doc | Scope | Re-Engineering Priority |
|-----|-------|------------------------|
| [01-ARCHITECTURE.md](01-ARCHITECTURE.md) | Bootstrap chain, request flow, database layers, file conventions | Low — glob include is the main concern |
| [02-BOOTSTRAP-AND-CONFIG.md](02-BOOTSTRAP-AND-CONFIG.md) | common.php, common_auth.php, common_api.php, definition.php, config | Low — globals cleanup |
| [03-FRAMEWORK-SYSTEM.md](03-FRAMEWORK-SYSTEM.md) | Framework sync, helpers, versioning, stage/content/question data | Medium — DB query per helper call |
| [04-ASSESSMENT-ENGINE.md](04-ASSESSMENT-ENGINE.md) | Chat loop, question matrix, scoring engine, AI integration, instance files | Medium — 1,268-line monolith |
| [05-CONTENT-SYSTEM.md](05-CONTENT-SYSTEM.md) | Content state machine, recommendations, progress tracking | Low — magic numbers |
| [06-COACH-AND-TEAM.md](06-COACH-AND-TEAM.md) | Invites, relationships, coach notes, team/cohort APIs | Low — cache TTL only |
| [07-ACTIONS-REFERENCE.md](07-ACTIONS-REFERENCE.md) | All 47 action handlers across 16 files | Medium — no action registry |
| [08-VIEWS-AND-UI.md](08-VIEWS-AND-UI.md) | Views, snippets, modals, SPA navigation | Low — inline scripts |
| [09-JAVASCRIPT.md](09-JAVASCRIPT.md) | All 14 client-side JS modules + PWT game loop migration plan | **HIGH — full rebuild needed** |
| [10-THEME-AND-SCSS.md](10-THEME-AND-SCSS.md) | Themes, SCSS pipeline, app.css, dark/light mode | Low — unused theme dir |
| [11-AUTH-AND-PRIVACY.md](11-AUTH-AND-PRIVACY.md) | Auth flow, GDPR, consent, export, deletion | Low — sync export |
| [12-DOCKER-AND-DEPLOY.md](12-DOCKER-AND-DEPLOY.md) | Dockerfile, compose, entrypoint, cron jobs | Low — dev ergonomics |
| [13-DATABASE-SCHEMA.md](13-DATABASE-SCHEMA.md) | Tables by database, migration system | Low — implicit schema |

## Quick Stats

- **Total PHP files:** ~60
- **Total JS files:** 14 (child-app specific) + 5 admin-provided
- **Total JS lines:** ~5,342
- **Action handlers:** 47 across 16 files
- **Database layers:** 6 (3 admin read-only, 3 child read/write)
- **Lines of code (helpers + actions):** ~5,700+
- **Largest PHP file:** assessmentApiActions.php (1,268 lines)
- **Largest JS file:** lotus-canvas.js (1,833 lines)

## Architecture Principles (By Design)

These are **not** problems — they are intentional decisions:

- **Three bootstrap files** (common.php, common_auth.php, common_api.php) exist for security isolation. Auth pages load minimal code with no session guard. Different migration targets per bootstrap are intentional.
- **Instance data follows strict schema v2.3** defined by the framework spec. Filesystem is canonical, DB is index/backup.
- **One admin shard + one child shard per user**, assigned at registration, permanent. No resharding needed.
- **Compiled CSS is git-tracked** because the deploy pipeline doesn't run npm build.
