# child-app -- Architecture

## Directory Structure

```
elevateher/
├── api/                    # Single JSON endpoint (index.php)
├── app/                    # App shell (index.php) + modal controller
├── assets/
│   ├── css/                # elevateher.css (app-specific, 1,810 lines)
│   ├── js/                 # Child-app JS (14 files, ~5,342 lines)
│   ├── scss/               # SCSS theme sources
│   └── themes/             # Compiled themes (glassmorphism/, dswa/)
├── auth/                   # Login, register, OAuth, SAML, 2FA, consent
├── config/                 # config.php (gitignored), config.sample.php
├── cron/
│   ├── days/               # Daily: cohort_snapshot, framework_sync, cache_cleanup
│   ├── weeks/              # Weekly: inactive_check
│   └── minutes/{N}/        # Every N minutes
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
├── views/                  # Page views (11+ files)
├── .htaccess               # Security + routing
├── Dockerfile
├── docker-compose.yml
└── package.json            # SCSS build scripts
```

## Path Reference (Expanded)

```
auth/*.php          -> include: ../include/common_auth.php -> admin/include/common_auth.php
app/index.php       -> include: ../include/common.php      -> admin/include/common.php
api/index.php       -> include: ../include/common_api.php  -> admin/include/common_api.php
views/*.php         -> included BY app/index.php (never directly)
include/common.php  -> include: ../../admin/include/common.php
```

## Bootstrap Chain

```
Auth pages:     auth/*.php -> include/common_auth.php -> admin/include/common_auth.php
App pages:      app/index.php -> include/common.php -> admin/include/common.php
API endpoints:  api/index.php -> include/common_api.php -> admin/include/common_api.php
```

Each bootstrap file:
1. Loads admin core (session, `$db`, admin helpers)
2. Loads `config/config.php` or falls back to environment variables
3. Opens child `$child_db` (PDO)
4. Runs child migrations (`child_check_and_migrate()`)
5. Globs helper and action files

**Migration version targets differ across bootstrap files -- by design.** Auth: 1.0/1.0, API: 1.1/1.2, App: 1.1/1.4. Auth pages need only the base schema. Do not consolidate.

## Request Flow

```
Browser -> .htaccess (security + routing)
  ├── auth/*.php           -> common_auth.php (no session guard)
  ├── app/index.php        -> common.php (session guard) -> ?page= router -> views/*.php
  ├── api/index.php        -> common_api.php (JSON) -> action handlers -> JSON response
  └── app/modal_controller.php -> common.php -> snippets/*.php (HTML fragment)
```

SPA navigation (spa-nav.js) intercepts `a[href*="index.php?page="]` clicks and loads views via AJAX with `reloadView=true`, receiving JSON `{html, title, page, toast}`.

## Template Shell (views/template.php)

The app shell wraps all view content. JS load order:

```
1.  Bootstrap 5.3.2 bundle (CDN)
2.  bs-init.js (admin)         -- provides apiPost()
3.  error-reporter.js (admin)
4.  sidebar.js (admin)
5.  color-mode.js (admin)
6.  modal.js                   -- modalView(), reloadModalThisForm()
7.  theme.js                   -- forces DSWA theme
8.  toast.js                   -- DOMContentLoaded init
9.  page-nav.js                -- DOMContentLoaded init
10. notifications.js (admin)
11. lotus-canvas.js            -- DOMContentLoaded -> initLotus() -> dispatches 'lps-ready'
12. water-ripples.js           -- DOMContentLoaded -> startSwayLoop()
13. chat-overlay.js            -- mounts chat, auto-starts or resumes session
14. explorer-view.js           -- createToggleButton(), waits for 'lps-ready'
15. spa-nav.js                 -- binds click handlers (loaded last)
```

## UI Component Reference

### App Router (app/index.php)

Routes `?page=` parameter to view files:

| Page | View File | Role Guard |
|------|-----------|------------|
| `dashboard` | views/dashboard.php | -- |
| `assessment` | views/assessment.php | -- |
| `results` | views/results.php | -- |
| `content` | views/content.php | -- |
| `coaches` | views/coaches.php | -- |
| `team` | views/team.php | Coach |
| `invite` | views/invite.php | Coach |
| `cohort` | views/cohort.php | Org Lead |
| `admin-framework` | views/admin-framework.php | Admin |
| `onboarding` | views/onboarding.php | -- |
| `profile` | views/profile.php | -- |
| `feedback` | views/feedback.php | -- |
| `items` | views/items.php | -- |
| `examples` | views/examples.php | -- |
| `roadmap` | views/roadmap.php | -- |
| `game` | views/game.php | -- |
| `privacy` | views/privacy.php | -- |
| `ui-guide` | views/ui-guide.php | -- |

### Pre-Template Redirects

- Assessment completion -> results page
- Missing onboarding -> onboarding page
- Non-coach accessing team -> dashboard
- Non-org-lead accessing cohort -> dashboard

### Modal System (app/modal_controller.php)

Endpoint: `modal_controller.php?modal=<name>`

| Modal Name | Snippet File |
|------------|-------------|
| `item_detail` | snippets/modal_item_detail.php |
| `confirm_delete` | snippets/modal_confirm_delete.php |
| `example_form` | snippets/modal_example_form.php |
| `seller_detail` | snippets/modal_seller_detail.php |
| `coach_note` | snippets/modal_coach_note.php |

## JavaScript Files

### Core Application JS (assets/js/)

| File | Lines | Purpose |
|------|-------|---------|
| lotus-canvas.js | 1,833 | Lotus flower visualization: radial petal rings, LMS state, falling petals, wind sway |
| chat-overlay.js | 895 | Assessment chat UI: session management, message rendering, MC choices, content banners |
| explorer-view.js | 902 | Stage exploration compass: stage tiles, dimension bars, hard wall dividers, KPI accordion |
| bg-canvas.js | 194 | Full-viewport background: nebula gradient, grid overlay, theme-aware via MutationObserver |
| water-ripples.js | 209 | Water animation: radial ripple rings, caustic stripe shimmer, parallax sway |
| pong-game.js | 379 | Self-playing Pong overlay: ball physics, paddle AI, particle effects |
| spa-nav.js | 299 | SPA navigation: AJAX view loading, history API, script re-execution |
| modal.js | 97 | AJAX modal loader: `modalView()`, `reloadModalThisForm()` |
| toast.js | 109 | Bootstrap 5 toast wrapper: `showToast()`, session flash integration |
| search.js | 115 | Live nav search: 300ms debounce, `apiPost('liveSearch')` |
| page-nav.js | 94 | Secondary sidebar toggle: desktop inline / mobile drawer, localStorage state |
| tabdrop.js | 178 | Responsive tab overflow: measures widths, moves to "More" dropdown |
| theme.js | 32 | Forces DSWA theme: sets cookie, removes theme selector |
| sw.js | 7 | Service worker proxy for push notifications |

### Admin-Provided JS (loaded from /admin/)

| File | Purpose |
|------|---------|
| bs-init.js | Bootstrap init, `apiPost()` helper |
| sidebar.js | Main sidebar collapse/expand |
| color-mode.js | Dark/light mode toggle |
| error-reporter.js | Client error reporting |
| notifications.js | Push notification registration |

## apiPost() Pattern

All modules use wrappers around `fetch()`:

| Module | Wrapper | Endpoint |
|--------|---------|----------|
| chat-overlay.js | `chatApiPost(action, data, cb)` | `../api/index.php` |
| explorer-view.js | `explorerApiPost(action, data, cb)` | `../api/index.php` |
| lotus-canvas.js | `initFromAPI()` | `../api/index.php` |
| search.js | `apiPost('liveSearch', ...)` | via admin's bs-init.js |
| spa-nav.js | `fetch(url?reloadView=true)` | `app/index.php` |

Response shape:
```json
{
  "error": "...",
  "success": "...",
  "results": { "data": "..." }
}
```

## modalView() Pattern

```javascript
modalView({modal: 'item_detail', id: 123})     // Load modal via AJAX
reloadModalThisForm('formId', {extra: 'vars'})  // Reload with form data
```

Called from JavaScript, loads HTML fragment from `modal_controller.php`, injects into Bootstrap 5 modal container.
