# Child App — Wave Networks Example Application

Example child application for the [Wave Networks](https://github.com/WaveNetworks) ecosystem. Clone this repo, rename it, and use it as the starting point for your own app. Demonstrates the complete pattern: own databases, own shards, branded auth pages, custom glassmorphism theme, D3.js dashboards, DataTables, live search, notifications, and a full Docker dev environment.

## Prerequisites

- [Wave Networks Admin Core](https://github.com/WaveNetworks/wave-networks-core) checked out as a sibling directory
- PHP 8.2+ with PDO MySQL, GD, Imagick, Zip extensions
- MySQL 8.0+
- Node.js 18+ (for SCSS theme compilation)
- Docker (optional, recommended for development)

## Quick Start — Docker

1. Clone both repos as siblings:
   ```
   git clone git@github.com:WaveNetworks/wave-networks-core.git admin
   git clone git@github.com:WaveNetworks/child-app.git child-app
   ```

2. Start everything:
   ```
   cd child-app
   docker compose up
   ```

3. Open your browser:
   - Child app: `http://localhost/child-app/auth/login.php`
   - Admin: `http://localhost/admin/auth/login.php`
   - Mail testing: `http://localhost:8025`

Source code is volume-mounted — edit any PHP file and refresh to see changes instantly.

## Quick Start — Shared Hosting

1. Upload both repos into your webroot:
   ```
   public_html/admin/
   public_html/child-app/
   ```

2. Copy and configure:
   ```
   cd admin
   cp config/config.sample.php config/config.php
   # Edit config.php with admin DB credentials

   cd ../child-app
   cp config/config.sample.php config/config.php
   # Edit config.php with child app DB credentials
   ```

3. Create the databases:
   - `wncore_main`, `wncore_shard_1`, `wncore_shard_2` (for admin)
   - `childapp_main`, `childapp_shard_1`, `childapp_shard_2` (for child app)

4. Visit `http://yourdomain.com/child-app/auth/login.php` — migrations run automatically on first load.

## Renaming for Your Own App

1. Clone this repo with your app name:
   ```
   git clone git@github.com:WaveNetworks/child-app.git my-app
   ```

2. Update `docker-compose.yml` volume mount:
   ```yaml
   - .:/var/www/html/my-app
   ```

3. Update database names in `docker-compose.yml` and `config/config.sample.php` if desired.

4. Replace the example views, actions, and migrations with your own.

## What's Included

### Pages

| Page | Route | Description |
|------|-------|-------------|
| Dashboard | `?page=dashboard` | Welcome hero, breadcrumb nav, D3.js line chart (items per month), D3.js donut chart (status breakdown) |
| Items | `?page=items` | CRUD item management with status badges, inline editing via AJAX modals |
| Profile | `?page=profile` | User profile editing (reads from admin shard) |
| Examples | `?page=examples` | DataTables (Bootstrap 5 + Responsive), AJAX modal demos, tabbed card layout |
| UI Guide | `?page=ui-guide` | Living style guide — every Bootstrap 5 component rendered with the active theme |

### Template Shell (`views/template.php`)

The main layout provides a complete application shell:

- **Sidebar** — collapsible navigation with icon+text links, item count badge, brand logo
- **Top navigation** — live search (debounced AJAX), notification bell with dropdown, color mode toggle (dark/light), theme switcher, user dropdown with logout
- **Right panel** — offcanvas settings panel (triggered by vertical dots icon) with tabbed content (General, Preferences, About)
- **Footer** — sticky footer with Terms of Use, Privacy Policy links, and copyright
- **Canvas layer** — background canvas element for gamified/animated apps
- **Toast system** — session-based flash messages rendered as Bootstrap toasts
- **Large modal** — shared modal container for AJAX-loaded content via `modalView()`

### JavaScript

Scripts loaded by the template (admin shared + child-app specific):

| Script | Source | Purpose |
|--------|--------|---------|
| `bootstrap.bundle.min.js` | CDN | Bootstrap 5.3 components + Popper |
| `bs-init.js` | Admin | `apiPost()` AJAX helper, Bootstrap init |
| `sidebar.js` | Admin | Sidebar collapse/expand behavior |
| `color-mode.js` | Admin | Dark/light mode toggle with localStorage |
| `notifications.js` | Admin | Notification bell polling and rendering |
| `modal.js` | Child | `modalView()` and `reloadModalThisForm()` helpers |
| `theme.js` | Child | Bootswatch theme switcher |
| `toast.js` | Child | Reads toast data div and shows Bootstrap toasts |
| `search.js` | Child | Debounced live search in topnav (desktop + mobile) |
| `page-nav.js` | Child | Page navigation helpers |
| `bg-canvas.js` | Child | Background canvas rendering |

### SCSS Theme Pipeline

```
assets/scss/
├── custom.scss          Entry point (imports variables → bootstrap → bootswatch)
├── _variables.scss      Bootstrap variable overrides (colors, fonts, spacing)
├── _bootswatch.scss     Glassmorphism theme (glass mixin, dark/light mode, all components)
└── themes/
    └── custom-theme/    Additional theme variants
```

The default theme uses a glassmorphism design with `backdrop-filter: blur()` effects. The `_bootswatch.scss` file includes styled overrides for:

- Cards, tables, forms, modals, offcanvas panels
- Sidebar and navbar with glass effects
- DataTables controls (search, pagination, length selector)
- Dashboard components (hero box, breadcrumbs, charts)
- Live search bar with glass input
- Footer with transparent background
- Full light mode overrides via `[data-bs-theme="light"]`

Build commands:
```bash
npm install
npm run build:css      # Compile custom.scss → assets/css/custom.css
npm run watch          # Watch for changes
npm run build:themes   # Compile all themes in assets/scss/themes/
```

### Third-Party Libraries

| Library | Version | Loaded via | Used in |
|---------|---------|------------|---------|
| Bootstrap | 5.3.2 | CDN (JS), npm (SCSS) | Everywhere |
| Bootstrap Icons | 1.11.3 | CDN | Icons throughout UI |
| D3.js | 7.x | CDN | Dashboard charts |
| jQuery | 3.7.1 | CDN | DataTables dependency |
| DataTables | 2.3.7 | CDN | Examples page |
| DataTables Responsive | 3.0.4 | CDN | Examples page |

## Directory Structure

```
child-app/
├── auth/               Auth pages (login, register, forgot, etc.)
├── app/                Main app controller (?page= routing)
├── api/                JSON API endpoint (single entry point)
├── include/
│   ├── common.php              Authenticated bootstrap
│   ├── common_auth.php         Auth page bootstrap
│   ├── common_api.php          API bootstrap
│   ├── common/                 Helper functions
│   └── actions/                Action files (auto-included)
│       ├── memberActions/      Authenticated actions (itemActions, profileActions, exampleActions)
│       └── apiActions/         API/public actions (exampleApiActions, searchActions)
├── views/              View templates (loaded by app/index.php)
│   ├── template.php            Main HTML shell (sidebar + topnav + footer)
│   ├── dashboard.php           Dashboard with D3 charts
│   ├── items.php               CRUD item management
│   ├── profile.php             User profile
│   ├── examples.php            DataTables, modals, tabbed cards
│   ├── ui-guide.php            Bootstrap 5 component showcase
│   └── 404.php                 Not found page
├── snippets/           Modal content templates
│   ├── modal_example_form.php
│   ├── modal_item_detail.php
│   └── modal_confirm_delete.php
├── config/             App configuration (gitignored)
├── db_migrations/      Database migrations
│   ├── main/           Child app main DB
│   ├── shard/          Child app shard DBs
│   └── seeds/          Development seed data
├── assets/
│   ├── css/            Compiled CSS output
│   ├── scss/           SCSS source files
│   └── js/             Child-app JavaScript
└── docker/             Docker configuration
```

## Adding a New Feature

### 1. Add a view

Create `views/myfeature.php`, then register it in `app/index.php`:

```php
$views = [
    'dashboard' => __DIR__ . '/../views/dashboard.php',
    'myfeature' => __DIR__ . '/../views/myfeature.php',
];
```

### 2. Add actions

Create `include/actions/memberActions/myFeatureActions.php`:

```php
if ($_POST['action'] == 'doSomething') {
    $errs = [];
    // validate...
    if (count($errs) <= 0) {
        child_db_query_prepared("INSERT INTO ...", [...]);
        $_SESSION['success'] = 'Done.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}
```

### 3. Add an API action

Create `include/actions/apiActions/myFeatureApiActions.php`:

```php
if ($_POST['action'] == 'mySearch') {
    $data['results'] = [];
    // query and populate $data['results']
}
```

Call from JS: `apiPost('mySearch', {q: 'term'}, function(resp) { /* resp.results */ });`

### 4. Add a migration

Create `db_migrations/main/1.1.sql`, then update `$child_db_version` in `include/common.php`.

### 5. Add a modal

Create `snippets/modal_myfeature.php`, register in `modal_controller.php`, then open with:

```html
<button onclick="modalView('modal=myfeature&id=5')">Open</button>
```

## Architecture

- **Admin core** handles auth, sessions, email queue, notifications, branding, and user management
- **Child app** has its own databases and shards for app-specific data
- **Three DB layers**: admin main (read-only from child), child main (`$child_db`), child shards
- **Sharding**: user_event_log table demonstrates per-user data distribution across shards
- All auth actions (login, register, 2FA, etc.) are provided by admin's action files automatically
- **Bootstrap chain**: `child-app/include/common.php` → `admin/include/common.php` — admin handles config, `$db`, helpers, migrations, and session guard; child app then opens `$child_db` and runs its own migrations
- **Asset sharing**: admin provides shared CSS (`style.css`, `bs-theme-overrides.css`) and JS (`bs-init.js`, `sidebar.js`, `color-mode.js`, `notifications.js`); child app adds its own theme CSS and feature JS on top
