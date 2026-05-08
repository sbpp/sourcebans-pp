# SourceBans++ — agent guide

Conventions and workflow for AI agents and human contributors. Read
[`ARCHITECTURE.md`](ARCHITECTURE.md) first if you need a tour of the
codebase; this file is the cheatsheet.

## Stack at a glance

- `web/` — PHP 8.5 panel (Smarty 5, PDO/MariaDB, vanilla JS). Entry:
  `web/index.php` (pages) and `web/api.php` (JSON API).
- `game/addons/sourcemod/` — SourceMod plugin sources (`.sp`).
- `docker/` + `docker-compose.yml` + `sbpp.sh` — local dev stack.
- `web/install/` — installer wizard self-hosters run on every fresh
  install (the dev stack seeds the DB out of band via `docker/db-init/`,
  so the wizard isn't exercised locally). Live code; modernize and
  extend like anything else under `web/`.
- `web/updater/` — upgrade runner self-hosters hit on every panel
  upgrade. Wrapper code (`Updater.php`, `index.php`, `store.json`) and
  the numbered migration scripts under `web/updater/data/<N>.php` are
  both live and both modernization-friendly. The one practical wrinkle:
  if you're changing what an already-shipped `<N>.php` *does* (different
  SQL, different defaults), land it as a new `<N+1>.php` so fresh and
  upgraded installs converge — see "Updater migrations" below.

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
./sbpp.sh db-seed [args]           # populate dev DB with realistic synthetic data
                                   # --scale=small|medium|large (default medium),
                                   # --seed=<int> (default pinned in code, deterministic).
                                   # Re-login required after each run (login_tokens truncated).
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
- Cross-process resets are serialized via a MySQL named lock in
  `Sbpp\Tests\Fixture::truncateAndReseed` (per-DB scope, 30s timeout).
  `reset-e2e-db.php` runs in a fresh PHP process per spec, so the
  truncate→seed pair has to be atomic across processes — without the
  lock two callers can race and the second hits
  `1062 Duplicate entry '0' for key 'PRIMARY'`. Don't reach around it.
- CI runs **`workers: 1`**. The suite shares one DB (`sourcebans_e2e`),
  and even with the truncate-and-reseed lock above making *resets*
  atomic, two workers running simultaneously means worker B's reset
  can wipe table state out from under worker A's in-flight test
  (missing rows → 404, missing admin row during a reseed window →
  `forbidden / No access`, etc.). Until each worker has its own DB,
  parallelism here is unsound. Don't bump `workers` back up without
  shipping per-worker DB isolation.
- Flake tolerance is **off**: `retries: 1` in CI **plus**
  `failOnFlakyTests: true`. A spec that fails first try and passes on
  retry counts as a real failure — the retry exists so
  `trace: 'on-first-retry'` produces diagnostic artifacts, not as a
  release valve. If a real flake creeps in, fix the underlying race
  (the truncate-and-reseed lock and `workers: 1` above are the
  canonical examples) instead of weakening the gate.
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
  attribute (see `_base.ts`). The CSS side honours the same media
  query — `theme.css` carries a `@media (prefers-reduced-motion:
  reduce)` global rule that collapses every `animation-duration` /
  `transition-duration` to ~0ms (#1207). Without that guard the
  drawer's `slide-in` keyframe would still run for 250ms inside
  the test browser and a `boundingBox()` read right after
  `[data-drawer-open="true"]` settles can land mid-translateX,
  off the viewport. Don't gate animations on JS state machines;
  let the CSS guard handle it.
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

### Native types over docblocks

Every method signature in `web/includes/` that PHP can express
natively uses native parameter and return types — `int $x`, `?array`,
`mixed`, `int|false`, `: void`, `: never`. Docblocks (`@param` /
`@return`) survive ONLY when carrying refinement PHP can't express
(generic shape like `list<array{slug: string, name: string}>`,
template variable hints for the SmartyTemplateRule).

Use `?T` for nullable types, `T|U` for unions, `?T = null` for
nullable optional parameters with a `null` default. Methods that
return nothing get `: void`; methods that unconditionally exit
(`header() + exit()`, `throw`, `die`) get `: never`.

Issue #1290 phase A finished this across the legacy core
(`CUserManager`, `Database`, `Log`, `Auth`, `JWT`, `CSRF`, `Crypto`,
`Api`, `ApiError`, `AdminTabs`, `Theme`, `Mailer`, the auth
handlers, `Config`, `Host`, `system-functions.php`,
`SmartyCustomFunctions.php`, `page-builder.php`). New code follows
the same convention by default; the only legitimate `@param` /
`@return` survivors carry refinements PHP can't express.

### Null-into-scalar discipline (PHP 8.5+)

`web/composer.json` requires `php >= 8.5`, so PHP's
"`Deprecated: <fn>(): Passing null to parameter #1 of type string`"
surface (introduced in PHP 8.1) is active. PHP 9 will turn it into a
`TypeError`. Every
`strlen` / `trim` / `substr` / `preg_match` / `mb_strlen` / etc.
call against a value that can be `null` at runtime needs one of
two idiomatic shapes (#1273):

- **Coalesce** when null is semantically "absent" — e.g. a `$_POST`
  / `$_GET` / `$_SESSION` / `$_SERVER` lookup the form may have
  omitted:

  ```php
  if (strlen($_POST['password'] ?? '') < MIN_PASS_LENGTH) { … }
  $name = trim((string) ($_POST['name'] ?? ''));
  ```

- **Cast** when the value should always be a string but the type
  system can't see it (most often a nullable `:prefix_*` row column,
  or a `mixed`-returning helper like `CUserManager::GetProperty`):

  ```php
  if (strlen((string) $row['ban_ip']) < 7) { … }
  $steam = trim((string) $userbank->GetProperty('authid', $aid));
  ```

- Never `if (!is_null($x) && strlen($x) > 0)` — verbose and the
  conditional reads worse than the coalesce.
- `phpstan/phpstan-deprecation-rules` + `phpVersion: 80500` is the
  static gate; `Php82DeprecationsTest` (PHPUnit) is the runtime gate
  for the bits PHPStan doesn't see (excluded paths like
  `includes/auth/openid.php`, runtime values that look non-null to
  the type system but actually aren't).
- `web/includes/auth/openid.php` is excluded from PHPStan and is
  third-party-shaped: cast at function inputs (entry points) so the
  diff stays bounded; never sprinkle `(string)` at every internal
  call.
- Replacing nullable `:prefix_*` columns with `NOT NULL DEFAULT ''`
  would also clear the deprecation, but it's a paired schema
  migration with a separate semantic change ("no IP" vs "empty IP")
  — file separately, not as part of a deprecation sweep.

### Dev DB seeder (`./sbpp.sh db-seed`)

`./sbpp.sh db-seed` populates the dev DB (`sourcebans`) with a deterministic,
realistic synthetic dataset across bans, comms, servers, admins, groups,
submissions, protests, comments, notes, banlog, and the audit log. Use it
when you need the panel surfaces (banlist, dashboard, drawer, moderation
queues, audit log) to render with real-looking data instead of empty
states. Acceptance audits and screenshot work both depend on it.

- Lives at `web/tests/Synthesizer.php` (`Sbpp\Tests\Synthesizer`); CLI
  driver at `web/tests/scripts/seed-dev-db.php`. Both are dev-only —
  the synthesizer refuses any `DB_NAME` other than `sourcebans`, so
  `sourcebans_test` / `sourcebans_e2e` stay untouched and the E2E
  suite (which builds its own rows per spec) is unaffected.
- Idempotent: every run truncates the synth-owned tables first
  (preserving `sb_settings` / `sb_mods` from `data.sql`), re-seeds the
  CONSOLE + `admin/admin` rows, then inserts the synthetic dataset.
- Deterministic given a fixed `--seed` — `mt_srand($seed)` pins PHP's
  RNG so two devs hit the same names/reasons/timestamps. Default seed
  is `Synthesizer::DEFAULT_SEED` (pinned in code).
- Three tiers: `--scale=small` (~30 bans, fast iteration), `medium`
  (default, ~200 bans), `large` (~2000 bans for pagination / perf).
- Do NOT reach for this from `Fixture::truncateAndReseed()` (the e2e
  hot path) — those two diverge by design. The synthesizer is for
  manual dev / screenshot work; the e2e fixture stays minimal so each
  spec controls what rows it needs.
- Anti-pattern: extending `data.sql` with synthetic rows (it's the
  fresh-install seed and would force a paired updater migration for
  every change) or shipping the seeder in a release tarball (the
  refusal guard is the safety mechanism; see `seed-dev-db.php`'s
  docblock for the full risk model).

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
- Modernizing an already-shipped `<N>.php` is fine when the script's
  *effect* doesn't change — typed signatures, `array()` → `[]`, swapping
  helper calls, etc. The thing to watch for is **substantive behavior
  changes** to a shipped script (different SQL, different defaults, new
  side effects): a fresh install on `data.sql` never runs the updater
  while an upgraded install already ran the old version, so the two
  silently diverge. Land that kind of change as a new `<N+1>.php` that
  converges the divergence forward. The wrapper (`Updater.php` /
  `index.php` / `store.json`) carries no such constraint.

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

### Backed enums for column-typed fields

Where a `:prefix_*` column carries a small fixed set of values
(letter codes for log types, integer kinds for ban types, the
varchar removal-type tag, the integer bitmask for web permissions),
use a backed enum to wrap the on-disk type:

- `LogType: string` — letter codes (`'m'`, `'w'`, `'e'`) — matches
  `:prefix_log.type enum('m','w','e')`.
- `LogSearchType: string` — `advType=` query param tags
  (`'admin'` / `'message'` / `'date'` / `'type'`); the enum carries
  the WHERE-fragment builder so `Log::getAll()` / `Log::getCount()`
  no longer carry parallel `switch ($type)` blocks.
- `BanType: int` — wraps `:prefix_bans.type tinyint`
  (`Steam=0`, `Ip=1`).
- `BanRemoval: string` — wraps the ban / comm removal-type column
  (`:prefix_bans.RemoveType varchar(3)` / `:prefix_comms.RemoveType
  varchar(3)`: `Deleted='D'`, `Unbanned='U'`, `Expired='E'`).
  String-backed because the column is `varchar(3)` on disk —
  the enum's job is to mirror the on-disk type.
- `WebPermission: int` — wraps the integer bitmask flags from
  `web/configs/permissions/web.json` (mirrors `init.php`'s `define`d
  `ADMIN_*` constants — both shapes coexist for backward
  compatibility).

The on-disk schema is unchanged; the enum is purely a PHP-side
wrapper. At every SQL bind site, always pass `$enum->value` (not the
enum case itself) so the dba plugin and the underlying PDO see the
column-typed primitive. `enum('m','w','e')` / `varchar(3)` columns
get `string` values; `int` columns get `int` values; this is the
contract.

For variadic permission masks,
`WebPermission::mask(WebPermission::Owner, WebPermission::AddBan)`
returns the integer bitmask. The `HasAccess()` signature is
`WebPermission|int|string $flags` to keep both the modern
enum-passing shape and the legacy
`HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)` shape working. Single-flag
checks read naturally as `HasAccess(WebPermission::Owner)`;
multi-flag checks go through `WebPermission::mask(…)`. The
`int|` and `string|` arms keep working for dynamic-value sites
(`HasAccess($mask)` where `$mask` was assembled at runtime, or
`HasAccess(SM_RCON . SM_ROOT)` for SourceMod char flags) and for
the `ALL_WEB` rolled-up bitmask which deliberately stays out of the
enum.

`LogType` / `LogSearchType` / `BanType` / `BanRemoval` /
`WebPermission` all live in the global namespace under
`web/includes/` (not `Sbpp\…`). They're loaded by `require_once` in
`init.php` + `tests/bootstrap.php` so they're available before
`Log.php` / `CUserManager.php` reference them. Issue #1290 phase D.

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
- Templates with non-default delimiters (currently `page_login.tpl`,
  `page_blockit.tpl`, `page_kickit.tpl`, and
  `page_admin_servers_rcon.tpl` using `-{ … }-`) override
  `View::DELIMITERS`. `page_youraccount.tpl` was on this list before
  #1123 B20 rewrote it in standard `{ }` delimiters; do NOT regress
  it back to `-{ … }-` without a paired edit here.

### Permission display surfaces

When a page surfaces the user's **own** permission flags back to them
(currently `page_youraccount.tpl`'s "Your permissions" card), do NOT
render a flat list of `BitToString()` output — group by category via
`Sbpp\View\PermissionCatalog::groupedDisplayFromMask($mask)` so the
section reads:

```
Bans            Servers
- Add Bans      - View Servers
- …             - …

Admins          Groups          Mods            Settings
…               …               …               …
```

The categories live in `PermissionCatalog::WEB_CATEGORIES` (Bans /
Servers / Admins / Groups / Mods / Settings / Owner — order matters,
it's the render order). Adding a new flag to
`web/configs/permissions/web.json` requires a paired addition to one
of these categories; `PermissionCatalogTest::testEveryAdminConstantBelongsToExactlyOneCategory`
fails the gate otherwise so a new flag isn't silently invisible on
the account page.

`Perms::for()` (the permission **gate** snapshot) and
`PermissionCatalog` (the permission **display** structure) are two
different surfaces — don't conflate them. `Perms::for` is what page
Views consume to gate `{if $can_add_ban}`; `PermissionCatalog` is
what the rare display-the-user's-flags-back-to-them surfaces consume.

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
- A live preview pane (textarea on the left, server-rendered HTML on
  the right) is the canonical UX. Updates POST the textarea value to
  the `system.preview_intro_text` JSON action, which pipes the value
  through the same `IntroRenderer` so the preview matches the public
  dashboard byte-for-byte. The first paint is server-rendered (so the
  page works without JS); the JS-side update only fires on input.
  Reuse this pattern — never call a third-party Markdown renderer
  client-side, as it would diverge from the safe-on-render contract.

### Sub-paged admin routes (`?section=…` routing)

Admin routes that subdivide into a small fixed set of sub-tasks
(servers / mods / groups / comms / settings / **admins** / **bans**)
ride the **`?section=<slug>` URL pattern** instead of stacking all
panes in one DOM. Each section is its own URL — linkable,
back-button-friendly, server-rendered, works without JS — and the
page handler renders exactly one View per request.

Reference: `web/pages/admin.settings.php` is the long-standing
canonical shape; #1239 brought servers / mods / groups / comms onto
the same convention; #1259 unified the chrome on the Settings-style
vertical sidebar partial `core/admin_sidebar.tpl`; #1275 collapsed
the dual-pattern world by migrating admin-admins (`admins` /
`add-admin` / `overrides`) and admin-bans (`add-ban` / `protests`
/ `submissions` / `import` / `group-ban`) onto Pattern A too,
deleting the page-level ToC partial along the way.

#1275 — the page-level ToC pattern is removed
---------------------------------------------
Pre-#1275 admin-admins and admin-bans rode a "Pattern B" page-level
ToC — a sticky anchor sidebar that emitted `#fragment` URLs and
scrolled within a single long-scroll DOM. The chrome looked
identical to Pattern A (#1266 unified them) but the routing
semantics diverged: clicks emitted `#fragment` URLs, browser back
went to the previous *page* not the previous section, and link
sharing broke. #1275 unified everything on Pattern A so the URL
shape is the **only** sub-route nav contract on the panel. The
`page_toc.tpl` partial, the cross-template `.page-toc-shell`
wrappers, and the `IntersectionObserver` active-link script are
all gone; if you find any new prose / code that introduces a
parallel "page ToC" or `#fragment` admin nav, it's an anti-pattern
(see "Anti-patterns" below).

Page-handler shape:

```php
$canList = $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_SERVERS);
$canAdd  = $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_SERVER);

/** @var list<array{slug: string, name: string, permission: int, url: string, icon: string}> $sections */
$sections = [
    ['slug' => 'list', 'name' => 'List servers',   'permission' => …, 'url' => '?p=admin&c=servers&section=list', 'icon' => 'server'],
    ['slug' => 'add',  'name' => 'Add new server', 'permission' => …, 'url' => '?p=admin&c=servers&section=add',  'icon' => 'plus'],
];

$validSlugs = ['list', 'add'];
$section = (string) ($_GET['section'] ?? '');
if (!in_array($section, $validSlugs, true)) {
    $section = $canList ? 'list' : ($canAdd ? 'add' : 'list');
}

// AdminTabs opens the sidebar shell + emits the <aside> + opens the
// content column. The page handler is responsible for closing both
// wrappers AFTER the View renders — see the docblock on AdminTabs.php.
new AdminTabs($sections, $userbank, $theme, $section, 'Server sections');

if ($section === 'add') {
    Renderer::render($theme, new AdminServersAddView(...));
    echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
    return;
}
Renderer::render($theme, new AdminServersListView(...));
echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
```

Conventions:

- Default to the FIRST accessible section when `?section=` is
  missing or unknown — never render a blank body.
- Chrome is the parameterized vertical sidebar `core/admin_sidebar.tpl`
  (#1259). `AdminTabs.php` opens `<div class="admin-sidebar-shell">`,
  emits the `<aside>` + link list, then opens
  `<div class="admin-sidebar-content">` for the page View; the page
  handler **must** close both wrappers (`echo '</div></div>'`) AFTER
  the `Renderer::render(...)` call so each section nests correctly.
- Each link is an anchor (`<a href="?p=admin&c=…&section=…"
  data-testid="admin-tab-<slug>" aria-current="page">`), not a button.
  Pre-#1239 the strip emitted `<button onclick="openTab(...)">` which
  dispatched to a JS function in `sourcebans.js` (deleted at #1123
  D1) — clicks did nothing and every pane stacked together. Don't
  reintroduce the button shape.
- Each `$sections` entry carries an `icon` (Lucide name — `server`,
  `plus`, `users`, `puzzle`, `globe`, `package`, `cog`, `image`,
  `flag`, `clipboard-list`, …). When omitted, the partial renders a
  generic `circle-dot` so every row has matching visual weight.
  Pick icons that match the visual vocabulary already in the
  Settings sidebar (`page_admin_settings_*.tpl`).
- Each section's `slug` matches `?section=<slug>` AND the
  `data-testid="admin-tab-<slug>"` hook on the rendered link.
  E2E specs anchor on the testid + the active link's
  `aria-current="page"` attribute (see
  `web/tests/e2e/specs/responsive/admin-tabs.spec.ts` for the
  mobile accordion contract; the sidebar sits at the top of the
  content column at `<1024px` and floats next to it as a sticky
  14rem rail at `>=1024px`).
- Pass an aria-label as the fifth `AdminTabs` argument
  (`'Server sections'`, `'MOD sections'`, …); screen readers
  announce the navigation by this label. Defaults to "Page
  sections" when omitted.
- The `core/admin_tabs.tpl` partial still exists but is now
  exclusively the **back-link-only** shape for edit-* pages
  (`admin.edit.ban.php`, `admin.rcon.php`, …) which call
  `new AdminTabs([], $userbank, $theme)`. AdminTabs.php routes
  empty `$tabs` to that partial and non-empty `$tabs` to
  `core/admin_sidebar.tpl`. Don't reach for `core/admin_tabs.tpl`
  directly from new code.
- Single-section "pages" that used to render a one-button AdminTabs
  strip (e.g. admin.comms.php's "Add a block" surface) drop the
  strip entirely — there's nothing to route to, so the surface is
  reachable from the parent list's CTA + the sidebar.
- Sections where two operations form a tight workflow (e.g.
  admin-admins's `search` + admins-list, admin-bans's protests
  current/archive split) consolidate into one section rather than
  splitting on every conceptual boundary. The rule is "one Pattern
  A section per **distinct workflow**, not per heading" — see
  `web/pages/admin.admins.php` for the rationale on the search +
  list collapse and `web/pages/admin.bans.php` for the protests
  /submissions sub-view shape (`?section=protests&view=archive`).
- Sub-views inside a section (e.g. protests current vs archive)
  use a `view=<slug>` query param + a `.chip-row` of real anchors,
  not a JS toggle. The chips are server-rendered with
  `data-active="true|false"` + `aria-selected` and the page
  handler runs only the active sub-view's data path.

### Empty states (`first-run` vs `filtered`)

Empty surfaces follow one of two shapes; pick by whether the empty
result is structural (no rows exist anywhere) or filter-induced
(rows exist but the active filter excludes everything):

- **first-run** (no data exists): icon + short title ("No servers
  configured yet") + one-line body explaining what will appear here +
  a primary CTA gated on the appropriate `ADMIN_*` flag (e.g.
  `dashboard-recent-bans-empty-add`, `servers-empty-add`,
  `banlist-empty-add`, `comms-empty-add`). Mark the container with
  `data-filtered="false"`. Read-only streams (e.g. "Latest blocked
  attempts", admin submission/protest archives) get the same card
  layout but **no** CTA — there's no admin action that seeds them.
- **filtered** (data exists, filter excluded everything): icon +
  short title ("No bans match those filters") + one-line body + a
  secondary "Clear filters" CTA that drops the user back at the
  unfiltered route. Mark the container with `data-filtered="true"`.

Surfaces that mix the two (banlist, commslist, audit log) compute an
`$is_filtered` flag in the page handler from the active `_GET` /
session params and branch the entire empty-state block on it; the
View DTO carries the flag. See `page.banlist.php` /
`page.commslist.php` / `page_admin_audit.tpl` for the reference
shapes. Tests anchor on the `data-filtered` attribute (`[data-filtered="false"]`
for the first-run shape, `[data-filtered="true"]` for the filtered
shape) — never on visible copy.

Use the shared `.empty-state` / `.empty-state__icon` /
`.empty-state__title` / `.empty-state__body` /
`.empty-state__actions` classes from `web/themes/default/css/theme.css`
so the visual treatment stays consistent across surfaces. Never
inline an ad-hoc empty state — the unified pattern is what the
audit (#1207) locked in. New CTAs:

- Bind to a `data-testid` per surface (e.g.
  `dashboard-servers-empty-add`, `servers-empty-add`) so E2E specs
  anchor on the contract, not visible text.
- Live behind `{if $can_*}` (the `Sbpp\View\Perms::for($userbank)`
  snapshot) so a user without the relevant `ADMIN_*` flag sees the
  body copy without the link they couldn't follow.

## Anti-patterns (do NOT reintroduce)

- `@param int $x` / `@return string` docblocks where PHP can express
  the type natively → use the native parameter / return type
  declaration instead. The docblock stays only when the type carries
  refinement PHP can't express (e.g. `list<array{slug: string, …}>`).
  Removed wholesale across legacy classes by issue #1290 phase A.
- Non-`final` classes in `web/includes/` that nothing extends → mark
  `final class`. Same applies in `web/includes/auth/handler/` and
  `web/includes/Mail/`. The only intentional non-final / abstract
  class in `web/includes/` is `View` (subclassed by every concrete
  view DTO). Marking final unblocks the JIT's monomorphic-call
  optimization. Issue #1290 phase J.
- `Log::add('m', …)` / `Log::add('w', …)` / `Log::add('e', …)` magic
  letter codes for the log type column → use
  `Log::add(LogType::Message, …)` /
  `Log::add(LogType::Warning, …)` /
  `Log::add(LogType::Error, …)`. The letter still hits the disk
  (the column stays `enum('m','w','e')`); the enum is a PHP-side wrapper so
  the call site reads as intent ("this is a message log entry")
  rather than as a magic char. Same shape for `BanType`,
  `BanRemoval`, `WebPermission`. The static gate is the
  `LogType $type` typed parameter on `Log::add()`; the runtime gate
  is PHP itself rejecting a string at the call site. Issue #1290
  phase D.
- `HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)` integer-bitmask call
  shape → `HasAccess(WebPermission::mask(WebPermission::Owner,
  WebPermission::AddBan))`. Single-flag checks read as
  `HasAccess(WebPermission::Owner)`. Both compile to the same
  integer bitmask under the hood; the enum form documents intent at
  the call site. The `ADMIN_*` `define`d constants from `init.php`
  are preserved for procedural-code back-compat — both shapes
  work. Dynamic-value sites (`HasAccess($mask)` where `$mask` was
  assembled at runtime, or `HasAccess(SM_RCON . SM_ROOT)` for
  SourceMod char flags, or `HasAccess(ALL_WEB)` for the rolled-up
  is-any-web-admin gate) deliberately keep the legacy form because
  the enum doesn't fit. Issue #1290 phase D.4.
- `RemoveType = 'U'` / `'D'` / `'E'` SQL string literals for ban /
  comm removal types in PHP-driven write paths → bind
  `BanRemoval::Unbanned->value` / `BanRemoval::Deleted->value` /
  `BanRemoval::Expired->value` (or pass the case directly through
  `match()` for read-side branching). Inline literals in pure-SQL
  predicates (e.g. `WHERE RemoveType = 'E'` inside cron-style
  `PruneBans`/`PruneComms` UPDATEs that don't take a PHP value) are
  fine — the enum is for "PHP value crosses the wire" sites, not for
  static SQL. Issue #1290 phase D.3.
- `$row['type'] == 0` / `== 1` for ban-type branching →
  `BanType::tryFrom((int) $row['type']) === BanType::Steam` (or
  `=== BanType::Ip`). Same justification as `BanRemoval` above:
  PHP-side branches go through the enum; bare SQL predicates can
  keep `WHERE type = '0'`. Issue #1290 phase D.2.
- `xajax` / `sb-callback.php` → use the JSON API.
- ADOdb → use `Database` (PDO).
- MooTools / React / a runtime bundler → vanilla JS in `web/scripts/`.
- `web/scripts/sourcebans.js` (the v1.x ~1.7k-line bulk file shipping
  `ShowBox`, `DoLogin`, `LoadServerHost`, `selectLengthTypeReason`, …)
  → removed at v2.0.0 (#1123 D1). Page-tail helpers are inlined as
  self-contained vanilla JS per page (see `web/pages/admin.edit.ban.php`
  / `admin.edit.comms.php` for canonical examples); toasts go through
  `window.SBPP.showToast` from the theme JS.
- `openTab()` JS (and the matching `<button onclick="openTab(...)">`
  chrome on `core/admin_tabs.tpl`) → the JS handler was dropped with
  sourcebans.js at #1123 D1; the buttons did nothing and every pane
  stacked together (#1239). All sub-paged admin routes (servers /
  mods / groups / comms / settings / admins / bans) ride Pattern A
  (`?section=…` routing); see "Sub-paged admin routes" above.
- `page_toc.tpl` / page-level ToC sidebar / `#fragment` anchor
  sub-route nav → removed at #1275. Pre-#1275 admin-admins and
  admin-bans rode a "Pattern B" sticky page-level ToC that emitted
  `#fragment` URLs and scrolled within a single long-scroll DOM —
  same chrome as Pattern A after #1266 unified them, but different
  routing semantics (clicks lost browser history, scroll position
  reset on back, link sharing broke, the active-link
  `IntersectionObserver` was the second source of truth alongside
  the URL). #1275 collapsed both routes onto Pattern A; the partial,
  the cross-template `.page-toc-shell` wrappers, and the
  `IntersectionObserver` script are gone. Don't reintroduce a
  parallel pattern: every admin route that needs sub-section nav
  uses `?section=<slug>` + `core/admin_sidebar.tpl`.
- The horizontal `core/admin_tabs.tpl` pill strip for Pattern A
  routes → #1259 unified the chrome on the Settings-style vertical
  sidebar (`core/admin_sidebar.tpl`). New Pattern A routes (or
  changes to existing ones) build a `$sections` array with a
  Lucide `icon` per entry, pass an aria-label as the fifth
  `AdminTabs` argument, and close the sidebar shell + content
  column with `echo '</div></div>'` AFTER `Renderer::render(...)`.
  `core/admin_tabs.tpl` is now exclusively the back-link-only
  partial for edit-* pages — don't reach for it from new code.
- Inlining settings-style sidebar markup inside templates (the
  pre-#1259 shape: `<div class="grid" style="grid-template-columns:14rem 1fr">`
  followed by an inline `<nav><a class="sidebar__link">…</a></nav>`
  block in every `page_admin_settings_*.tpl`) → the sidebar is
  now single-source in `core/admin_sidebar.tpl` and mounted by
  `AdminTabs.php`. Page templates render their content column
  body and nothing else.
- Substantively changing what an already-shipped `web/updater/data/<N>.php`
  *does* (different SQL, different defaults, new side effects) → fresh
  installs (which never run the updater) silently diverge from upgraded
  installs (which already ran the old version). Land the change as a
  new `<N+1>.php` that converges the divergence forward. Pure
  modernization (typed signatures, `array()` → `[]`, helper swaps) that
  preserves the script's effect doesn't trip this — see "Updater
  migrations" above for the per-script contract.
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
  pipe the value through `Sbpp\Markup\IntroRenderer` (Markdown). For
  immediate visual feedback, pair the textarea with the live preview
  pane shape from `page_admin_settings_settings.tpl` (calls
  `system.preview_intro_text`, server-renders through `IntroRenderer`).
- Ad-hoc per-page empty-state copy → use the shared `.empty-state`
  layout + the first-run-vs-filtered split documented under
  "Empty states" above. Inconsistent voice and missing CTAs are what
  #1207's empty-state audit caught; future surfaces stay on the
  unified pattern.
- Markdown-rendering admin display text client-side → use the
  server-side `system.preview_intro_text` action (same `IntroRenderer`
  the public dashboard uses). A bundled JS Markdown library would
  diverge from the safe-on-render contract.
- Unannotated `{$foo nofilter}` → every `nofilter` is an assertion the
  value is safe HTML; without a `{* nofilter: <why> *}` comment above
  it, future readers can't tell whether it's a real escape hatch or a
  copy-paste accident waiting to be exploited (#1113 audit).
- `intval($x)` / `strval($x)` / `floatval($x)` → `(int) $x` / `(string) $x`
  / `(float) $x`. Cast operators are PHP-native, faster, and don't have
  the function-call overhead. Two pitfalls: when crossing a radix boundary
  (`intval($x, 16)`) keep `intval` (cast doesn't take a radix); when
  casting a binary expression, keep the parentheses: `(int) ($a + $b)`,
  not `(int) $a + $b` (cast precedence binds tighter). Issue #1290 phase F.
- `is_null($x)` → `$x === null`. Pure stylistic swap, but the prettier
  shape is `??=` whenever the surrounding code is
  `if (is_null($x)) { $x = $y; }` — becomes `$x ??= $y;`. Excluded:
  `web/includes/auth/openid.php` (third-party). Issue #1290 phase G.
- `array(…)` literal constructor → `[…]` short-array syntax. PHP 5.4+
  shape; the only reason `array(…)` survived this long was nobody got
  around to it. Excluded: `web/includes/auth/openid.php` and
  `web/includes/tinymce/**` are third-party. Function signatures using
  `array $x` as a TYPE HINT are unrelated and stay. Issue #1290 phase H.
- `if (…) { return true; } return false;` → `return …;`. Three lines
  collapse to one when the condition itself is the boolean. When
  simplifying a method body this way, add the `: bool` native return
  type in the same commit (phase A pairing per the issue body). Issue
  #1290 phase I.
- `strstr($haystack, $needle)` (when used in boolean context) →
  `str_contains($haystack, $needle)`. PHP 8.0+ shape; `strstr` was
  doing double duty as substring-finder + boolean-existence-checker, and
  the latter is more clearly expressed by `str_contains`. The third-arg
  "before-needle" form (`strstr($haystack, $needle, true)`) stays — that
  one really is asking for the substring, not a bool. Issue #1290 phase E.
- `switch ($x) { case A: return [a, b]; case B: return [c, d]; … }` →
  `match ($x) { 'A' => [a, b], 'B' => [c, d], … }` for value-returning
  switches. `match` is strict-equal (no implicit string→int coercion),
  exhaustive (throws `\UnhandledMatchError` on a miss instead of
  silently falling through), and reads better. Side-effect-only switch
  arms (e.g. `header(); exit;`) stay as a small `if` ladder OUTSIDE
  the match — don't try to cram them into match arms. Issue #1290
  phase C.
- `strlen($_POST['x'])` / `trim($_POST['x'])` / `substr($row['col'], …)`
  on values that can be `null` at runtime → coalesce
  (`strlen($_POST['x'] ?? '')`) when null is "absent", or cast
  (`strlen((string) $row['col'])`) when the value should always be a
  string. PHP 8.1 deprecated this implicit null-into-scalar coercion;
  PHP 9 makes it a `TypeError` (#1273). The static gate is
  `phpstan/phpstan-deprecation-rules` + `phpVersion: 80500`; the
  runtime gate (for PHPStan-excluded files like `auth/openid.php`) is
  `Php82DeprecationsTest`. See "Null-into-scalar discipline" in
  Conventions for the per-shape idiom.
- `setTimeout` / `waitForTimeout` waits in E2E specs → wait on
  terminal attributes (`[data-loading="false"]` settled, `[data-skeleton]`
  removed) per #1123's "Testability hooks" rule.
- CSS class chains or visible-text *primary* selectors in E2E specs
  → use `data-testid` / ARIA roles per #1123. `hasText` filters for
  disambiguation are fine; "find element by its label text" as the
  whole selector is not.
- Hover-only row-action affordances (`.row-actions { opacity: 0 }`
  flipped to `1` on `tbody tr:hover`) → row-level Edit / Unmute /
  Remove (and equivalent) buttons must be visible by default at
  every viewport. The opacity-on-hover trick was removed from the
  comms list in #1207 ADM-5; it never worked on touch viewports
  (no hover state) and silently regressed discoverability for
  every keyboard / screen-reader user. New surfaces add visible
  buttons in the same shape as `.queue-row` (admin moderation
  queue) or the comms-list desktop table (`web/themes/default/page_comms.tpl`).
- Removing `<meta name="format-detection" content="telephone=no…">`
  from `core/header.tpl` (or the defensive `.drawer a[href^="tel:"]`
  reset in `theme.css`) → mobile Safari + some Android Chromes
  auto-detect colon-/digit-heavy strings like `STEAM_0:0:N`,
  `[U:1:N]`, and IPs as phone numbers and overlay a tap-to-dial
  link with the platform's accent colour (#1207 DET-1: pinkish on
  iOS dark, blueish on Android). The chrome doesn't have a single
  phone number on it; the meta is the canonical opt-out and the
  CSS reset is the belt-and-suspenders for variants that ignore it.
- Re-adding a labelled search input or a visible `Ctrl K` / `⌘K` hint
  to the topbar palette trigger (the `.topbar__search` button in
  `core/title.tpl`) → the labelled shape was a duplicate affordance
  for the same `<dialog id="palette-root">` the ⌘K shortcut already
  opens, and on mobile it broke the topbar (#1207 CC-1, slice 1) and
  on desktop it visibly competed with the palette itself (#1207 CC-3,
  slice 9). The trigger is now icon-only at every viewport, matching
  the sibling theme-toggle's chrome. The `.topbar__search-label` /
  `.topbar__search-kbd` spans stay in the DOM for SR users + the
  Mac glyph swap, but `display: none` everywhere — don't unhide them.
- Moving `<footer class="app-footer">` back outside `<div class="app">`
  (the body-level sibling shape from before #1271's structural fix) →
  `.sidebar` is `position: sticky; top: 0; height: 100vh` and its
  sticky containing block is `.app`. Pulling the footer out leaves
  `.app` `footerHeight` short of the document, so on tall pages the
  sidebar releases at the bottom (brand cuts off) and on barely-tall
  pages where `docHeight - viewport ≤ footerHeight` (the bare-e2e
  `?p=admin&c=audit` shape) the entire scroll range falls inside the
  release phase and the sidebar appears to track the scroll — exactly
  the symptom rumblefrog reported in #1271. The footer must stay as
  the last flex column item of `<div class="main">`. The
  `align-self: flex-start` on `.sidebar` (added by #1278) is
  defensive parity with `.admin-sidebar`, NOT the load-bearing fix —
  a future refactor that puts the footer back outside `.app` will
  silently regress even with `align-self` in place. The regression
  guard is `web/tests/e2e/specs/responsive/sidebar-sticky.spec.ts`'s
  strict `top===0` assertion at scroll=`document.scrollHeight`.
- Pinning `<aside id="drawer-root">` or `<dialog id="palette-root">`
  inside `<div class="app">` "to be consistent with the footer" → the
  drawer is `position: fixed; right: 0; top: 0; height: 100%`
  (right-pinned panel, NOT full-bleed — `inset: 0` is on the
  separate `.drawer-backdrop`); `<dialog>` promotes itself to the
  top layer when `showModal()`-ed. Both are conceptually top-layer
  overlays — they're not part of the app shell's layout, so they
  belong outside `.app` for the same reason a Linear/Notion modal
  isn't nested inside the page header. The defensiveness reason is
  CSS containing-block scoping: a future refactor that declares
  `transform`, `filter`, `perspective`, `contain: layout`, or
  `will-change: transform` on `.app` (or any descendant in the
  drawer's would-be ancestry) RE-ESTABLISHES THE CONTAINING BLOCK
  for `position: fixed` descendants per CSS Position Module §3.2 —
  the drawer would suddenly be positioned relative to that
  ancestor instead of the viewport, painting at the wrong size /
  in the wrong place. Keeping the drawer as a direct `<body>`
  child sidesteps that landmine. The structural-fix concern that
  motivated #1271 (sidebar's sticky CB short of the document)
  doesn't apply — `position: fixed` removes the drawer from flow,
  so it cannot grow `.app`'s height.

## Where to find what

| Need to …                              | Look at                                                  |
| -------------------------------------- | -------------------------------------------------------- |
| Understand request lifecycle           | `ARCHITECTURE.md` ("Page request lifecycle" / "JSON API request lifecycle") |
| Add a JSON action                      | `web/api/handlers/_register.php` + `web/api/handlers/<topic>.php` |
| Add or rename a permission             | `web/configs/permissions/web.json`, then regen contract  |
| Render a page                          | `web/pages/<page>.php` + `web/includes/View/*View.php`   |
| Gate compute that only feeds legacy theme-fork output (e.g. `wantsLegacyAdminCounts()`) | `web/includes/Theme.php` (`Sbpp\Theme`) — predicates page handlers ask before they pay for DTO fields the shipped default theme doesn't render. Default returns `false`; forks opt back in via `define('<flag>', true)` in their `theme.conf.php`. First user (#1270): `Sbpp\Theme::wantsLegacyAdminCounts()` keeps the 9-COUNT subquery + `getDirSize(SB_DEMOS)` walk off `web/pages/page.admin.php`'s default-theme path. New compute-paying-for-fork-only-output surfaces add a sibling `wants<X>()` predicate; the regression test in `web/tests/integration/AdminHomePerformanceTest.php` is the reference shape (resets `Sbpp\Theme::legacyComputeCount()`, asserts the gated branch did NOT fire on the default theme). |
| Edit a template                        | `web/themes/default/*.tpl`                               |
| Reuse the moderation-queue card layout (admin submissions / protests, mobile-stacked summary rows) | `web/themes/default/css/theme.css` (`.queue-row`, `.queue-row__body`, `.queue-row__date` — #1207 PUB-2). Apply by adding `class="queue-row …"` to the outer `<details>` and dropping the inline `flex` / `flex-shrink:0` styles from the summary children. |
| Add visible row actions to a table-rendered admin list (Edit / Unmute / Remove buttons + responsive mobile-card mirror) | `web/themes/default/page_comms.tpl` (#1207 ADM-5) is the canonical reference: `<button class="btn btn--secondary btn--sm">` / `<a class="btn btn--ghost btn--sm">` inside a `.row-actions` cell, plus `.ban-card__actions` row of identical-data-action buttons in the mobile card. Wire destructive / state-changing buttons via `data-action="…"` + `data-bid` + `data-fallback-href`; the inline page-tail JS calls `sb.api.call(Actions.PascalName)` and falls back to the GET URL if the JSON dispatcher is absent. |
| Edit the player-detail drawer (open trigger, tabs, panes, lazy loaders) | `web/themes/default/js/theme.js` (`renderDrawerBody` / `loadPaneIfNeeded`) |
| Edit the command palette (icon-only trigger, ⌘K binding, result rows, kbd hints, Ctrl+Enter copy) | `web/themes/default/js/theme.js` (`openPalette` / `closePalette` / `renderPaletteResults` / `applyPlatformHints` / `handlePaletteCopyShortcut`) + `core/title.tpl` (the `.topbar__search` icon button) + the `.palette__row*` rules in `web/themes/default/css/theme.css`. Player rows carry `data-drawer-bid="<bid>"` (bare Enter / click → `loadDrawer`, palette closes itself) + `data-steamid="<steam>"` (`Ctrl/Cmd+Enter` → `navigator.clipboard.writeText` + `showToast`). The kbd glyphs are server-rendered in non-Mac form (`Enter`, `Ctrl`); `applyPlatformHints` swaps `[data-enterkey]` → ⏎ and `[data-modkey]` → ⌘ on Mac/iOS at boot and after every render (#1184, #1207 DET-2). |
| Add admin-only per-player notes | `web/api/handlers/notes.php` (CRUD) — Notes tab is gated by `bans.detail`'s `notes_visible` flag |
| Render admin-authored Markdown to safe HTML | `web/includes/Markup/IntroRenderer.php` (`Sbpp\Markup`) |
| Display a user's own permission flags grouped by category | `Sbpp\View\PermissionCatalog::groupedDisplayFromMask($mask)` (`web/includes/View/PermissionCatalog.php`). Adding a new flag to `web/configs/permissions/web.json` requires a paired entry in `WEB_CATEGORIES`; `PermissionCatalogTest` enforces it. |
| Live-preview Markdown in a settings textarea | `system.preview_intro_text` JSON action + `web/themes/default/page_admin_settings_settings.tpl` (`.dash-intro-editor` / `.dash-intro-preview`) |
| Build an empty-state surface (first-run vs filtered, primary/secondary CTAs) | `.empty-state` rules in `web/themes/default/css/theme.css` + reference shapes in `page_servers.tpl`, `page_dashboard.tpl`, `page_bans.tpl`, `page_comms.tpl`, `page_admin_audit.tpl`, `page_admin_bans_protests.tpl`, `page_admin_bans_submissions.tpl` |
| Subdivide an admin route into `?section=<slug>` URLs (servers, mods, groups, comms, settings, **admins**, **bans**) | `web/pages/admin.settings.php` is the long-standing reference; #1239 brought servers / mods / groups / comms onto the same shape; #1259 unified the chrome on the Settings-style vertical sidebar; #1275 brought admins (`admins` / `add-admin` / `overrides`) and bans (`add-ban` / `protests` / `submissions` / `import` / `group-ban`) onto the same shape, deleting the page-level ToC (`page_toc.tpl`) along the way so `?section=` is now the **only** sub-route nav contract. The shared partial is `web/themes/default/core/admin_sidebar.tpl` (parameterized on `tabs` / `active_tab` / `sidebar_id` / `sidebar_label`); `web/includes/AdminTabs.php` opens `<div class="admin-sidebar-shell">`, emits the `<aside>` + link list, opens `<div class="admin-sidebar-content">`, and the page handler closes both wrappers (`echo '</div></div>'`) AFTER `Renderer::render(...)`. Each `$sections` entry carries `slug` + `name` + `permission` + `url` + `icon` (Lucide name); the link emits `<a href="?p=admin&c=<page>&section=<slug>" data-testid="admin-tab-<slug>" aria-current="page">` — never `<button onclick="openTab(...)">` (the JS handler was deleted at #1123 D1). See "Sub-paged admin routes" in Conventions. |
| Render sub-views inside a Pattern A section (e.g. protests / submissions current-vs-archive) | `?view=<slug>` query param + a server-rendered `.chip-row` of real anchors (each carries `data-active="true|false"` + `aria-selected`). Reference: the protests / submissions chip rows in `web/pages/admin.bans.php` (`?section=protests&view=archive` / `?section=submissions&view=archive`). Pre-#1275 the chips called `Swap2ndPane()` — a `web/scripts/sourcebans.js` helper deleted at #1123 D1, leaving them dead — and the page rendered both views simultaneously. The new shape only renders the active view's data path; back/forward and link sharing both work. |
| Lay out a sub-paged admin route's chrome (the 14rem vertical sidebar at `>=1024px`, the `<details open>` accordion at `<1024px`) | `web/themes/default/core/admin_sidebar.tpl` (the partial) + the `.admin-sidebar-shell` / `.admin-sidebar` / `.admin-sidebar__details` / `.admin-sidebar__summary` / `.admin-sidebar__nav` / `.admin-sidebar__link` / `.admin-sidebar-content` rules in `web/themes/default/css/theme.css` (#1259). The active link reuses the shared `.sidebar__link[aria-current="page"]` rule from the main app shell so the dark-pill-in-light / brand-orange-in-dark treatment is single-source. |
| Render the trailing "Back" link on edit-* admin pages (the only surface that calls `new AdminTabs([], …)`) | `web/themes/default/core/admin_tabs.tpl` is the back-link-only partial (it still has a defensive `{foreach}` for legacy themes, but `AdminTabs.php` only routes here when `$tabs === []`). Page handlers like `admin.edit.ban.php` / `admin.rcon.php` / `admin.email.php` call `new AdminTabs([], $userbank, $theme)` and the partial emits the right-aligned Back anchor (`.admin-tabs__back` in theme.css). |
| Add or rename an admin-admins advanced-search filter | `web/pages/admin.admins.php` (filter-building loop + active-filter map for pagination) + `web/pages/admin.admins.search.php` (DTO population) + `web/includes/View/AdminAdminsSearchView.php` (`active_filter_*` properties) + `web/themes/default/box_admin_admins_search.tpl` (input + pre-fill). The form is single-submit AND-semantics with a backward-compat shim for legacy `advType=…&advSearch=…` URLs (#1207 ADM-4); cover new filters in `web/tests/integration/AdminAdminsSearchTest.php`. |
| Add a shared "1 of these required" badge for an either/or input pair | `web/themes/default/page_submitban.tpl` (`data-required-group="…"` + the inline guard script — vanilla JS `// @ts-check`, blocks submit when both are empty) |
| Bootstrap (paths, autoload, theme)     | `web/init.php`                                           |
| Routing (`?p=…&c=…&o=…`)               | `web/includes/page-builder.php` — unrecognised admin `c=…` returns the 404 page slot via `web/pages/page.404.php` + `Sbpp\View\NotFoundView` (#1207 ADM-1) |
| Resolve the panel version (`SB_VERSION`, `data-version="…"` footer hook) | `web/includes/Version.php` (`Sbpp\Version::resolve()`) — three-tier fallback: `configs/version.json` → `git describe` → the `'dev'` sentinel (#1207 CC-5) |
| Auth / JWT cookie                      | `web/includes/auth/`                                     |
| CSRF                                   | `web/includes/security/CSRF.php`                         |
| Schema                                 | `web/install/includes/sql/struc.sql`                     |
| Wrap a `:prefix_*` column with a backed PHP enum (log letter codes, ban types, removal-type tags, web permissions) | `web/includes/LogType.php` / `LogSearchType.php` / `BanType.php` / `BanRemoval.php` / `WebPermission.php` (global namespace; loaded by `init.php` + `tests/bootstrap.php`). Pass `$enum->value` at every SQL bind site so the dba plugin sees the column-typed primitive; use `WebPermission::mask(…)` to assemble multi-flag bitmasks for `HasAccess()`. Issue #1290 phase D. |
| Seed `sb_settings` rows for fresh installs | `web/install/includes/sql/data.sql`                  |
| Add a one-off DB upgrade for existing installs | `web/updater/data/<N>.php` + `web/updater/store.json` |
| Test fixtures                          | `web/tests/Fixture.php`, `web/tests/ApiTestCase.php`     |
| Populate the dev DB with realistic synthetic data (banlist > 1 page, drawer history, moderation queues, audit log) | `./sbpp.sh db-seed` → `web/tests/scripts/seed-dev-db.php` (CLI driver) → `web/tests/Synthesizer.php` (`Sbpp\Tests\Synthesizer`). Dev-only: refuses any `DB_NAME` other than `sourcebans` (so `sourcebans_test` / `sourcebans_e2e` stay untouched). Idempotent; deterministic given a fixed `--seed` (default `Synthesizer::DEFAULT_SEED`). Does NOT share plumbing with `Fixture::truncateAndReseed` — the e2e hot path stays minimal. |
| API wire-format snapshots              | `web/tests/api/__snapshots__/<topic>/<scenario>.json`    |
| Action -> permission lock              | `web/tests/api/PermissionMatrixTest.php`                 |
| Trap PHP 8.1 null-into-scalar deprecations at runtime (the bits PHPStan can't see) | `web/tests/integration/Php82DeprecationsTest.php` (#1273) — process-isolated render harness with a stub Smarty + `set_error_handler` that promotes `E_DEPRECATED` / `E_USER_DEPRECATED` to `\ErrorException`. Mirrors the LostPasswordChromeTest stub-Smarty pattern; each test method runs in a separate process because the page handlers declare top-level helpers (`setPostKey()` etc.) that PHP can't redeclare in one process. Add a marquee route here whenever a new high-traffic page handler ships, especially if it reads nullable `:prefix_*` columns or `$_POST` / `$_GET` lookups. |
| Add an E2E spec                        | `web/tests/e2e/specs/<smoke|flows|a11y|responsive>/...` + `web/tests/e2e/pages/...` |
| Add a route to the screenshot gallery  | `web/tests/e2e/specs/_screenshots.spec.ts` (`ROUTES` array) |
| Tweak mobile (<=768px) chrome layout   | `web/themes/default/css/theme.css` — see the `#1207` `@media (max-width: 768px)` blocks for the canonical shapes (icon-only topbar search, full-width drawer + scroll lock). Sub-paged admin routes (servers / mods / groups / comms / settings / admins / bans) use the `<details open>` accordion in the `#1259` `@media (min-width: 1024px)` block (sidebar inline at `<1024px`, sticky 14rem rail at `>=1024px`); see "Sub-paged admin routes" in Conventions. |
| Stop mobile browsers auto-linking SteamIDs / IPs as phone numbers | `web/themes/default/core/header.tpl` (`<meta name="format-detection" content="telephone=no…">` + `<meta name="x-apple-data-detectors">`) and the defensive `.drawer a[href^="tel:"]` reset in `theme.css` |
| Lock page scroll while a modal-style chrome is open | `web/themes/default/css/theme.css` (`html:has(#drawer-root[data-drawer-open="true"]) { overflow: hidden; }` — pure-CSS, gates on the same `data-drawer-open` mirror theme.js sets, applies at every viewport so the drawer-open contract is symmetric desktop/mobile per the Linear/Vercel/Notion modal idiom) |
| Keep the main sidebar sticky-pinned across the full document scroll (`<aside class="sidebar">`) | The structural half of #1271 lives in `web/themes/default/core/footer.tpl`: `<footer class="app-footer">` is rendered as the LAST flex column item of `<div class="main">`, INSIDE `<div class="app">`. `.sidebar`'s sticky containing block is `.app`; if the footer were a body-level sibling of `.app` (the pre-fix shape), `.app`'s height would fall short of the document by `footerHeight` and the sidebar would release at the bottom — brand cut off, on barely-tall pages (`docHeight - viewport ≤ footerHeight`, e.g. `?p=admin&c=audit` on the bare e2e seed) the entire scroll range would be in the release phase and the sidebar would track the scroll. Keeping the footer inside `.app` makes the sticky CB extend to the full document. The CSS half (`.sidebar { align-self: flex-start; }` from #1278) is defensive parity with `.admin-sidebar` and is RETAINED but not load-bearing on its own. The footer's `margin-top: auto` (`.app-footer` rule in `theme.css`) is the classic "sticky footer" pattern — pushes the footer to the bottom of `.main`'s flex column on short pages so the credit doesn't float halfway up the viewport. Regression guard: `web/tests/e2e/specs/responsive/sidebar-sticky.spec.ts` asserts strict `top===0` at scroll=`document.scrollHeight` on `?p=admin&c=bans` (the canonical tall page) AND on `?p=admin&c=audit` (the barely-tall page that historically presented the bug most visibly). |
| Disable the chrome's slide-in / fade animations for `prefers-reduced-motion` users | `web/themes/default/css/theme.css` (`@media (prefers-reduced-motion: reduce)` global block — see the matching note in "Playwright E2E specifics" / Conventions) |
| Run a stack in parallel with another worktree | Worktree-local `docker-compose.override.yml` (see "Parallel stacks") |
| Local dev stack details                | `docker/README.md`                                       |
