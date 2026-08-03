# CSS Architecture

All stylesheets in this directory are **plain CSS** (no LESS, SASS, or other
preprocessor). The project currently has no CSS preprocessor configured in
`package.json`.

## Files

- `app-common.css` — Brand tokens, toast styles, accessibility helpers
- `school-theme.css` — Theme variables and base styles
- `dashboards.css` — Dashboard grid and widget styles
- `no-script.css` — Shown via `<noscript>` when JavaScript is disabled
- `roles/` — Per-role theme overrides (`admin-theme.css`, `manager-theme.css`,
  `operator-theme.css`, `viewer-theme.css`)

If a preprocessor (SASS, PostCSS) is added later, these files should become
entry points with `@use` / `@import` for partials.
