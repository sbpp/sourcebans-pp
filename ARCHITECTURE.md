# SourceBans++ — Architecture

A tour of the codebase for new contributors (human or LLM). Pair this with
[`AGENTS.md`](AGENTS.md) (workflow + conventions) and
[`docker/README.md`](docker/README.md) (local dev stack).

> **Maintainers:** this file describes the codebase as it stands. When
> you change the architecture — new subsystem, new request flow, schema
> change, removed legacy pattern — update the relevant section in the
> same PR. See [`AGENTS.md` → "Keep the docs in sync"](AGENTS.md#keep-the-docs-in-sync)
> for the trigger-by-trigger checklist.

## What this project is

SourceBans++ is a Source-engine admin/ban/comms management system. It has
two halves that are deployed separately:

- **Web panel** (`web/`) — a PHP 8.5 + MariaDB application that admins use
  in a browser to manage bans, server admins, groups, etc. It also serves
  the public ban list and a JSON API consumed by its own client-side JS.
- **SourceMod plugins** (`game/addons/sourcemod/`) — `.sp` plugins that
  game servers load to enforce bans, gags, mutes, etc. They talk to the
  same MariaDB the web panel uses.

The web panel is the primary surface area for day-to-day development; the
plugins are stable and updated less often.

## Top-level layout

```
.
├── web/                  PHP web panel (panel + JSON API + tests)
├── game/addons/          SourceMod plugin sources (.sp / configs / translations)
├── docker/               Local dev stack (Dockerfile, db-init, php config)
├── docker-compose.yml    web + db (MariaDB) + adminer + mailpit
├── sbpp.sh               Wrapper for the dev stack and quality gates
├── .github/workflows/    CI gates (phpstan, test, ts-check, api-contract, release)
├── README.md             Landing page — short, links to docs / AGENTS / ARCHITECTURE
├── ARCHITECTURE.md       This file — codebase overview
├── AGENTS.md             Conventions for AI agents / contributors
├── CHANGELOG.md          Release notes
├── docs/                 Starlight docs site (install / upgrade / configure)
└── LICENSE.md
```

## Web panel (`web/`)

### Stack

- **PHP 8.5** with `pdo`, `pdo_mysql`, `gmp`, `intl`, `mbstring`, `openssl`,
  `sodium`. Composer manages dependencies into `web/includes/vendor/`
  (note the non-default `vendor-dir`, set in `composer.json`).
- **MariaDB 10.11** in dev (MySQL 5.6+ supported in production).
- **Smarty 5** for server-side templates.
- **lcobucci/jwt** for the auth cookie.
- **symfony/mailer** for outbound email.
- **league/commonmark** for safely rendering admin-authored Markdown
  (dashboard intro text — see `Sbpp\Markup\IntroRenderer`).
- **xpaw/php-source-query-class** for live server queries.
- **maxmind-db/reader** for IP→country lookups (`web/data/GeoLite2-Country.mmdb`).
- **Vanilla JavaScript** on the client — no framework, no bundler. Files
  carry `// @ts-check` and are type-checked with `tsc --checkJs`.

### Directory layout

```
web/
├── index.php             Page entry point
├── api.php               JSON API entry point
├── init.php              Bootstrap (constants, autoload, DB, Auth, CSRF, Smarty)
├── config.php            DB credentials etc. (generated; ignored by git)
├── config.php.template   Template the installer + dev entrypoint render
├── exportbans.php        Public ban-list export (CSV/XML)
├── getdemo.php           Demo file download
│
├── api/handlers/         JSON API: one file per topic, _register.php wires them
├── pages/                Page handlers (procedural .php, included by build())
│   └── core/             header / navbar / title / footer chrome
├── includes/             Library code (PSR-4 Sbpp\ at this prefix; #1290 phase B)
│   ├── Db/Database.php       Sbpp\Db\Database — PDO wrapper + :prefix_ substitution
│   ├── Config.php            Sbpp\Config — DB-backed settings key/value cache
│   ├── Log.php               Sbpp\Log — audit + error log (writes to sb_log)
│   ├── Api/Api.php           Sbpp\Api\Api — JSON dispatcher
│   ├── Api/ApiError.php      Sbpp\Api\ApiError — structured API error
│   ├── Auth/UserManager.php  Sbpp\Auth\UserManager (was CUserManager) — current admin + perms
│   ├── Auth/Auth.php         Sbpp\Auth\Auth — login flow / cookie issue
│   ├── Auth/JWT.php          Sbpp\Auth\JWT — token encode/decode
│   ├── Auth/Host.php         Sbpp\Auth\Host — hostname helper
│   ├── Auth/Handler/         Sbpp\Auth\Handler\{Normal,Steam}AuthHandler — login handlers
│   ├── Auth/openid.php       LightOpenID — third-party, intentionally global ns
│   ├── Security/CSRF.php     Sbpp\Security\CSRF — token helpers
│   ├── Security/Crypto.php   Sbpp\Security\Crypto — password / token crypto
│   ├── View/AdminTabs.php    Sbpp\View\AdminTabs — Pattern A admin sub-section nav
│   ├── View/                 Sbpp\View\* — typed Smarty view-model DTOs
│   ├── View/Install/         Sbpp\View\Install\* — install-wizard step DTOs (#1332)
│   ├── Markup/               Sbpp\Markup\IntroRenderer — admin Markdown -> safe HTML
│   ├── Servers/              Sbpp\Servers\{SourceQueryCache, RconStatusCache} — per-(ip, port) cache around the xPaw A2S probe (#1311) + per-sid cache around the RCON `status` command (#PLAYER_CTX_MENU)
│   ├── Mail/                 Sbpp\Mail\{Mail,Mailer,EmailType} — Symfony Mailer wrapper + enum
│   ├── Telemetry/            Sbpp\Telemetry\{Telemetry,Schema1} — anonymous opt-out daily ping (#1126); schema-1.lock.json is the vendored cross-repo contract
│   ├── SteamID/              SteamID parsing / vanity-URL resolution
│   ├── PHPStan/              Sbpp\PHPStan\* — custom PHPStan rules (Smarty + SQL prefix)
│   ├── page-builder.php      route() + build() (the page router; procedural)
│   ├── system-functions.php  Legacy helpers shared across pages (procedural)
│   ├── SmartyCustomFunctions.php  {help_icon} / {csrf_field} / {load_template}
│   └── vendor/               Composer artifacts (gitignored)
├── scripts/              Browser JS (// @ts-check + JSDoc, no bundler)
│   ├── sb.js                 DOM helpers + sb namespace
│   ├── api.js                sb.api.call() — JSON client
│   ├── banlist.js            Public ban-list interactions (filters, drawer)
│   ├── api-contract.js       AUTOGEN: Actions.* + Perms.*
│   ├── globals.d.ts          Ambient TS declarations
│   └── tsconfig.json
├── themes/default/       Smarty templates + CSS + images for the default theme
├── configs/permissions/  web.json + sourcemod.json — bitmask flag definitions
├── tests/                PHPUnit (api/ for handlers, integration/ for flows)
├── bin/                  CLI tools (currently just generate-api-contract.php)
├── init-recovery.php     Panel-runtime guard helpers + friendly error pages (#1335 C1 + M1)
├── install/              Install wizard self-hosters run on a fresh setup (#1332)
│   ├── index.php             Entry point — paths-init → already-installed gate (#1335 C2) → vendor/-check (recovery short-circuit) → bootstrap → dispatch
│   ├── init.php              Paths-only bootstrap (NEVER touches vendor/)
│   ├── bootstrap.php         Composer + Smarty bootstrap (loaded only when vendor/ is present)
│   ├── recovery.php          Self-contained "vendor/ missing" surface (pure inline HTML + CSS)
│   ├── already-installed.php Self-contained "panel already installed" guard (#1335 C2 — pure inline HTML + CSS)
│   ├── pages/page.<N>.php    Per-step page handlers (1=license, …, 6=optional AMXBans import)
│   ├── includes/routing.php  Step → page-handler dispatch
│   ├── includes/helpers.php  Shared step-handler helpers (prefix validation, raw-PDO probe, KV escape, PDO error translation)
│   └── includes/sql/         struc.sql + data.sql — the schema source of truth
├── updater/              Per-version migrations existing installs run after upgrade
├── phpstan.neon          PHPStan level 5 + custom rules + dba bootstrap
├── phpstan-baseline.neon Existing violations (regenerate only on real fixes)
├── phpunit.xml           PHPUnit config (tests bootstrap from tests/bootstrap.php)
├── package.json          Dev-only — pulls in typescript for the ts-check gate
└── composer.json         vendor-dir set to includes/vendor
```

### Two entry points

The panel has exactly two PHP entry points reachable from the browser:

| URL                         | Script        | Purpose                           |
| --------------------------- | ------------- | --------------------------------- |
| `index.php?p=…&c=…&o=…`     | `index.php`   | HTML pages (server-rendered)      |
| `api.php` (POST JSON)       | `api.php`     | JSON API (client-side fetch)      |

Both scripts include `init.php` first, which performs identical bootstrap.

### Bootstrap (`init.php`)

`init.php` does the following, in order:

1. Defines path constants (`ROOT`, `INCLUDES_PATH`, `TEMPLATES_PATH`, …)
   and the `IN_SB` sentinel that page files check.
2. Redirects to `/install/` if `config.php` is missing (the
   wizard is the canonical fresh-install path; #1335 M1 replaced
   the bare-text `die()`). If `install/` or `updater/` are still
   on disk after a successful install/upgrade, refuses to boot via
   `web/init-recovery.php`'s `sbpp_check_install_guard()` —
   unconditional in production, with a single explicit
   `SBPP_DEV_KEEP_INSTALL` opt-in for the docker dev stack
   (#1335 C1; the loopback / `HTTP_HOST` exemption was a
   panel-takeover path and is gone).
3. Loads Composer autoload (`includes/vendor/autoload.php`).
4. Manually requires the auth + security + Database modules and
   initialises them. The classes themselves ARE PSR-4 namespaced
   (`Sbpp\Db\Database`, `Sbpp\Auth\UserManager`, `Sbpp\Security\CSRF`, …
   — see `AGENTS.md` "Namespacing"), but the explicit `require_once`
   chain stays in place so each file's `class_alias(\Sbpp\…\X::class,
   'X')` runs eagerly. Without that, procedural call sites that say
   `new Database()` would trigger an autoload lookup for global
   `Database`, find nothing (the autoloader resolves the namespaced
   name, not the alias), and die. The chain becomes optional only
   after the follow-up #1290 PR burns every legacy global-name call
   site.
5. Resolves the panel version via `Sbpp\Version::resolve()` — three-tier
   fallback (release tarball's `configs/version.json` → `git describe`
   → the `'dev'` sentinel) and `define()`s `SB_VERSION` / `SB_GITREV`
   from the result. The chrome's `<footer data-version="…">` hook
   (`web/themes/default/core/footer.tpl`) mirrors `SB_VERSION`
   verbatim so telemetry and E2E specs can distinguish dev installs
   (`data-version="dev"`) from release tarball installs without
   parsing the user-visible string (#1207 CC-5). Dev-checkout panels
   are identified by `SB_VERSION === Version::DEV_SENTINEL`; the
   footer's "| Git: <sha>" suffix gates on `SB_GITREV` directly so a
   separate boolean isn't needed (#1214).
6. Reads `configs/permissions/web.json` + `sourcemod.json` and `define()`s
   each flag as a global PHP constant (`ADMIN_OWNER`, `ADMIN_ADD_BAN`, …).
7. Constructs the global `$theme` (Smarty) with the configured theme dir,
   registers custom functions (`{csrf_field}`, `{help_icon}`, …), and
   assigns `csrf_token` / `csrf_field_name` so every rendered page has
   them available.

After `init.php` returns, callers may rely on these globals:
`$GLOBALS['PDO']` (a `Sbpp\Db\Database` instance, also reachable as the
`Database` alias), `$userbank` (`Sbpp\Auth\UserManager`, also `CUserManager`
via alias), `$theme` (`Smarty`), the permission constants, and
`SB_VERSION`/`SB_GITREV`.

### Page request lifecycle

```
┌──────────────┐    ┌──────────┐    ┌──────────────────┐    ┌───────────────┐
│ index.php    │ -> │ init.php │ -> │ route() / build()│ -> │ pages/*.php   │
└──────────────┘    └──────────┘    └──────────────────┘    └───────────────┘
                                                                    │
                                                                    v
                                                            Smarty .tpl render
```

1. `index.php` includes `init.php`, then `system-functions.php` and
   `page-builder.php`.
2. `route(default_page)` reads `?p=` (page), `?c=` (category), `?o=`
   (option) from the query string and returns `[title, page_php_file]`.
   Admin pages also call `CheckAdminAccess(flags)` before returning.
   An unrecognised admin sub-route (e.g. `?p=admin&c=overrides`,
   `?p=admin&c=bnas`) returns `['Page not found', '/page.404.php']` and
   sets `http_response_code(404)` so the chrome still renders around
   the error message but the HTTP status reflects reality (#1207 ADM-1).
   The bare admin landing (`?p=admin` with no `c=`) still resolves to
   the admin home — only *populated*, unrecognised `c=` values 404.
3. `build(title, page)` includes `pages/core/header.php`,
   `pages/core/navbar.php`, `pages/core/title.php`, then the page file,
   then `pages/core/footer.php`.
   - `pages/core/title.php` runs **before** the page handler, so it
     can't read a `$breadcrumb` the page handler will assign later.
     Instead it builds the default 2-segment "Home > $title" breadcrumb
     itself and dispatches by `?p=…` slug to `Sbpp\View\*View::breadcrumb()`
     for routes whose audience makes the "Home" prefix misleading
     (currently `login` and `lostpassword`, where logged-out visitors
     have no meaningful Home — #1207 AUTH-3). View DTOs that want to
     publish a non-default breadcrumb shape expose a static
     `breadcrumb(): array` returning the same `[ ['title' => ..., 'url' => ...] ]`
     structure `core/title.tpl` consumes.
4. The page file (e.g. `pages/page.home.php`) queries the DB and renders
   either:
   - **Legacy:** ad-hoc `$theme->assign(...)` chains followed by
     `$theme->display('foo.tpl')`.
   - **Preferred:** a `Sbpp\View\*` DTO passed to
     `Sbpp\View\Renderer::render($theme, $view)` (see "View DTOs" below).

POST forms hit `index.php` again with `?p=…`. `route()` calls
`CSRF::rejectIfInvalid()` for any POST before dispatching, so every form
must include `{csrf_field}` in its template.

### JSON API request lifecycle

```
fetch /api.php          ┌─────────────┐    ┌──────────────────┐    ┌───────────────┐
{action, params}   ->   │  api.php    │ -> │ Api::dispatch()  │ -> │ Api::invoke() │
{X-CSRF-Token}          └─────────────┘    └──────────────────┘    └───────┬───────┘
                                                                            v
                                                                  api/handlers/*.php
                                                                            v
                                                                  pure fn(array): array
```

1. `api.php` registers a JSON-emitting exception handler + shutdown
   handler (so even fatal errors return `{ok:false, error:{…}}` with a
   500 status), then includes `init.php`, registers handlers via
   `Api::bootstrap()`, and calls `Api::dispatch()`.
2. `Api::dispatch()` enforces `POST`, parses the JSON body into
   `{action, params}`, validates the CSRF token (header
   `X-CSRF-Token` or `params.csrf_token`), and calls `Api::invoke()`.
3. `Api::invoke()` looks up the registered handler. The dispatcher
   enforces the auth baseline:
   - `public=true` → anyone.
   - `requireAdmin=true` → must be a logged-in admin.
   - `perm != 0` → must hold the bitmask (web flags) or chars (SM flags)
     via `CUserManager::HasAccess()`.
   - Otherwise → must be logged in.
   Permission failures get logged via `Log::add('w', 'Hacking Attempt', …)`.
4. The handler is a pure `function(array $params): array`. It can:
   - Return an array — becomes `{ok:true, data:{…}}`.
   - `throw new ApiError($code, $msg, $field?, $httpStatus?)` — becomes
     a structured `{ok:false, error:{code, message, field?}}` envelope.
   - `return Api::redirect($url)` — becomes `{ok:false, redirect:…}` and
     `sb.api.call()` follows it client-side.

#### Handler registration (`web/api/handlers/_register.php`)

Every action lives in a single registry so the action-to-permission map
is reviewable in one place:

```php
Api::register('bans.add',          'api_bans_add',          ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('account.change_email', 'api_account_change_email');  // logged-in only
Api::register('auth.login',        'api_auth_login',        0, false, true);  // public
Api::register('admins.update_perms', 'api_admins_update_perms', 0, true);  // any admin
```

Handler functions live in topic-grouped files
(`api/handlers/{account,admins,auth,bans,blockit,comms,groups,kickit,mods,notes,protests,servers,submissions,system}.php`).
The `notes` topic was added with #1165 to back the player-detail
drawer's admin-only Notes tab; `bans.player_history` and
`comms.player_history` (live in their existing topic files) feed the
drawer's History and Comms tabs.

### Auth (`includes/Auth/` — `Sbpp\Auth\*`)

- `Sbpp\Auth\Auth::login(aid, maxlife)` mints a JWT and stores it in
  the `sbpp_auth` cookie (HttpOnly, SameSite=Lax). `Auth::verify()`
  returns the parsed token (or `null`). `Auth::logout()` clears the
  cookie.
- The token's only meaningful claim is `aid` (admin id).
  `Sbpp\Auth\UserManager` (legacy `CUserManager` alias) reads the row
  from `sb_admins` and exposes `is_logged_in()`, `is_admin()`,
  `HasAccess(flags)`, and `GetProperty(name)`.
- Two login back-ends:
  - `Sbpp\Auth\Handler\NormalAuthHandler` — username + bcrypt password,
    with attempt counter and 10-minute lockout after 5 failures (#1081
    hardening).
  - `Sbpp\Auth\Handler\SteamAuthHandler` — OpenID via the third-party
    `includes/Auth/openid.php` (LightOpenID, intentionally kept in
    the global namespace).
- `Sbpp\Auth\JWT::validate()` rejects expired or tampered tokens.
  `Auth::gc()` garbage-collects `sb_login_tokens` rows older than 30
  days.

### Permissions

Two parallel permission systems:

- **Web flags** (`configs/permissions/web.json`) — 32-bit bitmask. Used
  by handler registrations and `CheckAdminAccess()`. Constants get
  defined globally in `init.php` (`ADMIN_OWNER`, `ADMIN_ADD_BAN`, …).
  Mirrored to JS as `Perms.*` in the autogenerated `api-contract.js`.
- **SourceMod flags** (`configs/permissions/sourcemod.json`) — character
  string (e.g. `'mz'`). `Sbpp\Auth\UserManager::HasAccess()` accepts
  either form; `Sbpp\Api\Api::register()` forwards whichever the
  registration declared.

`ADMIN_OWNER` (1<<24) is the implicit super-user bit; nearly every
registration ORs it in. `ALL_WEB` is the union mask used for `is_admin()`.

### CSRF (`includes/Security/CSRF.php` — `Sbpp\Security\CSRF`)

- `CSRF::init()` (called from `init.php`) starts the session and lazily
  generates a 256-bit hex token bound to `$_SESSION['csrf_token']`.
- Templates emit the hidden form input with `{csrf_field}`.
- `Api::dispatch()` validates the token from `X-CSRF-Token` (preferred)
  or the JSON body's `csrf_token` field. `sb.api.call()` reads the token
  from `<meta name="csrf-token">` and sets the header automatically.
- Page POST handlers call `CSRF::rejectIfInvalid()` (also invoked
  centrally by `route()`).

### Database (`includes/Db/Database.php` — `Sbpp\Db\Database`)

A thin PDO wrapper. Two things to know:

- All queries write `:prefix_` literals (e.g.
  `SELECT … FROM \`:prefix_bans\``) which `setPrefix()` rewrites to the
  configured prefix (`sb` in dev/CI). Use this — never inline the prefix.
- The wrapper is a "prepare → bind → execute → fetch" chain:

  ```php
  $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = :aid");
  $GLOBALS['PDO']->bind(':aid', $aid);
  $row = $GLOBALS['PDO']->single();   // or ->resultset() / ->execute()
  ```

- The constructor sets `PDO::ATTR_EMULATE_PREPARES => false` (added at
  #1124 / motivated by #1167's `LIMIT '0','30'` MariaDB strict-mode
  regression). Two practical consequences for callers: numeric values
  go through MySQL's binary protocol with proper type metadata
  (`LIMIT ?,?` works as expected), AND every named placeholder
  occurrence is expanded into its own positional `?` slot in the
  prepared statement — so a query that mentions `:sid` twice needs
  TWO `bind(':sid', …)` calls (or distinct names like `:sid` +
  `:sid_inner`), otherwise `execute()` raises `SQLSTATE[HY093]
  Invalid parameter number`. Pre-#1124 emulated prepares masked
  the duplicate-name pattern by client-side string substitution at
  every occurrence. The contract is pinned by
  `web/tests/integration/SrvAdminsPdoParamTest.php` (the regression
  guard for #1314, where `pages/admin.srvadmins.php` reused `:sid`
  twice and bound once — page-load-blocking fatal for every admin
  with `ADMIN_LIST_SERVERS` after upgrading to v2.0). The `:prefix_`
  placeholder is rewritten by `setPrefix()` BEFORE `prepare()`, so
  `:prefix_admins` reuse is harmless and stays out of this rule.

The legacy ADOdb layer was fully removed in commit `b9c812b2`; do not
reintroduce it. PHPStan + `staabm/phpstan-dba` introspect the live
schema (rendered from `install/includes/sql/struc.sql`) and type-check
every raw SQL string at analysis time.

The PDO DSN defaults to `charset=utf8mb4` (MariaDB's 4-byte-safe
alias). `init.php` wires `DB_CHARSET` → the Database constructor →
`mysql:…;charset=utf8mb4`, which issues `SET NAMES utf8mb4` on every
connection. That matches the SourceMod plugin (`sbpp_comms.sp`,
`sbpp_main.sp`) and the `{charset}` placeholder the installer renders
into `struc.sql`. The older `utf8` alias is a 3-byte subset and will
reject supplementary-plane characters (emoji, some CJK), so do not
downgrade the default.

### Config (`includes/Config.php` — `Sbpp\Config`)

- Settings live in `sb_settings` as a flat key/value table.
- `Config::init($PDO)` loads them all into a static array on bootstrap.
- `Config::get('config.theme')`, `Config::getBool(...)`, `Config::time(ts)`.
- The cache is process-local — re-read by tests via `Config::init()` after
  truncating tables (see `tests/Fixture.php`).

### Smarty templates + View DTOs (`includes/View/`)

Templates live in `themes/<name>/*.tpl` and are rendered through Smarty
5. The default theme is `themes/default/` — a ground-up redesign that
shipped at v2.0.0 (#1123): drawer-based navigation, command palette
(Ctrl/Cmd-K), Markdown-rendered admin intro, accessibility-first form
controls, light/dark/system theming. Custom themes ship their own
`theme.conf.php` with `theme_name` / `theme_author` / `theme_version` /
`theme_link` / `theme_screenshot`.

The command palette (`#palette-root` `<dialog>` rendered by
`themes/default/js/theme.js`) is the only search affordance in the
chrome. The topbar carries an icon-only ghost button
(`.topbar__search` in `core/title.tpl`) that opens the same dialog as
the `Meta+k` keybinding — the pre-v2.0.0 inline search input was
dropped at #1207 CC-1 (mobile, slice 1) + CC-3 (desktop, slice 9)
because the labelled "search input + Ctrl K hint" was a duplicate
affordance for the same dialog. Player result rows in the palette
carry `data-drawer-bid` (bare Enter / click hands off to the existing
`[data-drawer-bid]` click delegate, which closes the palette and
opens the player drawer) and `data-steamid` (the `Ctrl/Cmd+Enter`
handler in `theme.js`'s `handlePaletteCopyShortcut` reads it and
copies via `navigator.clipboard.writeText` + `showToast`). Keyboard
glyphs in the row's `.palette__row-hints` group are server-rendered
in non-Mac form (`Enter`, `Ctrl`); `applyPlatformHints` rewrites
`[data-enterkey]` → ⏎ and `[data-modkey]` → ⌘ on Mac/iOS clients at
boot and after every render so glyph swaps don't require re-fetching
results (#1184, #1207 DET-2).

The palette's "Navigate" entries are server-rendered + permission
filtered (#1304). `Sbpp\View\PaletteActions::for($userbank)` builds
the entry catalog the way `web/pages/core/navbar.php` builds the
sidebar: each entry carries a `permission` (int bitmask checked via
`HasAccess` with `ADMIN_OWNER` OR'd in, or `true` for public) and an
optional `config` key (a `sb_settings` boolean — same `config.enable*`
toggles the sidebar already honours). `web/pages/core/footer.php`
encodes the filtered list (with `JSON_HEX_TAG | JSON_HEX_AMP |
JSON_HEX_APOS | JSON_HEX_QUOT` so the content can never break out of
its `<script>` wrapper) and assigns it as `$palette_actions_json`;
`core/footer.tpl` emits it inside `<script type="application/json"
id="palette-actions" data-testid="palette-actions">`. `theme.js`'s
`loadNavItems()` reads + `JSON.parse`s the blob at boot and uses it
in place of the pre-#1304 hardcoded `NAV_ITEMS` array (which leaked
admin links — `Admin panel`, `Add ban` — to logged-out and
partial-permission users). The player-search half (`bans.search`)
stays public; the leak was strictly the navigation entries.

The preferred way to render is via typed view-model DTOs:

```php
use Sbpp\View\HomeDashboardView;
use Sbpp\View\Renderer;

Renderer::render($theme, new HomeDashboardView(
    dashboard_text: (string) Config::get('dash.intro.text'),
    total_bans:     $total_bans,
    // … every other variable the .tpl actually consumes …
));
```

- One `Sbpp\View\*` class per `.tpl`, keyed by its `TEMPLATE` constant.
- All template variables are declared as public readonly constructor
  promoted properties.
- `Renderer::render()` assigns every public property onto Smarty, then
  displays the template.
- `SmartyTemplateRule` (`includes/PHPStan/SmartyTemplateRule.php`)
  scans the `.tpl` for `{$foo}`, `{foreach from=$xs}`, `{include file=…}`,
  etc. references and reports:
  - View properties not referenced by the template (dead).
  - Template variables without a matching property (typos).
  Transitive `{include}`s are resolved on disk; the outer view must
  declare the union of variables both templates use.
- Templates that use the non-default delimiter pair `-{ … }-` (currently
  `page_login.tpl`, `page_blockit.tpl`, `page_kickit.tpl`, and
  `page_admin_servers_rcon.tpl`) override `View::DELIMITERS` so the rule
  parses them correctly. Page handlers swap `setLeftDelimiter` /
  `setRightDelimiter` around `Renderer::render()` so the chrome stays
  on the standard pair. `page_youraccount.tpl` was on this list before
  #1123 B20 rewrote it in standard `{ }` delimiters (rationale on the
  `Sbpp\View\YourAccountView` docblock).
- Permission gates inside templates are declared on the View as `can_*`
  booleans. `Sbpp\View\Perms::for($userbank)` returns the full
  `array<string, bool>` map keyed by snake-case flag name (`can_owner`,
  `can_add_ban`, `can_web_settings`, …); page handlers pluck the keys
  the View declares rather than splatting the whole map.
- Templates can also gate UI inline with `{has_access flags=ADMIN_OWNER|ADMIN_ADD_BAN}…{/has_access}`
  (Phase A3); the block plugin reads the same `CUserManager` the View
  was built with, so server-rendered UI and View permission checks
  always agree.

Pages that render multiple templates build one View per template and
call `Renderer::render` for each. The `Sbpp\View\` namespace currently
covers ~30 templates: home dashboard, ban/comms lists, every admin
sub-tab (bans, comms, admins, groups, mods, servers, overrides,
settings, features, themes, logs), the audit log, the ban submission /
protest forms, the login / your-account forms, the kickit / blockit
side-modals, the updater, and the upload-icon dialog.

#### Theme-fork compatibility predicates (`Sbpp\Theme`)

A handful of page handlers carry legacy DTO fields that the shipped
default theme stopped rendering at v2.0.0 (#1146) but third-party
forks of the pre-v2.0.0 default may still bind to. The fields stay
assignable — `SmartyTemplateRule` insists they keep matching the
template's `{if false}` parity reference — but **computing** them is
work whose only consumer might be a fork. `web/includes/Theme.php`
(`Sbpp\Theme`) is the single home for the predicates page handlers
ask before they pay that cost.

The first user (#1270): `\Sbpp\Theme::wantsLegacyAdminCounts()` gates
the 9-COUNT subquery + the recursive `getDirSize(SB_DEMOS)` walk in
`web/pages/page.admin.php`. The shipped default theme returns `false`
(the work is skipped, placeholder zeros / `'0 B'` flow into
`AdminHomeView`'s legacy fields, the `{if false}` parity reference
emits no visible output). A fork that still renders the legacy
counts row opts back in by adding

```php
define('theme_legacy_admin_counts', true);
```

to its `theme.conf.php` — same file every theme already declares
`theme_name` / `theme_author` / `theme_version` in. `Theme.php` also
exposes a `recordLegacyComputePass()` / `legacyComputeCount()` /
`resetLegacyComputeCount()` triple the regression test
(`web/tests/integration/AdminHomePerformanceTest.php`) reads to
assert default-theme installs really do skip the slow path.

When new compute-paying-for-fork-only-output surfaces show up, add a
sibling predicate to the same class (one method per legacy surface,
named `wants<X>()`) so the gate convention stays single-source.

### Frontend JavaScript (`web/scripts/`)

Vanilla JS, classic `<script>` tags, no bundler. The whole tree carries
`// @ts-check` and is checked by `tsc --noEmit --checkJs` in CI (#1098).

| File                | Role                                                |
| ------------------- | --------------------------------------------------- |
| `sb.js`             | DOM helpers (`sb.$id`, `sb.$qs`, `sb.$idRequired`), `sb.message`, tabs, accordion, tooltips. Also defines a global `$` shim used by inline page-tail scripts (replaces the few MooTools idioms legacy code expects). |
| `api.js`            | `sb.api.call(action, params)` — POSTs JSON to `/api.php` with `X-CSRF-Token`, returns the typed envelope, follows redirects. `sb.api.callOrAlert()` shows an `sb.message.error()` on failure. |
| `banlist.js`        | Layered enhancements for the public ban-list page (status-filter chips, comment-edit form via JSON API). The row's SteamID copy button is wired by `theme.js`'s document-level `[data-copy]` delegate alongside every other copy affordance on the panel. |
| `server-tile-hydrate.js` | Per-tile A2S hydration shared by the public servers list (`page_servers.tpl`), the admin Server Management list (`page_admin_servers_list.tpl`, #1313), and the dashboard Servers widget (`page_dashboard.tpl`, #1375). Auto-runs on first paint for every container marked `data-server-hydrate="auto"`; walks `[data-testid="server-tile"]` children and fires `Actions.ServersHostPlayers` per tile, patching the live cells (status pill / map / players / hostname / players bar / refresh button). Feature-detects every optional element so the same helper covers all three surfaces without per-page branching — the dashboard widget ships only the hostname slot and the helper no-ops the rest. |
| `contextMenoo.js`   | Right-click context menu (deliberately misspelled). |
| `api-contract.js`   | **Autogenerated.** `Actions.*` + `Perms.*` constants. |
| `globals.d.ts`      | Ambient TS declarations for the `sb` namespace.     |
| `tsconfig.json`     | `target: ES2020`, `strict: true`, `allowJs: true`, `checkJs: true`. |

The pre-v2.0.0 bulk file (`sourcebans.js`, ~1.7k lines of MooTools-flavoured
helpers — `ShowBox`, `DoLogin`, `LoadServerHost`, `selectLengthTypeReason`,
…) was dropped at v2.0.0 (#1123 D1). Pages that need a per-form helper
inline a self-contained vanilla version (see `web/pages/admin.edit.ban.php`
and `web/pages/admin.edit.comms.php` for canonical examples); the chrome's
toast surface is now `window.SBPP.showToast` from the theme JS.

Type contracts:

- `SbAnyEl` is intentionally permissive (every form-element member is
  REQUIRED, even on a `<div>`) so legacy code type-checks without a
  per-site cast. New code should prefer
  `document.querySelector<HTMLInputElement>(...)`.
- `sb.$id(id)` returns `SbAnyEl | null` and must be narrowed.
  `sb.$idRequired(id)` throws on missing — use it where a missing element
  is a programmer error.

MooTools, React, and any runtime bundler have all been removed and must
not come back: self-hosters install by unzipping the release tarball.

### API contract (`scripts/api-contract.js`)

The browser used to hand-duplicate every action name and perm constant.
That made silent drift easy. Now:

- `web/bin/generate-api-contract.php` reads `_register.php`,
  `api/handlers/*.php` (for `@param` / `@return` typedefs), and
  `configs/permissions/web.json`, and emits a deterministic, sorted JS
  file.
- The output is committed to git like a lockfile so release tarballs
  ship it; self-hosters never run codegen.
- CI (`.github/workflows/api-contract.yml`) regenerates on a clean
  checkout and fails on `git diff`. Regenerate locally with
  `./sbpp.sh composer api-contract` whenever a handler name, perm mask,
  or `@param`/`@return` changes.

In JS code: always reference actions and perms by symbol —
`sb.api.call(Actions.AdminsRemove, …)` and `Perms.ADMIN_ADD_BAN` —
never raw strings.

### Mail (`includes/Mail/`)

`Sbpp\Mail\Mail::send($to, EmailType::PasswordReset, ['{link}' => …])`
wraps `symfony/mailer`. SMTP creds come from the `smtp.*` keys in
`sb_settings` (`smtp.host` / `smtp.user` / `smtp.pass` / `smtp.port` /
`smtp.verify_peer`); the sender identity comes from
`config.mail.from_email` + `config.mail.from_name` (#1109), with the
legacy `SB_EMAIL` constant in `config.php` as a fallback that emits a
once-per-process deprecation warning to `sb_log`. `Mailer::resolveFrom()`
formats the chosen pair into `"Name" <email>` for Symfony's `Email::from()`.
Email templates live in `themes/<name>/mails/*.html`.

### Markup (`includes/Markup/`)

`Sbpp\Markup\IntroRenderer::renderIntroText($markdown)` wraps
`league/commonmark` for any DB-stored display text that admins type into
the panel and we then render to other users. Currently used only by the
dashboard `dash.intro.text` setting; the convention is "if a panel form
saves rich text into `sb_settings` and a template will render it to
arbitrary visitors, the value goes through `IntroRenderer` first."

The converter is configured with:

- `html_input: 'escape'` — inline HTML is rendered as visible escaped
  text, not parsed. So a `<script>` an admin pastes shows up literally
  in the dashboard, it does not execute. We deliberately don't use
  `'strip'` so admins notice when they pasted HTML by accident.
- `allow_unsafe_links: false` — `javascript:`, `data:`, `vbscript:`
  hrefs are stripped during rendering, so a Markdown link can't be
  turned into an XSS vector either.
- `max_nesting_level: 50` — belt-and-braces against pathological
  inputs blowing the parser stack.

The converter is constructed lazily and cached as a `private static`,
so configuration cost is paid once per request. Call sites pass the
**rendered HTML** (not the raw Markdown) into the View DTO; the
template emits with `nofilter` and a Smarty comment pointing back at
the renderer (see `web/themes/default/page_dashboard.tpl`).

`IntroRenderer` is also reachable from the JSON API as
`system.preview_intro_text` (#1207 SET-1). The settings page uses it to
power the live Markdown preview pane next to the `dash.intro.text`
textarea: the textarea is the source of truth, and on `input` (200ms
debounce) the JS handler POSTs the current value, receives the rendered
HTML back, and patches the preview pane in place. The first paint
comes from PHP via the `AdminSettingsView::$config_dash_text_preview`
field, so the page works without JS too. The preview pane runs the
**same** `IntroRenderer` the public dashboard runs, so what the admin
sees in the preview is what visitors see — never wire up a third-party
JS Markdown renderer in its place; that would diverge from the
safe-on-render contract.

Issue #1113 is the audit that introduced this: `dash.intro.text` used
to render straight DB HTML through `{$dashboard_text nofilter}`,
making any admin with `ADMIN_SETTINGS` a stored-XSS source. The
companion changes:

- A paired updater migration (`web/updater/data/804.php`) replaces
  only the legacy default value (`<center><p>Your new SourceBans
  install</p>…`) with the new Markdown default; admins who customised
  the value keep their text unchanged, but now rendered as escaped
  text — acceptable degradation for a security fix.
- The settings UI swapped the TinyMCE WYSIWYG for a plain `<textarea>`
  with a Markdown cheat-sheet link; the static `web/includes/tinymce/`
  bundle is no longer referenced and its directory is a follow-up
  cleanup.

### Server query cache (`includes/Servers/`)

`Sbpp\Servers\SourceQueryCache::fetch($ip, $port, $ttl=30)` is a thin
on-disk cache around `xPaw\SourceQuery\SourceQuery` — the UDP A2S
probe used by the public servers page (`?p=servers`) and any
third-party theme that still emits the legacy `__sbppLoadServerHost`
helper from `page.servers.php`. Returns either the cached
`{info, players}` payload or `null` when the underlying probe failed;
both states are persisted under `SB_CACHE/srvquery/<sha1(ip:port)>.json`
so an unreachable server costs ONE A2S probe per ~30s window instead
of one per request.

Keyed by `(ip, port)` rather than by `sid` — multiple
`:prefix_servers` rows pointing at the same game server share a slot,
and the cache stays user-agnostic. Per-caller fields (`is_owner`,
`can_ban`, the per-call `trunchostname` truncation) are stamped on
top by the handler after the fetch returns. Atomic writes use the
same tempfile + `rename()` shape as `_api_system_release_save_cache`
in `system.php` so two concurrent FPM workers never read a
half-written entry.

Issue #1311 is the audit that introduced this: every public servers
page hit, plus every per-tile Re-query button click (anonymous-callable
through `servers.host_players` / `servers.host_property` /
`servers.host_players_list` / `servers.players`, `public=true` in
`_register.php`), used to fan out one fresh `xPaw\SourceQuery::Connect`
+ `GetInfo` + `GetPlayers` UDP round-trip per configured server. A
hand-mash of the refresh button or `for i in $(seq 1 100); do curl
?p=servers; done` translated 1:1 to A2S queries leaving the panel
host. The handlers now go through `SourceQueryCache::fetch(...)` so
back-to-back panel hits coalesce at the cache boundary; the matching
client-side debounce on the public servers page (`page_servers.tpl`'s
`loadTile()` flips `tile.__sbppLoading` + the Re-query button's
`disabled` attr while a probe is in flight) closes the UX vector.

`Sbpp\Servers\RconStatusCache::fetch($sid, $ttl=30)` is the sibling
cache for the RCON `status` round-trip — needed because A2S
`GetPlayers` does NOT carry SteamIDs. Same on-disk shape
(`SB_CACHE/srvstatus/<sha1(sid)>.json`, tempfile + `rename` atomic
writes, success and failure both cached for ~30s), keyed by `sid`
instead of `(ip, port)` because the cache value depends on the
server's stored RCON password and only the panel's `:prefix_servers`
row carries it. The cache exists to feed the restored right-click
context menu on player rows (#PLAYER_CTX_MENU); `api_servers_host_players`
attaches the per-player `steamid` field by matching A2S-reported
names against the RCON-reported `status` output, ONLY when the
caller holds `WebPermission::Owner | WebPermission::AddBan` AND has
per-server RCON access via `_api_servers_admin_can_rcon`. The handler
itself is the load-bearing permission gate; the cache stays
user-agnostic.

The cache probes via `rcon($cmd, $sid, silent: true)` rather than the
default audit-logging shape (the third parameter on the global
`rcon()` helper in `web/includes/system-functions.php` defaults to
`false` everywhere else — only the cache opts in). Without the
silent flag every page hit on `?p=servers` from an admin would emit
a "RCON Sent" entry per server, drowning out the legitimate
RCON-from-the-RCON-panel entries the audit log exists to surface.

### Logging (`includes/Log.php`)

`Log::add('m', 'Topic', 'Detail')` writes a row to `sb_log` with the
current admin's id, IP, and a severity char (`m` info, `w` warning,
`e` error). The dispatcher's "Hacking Attempt" warnings and the
`set_error_handler` shim in `init.php` both go through here.

### Telemetry (`includes/Telemetry/`)

`Sbpp\Telemetry\Telemetry::tickIfDue()` is registered as a
`register_shutdown_function` at the tail of `init.php`, so every panel
+ JSON API request runs the tick after the response has been built.
The tick is the issue #1126 contract:

1. **Opt-out gate.** `Config::getBool('telemetry.enabled')` is the
   first read; a `false` returns immediately, so the disabled path
   does no DB work past the cached settings hit `init.php` already
   paid for.
2. **Cooldown check.** `now - last_ping < 24h ± 1h jitter` returns
   early. Jitter prevents panels behind the same NAT from
   synchronising and DDoS-ing the Worker on the hour boundary.
3. **Atomic slot reservation.** A single
   `UPDATE :prefix_settings SET value = :now WHERE setting =
   'telemetry.last_ping' AND CAST(value AS UNSIGNED) <= :threshold`
   either matches one row (we win the race) or zero (someone else
   already claimed this 24h window). `rowCount() === 1` is the gate
   — equivalent technique to `Mailer::resolveFrom()`'s warn-once
   lazy lock. The slot is reserved at the START of the attempt,
   not after success, so a flapping endpoint costs one ping/day,
   not one ping/request.
4. **Response handoff.** `fastcgi_finish_request()` closes the
   user's TCP socket before the network call when the SAPI is FPM;
   non-FPM falls back to `ob_end_flush + flush`.
5. **POST payload.** cURL with 3s connect / 5s total timeouts,
   `User-Agent: SourceBans++/<version> (telemetry)`, no redirects,
   any non-2xx silent. The endpoint comes from
   `:prefix_settings.telemetry.endpoint` so operators can repoint
   to a self-hosted collector or set it to `''` to disable network
   calls without flipping the user-facing toggle.

The whole `tickIfDue` body is wrapped in `try { … } catch (\Throwable)`
so telemetry can NEVER hard-fail the request. Pings are never
audit-logged either — a flapping endpoint would otherwise generate
`sb_log` noise that scares admins. Only the enable/disable
**transitions** are audit-logged (in
`web/pages/admin.settings.php`'s features POST handler).

`Telemetry::collect()` is the side-effect-free payload builder. The
exact wire format is captured in
`web/includes/Telemetry/schema-1.lock.json` — a Draft-7 JSON Schema
vendored byte-for-byte from the [sbpp/cf-analytics](https://github.com/sbpp/cf-analytics)
companion repo. `Sbpp\Telemetry\Schema1::payloadFieldNames()` reads
the lock file at request time and returns the recursively-flattened
leaf field set; this is the single source of truth that gates the
extractor parity test:

- `TelemetrySchemaParityTest` — `Telemetry::collect()`'s output
  field set deep-equals `Schema1::payloadFieldNames()` in BOTH
  directions. Adding a typed slot in cf-analytics → next sync → the
  panel build fails until a matching extractor lands.

The schema lock file is also the single source of truth for anyone
who wants the field-by-field breakdown — it is NOT mirrored into any
human-readable doc, and the previous README mirror + paired
`TelemetryReadmeParityTest` was removed because the duplication paid
for the drift risk it created.

The schema-lock-file vendoring + extractor-parity pair is the
convention for this codebase — when another subsystem grows a
similar cross-repo JSON contract, mirror this shape.

The `instance_id` is `bin2hex(random_bytes(16))`, lazily generated
on the first call to `collect()` and persisted in
`:prefix_settings.telemetry.instance_id`. The Settings UI's Features
section wipes the row to `''` on opt-out (so a re-enable mints a
fresh ID and the Worker can't link the two states) — the panel never
re-uses an `instance_id` once the toggle has flipped to disabled and
back. `panel.theme` is reported as `default` only when the active
theme exists under `web/themes/` (currently only `default` ships);
custom forks report `custom` so a community theme name like
`gridiron-clan-2025` never leaves the panel.

Manual schema sync: `make sync-telemetry-schema` pulls the latest
lock file from
`https://raw.githubusercontent.com/sbpp/cf-analytics/main/schema/1.lock.json`
and overwrites the vendored copy. No scheduled auto-PR workflow in
v1 — the parity tests gate the result and the maintainer invokes
the make target when picking up cf-analytics changes.

## Database schema

The schema source of truth is `web/install/includes/sql/struc.sql` (with
`{prefix}` and `{charset}` placeholders rendered to `sb` and `utf8mb4`
in dev/CI). Major tables:

| Table                       | Purpose                                       |
| --------------------------- | --------------------------------------------- |
| `sb_admins`                 | Web admins + bcrypt password + lockout state. |
| `sb_groups`                 | Web admin groups (permission bitmasks).       |
| `sb_srvgroups`              | SourceMod admin groups (char flags).          |
| `sb_admins_servers_groups`  | Admin × server × group mapping.               |
| `sb_servers` / `sb_servers_groups` | Game servers + server-group membership. |
| `sb_bans`                   | The bans themselves.                          |
| `sb_comms`                  | Mutes / gags / blocks.                        |
| `sb_banlog`                 | Per-server enforcement events (dashboard).    |
| `sb_comments`               | Threaded comments on a ban.                   |
| `sb_demos`                  | Uploaded demo metadata.                       |
| `sb_protests`               | Ban-appeal submissions.                       |
| `sb_submissions`            | Public ban-report submissions.                |
| `sb_overrides` / `sb_srvgroups_overrides` | SM command overrides.            |
| `sb_mods`                   | Configured game mods (icons, names).          |
| `sb_settings`               | Flat key/value config used by `Config`.       |
| `sb_log`                    | Audit log (see `Log.php`).                    |
| `sb_login_tokens`           | JWT id (`jti`) → last-accessed for GC.        |
| `sb_notes`                  | Per-Steam-ID admin scratchpad (Notes tab in the player drawer, #1165). |

Reseeded in tests via `web/tests/Fixture.php`, which renders `struc.sql`
+ `data.sql` against a dedicated `sourcebans_test` database before every
test method.

### Column-typed PHP enums (issue #1290 phase D)

Five `:prefix_*` columns carry a small fixed set of values; PHP wraps
each with a backed enum so call sites read as intent rather than as
magic primitives:

| Enum                | On-disk column                                                  | Backing | Cases                                                              |
| ------------------- | --------------------------------------------------------------- | ------- | ------------------------------------------------------------------ |
| `LogType`           | `:prefix_log.type enum('m','w','e')`                            | string  | `Message='m'` / `Warning='w'` / `Error='e'`                        |
| `LogSearchType`     | the audit-log `?advType=` query param tag (no DB column)        | string  | `Admin` / `Message` / `Date` / `Type` (also carries WHERE-builder) |
| `BanType`           | `:prefix_bans.type tinyint`                                     | int     | `Steam=0` / `Ip=1`                                                 |
| `BanRemoval`        | `:prefix_bans.RemoveType varchar(3)` / `:prefix_comms.RemoveType varchar(3)` | string  | `Deleted='D'` / `Unbanned='U'` / `Expired='E'`                     |
| `WebPermission`     | `:prefix_admins.extraflags int` / `:prefix_groups.flags int` (bitmask) | int     | one case per `web/configs/permissions/web.json` flag (`Owner=16777216`, …) |

The on-disk schema stays as `enum('m','w','e')` / `varchar(3)` /
`int` / `tinyint`. The enum is the PHP-side typed wrapper. At every SQL bind
site, pass `$enum->value` (the column-typed primitive); the case
itself is for in-PHP type-safety only. `phpstan/phpstan-dba` types the
raw SQL against the live MariaDB schema, so a wrong-typed bind (e.g.
binding a `BanType` enum case directly instead of `->value`) fails
the gate.

Files live in the global namespace under `web/includes/`
(`LogType.php`, `LogSearchType.php`, `BanType.php`, `BanRemoval.php`,
`WebPermission.php`); they're loaded by `require_once` in `init.php`
+ `tests/bootstrap.php` ahead of `Log.php` / `CUserManager.php` so
the typed parameters compile.

`WebPermission` adds a `mask(WebPermission ...$flags): int` helper
for assembling multi-flag bitmasks; `HasAccess()` accepts
`WebPermission|int|string` so the modern enum form
(`HasAccess(WebPermission::Owner)`) and the legacy procedural form
(`HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)`) coexist. The `ADMIN_*`
`define`d constants in `init.php` stay as the back-compat surface
for procedural code that pre-dates the enum; the JS-side
`Perms.ADMIN_*` contract in `web/scripts/api-contract.js` is
unchanged.

### Updater (`web/updater/`)

The updater is how *existing* installs catch up to schema or data changes
that fresh installs receive via `install/includes/sql/{struc,data}.sql`.
Operators run it by visiting `web/updater/index.php` after dropping in a
new release.

`Updater.php` reads `web/updater/store.json`, a sorted map of integer
version keys to PHP file names:

```json
{
  "1": "1.php",
  "...": "...",
  "802": "802.php",
  "803": "803.php"
}
```

It selects `config.version` from `sb_settings`, then runs every script
whose key is greater than that value, in order, and bumps `config.version`
to the highest key on success. The keys are loose historical numbers, not
semver — pick the next integer above the current max when adding one.

Each script is a tiny PHP file `require_once`'d inside the `Updater`
instance scope, so `$this->dbs` (the `Database` wrapper) is in scope.
Migrations should:

- Use the `:prefix_` placeholder, never a literal table prefix.
- Be **idempotent**: prefer `INSERT IGNORE`, `CREATE TABLE IF NOT EXISTS`,
  and `ALTER TABLE` paired with a `SHOW COLUMNS` guard. The runner has no
  rollback — partial state must be safe to re-run.
- Mirror the corresponding seed in `web/install/includes/sql/data.sql`
  (or DDL in `struc.sql`). `data.sql` is consulted **only** on a fresh
  install; a row added there without a matching updater script will be
  missing on every upgraded install. The two are halves of the same change.

PHPStan can't see that `$this` is supplied by the loader, so each script
suppresses the false positive with `// @phpstan-ignore variable.undefined`
above each `$this->dbs` call. See `802.php` (new `sb_settings` row) and
`803.php` (the `config.mail.from_*` rows for #1109) for the canonical
shape; `700.php` shows a multi-row insert and `801.php` shows DDL.

## SourceMod plugins (`game/addons/sourcemod/`)

```
game/addons/sourcemod/
├── scripting/
│   ├── sbpp_main.sp        Core ban/admin enforcement (loaded by every server)
│   ├── sbpp_admcfg.sp      Admin auth-config writer (sm_addgroup, sm_addadmin)
│   ├── sbpp_checker.sp     Auto-checker: blocks evading bans/comms
│   ├── sbpp_comms.sp       Mute/gag enforcement
│   ├── sbpp_report.sp      In-game !report → web submission
│   ├── sbpp_sleuth.sp      Alt-account / shared-account detection
│   └── include/            Public natives (`sourcebanspp.inc`)
├── configs/                Plugin configs (cvars, defaults)
├── translations/           SourceMod translation files
└── plugins/                Empty in source — `.smx` lands here when compiled
```

Plugins talk to the same MariaDB the panel uses, write to `sb_bans` /
`sb_comms` directly, and consume `sb_settings` for runtime configuration.
Build with the standard SourceMod compiler — see the
[SourceMod wiki](https://wiki.alliedmods.net/Compiling_SourceMod_Plugins).

## Local development stack

Spelt out fully in [`docker/README.md`](docker/README.md). Quick mental
model:

- **`docker-compose.yml`** brings up four services: `web` (PHP 8.5 +
  Apache, bind-mounting `./web`), `db` (MariaDB 10.11), `adminer`
  (DB UI), and `mailpit` (catch-all SMTP).
- **`docker/Dockerfile`** layers the PHP extensions, OPcache config, and
  Composer onto `php:8.5-apache`.
- **`docker/php/web-entrypoint.sh`** waits for MariaDB, renders
  `web/config.php` from env vars (only if absent), runs `composer install`
  if `vendor/` is empty, then `exec`s Apache.
- **`docker/php/dev-prepend.php`** defines `SBPP_DEV_KEEP_INSTALL`
  on every request so `web/init-recovery.php`'s
  `sbpp_check_install_guard()` skips the install/ + updater/-presence
  refusal — the dev stack ships those directories on the bind mount
  by design (the wizard isn't exercised locally; `docker/db-init/`
  seeds the schema out of band). Pre-#1335 this file rewrote
  `HTTP_HOST` to `localhost` to ride a `init.php` exemption that
  was a panel-takeover path in production; the explicit
  loud-named-define escape hatch replaced it.
- **`docker/db-init/00-render-schema.sh`** runs once on first DB boot:
  substitutes `{prefix}` / `{charset}` in `struc.sql` + `data.sql`,
  loads them, and seeds an `admin` row with bcrypt of `admin`.
- **`sbpp.sh`** is a thin wrapper around `docker compose` plus the
  quality gates and DB tasks. Run `./sbpp.sh -h` for the full menu.

The seeded admin password and the `SBPP_DEV_KEEP_INSTALL` constant are
dev-only and documented as such in `docker-compose.yml`.

## Quality gates

Five CI jobs run on every PR (`.github/workflows/`):

| Gate           | Workflow              | Local command                | What it covers                                |
| -------------- | --------------------- | ---------------------------- | --------------------------------------------- |
| PHPStan        | `phpstan.yml`         | `./sbpp.sh phpstan`          | Static analysis (level 5) + Smarty rule + phpstan-dba SQL checks. |
| PHPUnit        | `test.yml`            | `./sbpp.sh test`             | Behavioural tests against `sourcebans_test`.  |
| ts-check       | `ts-check.yml`        | `./sbpp.sh ts-check`         | `tsc --checkJs` over `web/scripts/`.          |
| API contract   | `api-contract.yml`    | `./sbpp.sh composer api-contract` | Regenerates `scripts/api-contract.js` and fails on diff. |
| Playwright E2E | `e2e.yml`             | `./sbpp.sh e2e`              | Browser-level smoke / flows / a11y against the dev stack (chromium + mobile-chromium). |

PHPStan is at level 5 (bumped from 4 in #1101); raise one step at a
time, never jump 5→7. The baseline at `web/phpstan-baseline.neon`
captures pre-existing violations; only regenerate it when a real fix
removes an entry or when bumping the level.

`phpstan-dba` (#1100) introspects the live MariaDB to type-check raw SQL
strings against the schema. Set `PHPSTAN_DBA_DISABLE=1` to skip it; CI
sets `DBA_REQUIRE=1` so credential drift fails loudly.

`phpstan/phpstan-deprecation-rules` (#1273) is wired in via
`phpstan.neon` with `phpVersion: 80500` so the analyser flags the
PHP 8.1 null-into-scalar deprecation surface (`strlen($null)`,
`trim($null)`, `substr($null, ...)`, `preg_match($null, ...)`, …)
before it bites us on the PHP 9 bump. `web/includes/Auth/openid.php`
is excluded from PHPStan, so the same surface there is gated by the
runtime smoke test in `web/tests/integration/Php82DeprecationsTest.php`
(per-process `set_error_handler` that promotes `E_DEPRECATED` to a
thrown `ErrorException` while it requires each marquee page handler).

## Test architecture

Tests live in `web/tests/`:

```
tests/
├── bootstrap.php            Defines path/env/permission constants without config.php
├── Fixture.php              Drops + re-creates sourcebans_test, seeds admin row
├── ApiTestCase.php          Base class: setUp() truncates DB, $this->loginAs(aid),
│                            $this->assertSnapshot() for wire-format snapshots
├── api/                     Per-handler tests + the per-action permission-matrix lock
│   └── __snapshots__/       Checked-in JSON envelopes asserted byte-for-byte (#1112)
└── integration/             End-to-end flows (LoginFlowTest, BanFlowTest, …)
```

`Fixture::install()` runs once per test process; `Fixture::reset()`
truncates every table and re-seeds defaults between tests so each
`setUp()` starts identical to a fresh `./sbpp.sh up`.

`ApiTestCase::api(action, params)` invokes a handler in-process through
`Api::invoke()` and returns the same envelope the dispatcher would
produce, so auth/permission checks are exercised exactly the way HTTP
requests would exercise them.

`ApiTestCase::assertSnapshot(name, envelope, redact)` (#1112) compares
the envelope against a checked-in JSON file under
`tests/api/__snapshots__/<topic>/<scenario>.json`. The file is the
contract between the panel and any custom theme / external integration:
shape changes have to be intentional and re-recorded with
`UPDATE_SNAPSHOTS=1 ./sbpp.sh test`. Dynamic values (autoincrement IDs,
the seeded admin's aid, RNG-derived passwords) are passed in as a
`redact` list of dot-paths and replaced with the literal `<*>` so the
rest of the shape still locks down.

`web/tests/api/PermissionMatrixTest.php` (#1112) pins every registered
action's `(perm, requireAdmin, public)` triple via PHPUnit dataProvider
rows. A new action without a matrix entry — or an existing action whose
gate moves — fails the build loudly. `Api::actions()` (added alongside)
exposes the registry's keys for the matrix sweep so the test can detect
both directions of drift (extra registrations, removed registrations).

### End-to-end tests (`web/tests/e2e/`)

Browser-level coverage on top of the unit + API gates. Lives under
`web/tests/e2e/` with its own `package.json` so PHPUnit and Playwright
don't fight over a shared dependency surface.

```
web/tests/e2e/
├── package.json              # @playwright/test, @axe-core/playwright, typescript
├── playwright.config.ts      # baseURL, projects (chromium + mobile-chromium), reporters
├── tsconfig.json             # strict, ES2020, bundler resolution
├── fixtures/
│   ├── auth.ts               # single-import surface re-exporting test/expect (extended in later slices)
│   ├── axe.ts                # expectNoCriticalA11y(page, testInfo, …)
│   ├── db.ts                 # resetE2eDb / truncateE2eDb helpers (host-side or in-container)
│   └── global-setup.ts       # one-time: reset sourcebans_e2e + mint admin storageState
├── pages/                    # Page Object Models (BasePage in _base.ts)
├── scripts/
│   ├── reset-e2e-db.php      # bridge to Sbpp\Tests\Fixture pointed at sourcebans_e2e
│   └── upload-screenshots.sh # pushes per-PR PNGs to the screenshots-archive orphan branch
└── specs/
    ├── _screenshots.spec.ts  # @screenshot gallery spec (skipped unless SCREENSHOTS=1)
    ├── smoke/                # one .spec.ts per route — login.spec.ts is the seed
    ├── flows/                # multi-step critical flows (added in later slices)
    ├── a11y/                 # axe scans (added in later slices)
    └── responsive/           # mobile-viewport behaviour (added in later slices)
```

Three properties drive the harness:

- **DB isolation.** The suite owns a dedicated `sourcebans_e2e`
  schema, parallel to `sourcebans_test`. `reset-e2e-db.php` reuses
  the same `Sbpp\Tests\Fixture` PHPUnit uses (struc.sql + data.sql,
  same seeded admin) but pointed at the e2e DB, so a passing PHPUnit
  run guarantees the e2e fixture is structurally identical. Specs
  call `truncateE2eDb()` between cases for the cheap reset path;
  full install only runs once per `playwright test` invocation.
- **Storage-state auth.** `fixtures/global-setup.ts` drives the
  login form once against the seeded admin/admin user and writes
  `playwright/.auth/admin.json`. Every spec inherits that storage
  state via `playwright.config.ts` so they don't pay the login cost
  per-test. The login spec is the only spec that opts back out
  (`test.use({ storageState: { cookies: [], origins: [] } })`) so
  the form itself stays exercised.
- **axe gate at `critical`.** `expectNoCriticalA11y` runs
  `@axe-core/playwright` against the current page, attaches the full
  report to the failing test as `axe`, and asserts zero
  `critical`-impact violations. The threshold is locked here — see
  `AGENTS.md` "Playwright E2E specifics" for why.

A separate `_screenshots.spec.ts` walks every route × theme × project
and emits PNGs into `web/tests/e2e/screenshots/<theme>/<viewport>/`.
The accompanying `upload-screenshots.sh` pushes the gallery to the
`screenshots-archive` orphan branch under `screenshots/pr-<N>/<slug>/`
(unique per PR + slice, so the orphan branch never has merge
conflicts) and prints a markdown table for `gh pr comment`.

## Legacy patterns being phased out

When working in older code, you'll see things that are no longer the
recommended pattern. Prefer the current pattern when adding new code,
but don't bulk-rewrite legacy code without justification.

| Old                                        | Current                                                  |
| ------------------------------------------ | -------------------------------------------------------- |
| `xajax` callbacks (`sb-callback.php`)      | JSON API in `api/handlers/*.php`                         |
| ADOdb (`$db->Execute`, `RecordSet`)        | `Database` PDO wrapper                                   |
| MooTools (`$('id').addEvent(...)`)         | `sb.$id` / `sb.api.call` + native `addEventListener`     |
| `web/scripts/sourcebans.js` (`ShowBox`, `DoLogin`, `LoadServerHost`, …) | Removed at v2.0.0 (#1123 D1); inline self-contained helpers per page; `window.SBPP.showToast` for toasts |
| Ad-hoc `$theme->assign()` chains           | `Sbpp\View\*` DTO + `Renderer::render`                   |
| String literals for action names           | `Actions.PascalName` (from `api-contract.js`)            |
| `install/` flow as a runtime concern       | DB seeded out-of-band; installer left for production users (modernized in #1332 — typed `Sbpp\View\Install\*View` DTOs + Smarty templates, no MooTools / wizard-local sourcebans.js) |
| `web/install/template/*.php` procedural templates + `web/install/scripts/sourcebans.js` (MooTools-dependent `ShowBox`/`$E`/`$()` helpers, broken since #1123 D1) | Removed at #1332. Wizard pages live as `web/install/pages/page.<N>.php` handlers + `web/themes/default/install/page_<step>.tpl` Smarty templates + `Sbpp\View\Install\Install*View` DTOs |
| `htmlspecialchars_decode` on JSON params   | Store raw UTF-8; Smarty auto-escape handles display (#1108) |
| `DB_CHARSET = 'utf8'` (3-byte alias)       | `utf8mb4` end-to-end (panel PDO + plugin `SET NAMES`) (#1108)|
| TinyMCE WYSIWYG for `dash.intro.text`      | Plain `<textarea>` + `Sbpp\Markup\IntroRenderer` (CommonMark, escape unsafe HTML) (#1113) |
| `init.php`'s `HTTP_HOST != 'localhost'` exemption on the install/ + updater/-presence guard | Unconditional guard via `web/init-recovery.php`'s `sbpp_check_install_guard()`; docker dev rides explicit `SBPP_DEV_KEEP_INSTALL` constant (#1335 C1) |
| Bare-text `die()` in `init.php` for missing-config / install-still-present / autoload-missing paths | Self-contained chrome via `web/init-recovery.php`'s `sbpp_render_install_blocked_page()` (mirror of `recovery.php`'s pure-inline-HTML contract) (#1335 M1) |
| `/install/` walkable on a panel where `config.php` already exists | Wizard refuses to start via `web/install/already-installed.php`'s 409 surface (#1335 C2) |
