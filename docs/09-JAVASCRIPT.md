# JavaScript System

The scaffold ships a small set of dependency-free ES modules under
`assets/js/`. There is **no bundler and no build step** — each file is a
plain `<script>` include loaded from the page template. Keep modules
small, single-purpose, and free of app-specific domain logic so the
reference stays reusable.

## Modules

| File | Purpose |
|---|---|
| `spa-nav.js` | Intercepts in-app links, fetches the target view via `?reloadView=true`, swaps the main content, updates the title and history, and shows any flashed toast. The core of the single-page navigation model. |
| `page-nav.js` | Sidebar / top-nav active-state tracking and mobile nav toggling. |
| `toast.js` | Renders transient success / error / warning / info toasts. Reads the `toast` payload returned by SPA navigation JSON. |
| `modal.js` | Opens, closes, and focus-traps modal dialogs loaded from `snippets/`. |
| `search.js` | Nav search-bar behaviour (debounced `apiPost` lookups). |
| `tabdrop.js` | Collapses overflowing tab strips into a dropdown on narrow viewports. |
| `theme.js` | Applies the active theme cookie and (optionally) exposes a theme selector. |
| `onboarding.js` | Client-side validation and step flow for the onboarding view. |
| `bg-canvas.js`, `water-ripples.js`, `pong-game.js` | Optional decorative `<canvas>` background effects. Purely cosmetic — safe to delete per app. |
| `chat-overlay.js` | Generic floating help/chat overlay widget. |

## Conventions

- **No framework.** Vanilla DOM APIs only. If a page needs richer state,
  add a focused module rather than pulling in a library.
- **AJAX** goes through the shared `apiPost(action, data, callback)`
  helper, which POSTs to `api/index.php`. Never build endpoint URLs by
  hand.
- **SPA navigation contract:** `app/index.php` returns JSON
  (`{html, page, title, toast}`) when called with `reloadView=true`;
  `spa-nav.js` consumes that shape. A `{redirect: url}` payload tells the
  client to navigate instead of swapping content.
- **Cleanup on navigation:** any module that registers timers,
  intervals, or `requestAnimationFrame` loops must tear them down when
  its view is swapped out, or SPA navigation will leak zombie timers and
  orphaned listeners. Prefer a single shared animation tick over one RAF
  loop per module.
- **Cache-busting:** bump the `?v=` query string on a `<script>` include
  when you change a file, since assets are served with long cache
  lifetimes.

## Adding a module

1. Drop a new file in `assets/js/`.
2. Add a `<script src="../assets/js/yourmodule.js?v=1"></script>` include
   to the page template (or the specific view that needs it).
3. Keep it generic — no hardcoded app slug, brand name, or domain table
   references. Read runtime identity from the exposed config/branding
   instead.
