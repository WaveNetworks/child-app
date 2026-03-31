# DSWA Theme

Custom Bootstrap 5 theme for the ElevateHER app. Uses a dark immersive
palette inspired by the DSWADemo aesthetic: deep blues, DSWA brand greens,
gold accents, and glass panels on a textured dark background.

## Build

From the elevateher root:
```
npm run build:dswa
```

## Architecture

This theme follows the **Bootswatch pattern** and MUST remain compatible
with all Bootswatch themes and the admin's theme switcher:

1. `_variables.scss` -- Bootstrap variable overrides (DSWA brand colors,
   dark backgrounds, typography). Applied BEFORE Bootstrap compiles.
2. `custom.scss` -- Entry point. Imports variables, then full Bootstrap,
   then bootswatch overrides. Do not add rules here.
3. `_bootswatch.scss` -- Post-Bootstrap overrides. All custom component
   styles, mixins, dimension badges, and layout rules go here.
4. `custom.css` -- Compiled output. Built by `npm run build:dswa`.

## Rules

- **Keep Bootstrap Bootstrap.** Override variables in `_variables.scss`,
  override component styles in `_bootswatch.scss`. Never fork Bootstrap.
- **Stay compatible with Bootswatch.** The theme switcher lets users pick
  any Bootswatch theme OR this custom theme. Use standard Bootstrap
  variable names.
- **Dark + light mode.** Base styles are dark mode. Light mode overrides
  go inside `[data-bs-theme="light"] { ... }` blocks in `_bootswatch.scss`.
- **glass() mixin.** `@include glass($bg, $blur)` applies background +
  backdrop-filter. Default: `rgba(0, 0, 0, 0.4)` bg, `16px` blur.
- **Dimension colors.** ElevateHER dimensions use coded colors:
  - Mindset: purple (#b388d9)
  - Skillset: green (#6bc96b)
  - Toolset: blue (#6ca6d9)
  - Hard Walls: gold (#c89a40)
  - Network: green (#4cb748)
  Use `.badge-{dimension}` and `.progress-{dimension}` classes.
- **Sidebar active:** Blue background with gold left-border accent.
