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
| Add or rename a DB table, or change the schema substantively | `ARCHITECTURE.md` (Database schema table) + ensure `install/includes/sql/struc.sql` is the source of truth + paired `web/updater/data/<N>.php` registered in `store.json` |
| Add or change a row in `install/includes/sql/data.sql` (e.g. new `sb_settings` key) | Paired migration in `web/updater/data/<N>.php` + register in `web/updater/store.json` (see "Updater migrations") |
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
./sbpp.sh e2e [args]               # Playwright E2E suite (lazy npm install + chromium browser)

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

## Parallel stacks (subagents / multiple worktrees)

`docker-compose.yml` ships hardcoded `container_name`s (`sbpp-web`,
`sbpp-db`, `sbpp-adminer`, `sbpp-mailpit`) and lets `docker compose`
derive the project name from the cwd basename. Every worktree of this
repo has the same basename (`sourcebans-pp`), so two `./sbpp.sh up`
invocations from different worktrees collide on **container names** (Docker
rejects the second one), **host ports** (default `8080` / `8081` / `8025`
/ `1025` / `3307`), **and the project's named volumes** (`dbdata`,
`vendor`, `cache`, `smarty`) — they'd silently share/corrupt each
other's DB state.

If you're a subagent (or a human) running in a worktree alongside another
stack, drop a worktree-local `docker-compose.override.yml` that scopes
the project name, container names, and host ports to this worktree.
`docker compose` auto-loads it on top of `docker-compose.yml` and the
file is gitignored so it never sneaks into a PR:

```yaml
# docker-compose.override.yml — parallel-stack scaffolding for this worktree.
name: sbpp-task-1109                # unique project name → unique volumes/network

services:
  web:
    container_name: sbpp-1109-web
    ports: !override
      - "${SBPP_WEB_PORT:-8189}:80"
  db:
    container_name: sbpp-1109-db
    ports: !override
      - "${SBPP_DB_PORT:-3416}:3306"
  adminer:
    container_name: sbpp-1109-adminer
    ports: !override
      - "${SBPP_ADMINER_PORT:-9189}:8080"
  mailpit:
    container_name: sbpp-1109-mailpit
    ports: !override
      - "${SBPP_MAILPIT_UI_PORT:-10189}:8025"
      - "${SBPP_MAILPIT_SMTP_PORT:-1134}:1025"
```

- The suffix (`1109` here) and host-port offsets are arbitrary — pick
  a free range tied to the issue/PR/task you're working on so two
  parallel agents don't collide on each other's overrides either.
- `./sbpp.sh up` / `phpstan` / `test` / `mysql` continue to work
  unchanged; `sbpp.sh` shells out to `docker compose` which composes
  both YAMLs.
- **Tear down before deleting the worktree.** `./sbpp.sh down` (or
  `reset`) removes the named containers/volumes; otherwise they leak
  and you'll discover orphan `sbpp-task-*_dbdata` volumes weeks later.

## Quality gates

CI runs five gates on every PR. Match them locally before opening one.

| Gate           | Local                                | CI workflow            |
| -------------- | ------------------------------------ | ---------------------- |
| PHPStan        | `./sbpp.sh phpstan`                  | `phpstan.yml`          |
| PHPUnit        | `./sbpp.sh test`                     | `test.yml`             |
| ts-check       | `./sbpp.sh ts-check`                 | `ts-check.yml`         |
| API contract   | `./sbpp.sh composer api-contract`    | `api-contract.yml`     |
| Playwright E2E | `./sbpp.sh e2e`                      | `e2e.yml`              |

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

- API contract snapshots live under `web/tests/api/__snapshots__/`. CI
  asserts byte-for-byte. When you intentionally change a handler's wire
  format, regenerate them in the same PR:

  ```sh
  UPDATE_SNAPSHOTS=1 ./sbpp.sh test
  ```

- The action-to-permission matrix is locked in
  `web/tests/api/PermissionMatrixTest.php`. **Adding or renaming an
  action in `_register.php` requires a matching row there** — the
  cross-check fails the build otherwise. Use `Api::actions()` if you
  ever need to enumerate the live registry from another test.

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

Playwright E2E specifics:

- Suite location: `web/tests/e2e/` with its own `package.json`
  (separate from PHPUnit; PHPUnit owns `web/composer.json` /
  `web/package.json`).
- DB: `sourcebans_e2e` (parallel to `sourcebans_test`). Reset between
  specs via `truncateE2eDb()` (truncate + re-seed); full
  install only on global setup. The shim
  `web/tests/e2e/scripts/reset-e2e-db.php` reuses
  `Sbpp\Tests\Fixture` so the fixture stays single-source.
- Auth: storage state minted once per run by
  `fixtures/global-setup.ts` against the seeded `admin/admin` user.
  The login spec is the **one** exception that drives the form
  itself — every other spec inherits the storage state.
- Selectors must use #1123's testability hooks (`data-testid`,
  `[data-active]`, `[data-loading]`, `[data-skeleton]`, ARIA roles,
  `<html class="dark">` for resolved theme). Never CSS class chains;
  never visible text as the *primary* selector. `hasText` filters
  are fine to disambiguate when the primary selector matches more
  than one node (e.g. multiple toasts).
- axe (`@axe-core/playwright`) threshold is **critical**. Use
  `expectNoCriticalA11y(page, testInfo)` from `fixtures/axe.ts`;
  do NOT downgrade the threshold to make tests green — file a
  follow-up against the underlying #1123 testability patterns.
- `prefers-reduced-motion: reduce` is set globally via
  `playwright.config.ts`. Animations should never gate visibility;
  if a test needs a `setTimeout`, the chrome's missing a terminal
  attribute (see `_base.ts`).
- `./sbpp.sh e2e --grep @screenshot SCREENSHOTS=1` produces the
  per-PR screenshot gallery. The
  `web/tests/e2e/scripts/upload-screenshots.sh` wrapper pushes the
  PNGs to the `screenshots-archive` orphan branch under a per-PR
  subdirectory and prints the markdown table to comment on the PR.

## Conventions

### Database

- Access goes through `web/includes/Database.php` (PDO).
- Tables use `:prefix_` literals (`SELECT … FROM \`:prefix_admins\``);
  `Database::query()` rewrites the placeholder. Never inline the prefix.
- Pattern: `query` → `bind` → `execute` / `single` / `resultset`.
- ADOdb was fully removed (commit `b9c812b2`). **Do not reintroduce it.**

### Updater migrations

Every change to `web/install/includes/sql/data.sql` (new `sb_settings` row,
new seed) **and** every schema change in `struc.sql` needs a paired
migration in `web/updater/data/<N>.php`, registered in
`web/updater/store.json`. `data.sql` is consulted **only on fresh installs**;
the updater scripts are how existing panels catch up. Adding a row to
`data.sql` alone silently breaks every upgraded install — the two halves
of the diff ship together or not at all.

- Pick `<N>` as the next integer above the current max key in
  `store.json`. Numbers are historical, not semantic.
- Keep migrations **idempotent**: `INSERT IGNORE`, `CREATE TABLE IF NOT EXISTS`,
  `ALTER TABLE … ADD COLUMN` guarded by an existence check. The runner
  has no rollback — re-running must be a no-op.
- Use the `:prefix_` placeholder. Never inline the prefix.
- Defaults in the migration must match the defaults in `data.sql` so
  fresh and upgraded installs converge to the same state.
- The script is `require_once`'d inside the `Updater` instance scope, so
  `$this->dbs` is in scope; PHPStan can't see this, so prefix each
  `$this->dbs` call with `// @phpstan-ignore variable.undefined`. See
  `web/updater/data/802.php` and `803.php` for the canonical shape.

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

### `nofilter` discipline

Smarty auto-escape is on globally (`$theme->setEscapeHtml(true)` in
`init.php`). `{$foo nofilter}` is the escape hatch. Every use is a load
bearing assertion that the value is already safe HTML, so:

- Each `{$foo nofilter}` (or `{$foo|nofilter}`) needs a Smarty comment
  immediately above it explaining **why** the value is safe to drop in
  raw. One-line format:

  ```smarty
  {* nofilter: <one-line reason — what built it, why no user input flows in unescaped> *}
  {$foo nofilter}
  ```

  A foreach block emitting many sibling `nofilter` items can share one
  annotation if the comment explicitly covers the block (e.g. `each
  *_link below is CreateLinkR-built …`).
- If you can't write a one-liner that's true, the value isn't safe —
  fix the upstream PHP (escape on store, or rebuild without `nofilter`)
  rather than papering over it. Admin-controlled display text that's
  meant to be rich rendering goes through `Sbpp\Markup\IntroRenderer`
  (CommonMark, `html_input: 'escape'`, `allow_unsafe_links: false`); see
  the `IntroRenderer` row in "Where to find what".

### Admin-authored display text (`Sbpp\Markup\IntroRenderer`)

- Anything an admin types in the panel that we render to other users
  (currently the dashboard `dash.intro.text` setting) goes through
  `Sbpp\Markup\IntroRenderer::renderIntroText()` before it reaches
  Smarty. The renderer wraps `league/commonmark` with
  `html_input: 'escape'` and `allow_unsafe_links: false`, so:
  - Inline HTML is rendered as visible escaped text, not parsed.
  - `javascript:` / `data:` / `vbscript:` URLs are stripped during
    rendering.
- Page handlers pass the **rendered HTML** into the View DTO and the
  template emits it with `nofilter`, with the canonical safety comment
  above the line (see `web/themes/default/page_dashboard.tpl` for the
  reference shape).
- Settings UIs surface a Markdown cheat-sheet link in the help icon.
  **Do not** reintroduce a WYSIWYG editor for these fields; the
  WYSIWYG was the source of #1113's stored-XSS vector.

## Anti-patterns (do NOT reintroduce)

- `xajax` / `sb-callback.php` → use the JSON API.
- ADOdb → use `Database` (PDO).
- MooTools / React / a runtime bundler → vanilla JS in `web/scripts/`.
- `web/scripts/sourcebans.js` (the v1.x ~1.7k-line bulk file shipping
  `ShowBox`, `DoLogin`, `LoadServerHost`, `selectLengthTypeReason`, …)
  → removed at v2.0.0 (#1123 D1). Page-tail helpers are inlined as
  self-contained vanilla JS per page (see `web/pages/admin.edit.ban.php`
  / `admin.edit.comms.php` for canonical examples); toasts go through
  `window.SBPP.showToast` from the theme JS.
- New `install/` flow → DB is seeded out-of-band in dev.
- String literals for action names → `Actions.PascalName`.
- Inlining the table prefix → use `:prefix_` and let `Database` rewrite.
- `htmlspecialchars_decode` / `html_entity_decode` on JSON-API params
  (nickname, reason, chat message, …) → the JSON body is raw UTF-8. The
  xajax callbacks used to HTML-encode payloads in transit; the JSON API
  does not, and re-decoding now silently collapses literal `&amp;` and
  double-escapes on re-render (#1108). Store raw, escape on display.
- `utf8` (3-byte alias) for `DB_CHARSET` → always `utf8mb4`. 4-byte
  sequences (emoji, some CJK) otherwise trip `Incorrect string value`
  from the plugin's insert path (#1108, #765).
- Editing `install/includes/sql/data.sql` (or `struc.sql`) without a paired
  `web/updater/data/<N>.php` → upgraded installs silently miss the change.
- WYSIWYG / "rich HTML" editors (TinyMCE, CKEditor, …) for fields stored
  in `sb_settings` and rendered to other users → these fields end up
  emitted through `nofilter` and become a stored-XSS vector for every
  admin with the relevant flag (#1113). Use a plain `<textarea>` and
  pipe the value through `Sbpp\Markup\IntroRenderer` (Markdown).
- Unannotated `{$foo nofilter}` → every `nofilter` is an assertion the
  value is safe HTML; without a `{* nofilter: <why> *}` comment above
  it, future readers can't tell whether it's a real escape hatch or a
  copy-paste accident waiting to be exploited (#1113 audit).
- `setTimeout` / `waitForTimeout` waits in E2E specs → wait on
  terminal attributes (`[data-loading="false"]` settled, `[data-skeleton]`
  removed) per #1123's "Testability hooks" rule.
- CSS class chains or visible-text *primary* selectors in E2E specs
  → use `data-testid` / ARIA roles per #1123. `hasText` filters for
  disambiguation are fine; "find element by its label text" as the
  whole selector is not.

## Where to find what

| Need to …                              | Look at                                                  |
| -------------------------------------- | -------------------------------------------------------- |
| Understand request lifecycle           | `ARCHITECTURE.md` ("Page request lifecycle" / "JSON API request lifecycle") |
| Add a JSON action                      | `web/api/handlers/_register.php` + `web/api/handlers/<topic>.php` |
| Add or rename a permission             | `web/configs/permissions/web.json`, then regen contract  |
| Render a page                          | `web/pages/<page>.php` + `web/includes/View/*View.php`   |
| Edit a template                        | `web/themes/default/*.tpl`                               |
| Edit the player-detail drawer (open trigger, tabs, panes, lazy loaders) | `web/themes/default/js/theme.js` (`renderDrawerBody` / `loadPaneIfNeeded`) |
| Add admin-only per-player notes | `web/api/handlers/notes.php` (CRUD) — Notes tab is gated by `bans.detail`'s `notes_visible` flag |
| Render admin-authored Markdown to safe HTML | `web/includes/Markup/IntroRenderer.php` (`Sbpp\Markup`) |
| Bootstrap (paths, autoload, theme)     | `web/init.php`                                           |
| Routing (`?p=…&c=…&o=…`)               | `web/includes/page-builder.php`                          |
| Auth / JWT cookie                      | `web/includes/auth/`                                     |
| CSRF                                   | `web/includes/security/CSRF.php`                         |
| Schema                                 | `web/install/includes/sql/struc.sql`                     |
| Seed `sb_settings` rows for fresh installs | `web/install/includes/sql/data.sql`                  |
| Add a one-off DB upgrade for existing installs | `web/updater/data/<N>.php` + `web/updater/store.json` |
| Test fixtures                          | `web/tests/Fixture.php`, `web/tests/ApiTestCase.php`     |
| API wire-format snapshots              | `web/tests/api/__snapshots__/<topic>/<scenario>.json`    |
| Action -> permission lock              | `web/tests/api/PermissionMatrixTest.php`                 |
| Add an E2E spec                        | `web/tests/e2e/specs/<smoke|flows|a11y|responsive>/...` + `web/tests/e2e/pages/...` |
| Add a route to the screenshot gallery  | `web/tests/e2e/specs/_screenshots.spec.ts` (`ROUTES` array) |
| Run a stack in parallel with another worktree | Worktree-local `docker-compose.override.yml` (see "Parallel stacks") |
| Local dev stack details                | `docker/README.md`                                       |
