# SourceBans++ — agent guide

Conventions and workflow for AI agents and human contributors. Read
[`ARCHITECTURE.md`](ARCHITECTURE.md) first if you need a tour of the
codebase; this file is the cheatsheet.

## Stack at a glance

- `web/` — PHP 8.2 panel (Smarty 5, PDO/MariaDB, vanilla JS). Entry:
  `web/index.php` (pages) and `web/api.php` (JSON API).
- `game/addons/sourcemod/` — SourceMod plugin sources (`.sp`).
- `docker/` + `docker-compose.yml` + `sbpp.sh` — local dev stack.
- `web/install/` — legacy installer wizard. **Don't extend it.** Dev
  seeds the DB out of band; the wizard stays for production users.

## Keep the docs in sync

The docs are part of the codebase. Update them in the same PR as the
code change — never as a follow-up. CI doesn't gate this; it's on you.

| When you …                                                  | Update                                                |
| ----------------------------------------------------------- | ----------------------------------------------------- |
| Add/rename/remove a top-level subsystem in `web/includes/`  | `ARCHITECTURE.md` (Web panel → Directory layout, and the relevant subsystem section) |
| Change a request lifecycle (page or JSON API)               | `ARCHITECTURE.md` (the lifecycle section + any diagrams) |
| Add an API handler **topic file** (new file in `api/handlers/`) | `ARCHITECTURE.md` (handler list under "Handler registration") |
| Add or rename a DB table, or change the schema substantively | `ARCHITECTURE.md` (Database schema table) + ensure `install/includes/sql/struc.sql` is the source of truth |
| Add or remove a quality gate / CI workflow                  | `ARCHITECTURE.md` (Quality gates) **and** `AGENTS.md` (Quality gates) |
| Change a `./sbpp.sh` command surface                        | `AGENTS.md` (Dev commands) + `docker/README.md`       |
| Introduce a new convention or pattern (e.g. View DTOs)      | `AGENTS.md` (Conventions) + `ARCHITECTURE.md` if it's an architectural shift |
| Remove a legacy pattern                                     | `AGENTS.md` (Anti-patterns) + `ARCHITECTURE.md` (Legacy patterns being phased out) |
| Change auth, CSRF, or permissions semantics                 | `ARCHITECTURE.md` (the relevant subsystem) + `AGENTS.md` (Conventions) if the rule changes |
| Add a new permission flag                                   | `web/configs/permissions/web.json`, regen API contract; doc only if the **role** of the flag affects conventions |
| Change the local dev stack (Docker, db-init, env vars)      | `docker/README.md` first, link from `ARCHITECTURE.md` if it changes the dev mental model |
| Edit user-facing install/quickstart                         | `README.md`                                           |

Quick rules:

- If you removed a file or renamed a directory, grep both docs for the
  old path and update the references.
- If a rule in this file no longer matches the code, the rule is wrong
  — fix the rule (or delete it) in the same PR as the code change.
- Keep `ARCHITECTURE.md` descriptive ("how it works") and `AGENTS.md`
  prescriptive ("what to do / not do"). When in doubt, the actionable
  one-liner goes here; the explanation goes there.
- The "Where to find what" table at the bottom is the cheap index — add
  a row whenever you create a new file an agent might need to locate.

## Dev commands (`./sbpp.sh`)

Run from the repo root. All commands are idempotent.

```sh
./sbpp.sh up                       # build + start (panel at :8080, admin/admin)
./sbpp.sh down                     # stop, keep volumes
./sbpp.sh reset                    # stop + drop ALL volumes
./sbpp.sh status                   # ps
./sbpp.sh logs [svc]               # tail (svc: web|db|adminer|mailpit)
./sbpp.sh shell [svc]              # bash in svc (default web; svc=db opens mysql)
./sbpp.sh mysql                    # mariadb client on the dev DB
./sbpp.sh composer <cmd>           # composer in the web container
./sbpp.sh exec <cmd...>            # arbitrary command in the web container

# Quality gates (mirror CI exactly)
./sbpp.sh phpstan [args]           # static analysis (level 5 + dba)
./sbpp.sh test [args]              # PHPUnit on sourcebans_test DB
./sbpp.sh ts-check                 # tsc --checkJs over web/scripts
./sbpp.sh composer api-contract    # regen scripts/api-contract.js

# DB
./sbpp.sh db-dump [file]           # mysqldump to host
./sbpp.sh db-load <file>           # pipe a dump into the dev DB
./sbpp.sh db-reset                 # drop the DB volume + re-seed
./sbpp.sh rebuild                  # --no-cache rebuild of the web image
```

URLs after `up`: panel `http://localhost:8080` (admin/admin), Adminer
`:8081`, Mailpit `:8025`, MariaDB exposed on host `:3307`.

The web container bind-mounts `./web`, so PHP edits land on the next
request — no restart. Restart only when:

- `composer.json` changed → `./sbpp.sh composer install`
- anything in `docker/` changed → `./sbpp.sh rebuild`

## Quality gates

CI runs four gates on every PR. Match them locally before opening one.

| Gate          | Local                                | CI workflow            |
| ------------- | ------------------------------------ | ---------------------- |
| PHPStan       | `./sbpp.sh phpstan`                  | `phpstan.yml`          |
| PHPUnit       | `./sbpp.sh test`                     | `test.yml`             |
| ts-check      | `./sbpp.sh ts-check`                 | `ts-check.yml`         |
| API contract  | `./sbpp.sh composer api-contract`    | `api-contract.yml`     |

PHPStan specifics:

- Level 5 (bumped from 4 in #1101). Raise one step at a time; never
  jump 5 → 7 in one PR.
- Baseline at `web/phpstan-baseline.neon`. Regenerate **only** when a
  real fix removes an entry or when bumping the level:

  ```sh
  ./sbpp.sh phpstan --generate-baseline=phpstan-baseline.neon
  ```

- `staabm/phpstan-dba` introspects the live MariaDB to type-check raw
  SQL. Set `PHPSTAN_DBA_DISABLE=1` to skip when working offline; CI
  sets `DBA_REQUIRE=1` so credential drift fails loudly.

PHPUnit specifics:

- Tests run against a dedicated `sourcebans_test` DB so they never
  stomp dev data.

  ```sh
  ./sbpp.sh test --filter=SomeTest
  ./sbpp.sh test tests/api/AccountTest.php
  ```

ts-check specifics:

- Runs `tsc --noEmit --checkJs` against the `.js` files in place using
  `// @ts-check` + JSDoc. No `.ts` sources, no bundler. `npm install`
  inside the container is lazy on first run; `web/node_modules/` never
  ships.

API-contract specifics:

- `web/scripts/api-contract.js` is a checked-in source file (like a
  lockfile), not a build artifact. Release tarballs ship it; self-hosters
  need no codegen step.
- CI fails on `git diff` after regeneration. Always commit the
  regenerated file in the same PR as the PHP change.

## Conventions

### Database

- Access goes through `web/includes/Database.php` (PDO).
- Tables use `:prefix_` literals (`SELECT … FROM \`:prefix_admins\``);
  `Database::query()` rewrites the placeholder. Never inline the prefix.
- Pattern: `query` → `bind` → `execute` / `single` / `resultset`.
- ADOdb was fully removed (commit `b9c812b2`). **Do not reintroduce it.**

### JSON API

- Endpoints live in `web/api/handlers/<topic>.php`, registered in
  `web/api/handlers/_register.php`.
- Each handler is a pure `function(array $params): array`.
- To surface a structured error, `throw new ApiError($code, $msg, $field?, $http?)`.
- To navigate, `return Api::redirect($url)`.
- The dispatcher enforces auth: any non-public action requires a
  logged-in user; declare additional checks via the `$perm` /
  `$requireAdmin` args of `Api::register()`.
- After editing a handler's name, perm mask, or `@param`/`@return`
  docblock, regenerate the contract:

  ```sh
  ./sbpp.sh composer api-contract
  ```

- **Do not** add new functions to `sb-callback.php` (removed) or reach
  for xajax (removed).

### CSRF

- Required on every state-changing form/JSON call.
- In templates: `{csrf_field}`. In page POST handlers:
  `CSRF::validate(...)` or `CSRF::rejectIfInvalid()` (the dispatcher
  also accepts the token via the `X-CSRF-Token` header — `sb.api.call`
  sets it automatically).

### Permissions

- Web flags live in `web/configs/permissions/web.json`; `init.php`
  defines each as a global PHP constant (`ADMIN_OWNER`, `ADMIN_ADD_BAN`, …).
- SourceMod char flags live in `web/configs/permissions/sourcemod.json`.
- `CUserManager::HasAccess(flags)` accepts either form;
  `Api::register()` forwards whichever was registered.
- In JS, reference perms as `Perms.ADMIN_*` from the autogenerated
  contract — never raw integers.

### Frontend (`web/scripts/`)

- Vanilla JS only — `// @ts-check` + JSDoc on every file.
- DOM helpers and the `sb` namespace live in `sb.js`. Use
  `sb.$idRequired(id)` when a missing element is a programmer error;
  `sb.$id(id)` returns `HTMLElement | null` and must be narrowed.
- For new code, prefer typed selectors
  (`document.querySelector<HTMLInputElement>(...)`) over `SbAnyEl`.
  `SbAnyEl` is intentionally permissive for legacy form-element access.
- API calls go through `sb.api.call(Actions.PascalName, params)` — never
  string literals.
- **Do not** reintroduce MooTools, React, or a runtime bundler.
  Self-hosters install by unzipping the release tarball.

### Templates + View DTOs

- Pages are rendered via typed view-model DTOs in `Sbpp\View\*`
  (`web/includes/View/`), not ad-hoc `$theme->assign(...)` chains.

  ```php
  use Sbpp\View\HomeDashboardView;
  use Sbpp\View\Renderer;

  Renderer::render($theme, new HomeDashboardView(
      dashboard_text: (string) Config::get('dash.intro.text'),
      // … every variable the template consumes …
  ));
  ```

- One View per `.tpl`, keyed by its `TEMPLATE` constant. Public readonly
  properties match the template's variables.
- The `SmartyTemplateRule` PHPStan rule (`web/includes/PHPStan/SmartyTemplateRule.php`)
  cross-checks each concrete view's properties against the template
  tree. Include-expanded templates (e.g. `page_dashboard.tpl` pulls in
  `page_servers.tpl`) need the outer view to declare the union of both
  templates' variables.
- Pages that render multiple templates build one View per template and
  call `Renderer::render` for each.
- Templates with non-default delimiters (currently only
  `page_youraccount.tpl` using `-{ … }-`) override `View::DELIMITERS`.

## Anti-patterns (do NOT reintroduce)

- `xajax` / `sb-callback.php` → use the JSON API.
- ADOdb → use `Database` (PDO).
- MooTools / React / a runtime bundler → vanilla JS in `web/scripts/`.
- New `install/` flow → DB is seeded out-of-band in dev.
- String literals for action names → `Actions.PascalName`.
- Inlining the table prefix → use `:prefix_` and let `Database` rewrite.

## Where to find what

| Need to …                              | Look at                                                  |
| -------------------------------------- | -------------------------------------------------------- |
| Understand request lifecycle           | `ARCHITECTURE.md` ("Page request lifecycle" / "JSON API request lifecycle") |
| Add a JSON action                      | `web/api/handlers/_register.php` + `web/api/handlers/<topic>.php` |
| Add or rename a permission             | `web/configs/permissions/web.json`, then regen contract  |
| Render a page                          | `web/pages/<page>.php` + `web/includes/View/*View.php`   |
| Edit a template                        | `web/themes/default/*.tpl`                               |
| Bootstrap (paths, autoload, theme)     | `web/init.php`                                           |
| Routing (`?p=…&c=…&o=…`)               | `web/includes/page-builder.php`                          |
| Auth / JWT cookie                      | `web/includes/auth/`                                     |
| CSRF                                   | `web/includes/security/CSRF.php`                         |
| Schema                                 | `web/install/includes/sql/struc.sql`                     |
| Test fixtures                          | `web/tests/Fixture.php`, `web/tests/ApiTestCase.php`     |
| Local dev stack details                | `docker/README.md`                                       |
