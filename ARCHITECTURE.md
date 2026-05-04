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

- **Web panel** (`web/`) — a PHP 8.2 + MariaDB application that admins use
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
├── README.md             User-facing install + quickstart
├── ARCHITECTURE.md       This file — codebase overview
├── AGENTS.md             Conventions for AI agents / contributors
├── CHANGELOG.md          Release notes
├── SECURITY.md           Security disclosure policy
└── LICENSE.md
```

## Web panel (`web/`)

### Stack

- **PHP 8.2** with `pdo`, `pdo_mysql`, `gmp`, `intl`, `mbstring`, `openssl`,
  `sodium`. Composer manages dependencies into `web/includes/vendor/`
  (note the non-default `vendor-dir`, set in `composer.json`).
- **MariaDB 10.11** in dev (MySQL 5.6+ supported in production).
- **Smarty 5** for server-side templates.
- **lcobucci/jwt** for the auth cookie.
- **symfony/mailer** for outbound email.
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
├── upgrade.php           Manual schema-upgrade tool
│
├── api/handlers/         JSON API: one file per topic, _register.php wires them
├── pages/                Page handlers (procedural .php, included by build())
│   └── core/             header / navbar / title / footer chrome
├── includes/             Library code (PSR-4 Sbpp\ at this prefix)
│   ├── Database.php          PDO wrapper + :prefix_ table-name substitution
│   ├── Config.php            DB-backed settings key/value cache
│   ├── Log.php               Audit + error log (writes to sb_log)
│   ├── Api.php / ApiError.php  JSON dispatcher + structured errors
│   ├── CUserManager.php      Current admin + permission checks
│   ├── AdminTabs.php         Render admin sub-tab bar
│   ├── page-builder.php      route() + build() (the page router)
│   ├── system-functions.php  Legacy helpers shared across pages
│   ├── SmartyCustomFunctions.php  {help_icon} / {csrf_field} / {load_template}
│   ├── View/                 Typed Smarty view-model DTOs
│   ├── auth/                 JWT cookie auth + Steam OpenID + login handlers
│   ├── security/             CSRF + Crypto helpers
│   ├── Mail/                 Symfony Mailer wrapper + email templates
│   ├── SteamID/              SteamID parsing / vanity-URL resolution
│   ├── PHPStan/              Custom PHPStan rules (Smarty + SQL prefix)
│   └── vendor/               Composer artifacts (gitignored)
├── scripts/              Browser JS (// @ts-check + JSDoc, no bundler)
│   ├── sb.js                 DOM helpers + sb namespace
│   ├── api.js                sb.api.call() — JSON client
│   ├── sourcebans.js         Page-level logic (legacy bulk)
│   ├── contextMenoo.js       Right-click context menu utility
│   ├── api-contract.js       AUTOGEN: Actions.* + Perms.*
│   ├── globals.d.ts          Ambient TS declarations
│   └── tsconfig.json
├── themes/default/       Smarty templates + CSS + images for the default theme
├── configs/permissions/  web.json + sourcemod.json — bitmask flag definitions
├── tests/                PHPUnit (api/ for handlers, integration/ for flows)
├── bin/                  CLI tools (currently just generate-api-contract.php)
├── install/              Legacy installer wizard (skipped in dev)
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
2. Bails if `config.php` is missing or if the `install/` or `updater/`
   directories are present and the host isn't `localhost`.
3. Loads Composer autoload (`includes/vendor/autoload.php`).
4. Manually requires the auth + security + Database modules (they aren't
   PSR-4 namespaced) and initialises them.
5. Reads `configs/permissions/web.json` + `sourcemod.json` and `define()`s
   each flag as a global PHP constant (`ADMIN_OWNER`, `ADMIN_ADD_BAN`, …).
6. Constructs the global `$theme` (Smarty) with the configured theme dir,
   registers custom functions (`{csrf_field}`, `{help_icon}`, …), and
   assigns `csrf_token` / `csrf_field_name` so every rendered page has
   them available.

After `init.php` returns, callers may rely on these globals:
`$GLOBALS['PDO']` (the `Database` wrapper), `$userbank` (`CUserManager`),
`$theme` (`Smarty`), the permission constants, and `SB_VERSION`/`SB_GITREV`.

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
3. `build(title, page)` includes `pages/core/header.php`,
   `pages/core/navbar.php`, `pages/core/title.php`, then the page file,
   then `pages/core/footer.php`.
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
(`api/handlers/{account,admins,auth,bans,blockit,comms,groups,kickit,mods,protests,servers,submissions,system}.php`).

### Auth (`includes/auth/`)

- `Auth::login(aid, maxlife)` mints a JWT and stores it in the `sbpp_auth`
  cookie (HttpOnly, SameSite=Lax). `Auth::verify()` returns the parsed
  token (or `null`). `Auth::logout()` clears the cookie.
- The token's only meaningful claim is `aid` (admin id). `CUserManager`
  reads the row from `sb_admins` and exposes `is_logged_in()`,
  `is_admin()`, `HasAccess(flags)`, and `GetProperty(name)`.
- Two login back-ends:
  - `NormalAuthHandler` — username + bcrypt password, with attempt
    counter and 10-minute lockout after 5 failures (#1081 hardening).
  - `SteamAuthHandler` — OpenID via `includes/auth/openid.php` (legacy
    LightOpenID).
- `JWT::validate()` rejects expired or tampered tokens. `Auth::gc()`
  garbage-collects `sb_login_tokens` rows older than 30 days.

### Permissions

Two parallel permission systems:

- **Web flags** (`configs/permissions/web.json`) — 32-bit bitmask. Used
  by handler registrations and `CheckAdminAccess()`. Constants get
  defined globally in `init.php` (`ADMIN_OWNER`, `ADMIN_ADD_BAN`, …).
  Mirrored to JS as `Perms.*` in the autogenerated `api-contract.js`.
- **SourceMod flags** (`configs/permissions/sourcemod.json`) — character
  string (e.g. `'mz'`). `CUserManager::HasAccess()` accepts either form;
  `Api::register()` forwards whichever the registration declared.

`ADMIN_OWNER` (1<<24) is the implicit super-user bit; nearly every
registration ORs it in. `ALL_WEB` is the union mask used for `is_admin()`.

### CSRF (`includes/security/CSRF.php`)

- `CSRF::init()` (called from `init.php`) starts the session and lazily
  generates a 256-bit hex token bound to `$_SESSION['csrf_token']`.
- Templates emit the hidden form input with `{csrf_field}`.
- `Api::dispatch()` validates the token from `X-CSRF-Token` (preferred)
  or the JSON body's `csrf_token` field. `sb.api.call()` reads the token
  from `<meta name="csrf-token">` and sets the header automatically.
- Page POST handlers call `CSRF::rejectIfInvalid()` (also invoked
  centrally by `route()`).

### Database (`includes/Database.php`)

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

### Config (`includes/Config.php`)

- Settings live in `sb_settings` as a flat key/value table.
- `Config::init($PDO)` loads them all into a static array on bootstrap.
- `Config::get('config.theme')`, `Config::getBool(...)`, `Config::time(ts)`.
- The cache is process-local — re-read by tests via `Config::init()` after
  truncating tables (see `tests/Fixture.php`).

### Smarty templates + View DTOs (`includes/View/`)

Templates live in `themes/<name>/*.tpl` and are rendered through Smarty
5. The default theme is `themes/default/`. Custom themes ship their own
`theme.conf.php` with `theme_name` / `theme_author` / `theme_version`.

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
  only `page_youraccount.tpl`) override `View::DELIMITERS` so the rule
  parses them correctly.

Pages that render multiple templates build one View per template and
call `Renderer::render` for each.

### Frontend JavaScript (`web/scripts/`)

Vanilla JS, classic `<script>` tags, no bundler. The whole tree carries
`// @ts-check` and is checked by `tsc --noEmit --checkJs` in CI (#1098).

| File                | Role                                                |
| ------------------- | --------------------------------------------------- |
| `sb.js`             | DOM helpers (`sb.$id`, `sb.$qs`), `sb.message`, tabs, accordion, tooltips. Also defines a `wrap()` that mimics the few MooTools methods legacy code expects. |
| `api.js`            | `sb.api.call(action, params)` — POSTs JSON to `/api.php` with `X-CSRF-Token`, returns the typed envelope, follows redirects. `sb.api.callOrAlert()` shows an `sb.message.error()` on failure. |
| `sourcebans.js`     | Page-level logic, ~1.7k lines (legacy bulk).        |
| `contextMenoo.js`   | Right-click context menu (deliberately misspelled). |
| `api-contract.js`   | **Autogenerated.** `Actions.*` + `Perms.*` constants. |
| `globals.d.ts`      | Ambient TS declarations for the `sb` namespace.     |
| `tsconfig.json`     | `target: ES2020`, `strict: true`, `allowJs: true`, `checkJs: true`. |

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

### Logging (`includes/Log.php`)

`Log::add('m', 'Topic', 'Detail')` writes a row to `sb_log` with the
current admin's id, IP, and a severity char (`m` info, `w` warning,
`e` error). The dispatcher's "Hacking Attempt" warnings and the
`set_error_handler` shim in `init.php` both go through here.

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

Reseeded in tests via `web/tests/Fixture.php`, which renders `struc.sql`
+ `data.sql` against a dedicated `sourcebans_test` database before every
test method.

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

- **`docker-compose.yml`** brings up four services: `web` (PHP 8.2 +
  Apache, bind-mounting `./web`), `db` (MariaDB 10.11), `adminer`
  (DB UI), and `mailpit` (catch-all SMTP).
- **`docker/Dockerfile`** layers the PHP extensions, OPcache config, and
  Composer onto `php:8.2-apache`.
- **`docker/php/web-entrypoint.sh`** waits for MariaDB, renders
  `web/config.php` from env vars (only if absent), runs `composer install`
  if `vendor/` is empty, then `exec`s Apache.
- **`docker/php/dev-prepend.php`** rewrites `HTTP_HOST` to `localhost`
  for any loopback request so `init.php`'s install-folder guard accepts
  the forwarded `:8080` port.
- **`docker/db-init/00-render-schema.sh`** runs once on first DB boot:
  substitutes `{prefix}` / `{charset}` in `struc.sql` + `data.sql`,
  loads them, and seeds an `admin` row with bcrypt of `admin`.
- **`sbpp.sh`** is a thin wrapper around `docker compose` plus the
  quality gates and DB tasks. Run `./sbpp.sh -h` for the full menu.

The seeded admin password and the `HTTP_HOST` shim are dev-only and
documented as such in `docker-compose.yml`.

## Quality gates

Four CI jobs run on every PR (`.github/workflows/`):

| Gate          | Workflow              | Local command                | What it covers                                |
| ------------- | --------------------- | ---------------------------- | --------------------------------------------- |
| PHPStan       | `phpstan.yml`         | `./sbpp.sh phpstan`          | Static analysis (level 5) + Smarty rule + phpstan-dba SQL checks. |
| PHPUnit       | `test.yml`            | `./sbpp.sh test`             | Behavioural tests against `sourcebans_test`.  |
| ts-check      | `ts-check.yml`        | `./sbpp.sh ts-check`         | `tsc --checkJs` over `web/scripts/`.          |
| API contract  | `api-contract.yml`    | `./sbpp.sh composer api-contract` | Regenerates `scripts/api-contract.js` and fails on diff. |

PHPStan is at level 5 (bumped from 4 in #1101); raise one step at a
time, never jump 5→7. The baseline at `web/phpstan-baseline.neon`
captures pre-existing violations; only regenerate it when a real fix
removes an entry or when bumping the level.

`phpstan-dba` (#1100) introspects the live MariaDB to type-check raw SQL
strings against the schema. Set `PHPSTAN_DBA_DISABLE=1` to skip it; CI
sets `DBA_REQUIRE=1` so credential drift fails loudly.

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

## Legacy patterns being phased out

When working in older code, you'll see things that are no longer the
recommended pattern. Prefer the current pattern when adding new code,
but don't bulk-rewrite legacy code without justification.

| Old                                        | Current                                                  |
| ------------------------------------------ | -------------------------------------------------------- |
| `xajax` callbacks (`sb-callback.php`)      | JSON API in `api/handlers/*.php`                         |
| ADOdb (`$db->Execute`, `RecordSet`)        | `Database` PDO wrapper                                   |
| MooTools (`$('id').addEvent(...)`)         | `sb.$id` / `sb.api.call` + native `addEventListener`     |
| Ad-hoc `$theme->assign()` chains           | `Sbpp\View\*` DTO + `Renderer::render`                   |
| String literals for action names           | `Actions.PascalName` (from `api-contract.js`)            |
| `install/` flow as a runtime concern       | DB seeded out-of-band; installer left for production users |
| `htmlspecialchars_decode` on JSON params   | Store raw UTF-8; Smarty auto-escape handles display (#1108) |
| `DB_CHARSET = 'utf8'` (3-byte alias)       | `utf8mb4` end-to-end (panel PDO + plugin `SET NAMES`) (#1108)|
