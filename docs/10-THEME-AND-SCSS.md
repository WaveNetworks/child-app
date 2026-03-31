# Theme & SCSS

> **RE-ENGINEERING NOTES:**
> 1. **Two theme directories with unclear relationship** — `assets/themes/glassmorphism/` (active, compiled) and `assets/scss/themes/custom-theme/` (partial, no compiled output) both exist. Clarify which is the development target or remove the unused one.
> 2. **Dark mode is base, light is override** — Convention is inverted from Bootstrap's default (light base, dark override). This means all `[data-bs-theme="light"]` blocks are overrides, which is more verbose than necessary and confusing for developers expecting standard Bootstrap patterns.
> 3. **No CSS custom properties for theme values** — Colors and glassmorphism effects use hardcoded values in SCSS. CSS custom properties would enable runtime theme switching without recompilation.
>
> **BY DESIGN:** Compiled CSS is git-tracked intentionally. The deploy pipeline doesn't run `npm build`, so the compiled output must be committed to ensure it reaches production.

---

## Build Pipeline

```
package.json scripts
    │
    ├── build:glass  →  sass assets/themes/glassmorphism/custom.scss → custom.css (compressed)
    ├── build:dswa   →  sass assets/themes/dswa/custom.scss → custom.css (compressed)
    ├── build:css    →  runs both
    └── watch        →  sass --watch (expanded output for dev)
```

**Dependencies:**
- `bootstrap: ^5.3.2` (SCSS sources)
- `bootswatch: ^5.3.2` (theme presets)
- `sass: ^1.69.0` (compiler)

**Build command:**
```bash
npm run build:css    # Production (compressed)
npm run watch        # Development (expanded, watching)
```

## SCSS Architecture

### Entry Point: custom.scss

```scss
@import "variables";          // _variables.scss (Bootstrap overrides)
@import "bootstrap/scss/bootstrap";  // Full Bootstrap from node_modules
@import "bootswatch";         // _bootswatch.scss (post-Bootstrap overrides)
```

### _variables.scss

Bootstrap variable overrides:
- Color palette (primary, secondary, success, etc.)
- Font families and sizes
- Border radius values
- Spacing scale
- Component-specific variables (cards, modals, inputs)

### _bootswatch.scss

Post-Bootstrap component overrides:
- **Glass mixin:** `glass($bg, $blur)` — applies `backdrop-filter: blur()` with semi-transparent background
- Cards, tables, forms, modals — glassmorphism treatment
- Sidebar overrides (glass-effect sidebar with gradient)
- Dark mode base styles
- Light mode overrides in `[data-bs-theme="light"]` blocks

## Glassmorphism Pattern

```scss
@mixin glass($bg: rgba(30, 30, 60, 0.6), $blur: 12px) {
  background: $bg;
  backdrop-filter: blur($blur);
  -webkit-backdrop-filter: blur($blur);
  border: 1px solid rgba(255, 255, 255, 0.08);
}
```

Applied to: cards, modals, dropdowns, sidebar, offcanvas, navbar.

## Theme Registration

`themeRegistration.php` auto-discovers themes:
- Scans `assets/themes/*/custom.css` via glob
- Registers each with admin core via `register_theme(slug, name, metadata)`
- Metadata includes `sidebar_mode: 'glass'`, `created_by_app: 'elevateher'`

## Theme Resolution (appFunctions.php)

`get_app_theme_css_url()` fallback chain:
1. Custom DSWA theme (`assets/themes/dswa/custom.css`)
2. Glassmorphism theme (`assets/themes/glassmorphism/custom.css`)
3. Admin's Bootswatch theme system

Cache-busted via `?v=` with `filemtime()`.

## Dark/Light Mode

- **Dark (base):** Deep indigo/violet backgrounds, light text, glass overlays
- **Light (override):** White/grey backgrounds, dark text, reduced glass opacity
- **Toggle:** Admin's `color-mode.js` sets `data-bs-theme` on `<html>`
- **Canvas:** bg-canvas.js watches `data-bs-theme` via MutationObserver and redraws
- **Storage:** User preference saved in localStorage by admin core

## elevateher.css (1,810 lines)

Application-specific styles that are **not** part of the Bootstrap theme pipeline. Loaded separately from the theme CSS.

### Sections

| Section | Lines (approx) | Purpose |
|---------|----------------|---------|
| Chat overlay | ~400 | Chat container, message bubbles, typing indicator, MC choice buttons, content banners |
| Water background | ~150 | Body background gradients, ripple positioning, caustic shimmer keyframes |
| Lotus canvas | ~200 | Canvas mount, viewport positioning, compass rose, petal glow effects |
| Explorer view | ~300 | Stage grid, dimension bars (M/T/S colors), hard wall dividers, KPI accordion |
| Stage detail | ~150 | Drilldown panel, score gauges, recommendation cards |
| Locked sidebar items | ~50 | Disabled nav items for role-gated pages |
| Onboarding wizard | ~100 | Step indicators, profile form layout |
| Dashboard | ~200 | KPI cards, progress timeline, chart containers |
| Responsive overrides | ~260 | Mobile breakpoint adjustments for all above |

### Key CSS Variables Used

```css
--dim-mindset: #e040fb;   /* Magenta */
--dim-toolset: #448aff;   /* Blue */
--dim-skillset: #69f0ae;  /* Green */
```

These dimension colors appear in both CSS and JS (duplicated in chat-overlay.js and explorer-view.js).

## File Listing

```
assets/
├── css/
│   └── elevateher.css           # App-specific styles (1,810 lines, not SCSS-compiled)
├── themes/
│   ├── glassmorphism/
│   │   ├── _variables.scss      # Bootstrap variable overrides
│   │   ├── _bootswatch.scss     # Post-Bootstrap component styles
│   │   ├── custom.scss          # Entry point (imports above + Bootstrap)
│   │   ├── custom.css           # Compiled output (git-tracked)
│   │   ├── custom.css.map       # Source map
│   │   └── CLAUDE.md            # Theme architecture docs
│   └── dswa/
│       ├── _variables.scss      # DSWA brand variable overrides
│       ├── _bootswatch.scss     # DSWA-specific component styles
│       ├── custom.scss          # Entry point
│       ├── custom.css           # Compiled output (git-tracked)
│       └── custom.css.map       # Source map
└── scss/
    └── themes/
        └── custom-theme/
            ├── _variables.scss  # Alternate theme variables (unused?)
            └── _bootswatch.scss # Alternate theme overrides (unused?)
```
