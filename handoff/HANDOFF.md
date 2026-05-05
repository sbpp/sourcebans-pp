# SourceBans++ 2026 Theme — Claude Code Handoff

You are picking up a design refresh for SourceBans++. The visual design is **already done** — see the React mockups at `ui_kits/webpanel-2026/`. Your job is to translate it into the existing Smarty + PHP codebase **without redesigning anything**.

## Project context

- **Codebase**: PHP 7.4+ / 8.x, MySQL/MariaDB, Smarty 3 templating, jQuery 3.
- **Repo**: `sbpp/sourcebans-pp`. Theme files live in `web/themes/<name>/templates/*.tpl` with assets at `web/themes/<name>/css/`, `js/`, `images/`.
- **Existing theme**: `default/` — table-heavy, fixed 984px column, chocolate (`#38322c`) chrome, rust (`#b05015`) accent, Verdana 11px body. ~15 years old.
- **New theme**: drop into `web/themes/sbpp2026/` so admins can switch between the two during rollout. Don't delete the old theme.

## Visual spec (single source of truth)

The mockup at `ui_kits/webpanel-2026/index.html` is the spec. When in doubt, *match the mockup*. Tokens:

| Token | Value | Usage |
|---|---|---|
| `--brand-600` | `#ea580c` | Primary buttons, accents |
| `--brand-700` | `#c2410c` | Hover state |
| `--zinc-50` / `--zinc-950` | `#fafafa` / `#09090b` | Page bg (light / dark) |
| `--zinc-200` / `--zinc-800` | `#e4e4e7` / `#27272a` | Borders |
| Body font | `'Inter', system-ui, sans-serif` | All UI |
| Mono font | `'JetBrains Mono', ui-monospace, monospace` | SteamIDs, IPs, ban IDs |
| Radius | `0.375rem` (md), `0.75rem` (xl) | Buttons / cards |
| Ban states | Left border 3px: red-500 (perm), amber-500 (active), zinc-300 (expired), emerald-500 (unbanned) | Ban list rows |

All tokens are codified in `theme.css` as CSS custom properties — light + dark — driven by `<html class="dark">`.

## Page mapping

For each modern view in the React mockup, here's the existing Smarty template to update or replace:

| Modern view (React) | Existing Smarty template | Notes |
|---|---|---|
| `LoginScreen` | `login.tpl` | Replace whole file. Steam OpenID button + credential form. |
| `Sidebar` + `Topbar` (app shell) | `header.tpl` + `footer.tpl` | Used by every page. Replace both. |
| `DashboardView` | `index.tpl` | Stats row + sparkline + recent bans + servers. |
| `BanListView` | `bans.tpl` (public) + `admin/bans.tpl` (admin) | The marquee page. Sticky filter bar + chips + responsive cards. |
| `CommsView` | `comms.tpl` + `admin/comms.tpl` | Mirror of bans, slimmer columns. |
| `SubmitView` | `submit.tpl` | Public ban-submission form. |
| `AppealsView` | `appeal.tpl` (form) + `admin/appeals.tpl` (queue) | Two screens. Form is public, queue is admin. |
| `ServersView` | `servers.tpl` + `admin/servers.tpl` | Card grid replaces old table. |
| `AdminPanelView` | `admin.tpl` | Card grid + admins table. |
| `GroupsView` | `admin/groups.tpl` | Master-detail. Flag checkbox grid. |
| `SettingsView` | `admin/settings.tpl` | Sub-nav + section forms. |
| `AuditLogView` | `admin/audit.tpl` | New page. Add to admin nav. |
| `AccountView` | `admin/myaccount.tpl` | Profile + password + sessions. |
| `AddBanView` | `admin/addban.tpl` | Plain form. |
| `PlayerDrawer` | (new partial) | Right-side drawer. Use `partials/drawer.tpl` + jQuery to load `/index.php?p=banlist&c=details&id={id}` into it. |
| Toasts | (new partial) | Use `partials/toast.tpl`. Hook into existing `$smarty->assign('messages', …)` flash messages. |
| Command palette (⌘K) | (new partial) | Static markup in `theme.tpl`, AJAX search via existing `?p=banlist&search=…` endpoint. |

## Behavior contracts (don't break these)

The existing PHP layer talks to templates through specific Smarty variables. Preserve them:

- `$site_name`, `$site_url`, `$theme` — global, set in `bootstrap.php`
- `$user` — current admin (or null for public views). Has `name`, `srv_group`, `srv_flags`, `webid` (admin row id).
- `$tab` — active top-level nav item; the sidebar reads it to highlight.
- `$messages` — flash messages array of `{type: 'error'|'warn'|'info'|'success', body: '…'}`. Render as toasts.
- `{captcha}`, `{form_token}` — CSRF + captcha helpers, used on Submit / Appeal / Login.
- `$bans`, `$comms`, `$servers`, `$admins`, `$groups`, `$audit`, `$appeals` — page data. Don't rename, just iterate them in the new markup.
- Each ban row: `{$ban.bid}`, `{$ban.name}`, `{$ban.steam}`, `{$ban.ip}`, `{$ban.reason}`, `{$ban.banned}`, `{$ban.length}`, `{$ban.unbanned}`, `{$ban.aname}`, `{$ban.sname}`. State is **derived**: `length=0` → permanent; `unbanned` truthy → unbanned; `banned + length*60 < now` → expired; else active.

## Your job, in order

Follow `MIGRATION.md` from top to bottom. Each step is independently shippable. Do not skip ahead — the layout shell (step 1) must land before any page-level templates can render correctly.

If the mockup and the existing PHP behaviour disagree, **ask before changing PHP**. The visual spec is authoritative for visuals; the PHP behavior is authoritative for data + permissions.

## Things you MUST NOT do

- Don't introduce a JS framework. The codebase is jQuery + Smarty. Keep it that way. The React in the mockup is just a static design tool.
- Don't add a build step the project doesn't already have. Ship plain CSS + plain JS.
- Don't delete the `default/` theme. Both must coexist until the maintainers cut over.
- Don't touch the database schema. Audit log + appeals already have tables (`sb_log`, `sb_protests`).
- Don't change permission flag semantics. Read `includes/auth.php` to confirm flag-letter → action mappings.
- Don't reproduce the React component file structure. Smarty templates have their own composition story (`{include file="…"}`).

## Things to flag back to the user

When done, list anything that needs human review:
- Per-game icon sprites (kit uses placeholder squares).
- Sentry / VPN integrations (UI exists, no backend).
- Audit log table — confirm `sb_log` schema covers all event types in the mockup.
- Mobile cutover risk for any custom fork-specific pages not in the mapping above.
