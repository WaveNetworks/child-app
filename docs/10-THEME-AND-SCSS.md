# Theme & SCSS

Themes live under `assets/themes/{name}/` and are compiled from SCSS to
a single `custom.css`. The scaffold ships one reference theme,
`glassmorphism`; add your own alongside it per app.

## Theme layout

```
assets/themes/
└── glassmorphism/
    ├── _variables.scss   # Brand/color variable overrides
    ├── _bootswatch.scss  # Component-level style overrides
    ├── custom.scss       # Entry point that imports the partials
    ├── custom.css        # Compiled output (committed)
    └── custom.css.map
```

`custom.scss` is the compile entry point; it imports the `_variables`
and `_bootswatch` partials. Themes auto-register on each request via
`include/common/themeRegistration.php`, so a new theme directory is
picked up without routing config.

## Build

Compile SCSS → CSS with Sass. A convenience script per theme, e.g.:

```
npm run build:css          # compile all themes (compressed)
sass assets/themes/glassmorphism/custom.scss \
     assets/themes/glassmorphism/custom.css --style=compressed
```

Rebuild after editing any `.scss` file; the committed `custom.css` is
what the app actually serves.

## Application CSS

Plain (non-SCSS) stylesheets live in `assets/css/`:

| File | Purpose |
|---|---|
| `app.css` | Application-wide layout and component styles not part of the Bootstrap theme pipeline. Loaded separately from the theme CSS. |
| `onboarding.css` | Styles scoped to the onboarding flow. |

Add app-specific CSS here (or replace `app.css` wholesale) when building
a real app on the scaffold — keep brand identity in the theme's
`_variables.scss`, not hardcoded across views.

## Conventions

- Read the active theme from the theme cookie / branding helpers; never
  hardcode a theme name in a view.
- Keep the reference theme generic. App-specific brand motifs, colors,
  and fonts belong in that app's own theme directory, not in the shared
  scaffold.
