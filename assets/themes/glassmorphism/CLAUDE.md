# Glassmorphism Theme

Custom Bootstrap 5 theme for the child-app template. Uses transparent
glass-effect panels with backdrop-filter blur to let the canvas background
show through all UI elements.

## Build

From the child-app root:
```
npm run build:css
```

## Architecture

This theme follows the **Bootswatch pattern** and MUST remain compatible
with all Bootswatch themes and the admin's theme switcher:

1. `_variables.scss` — Bootstrap variable overrides (colors, typography,
   spacing). These are applied BEFORE Bootstrap compiles.
2. `custom.scss` — Entry point. Imports variables, then full Bootstrap,
   then bootswatch overrides. Do not add rules here.
3. `_bootswatch.scss` — Post-Bootstrap overrides. All custom component
   styles, mixins, and layout rules go here.
4. `custom.css` — Compiled output (gitignored). Built by `npm run build:css`.

## Rules

- **Keep Bootstrap Bootstrap.** Override variables in `_variables.scss`,
  override component styles in `_bootswatch.scss`. Never fork or patch
  Bootstrap source files.
- **Stay compatible with Bootswatch.** The theme switcher lets users pick
  any Bootswatch theme OR this custom theme. Your overrides must not break
  when users switch away and back. Use the same variable names Bootstrap
  and Bootswatch use.
- **Dark + light mode.** Base styles are dark mode. Light mode overrides
  go inside `[data-bs-theme="light"] { ... }` blocks in `_bootswatch.scss`.
- **No hardcoded colors in HTML.** All color comes from CSS. Template
  markup uses structural classes only (e.g. `active`, `rounded`), never
  `text-white` or `bg-dark` for theming.
- **glass() mixin.** `@include glass($bg, $blur)` applies background +
  backdrop-filter. Use it for all translucent panels.
