# SourceBans++ 2026 Theme — Smarty Handoff

This package translates the modernized `ui_kits/webpanel-2026/` mockups into
production-ready Smarty templates + CSS for the SourceBans++ codebase.

It is meant to be opened by **Claude Code** (or a human dev) sitting inside a
checked-out `sbpp/sourcebans-pp` working copy. Hand it the **HANDOFF.md** and
the contents of this folder, and it has everything it needs to update the
panel's theme without redesigning a single screen.

## What's in here

| File | Purpose |
|---|---|
| `HANDOFF.md` | The README to give to Claude Code. Maps each modern view to its existing Smarty template. |
| `theme.css` | All design tokens + component classes. Drop-in stylesheet, ~400 lines. |
| `theme.tpl` | Smarty layout partial: header (sidebar + topbar), footer, theme-toggle script. Replaces `header.tpl` / `footer.tpl`. |
| `partials/` | Reusable Smarty partials (status pill, avatar, drawer scaffold, command palette markup). |
| `pages/` | Page-level Smarty templates corresponding to every view in the React mockup. |
| `assets/` | The compiled Tailwind utilities used by the templates, plus theme.js. |
| `MIGRATION.md` | Step-by-step migration order (low risk → high risk). |

## How to use

1. Hand `HANDOFF.md` and the `pages/` + `partials/` folder to Claude Code along
   with a checked-out `sbpp/sourcebans-pp` repo.
2. Tell it: *"Apply the SourceBans++ 2026 theme. Follow MIGRATION.md."*
3. It will replace `web/themes/default/` (or create a new theme directory)
   with these files, wire up the new layout in `index.php`, and migrate one
   page at a time so the legacy panel keeps working until cutover.

The CSS is **framework-agnostic** — it doesn't require Tailwind at runtime.
Tailwind was used to design the tokens; the output is plain CSS with custom
properties + utility-ish classes.
