# Migration plan — SourceBans++ 2026 theme

Each step lands independently. After each, the panel still works end-to-end. Run the test suite + smoke-test the listed pages before moving on.

## 0. Bootstrap the theme directory

```
web/themes/sbpp2026/
  templates/   ← copy of handoff/pages/* and handoff/partials/*
  css/         ← theme.css
  js/          ← theme.js
  images/      ← copy from handoff/assets/images/
```

Add the theme to the admin theme picker (`admin/page_settings.php`). Set test users to `sbpp2026`; leave everyone else on `default`.

**Smoke test**: log in as a test admin, hit `/index.php` — login page should render with the new look. Public pages should still be `default`.

## 1. Layout shell (header + footer)

Replace `header.tpl` and `footer.tpl` with `handoff/partials/header.tpl` + `footer.tpl`. This wires:

- Sidebar with admin/public sections (driven by `{$tab}`)
- Topbar with breadcrumbs + theme toggle + ⌘K palette button
- Toast container (reads `{$messages}`)
- Drawer scaffold (empty until a page injects content)

**Smoke test**: every existing page renders inside the new shell. Old page bodies will look unstyled — that's expected, fixed in later steps.

## 2. Login

Replace `login.tpl`. Two-column split: form left, dark stat panel right (collapses on mobile). Steam OpenID button uses existing `auth/steam` endpoint.

## 3. Public ban list (the highest-traffic page)

Replace `bans.tpl` with `handoff/pages/banlist.tpl`. Required behaviour:

- Sticky filter bar with state chips
- Hover row actions (edit / unban) — admin-only, gated by `{if $user.srv_flags|strpos:'e' !== false}`
- Clicking a row opens the drawer (AJAX into `partials/player-drawer.tpl`)
- Mobile <768px: cards instead of table
- Skeleton state while AJAX loads

**Smoke test**: filter chips work, drawer opens, mobile cards render, pagination still works.

## 4. Public dashboard / index

Replace `index.tpl` with `handoff/pages/dashboard.tpl`. Stats are computed server-side in `index.php` — keep the existing query and just rename the assigned vars (see HANDOFF.md). Sparkline is inline SVG; data comes from existing `getBansPerDay()` helper.

## 5. Comms + Servers + Submit + Appeal (form)

Straight visual swaps. Same data shape, new templates from `handoff/pages/`.

## 6. Admin panel — read-only screens first

In order:
1. `admin/index.tpl` (admin home / nav grid)
2. `admin/audit.tpl` (NEW — read-only)
3. `admin/appeals.tpl` (admin queue)
4. `admin/servers.tpl`
5. `admin/myaccount.tpl`

## 7. Admin panel — write-heavy screens

Last because they have the most form-handling risk:
1. `admin/addban.tpl`
2. `admin/bans.tpl` (admin view of bans, with edit/unban inline)
3. `admin/groups.tpl` (master-detail with flag checkboxes — touches permission writes)
4. `admin/settings.tpl` (touches every config write)

After each: verify the corresponding `pages/*.php` controller still receives form data correctly (input `name=` attributes preserved).

## 8. Drawer + command palette JS

The shell's drawer + palette are markup-only after step 1. In step 8, ship `theme.js` which:

- Wires ⌘K / Ctrl+K to open the palette and AJAX-search `?p=banlist&search=&format=json`
- Wires drawer-open links (`<a data-drawer-href="…">`) to fetch + inject HTML
- Wires `.theme-toggle` button to cycle light/dark/system, persisting to `localStorage` AND POSTing to `?p=settings&c=theme` so server-rendered emails respect it.

## 9. Cleanup

- Move `default/` theme to `legacy-default/` (don't delete — some forks lean on its CSS classnames).
- Make `sbpp2026` the default for new installs (`config.php.example`).
- Update screenshots in the project README.

## Rollback plan

Every step is reversible by switching the user's theme back to `default` in `users.theme`. No DB migrations are required for steps 1–7. Step 8 introduces a `theme` column on `sb_settings`; ship the migration alongside.
