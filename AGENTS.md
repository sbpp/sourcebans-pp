# SourceBans++ — agent guide

Conventions and workflow for AI agents and human contributors. Read
[`ARCHITECTURE.md`](ARCHITECTURE.md) first if you need a tour of the
codebase; this file is the cheatsheet.

## Stack at a glance

- `web/` — PHP 8.5 panel (Smarty 5, PDO/MariaDB, vanilla JS). Entry:
  `web/index.php` (pages) and `web/api.php` (JSON API).
- All classes in `web/includes/` live under `Sbpp\…` namespaces (e.g.
  `Sbpp\Db\Database`, `Sbpp\Auth\UserManager`, `Sbpp\Log`,
  `Sbpp\Api\Api`, `Sbpp\View\AdminTabs`). The legacy global names
  (`Database`, `CUserManager`, `Log`, `Api`, …) are preserved as
  `class_alias` shims for procedural code that hasn't been migrated;
  new code uses the namespaced names. The only remaining global-namespace
  class in `web/includes/` is `LightOpenID` (third-party in
  `Auth/openid.php`). See "Namespacing" under Conventions for the full
  per-class table.
- `game/addons/sourcemod/` — SourceMod plugin sources (`.sp`).
- `docker/` + `docker-compose.yml` + `sbpp.sh` — local dev stack.
- `web/install/` — installer wizard self-hosters run on every fresh
  install (the dev stack seeds the DB out of band via `docker/db-init/`,
  so the wizard isn't exercised locally). Live code; modernize and
  extend like anything else under `web/`. As of #1332 the wizard
  rides the panel's V2.0 chrome (typed `Sbpp\View\Install\*View`
  DTOs + Smarty templates under `web/themes/default/install/`) and
  carries a vendor/-missing recovery surface (`web/install/recovery.php`)
  for git-checkout / partial-upload installs.
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
| Edit user-facing install/quickstart                         | `docs/src/content/docs/getting-started/quickstart.mdx` (the README is a tiny landing page that links to docs — don't grow it back into a manual) |
| Add or change a wizard step (page handler / View / template / shared helper) | `AGENTS.md` (Install wizard convention block) + the "Edit a step of the install wizard" row in "Where to find what" |
| Change a user-facing install / upgrade / troubleshooting flow (PHP or SourceMod version requirements, installer wizard steps, `config.php` behavior, `web/updater/` runner output, plugin `databases.cfg` / `sourcebans.cfg` shape, error messages a self-hoster will see) | The relevant page under `docs/src/content/docs/` (the Starlight site published at sbpp.github.io). |
| Add or remove a config knob a self-hoster sets (`config.php` keys, `databases.cfg` fields, plugin convars users tune) | `docs/` page that documents that knob, plus the matching `docs/src/content/docs/updating/*.mdx` page if it's a breaking change between releases |
| Ship a new feature with a self-hoster-visible setup step (Discord forwarder, demos, theming, etc.) | New page or section under the right `docs/` group + sidebar entry in `docs/astro.config.mjs` |
| Touch any UI under `web/install/` or the panel chrome that's screenshotted in docs | Run `npm run capture` in `docs/` locally and commit the PNG diff. Maintainers can alternatively apply the `safe-to-screenshot` label after reviewing the PR diff so `docs-screenshots-capture.yml` regenerates the captures (see `docs/README.md` for the security model + label-strip-on-push contract) |
| Change panel theme tokens — palette, geometry, semantic colors — in `web/themes/default/css/theme.css` (the `:root` block or `html.dark` overrides) | Mirror the change in `docs/src/styles/sbpp.css` so the docs site stays visually consistent with the panel. Same PR. (Fonts intentionally not mirrored — see #2.) |

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
- If your change affects a self-hoster — what they install, how they
  upgrade, what they configure, what error message they see — the docs
  change ships in the same PR. The docs are part of the codebase now;
  treat them like code. Run `npm run capture` in `docs/` locally to
  regenerate screenshots when the install / panel chrome moved (or wait
  for a maintainer to apply the `safe-to-screenshot` label and let
  `docs-screenshots-capture.yml` do it from CI), and panel theme-token
  changes mirror into `docs/src/styles/sbpp.css` so the two surfaces
  stay visually aligned.

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

### Namespacing

Every class in `web/includes/` lives under a `Sbpp\…` namespace
matching its directory. PSR-4 autoloads from `web/includes/` →
`Sbpp\` (`web/composer.json` autoload-psr-4 mapping). The shape:

| Class                                    | Role                                  |
| ---------------------------------------- | ------------------------------------- |
| `Sbpp\Db\Database`                       | DB access (PDO wrapper)               |
| `Sbpp\Auth\UserManager`                  | session / user-state (was `CUserManager`) |
| `Sbpp\Auth\Auth`                         | login flow                            |
| `Sbpp\Auth\Host`                         | hostname helper                       |
| `Sbpp\Auth\JWT`                          | token encode/decode                   |
| `Sbpp\Auth\Handler\NormalAuthHandler`    | password login handler                |
| `Sbpp\Auth\Handler\SteamAuthHandler`     | Steam OpenID login handler            |
| `Sbpp\Security\CSRF`                     | CSRF token helpers                    |
| `Sbpp\Security\Crypto`                   | password / token crypto               |
| `Sbpp\Log`                               | audit log                             |
| `Sbpp\Config`                            | settings cache                        |
| `Sbpp\Api\Api`                           | JSON API dispatcher                   |
| `Sbpp\Api\ApiError`                      | structured API error                  |
| `Sbpp\View\AdminTabs`                    | admin sub-route sidebar mounter       |
| `Sbpp\View\*`                            | view DTOs (page-level + partials)     |
| `Sbpp\Servers\SourceQueryCache`          | per-`(ip, port)` on-disk cache around the xPaw A2S probe (#1311) |
| `Sbpp\Servers\RconStatusCache`           | per-`sid` on-disk cache around the RCON `status` command (#PLAYER_CTX_MENU) |
| `Sbpp\Markup\IntroRenderer`              | admin-authored Markdown renderer      |
| `Sbpp\Mail\Mail` / `Sbpp\Mail\Mailer` / `Sbpp\Mail\EmailType` | `Mail::send(...)` entry point + Symfony Mailer SMTP wrapper + email-type enum |
| `Sbpp\Theme`                             | theme registry + per-theme behavior gates (e.g. `wantsLegacyAdminCounts()`) |
| `Sbpp\Version`                           | three-tier `SB_VERSION` resolver (tarball JSON → git → `'dev'`) |
| `Sbpp\Util\Duration`                     | minute-count humanizer for `sb_settings` token-lifetime echoes |
| `Sbpp\PHPStan\SmartyTemplateRule` (+ `Sbpp\PHPStan\SbppSyntaxErrorInQueryMethodRule` / `SbppPrefixAwareReflector` / `SbppNullReflector` under `web/phpstan/`) | bespoke PHPStan rules + DBA reflectors for the codebase |

Legacy global names (`Database`, `CUserManager`, `Log`, `Api`, …) are
preserved as `class_alias` shims for procedural code that hasn't been
migrated yet. The aliases are registered eagerly via the
`require_once` chain at the top of `web/init.php` (and
`web/tests/bootstrap.php` / `web/phpstan-bootstrap.php` for the
analyser-side surfaces) so the global name resolves before procedural
code references it — `class_alias()` is a runtime call the autoloader
can't trigger on a global-name lookup. New code uses the namespaced
names directly:

```php
use Sbpp\Db\Database;
use Sbpp\Auth\UserManager;
```

The only remaining global-namespace class in `web/includes/` is
`LightOpenID` (`Auth/openid.php` — documented third-party exception
also excluded from PHPStan via `phpstan.neon`'s `excludePaths`). The
backed enums (`LogType`, `LogSearchType`, `BanType`, `BanRemoval`,
`WebPermission`) also stay in the global namespace by design — they're
typed wrappers around `:prefix_*` column letter codes / bitmasks
rather than subsystem entry points, and their call sites read more
naturally without a `use` chain (see "Backed enums for column-typed
fields" below).

Issue #1290 phase B. A follow-up PR will burn the `class_alias` shims
as call sites adopt the namespaced names; until then, NEVER add a new
top-level `class Foo {}` in `web/includes/` (see "Anti-patterns").

### Database

- Access goes through `Sbpp\Db\Database` (`web/includes/Db/Database.php`,
  PDO wrapper). The legacy global `Database` alias keeps existing call
  sites working.
- Tables use `:prefix_` literals (`SELECT … FROM \`:prefix_admins\``);
  `Database::query()` rewrites the placeholder. Never inline the prefix.
- Pattern: `query` → `bind` → `execute` / `single` / `resultset`.
- ADOdb was fully removed (commit `b9c812b2`). **Do not reintroduce it.**
- Each named placeholder (`:name`) inside one query needs as many
  `bind()` calls as occurrences. The panel runs PDO with
  `PDO::ATTR_EMULATE_PREPARES => false` (`Sbpp\Db\Database::__construct`
  — set at #1124 / motivated by #1167 so `LIMIT '0','30'` stops
  tripping MariaDB strict mode). Under native prepares the MySQL
  driver expands every `:name` occurrence into its own positional
  `?` slot in the prepared statement, so reusing `:sid` twice and
  `bind(':sid', …)` once leaves the second slot unbound and
  `execute()` raises `SQLSTATE[HY093] Invalid parameter number`
  (#1314). Pre-#1124 emulated prepares masked this by client-side
  string substitution at every occurrence. Either rename each
  occurrence (`:sid` + `:sid_inner`) and `bind()` each, or pass the
  values via the `resultset(['sid' => …, 'sid_inner' => …])` array
  shortcut — both shapes are equivalent. The `:prefix_` literal is
  not a real PDO placeholder; it's a substring that
  `Database::setPrefix()` replaces before `prepare()`, so reuse
  there is harmless. Regression guard:
  `web/tests/integration/SrvAdminsPdoParamTest.php` pins both the
  contract (single-bind on a reused name throws `HY093`) and the
  page-level fix (`admin.srvadmins.php` renders without raising).

### PHP 8.5 idioms (post-#1289 floor bump)

The codebase floor is PHP 8.5. Beyond native types and constructor
promotion, four 8.4/8.5 features are documented here (#1290 phase K):
two adopted today (`#[\NoDiscard]`, the pipe operator), two declined
for now (property hooks, asymmetric visibility — neither has a paying
candidate in the current codebase):

- `#[\NoDiscard]` (PHP 8.5) on methods whose return value is the
  meaningful signal: `Api::redirect()` (the redirect envelope is the
  navigation; `Api::redirect(...);` without a `return` silently no-ops),
  `CSRF::validate()` (running the check and ignoring the verdict is
  the textbook bug shape this attribute exists to catch). New methods
  whose return is the only meaningful output should carry the
  attribute too. `Database::execute()` is a strong future candidate
  but adopting it requires a paired sweep of every legacy
  `$db->execute();` discard across `web/updater/data/*.php`,
  `web/pages/*.php`, and `web/api/handlers/*.php` (≈40 runtime sites
  the static gate can't see through `$GLOBALS['PDO']` / `$this->dbs`)
  — tracked as a follow-up in issue #1294.
- Property hooks (PHP 8.4) for computed / lazy / validated
  accessors. None currently in use — the codebase's getter methods
  (`UserManager::GetAid()`, `GetProperty()`, etc.) are simple
  delegators where a property hook would add call overhead without
  paying for itself. Reach for hooks when you have actual compute
  inside the getter (lazy DB lookup, derived value caching, value
  validation on set). For plain stored data, `public readonly` is
  the right shape.
- Asymmetric visibility (PHP 8.4): `public private(set) X $foo;`
  for properties that need to be written more than once internally
  but read-only externally. None currently in use —
  `public private(set) readonly X $foo;` is indistinguishable from
  plain `public readonly X $foo;` (the engine enforces single-write
  in both shapes), so reach for `private(set)` only when there's a
  concrete multi-write internal flow. For plain "single-write,
  externally read-only", `public readonly` is the right shape.
- Pipe operator `|>` (PHP 8.5) for multi-step value transformations
  that read better left-to-right than as nested function calls. The
  `IntroRenderer` chain is the canonical site:
  `($raw ?? '') |> strval(...) |> IntroRenderer::renderIntroText(...)`.
  Pipe is best when each step takes ONE argument and is named (no
  ad-hoc `fn() => f($x, ...)` lambda noise); reach for it only when
  the form is obviously clearer than the nested-call shape.

  **Precedence pitfall**: `|>` binds tighter than `??`, `?:`, `=`,
  and the boolean `&&` / `||` / `and` / `or`. When chaining a
  coalesce, parenthesize the LHS: `($raw ?? '') |> strval(...)`,
  NOT `$raw ?? '' |> strval(...)` (the latter parses as
  `$raw ?? ('' |> strval(...))` and silently never coerces when
  `$raw` is non-null).

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
  `includes/Auth/openid.php`, runtime values that look non-null to
  the type system but actually aren't).
- `web/includes/Auth/openid.php` is excluded from PHPStan and is
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

### Loading state on action buttons (`window.SBPP.setBusy`)

Every action button that fires `sb.api.call(...)` from a click handler
without an immediate page navigation MUST flip to a busy state for the
duration of the in-flight call. Pre-fix the confirm modals
(`#bans-unban-dialog`, `#comms-unblock-dialog`, `#admins-delete-dialog`)
read as frozen for 100-1000ms between the click and the response —
users instinctively double-clicked "to make it work" and queued
duplicate requests until the post-response `disabled` flag landed.

The contract:

- `window.SBPP.setBusy(btn, true)` runs BEFORE the `sb.api.call(...)`
 leaves the page (so the disabled flag lands on the first paint of
 the click).
- `setBusy(btn, false)` runs on every non-navigating response branch
 (success that flips state in-place, error toast, validation reject)
 so retries are possible. The success-then-navigate path can leave
 the button busy because the new paint resets the DOM.
- The helper (defined inside `web/themes/default/js/theme.js`'s IIFE
 and re-exposed via `window.SBPP.setBusy`) sets three things on the
 button: `data-loading="true"` (drives the CSS spinner), `aria-busy="true"`
 (announces to AT users), and the native `disabled` flag (the
 load-bearing gate against double-clicks). All three are the
 contract; do NOT split them.
- The visual spinner lives in `theme.css` under the
 `.btn[data-loading="true"]` rule and its `::after` donut + the
 `sbpp-btn-spin` keyframe. The CSS hides the button's content
 (`color: transparent !important` for text, `visibility: hidden`
 for icon children) but keeps the layout width locked so there's
 no shift between idle and busy. `cursor: progress` and
 `pointer-events: none` are the visual gate; `disabled` is the
 load-bearing one.
- Inline page-tail scripts inside `.tpl` files define a local
 `setBusy(btn, busy)` wrapper that delegates to `window.SBPP.setBusy`
 when present and falls back to `btn.disabled = busy` otherwise. The
 fallback keeps the double-click gate working on third-party themes
 that strip `theme.js`; the spinner naturally disappears with the
 missing CSS, which is the right degradation (no fake spinner on a
 theme that hasn't opted in).
- `prefers-reduced-motion: reduce` is the documented exception to
 the chrome's global animation reset (#1362). The reset in `theme.css`
 (the `*, *::before, *::after` block — see the matching note under
 "Disable the chrome's slide-in / fade animations" in "Where to find
 what") still pins every `animation-duration` to ~0ms for the chrome
 surfaces where motion-of-state is the contract (drawer slide-in,
 toast slide-in, chevron rotation). The spinner is the load-bearing
 exception: it's essential feedback — without rotation the donut
 reads as a decorative ring, not as in-progress feedback — and WCAG
 2.3.3 Animation from Interactions explicitly exempts essential
 motion. The CSS rule next to the spinner declaration carries a
 paired `@media (prefers-reduced-motion: reduce) { .btn[data-loading="true"]::after { animation-duration: 0.6s !important; animation-iteration-count: infinite !important; } }`
 block that wins on specificity over the universal `*::after`
 reset. Pre-#1362 the spinner froze under reduced motion (the v2.0
 RC1 regression that motivated the per-rule override); don't
 reintroduce the freeze. The regression guard in
 `web/tests/e2e/specs/flows/loading-animations.spec.ts`
 launches a fresh context with `reducedMotion: 'reduce'`, samples the
 rendered `transform` of `.btn[data-loading="true"]::after` at multiple
 frame boundaries, and asserts the matrix VALUE CHANGES across samples
 (the only Playwright-tractable way to assert a CSS animation is
 actually running).

Adding a new action button that fires `sb.api.call`:

1. Define the button with `class="btn btn--*"` so it picks up
 `--btn-color` (the spinner's border colour).
2. In the inline `<script>` (or in a `web/scripts/*.js` page-tail
 file), add the local `setBusy(btn, busy)` wrapper.
3. Call `setBusy(submitBtn, true)` immediately before the
 `sb.api.call(...)` line.
4. Call `setBusy(submitBtn, false)` in every non-navigating branch
 of the `.then`. The simplest shape is the canonical
 `page_comms.tpl` / `page_bans.tpl` confirm-dialog flow.
5. For drawer surfaces (Notes pane add/delete in `theme.js`),
 reach for the module-scope `setBusy(...)` directly — same
 helper, no wrapper needed.

The regression guards are paired:

- `web/tests/e2e/specs/flows/action-loading-indicator.spec.ts` stalls
 the `Actions.CommsUnblock` route via `page.route`, asserts
 `data-loading="true"` + `aria-busy="true"` + `disabled` on the
 submit button during the in-flight window, releases the route,
 and confirms the row flips in-place. The second test in the file
 proves the disabled gate blocks a double-click by counting the
 number of `Comms.Unblock` requests that reach the stall (exactly
 one).
- `web/tests/e2e/specs/flows/loading-animations.spec.ts`
 (#1362) launches a fresh browser context with
 `reducedMotion: 'reduce'` (so the suite's global
 `contextOptions: { reducedMotion: 'reduce' }` doesn't mask the
 spec — the project default already runs with reduce, so we need
 a control case too), and a sibling context with
 `reducedMotion: 'no-preference'`. Each context injects a
 `[data-loading="true"]` button into a panel page, samples
 `getComputedStyle(btn, '::after').transform` at multiple frame
 boundaries, and asserts the matrix VALUES CHANGE across samples.
 This is the only Playwright-tractable way to assert "the
 animation is actually running" — checking
 `animationDuration === "0.6s"` would catch the CSS-rule
 regression but not, say, a future `animation-play-state: paused`
 sneaking in via a parent rule.

### Loading state on drawers + lazy panes (`.skel` shimmer)

Two drawer surfaces fire a JSON action between user-click and
content-paint and need a visible loading indicator over that window
(otherwise the chrome reads as blank for the 100-1000ms the request
takes to resolve):

- **Initial drawer open** — `loadDrawer(bid)` in
  `web/themes/default/js/theme.js` fires `Actions.BansDetail`. Until
  the envelope returns, the drawer paints `renderDrawerLoading()`:
  a `[data-testid="drawer-loading"]` header with `aria-busy="true"`
  + `aria-label="Loading player details"` wrapping `.skel` shimmer
  rows tagged with `[data-skeleton]`. The `#drawer-root` element
  also carries `data-loading="true"` so the `_base.ts` page-load
  waiter (and the existing `responsive/drawer.spec.ts` /
  `flows/ui/player-drawer.spec.ts` assertions) gate on the same
  terminal marker.
- **Lazy pane activation** — clicking History / Comms / Notes for
  the first time fires `bans.player_history` / `comms.player_history`
  / `notes.list`. The panel placeholder is `renderPaneSkeleton()`:
  the same `.skel` shimmer rows wrapped in
  `[data-pane-empty][aria-busy="true"]`. The panel itself carries
  `data-loading="true"` for the duration of `loadPaneIfNeeded(tabId)`.

The contract:

- The `.skel` CSS rule lives in `theme.css` (linear-gradient + the
  `shimmer` keyframe + dark-mode override). The class name is
  `.skel` (singular) — NOT `.skeleton`. Pre-fix
  `renderDrawerLoading()` used `class="skeleton"`, which had no
  matching rule, so the shimmer divs rendered with zero background
  and the drawer read as "just blank" for the entire `bans.detail`
  window.
- `prefers-reduced-motion: reduce` is the documented exception
  for the shimmer (#1362, same shape as the spinner). The global
  reset in `theme.css` would otherwise pin
  `animation-duration: 0.001ms !important` +
  `animation-iteration-count: 1 !important` on every selector,
  freezing the shimmer at its 100% keyframe and leaving a static
  gradient that reads as a permanent layout placeholder — not as
  "loading". The shimmer is essential feedback (WCAG 2.3.3
  Animation from Interactions); the `.skel` rule in `theme.css`
  carries a paired
  `@media (prefers-reduced-motion: reduce) { .skel { animation-duration: 1.4s !important; animation-iteration-count: infinite !important; } }`
  block that wins on specificity over the universal `*` reset. The
  regression guard
  (`web/tests/e2e/specs/flows/loading-animations.spec.ts`)
  samples `getComputedStyle(.skel).backgroundPositionX` at multiple
  frame boundaries and asserts the values change across samples —
  the only Playwright-tractable way to prove "the shimmer is
  actually sliding". Pre-#1362 the shimmer froze under reduced
  motion (the v2.0 RC1 sister regression to the spinner's freeze);
  don't reintroduce the freeze. The chrome's *motion-of-state*
  surfaces (drawer slide-in, toast slide-in, chevron rotations)
  continue to honour the global reset correctly — only essential
  motion (spinner + shimmer) is exempt.
- The drawer header skeleton blocks carry `[data-skeleton]`
  (terminal marker for the page-level waiter — they live under
  `#drawer-root[data-loading="true"]`, so they cycle in/out
  cleanly when `bans.detail` resolves). The lazy-pane skeleton
  blocks do NOT carry `[data-skeleton]`: the panel parent starts
  with the `hidden` attribute, but `[data-skeleton]:not([hidden])`
  only checks the matched element's own attribute. A nested
  `[data-skeleton]` block inside a hidden tabpanel would still
  match the selector and stall every page-load wait that runs
  AFTER the drawer opens.
- Use `[data-pane-empty]` as the testability hook for the
  lazy-pane skeleton; the `refreshNotesPane` reset path already
  resets the panel innerHTML with the same helper so the visual
  contract is symmetric across initial-activation and
  post-mutation refreshes.

The regression guard is `web/tests/e2e/specs/flows/drawer-loading-indicator.spec.ts`:
it stalls `bans.detail` via `page.route`, asserts the skeleton
header is visible, the `.skel` block paints a `linear-gradient`
background (the computed-style probe is the regression catch for
the `class="skeleton"` typo — the missing rule leaves
`background-image: none`), releases the route, and confirms the
drawer flips to `renderDrawerBody`. The second test stalls
`bans.player_history` and asserts the History pane's
`renderPaneSkeleton()` paints the same shimmer rows.

### Anti-FOUC theme bootloader (`core/header.tpl` `<head>` script)

Light/dark theme is keyed off the `dark` class on `<html>`, with
`:root` declaring the light tokens and `html.dark` overriding to the
dark tokens. The persisted preference lives in
`localStorage['sbpp-theme']` (the `THEME_KEY` in `theme.js`); values
are `'light'` / `'dark'` / `'system'`, and `'system'` resolves
against `prefers-color-scheme: dark` at boot.

`theme.js` (loaded from the document tail via `core/footer.tpl`) is
the load-bearing path for user interactions — the toggle click and
the matchMedia listener for OS-preference changes mid-session. But
its boot-time `applyTheme(currentTheme())` is NOT what should land
the dark class on first paint: by the time `theme.js` executes, the
parser has finished `<body>` and the browser has already painted the
entire body in light mode (the `:root` defaults). The class flip
then triggers a full repaint the user perceives as a white flash +
content flicker on every page navigation (#1367).

The fix is a tiny inline blocking `<script>` in `<head>` of
`core/header.tpl`, ABOVE the `<link rel="stylesheet">`, that mirrors
`applyTheme(currentTheme())`'s dark-resolution logic: same
`THEME_KEY`, same default (`'system'`), same `mode === 'dark' || (mode
=== 'system' && matchMedia(...).matches)` predicate, and only ADDS
the class (light is the `:root` default, so removing would be a no-op
anyway). It runs synchronously before the body parses, so the very
first paint lands in the user's chosen mode.

The contract:

- The bootloader lives in `web/themes/default/core/header.tpl`
  inside `<head>`, immediately above the stylesheet link. The
  script is parser-blocking + synchronous, so the class is
  guaranteed to be set before `<body>` parses regardless of where
  in `<head>` it lives, but pinning it just above the stylesheet
  makes the "this resolves the CSS cascade" intent obvious.
- The script is a self-contained IIFE wrapped in `try/catch`:
  `localStorage` throws on private-mode iframes / SecurityError,
  and `matchMedia` is missing on very old browsers. In either
  failure mode the bootloader silently falls through to light
  (matching `theme.js`'s defensiveness).
- The bootloader does NOT write to `localStorage` — `theme.js`
  still owns persistence (its boot-time `applyTheme()` writes the
  resolved mode back). The bootloader is read-only on the
  persisted state.
- The bootloader uses `var` (not `let`/`const`) and avoids
  optional chaining / nullish coalescing. The script runs in the
  earliest realm setup phase; any syntax error means the whole
  body would paint in light first. Strict ES5 keeps the surface
  area defensive (theme.js itself uses ES6+, but theme.js failing
  is recoverable — the bootloader failing is the FOUC bug).
- Logic must stay byte-equivalent to `applyTheme(currentTheme())`
  in `theme.js` minus the `localStorage.setItem(...)` write. If
  `theme.js` ever changes the resolution rule (e.g., adds a
  `'high-contrast'` mode), the bootloader has to mirror the
  change in the same PR or the first paint silently desyncs from
  the user's persisted preference.

Regression guard: `web/tests/e2e/specs/flows/theme-fouc.spec.ts`.
The spec uses `page.route` to intercept and STALL the `theme.js`
network request, then asserts the state of `<html>`'s class list
WHILE theme.js is held — i.e. the bootloader is the only thing
that could have set the class. The contract: dark-pinned mode
must read `class="dark"`, light-pinned mode must NOT, and system
+ emulated OS-dark (via `colorScheme: 'dark'` on a fresh
`chromium.newContext()`) must read `class="dark"` via the
matchMedia branch. Releasing the route then lets theme.js boot
normally so the post-load shape is asserted too. This is the
only Playwright-tractable way to prove "the bootloader did it,
not theme.js" — checking `readyState === 'loading'` was tried
and fails because `addInitScript` runs before
`document.documentElement` exists.

The install wizard (`web/install/_chrome.tpl`) does NOT carry the
bootloader. It runs against an unconfigured panel with no
logged-in user and no `theme.js` chrome at all — there's no theme
toggle to gate, so `localStorage['sbpp-theme']` is never set
during install. The wizard inherits the `:root` light defaults,
which is the documented behavior; do NOT add the bootloader there
without a paired theme toggle in the wizard chrome.

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

### Install wizard (`web/install/`)

Self-hoster install surface; runs BEFORE the panel's
`web/init.php` bootstrap. Lifecycle (#1332):

1. `web/install/index.php` — entry. Requires `init.php`
   (paths-only), runs the "panel already installed?" guard
   (`already-installed.php`, #1335 C2), checks `vendor/autoload.php`
   for the recovery short-circuit, then requires `bootstrap.php`
   (Composer + Smarty) and dispatches via `includes/routing.php`.
2. `web/install/init.php` — paths-only bootstrap. NEVER
   touches `vendor/`. Defines `IN_INSTALL`, `PANEL_ROOT`,
   `PANEL_INCLUDES_PATH`, etc. The recovery surface relies on
   this being load-bearing-free of Composer dependencies.
3. `web/install/already-installed.php` — pure inline HTML + CSS
   "panel-takeover prevention" guard (#1335 C2). Loaded after
   `init.php` (so `PANEL_ROOT` is in scope) but BEFORE the
   vendor/-autoload check, so the surface is independent of
   Composer for the same defensiveness reason as `recovery.php`.
   `sbpp_install_is_already_installed(PANEL_ROOT)` returns true
   when `config.php` exists; the rendering helper emits a 409 +
   inline HTML page that links the operator back to `/` (already-
   installed panels boot from there) and explains how to
   reinstall (delete `config.php` first). Same shape as
   `recovery.php` (no `Sbpp\…`, no Smarty, no `vendor/`); the
   sister-guard on the panel runtime side is in `web/init.php`.
4. `web/install/recovery.php` — pure inline HTML + CSS surface
   served when `vendor/` is missing. Serves `503` + the
   "download a release zip OR run `composer install`"
   instructions. Self-contained; never extend it with code
   that needs Composer (the whole point is that it works
   without it). Direct visits with vendor present 302 to
   `/install/` (#1335 m1).
5. `web/install/bootstrap.php` — Composer autoload + the
   subset of the panel's eager-load chain the wizard needs
   (`Sbpp\Db\Database` only) + a Smarty instance configured
   with the panel's default theme dir.
6. `web/install/pages/page.<N>.php` — per-step page handlers.
   Each builds a `Sbpp\View\Install\Install*View` DTO and
   calls `Sbpp\View\Renderer::render($theme, $view)` against
   the install Smarty instance. Step → handler mapping lives
   in `web/install/includes/routing.php`.

Conventions for new wizard work:

- New step → new `web/install/pages/page.<N>.php`, new
  `Sbpp\View\Install\Install<Step>View`, new
  `web/themes/default/install/page_<step>.tpl`. Wire it into
  the dispatcher's `match` and bump `step_count` on every
  view's constructor (the progress stepper reads it).
- The wizard reuses the panel's `theme.css` design tokens
  (button / input / card / typography) but ships its own
  install-only inline CSS in `_chrome.tpl` (`.install-shell`,
  `.install-alert`, `.install-pill`, `.install-grid`,
  `.install-table`, …). Don't grow `theme.css` for
  installer-only chrome — the panel runtime never renders
  these.
- Forms POST natively (`<form method="post" action="?step=N">`).
  No JS-driven navigation. Vanilla JS is allowed only as a
  page-tail script for client-side validation hints — and the
  form's native `required` / `pattern` attributes must be the
  load-bearing gate, with JS as the UX polish. **Don't add
  `novalidate`** to a wizard form: it switches off the native
  pre-submit checks and silently shifts the load to the JS
  handler, which then has to re-implement empty / short / pattern
  / type-mismatch behaviour the browser already does for free
  (and the JS coverage tends to drift behind, leaving server-side
  bounces that wipe sensitive fields like passwords on re-render).
  The canonical cross-field-validation shape, when you genuinely
  need one (the only example today is the admin form's password-
  match check on step 5), is: keep native validation on, hook
  `submit`, run the cross-field check there, surface failures via
  `setCustomValidity(...)` + `reportValidity()` + `e.preventDefault()`,
  clear customValidity on the field's `input` event so the popover
  doesn't keep firing after the user fixes the value
  (`web/themes/default/install/page_admin.tpl` is the reference).
- The wizard runs OUTSIDE the panel's `core/header.tpl` chrome
  because it has no logged-in user, no DB on step 1, no
  `Config::get`, and no `$userbank` — anything that depends on
  the panel's chrome JS (theme.js, palette, lucide.min.js,
  drawer) is unavailable.
- Carry state between steps as **hidden POST fields**, never
  `$_SESSION`. The wizard runs against an unconfigured panel —
  there's no DB to anchor a session against until step 4
  applies the schema, and the operator may abandon the install
  half-way (so a session would silently leak credentials into
  the host's tmp dir).
- Successful POST validation followed by data-forwarding to
  the next step uses the canonical handoff template
  (`page_handoff.tpl` + `InstallDatabaseHandoffView`): a
  noscript-friendly auto-submit form that re-POSTs the
  validated data to `?step=<next>` (302 can't carry POST).
- CSRF is **off** for the wizard. There's no logged-in user yet —
  CSRF tokens have nothing to bind to in the multi-user sense, and
  the pre-install attack surface is intrinsically limited (anyone
  who can reach `/install/` can also overwrite files via the same
  upload channel they used to deploy the panel). The window for
  this surface is the install itself: two paired guards close
  the loop on either side once the wizard finishes:
   - **Panel runtime side** (`web/init.php`): the install/ +
     updater/-presence guard refuses to boot if either directory is
     on disk. Pre-#1335 this guard exempted `HTTP_HOST == "localhost"`,
     which was a panel-takeover path on any panel reachable via a
     `localhost` Host header (port-forward, SSH tunnel, ngrok,
     Cloudflare Tunnel) — that exemption is gone (#1335 C1). The
     replacement escape hatch is the explicit `SBPP_DEV_KEEP_INSTALL`
     constant; see "Dev-only escape hatch" below.
   - **Wizard side** (`web/install/index.php`): the
     "panel already installed?" guard refuses to start the wizard
     when `config.php` exists. Pre-#1335 the wizard had no such
     gate — combined with C1 (or any operator who simply forgot to
     delete `install/`), this was a complete panel-takeover path
     (#1335 C2). The guard surface lives in
     `web/install/already-installed.php` (pure inline HTML + CSS,
     same shape as `recovery.php`).
  Steps 3-6 instead defend against direct-POST
  bypass of step 2's input validation by re-running the same
  validation at the top of every handler — `sbpp_install_validate_prefix`
  on `prefix` (and step 6's `amx_prefix`) is the single source of
  truth, called eagerly so a forged hidden-field POST short-circuits
  to `?step=2` BEFORE any SQL runs (#1332 review).
- **Dev-only escape hatch** (`SBPP_DEV_KEEP_INSTALL`): the docker
  dev stack bind-mounts the worktree (which carries `install/` +
  `updater/` from git) into the panel's web root, so the post-#1335
  guard would refuse to boot the dev panel. The constant is the
  explicit opt-in: defining it tells `sbpp_check_install_guard()`
  to skip the presence check (the same way `IS_UPDATE` does for
  the updater itself). The constant is loud-named so a
  production-side define is visibly wrong; the panel's release
  tarball has no path to set it; only `docker/php/dev-prepend.php`
  (auto-prepended on every request inside the dev container via
  `auto_prepend_file`) actually defines it. Production panels MUST
  NOT define this constant. Reaching for `HTTP_HOST` magic on
  either side of the guard is an anti-pattern (see Anti-patterns).

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

### Filtered chrome navigation surfaces (sidebar + palette)

Two chrome surfaces ship the user a list of "where you can go from
here" entries:

- The sidebar (`web/pages/core/navbar.php` → `core/navbar.tpl`) — the
 vertical nav on the left of every page.
- The command palette (`web/includes/View/PaletteActions.php` →
 `<script id="palette-actions">` in `core/footer.tpl` →
 `theme.js`'s `loadNavItems()`) — the Ctrl/Cmd-K dialog's "Navigate"
 section (#1304).

Both **must filter against the same per-(user, permission) gates**.
Anything else leaks admin entries to logged-out / partial-permission
users; clicking such an entry lands them on the "you must be logged
in" / 403 surface and the chrome reads as broken (#1304 is the
audit issue).

The contract for either surface:

- Public entries (Dashboard, Banlist, Servers) are always shown.
- Public entries that ride a `config.enable*` toggle (Comm blocks /
 Submit / Appeals) are dropped on installs that disabled the
 surface — both surfaces honour the same toggle.
- Admin entries are gated via `HasAccess($mask | ADMIN_OWNER)` so
 owners see everything and per-flag holders see only what they
 can actually use.
- A `null` userbank (CSRF reject path / unhandled-error path
 reaches the chrome before auth) is treated identically to
 logged-out — fail closed.

When adding a new entry to either surface, add the matching entry
to the other in the same PR. The catalog files live next to each
other (`web/pages/core/navbar.php` for the sidebar,
`web/includes/View/PaletteActions.php` for the palette) for exactly
this reason; the two regression suites
(`web/tests/integration/PaletteActionsTest.php` and the existing
navbar coverage in `web/tests/integration/LostPasswordChromeTest.php`)
are the gates.

`web/includes/View/PaletteActions.php` is the only PaletteActions
catalog — never reintroduce the pre-#1304 hardcoded `NAV_ITEMS`
array in `theme.js`. The wire format from the server to the JS
client is the JSON blob's `{icon, label, href}` triple — never
expose the raw `permission` mask to the client (the gate is
server-side, full stop).

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

### Cross-repo JSON contracts (`web/includes/Telemetry/schema-1.lock.json`)

When the panel sends or receives a structured payload that's
**defined in a sibling repo** (currently only the telemetry contract
with [sbpp/cf-analytics](https://github.com/sbpp/cf-analytics)), the
canonical schema is **vendored** as a byte-identical lock file
under `web/includes/<subsystem>/` and consumed via a thin reader
class (e.g. `Sbpp\Telemetry\Schema1`). The reader exposes a
`payloadFieldNames(): list<string>` static — the recursively-flattened
leaf field set — and is the single source of truth for the
**extractor parity test** that asserts the panel's payload builder
(`Telemetry::collect()`) and the schema agree on the field set in
BOTH directions (`assertSame` after sort). Drift in either direction
(extractor without schema slot, or schema slot without extractor)
fails the build.

The field list is NOT mirrored into any human-readable doc — the
schema lock file is the source of truth, and anyone who wants the
field-by-field breakdown reads it. Don't reintroduce a markdown
mirror (the old `TELEMETRY-FIELDS-START` / `TELEMETRY-FIELDS-END`
README block + paired `TelemetryReadmeParityTest`) — it was pure
duplication of the schema with a parity test paying for the drift
risk it created.

Manual sync only — a `make sync-<subsystem>-schema` target pulls
the upstream lock file via curl and overwrites the vendored copy;
no scheduled auto-PR workflow. The parity test gates the result.

When a future subsystem grows a similar cross-repo JSON contract,
follow this shape: vendored Draft-7 JSON Schema lock file + reader
class + extractor parity + manual `make sync-…` target.

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

### Responsive desktop-table chrome (container queries + tiered column hiding)

The bans / comms desktop tables ship 9-10 columns. The sidebar
collapses at `<=1023px` so above that it eats 15rem (240px) of
horizontal real estate before the table sees any pixels; add the
p-6 page padding (48px) and even a 1280px viewport leaves the card
with ~975px to paint a row whose natural sum-of-columns is ~1247px.
The shared chrome that handles this:

- **`.table-scroll` wrapper** around every desktop-table list page
  (`page_bans.tpl` / `page_comms.tpl` / admin-side `mod` / `group` /
  `server` lists). Provides `overflow-x: auto` as the runtime
  escape hatch when a row genuinely exceeds the card after every
  other reduction has run, and (post-#1363) the
  `container-type: inline-size; container-name: tablescroll;`
  context that the column-tier rules below key off.
- **Tiered column-hiding classes** on every `<th>` AND matching
  `<td>` so the column hides as a unit (otherwise rows go out of
  alignment):
  - Tier-1 (always visible): the minimum row that answers
    "who, why, what state, what can I do" — Player, SteamID,
    Reason (banlist) / Type+Player (commslist), Status, Actions.
  - **`.col-tier-3`** hides at `@container tablescroll (max-width:
    1500px)`. The wider trio (IP / Length / Banned / Started;
    ~552px combined).
  - **`.col-tier-2`** hides at `@container tablescroll (max-width:
    1200px)`. Server / Admin (~219px combined).
  - Tier-3 hides FIRST despite the lower tier-number because the
    wider trio reclaims more room than tier-2; dropping it first
    is what actually buys back the table's natural width.
- **`.col-length` width cap** (`max-width: 10rem;
  overflow: hidden; text-overflow: ellipsis;`) because
  `SecondsToString()` builds long strings like
  `"1 mo, 2 wk, 4 d, 8 hr, 19 min, 33 sec"` — six units of
  granularity for one cell. Pair the cap with a `title="…"`
  attribute on the `<td>` so hover / long-press still surfaces
  the full string. The `col-banned` / `col-started` columns
  carry fixed-width ISO timestamps that don't vary per row, so
  they don't need the cap — capping would just emit ellipsis on
  every row without trimming anything meaningful.
- **Page-cap differentiation**: most list pages cap their outer
  wrapper at `max-width: 1400px` (max card ~1352px → tier-3
  always hidden there). Bans / comms specifically lift the cap to
  `max-width: 1700px` (max card ~1652px) so wide-monitor users
  actually see tier-3 columns at viewport `>=1788px`.

Container queries are load-bearing here. Pre-#1363 the tier
breakpoints were viewport-keyed (`@media (max-width: 1535px)`)
and missed the page-cap entirely — a 1920px monitor saw the same
scroll-required layout as a 1535px laptop because both fell into
the "all tiers visible" arm even though the painted card was
identical (1352px, capped by `max-width: 1400px`). Container
queries on `.table-scroll` see the actual painted width of the
card regardless of viewport.

When adding a new desktop-table list page that should ride this
chrome:

1. Wrap the `<table>` in `<div class="table-scroll">`.
2. Tag every column's `<th>` AND matching `<td>` with the right
   tier class — no class means tier-1 always-visible. Be
   conservative: the default is "show everything" and you opt
   in to hiding.
3. If a column's content varies wildly per row (the Length column
   is the canonical example), cap its width with a `.col-<name>`
   rule alongside `text-overflow: ellipsis` and pair with a
   `title="..."` attribute on the cell so the full value stays
   reachable on hover / long-press.
4. The mobile card layout (`.ban-cards` for bans, the comms-list
   equivalent) takes over completely at `<=768px` (`theme.css`
   `.table { display: none }`), so these tier classes only
   collapse the desktop table at intermediate viewports.

Regression guards: `web/tests/e2e/specs/flows/banlist-table-columns.spec.ts`
(STATUS / BANNED layout, `.table-scroll` wrapper presence, Remove
button reachability across 1280 / 1440 / 1920px viewports — the
1280 / 1440 cases land in the "tiers hidden" arm and 1920 lands in
the "tiers visible" arm) and
`web/tests/e2e/specs/flows/banlist-ip-column.spec.ts` (which runs
at 1920px so the IP column — tier-3 — is visible). When you add a
new tier-3 column to either list and it relies on visibility for
the spec, target a 1920px viewport, not 1440px.

## Anti-patterns (do NOT reintroduce)

- `btn.disabled = true` (or any other manual `disabled` flip) inside
 a confirm-modal submit handler or any other action button that
 fires `sb.api.call(...)` from a click handler without an immediate
 page navigation → use `window.SBPP.setBusy(btn, true)` (theme.js)
 with the inline-script local fallback shim. The `disabled` flag is
 the load-bearing gate but it ships without the visual spinner +
 ARIA + reduced-motion contract; users see no feedback during the
 100-1000ms in-flight window and double-click "to make it work",
 queuing duplicate requests until the post-response setter fires.
 See "Loading state on action buttons" in Conventions for the
 contract and the canonical reference shapes
 (`page_comms.tpl` / `page_bans.tpl` / `page_admin_admins_list.tpl`
 confirm dialogs; `page_admin_groups_list.tpl` / `page_admin_groups_add.tpl`
 form submits; `theme.js`'s drawer Notes paths). Regression guard:
 `web/tests/e2e/specs/flows/action-loading-indicator.spec.ts`
 (stalls `Actions.CommsUnblock` via `page.route` and asserts the
 three-attribute busy contract on the submit button + the
 double-click rejection).
- Splitting the `data-loading` + `aria-busy` + `disabled` triple
 (the three attributes `window.SBPP.setBusy` writes) into separate
 setters → reach for `window.SBPP.setBusy` (or the inline-script
 local wrapper that delegates to it). Hand-rolling one of the three
 silently drops one of: the spinner visual, the AT announcement, or
 the double-click gate. The contract is single-source for a reason.
- Removing the `@media (prefers-reduced-motion: reduce)` per-rule
 override that re-enables the spinner's rotation (the rule next to
 `.btn[data-loading="true"]::after`) OR the matching one on `.skel`
 that re-enables the skeleton shimmer → the global
 `*, *::before, *::after` reset further down in `theme.css` would
 otherwise pin `animation-duration: 0.001ms` +
 `animation-iteration-count: 1` on both selectors and the
 animations silently freeze. That's the v2.0 RC1 paired regression
 that motivated #1362: #1361 shipped the busy contract + the
 `.skel` shimmer surfaces but both inherited the freeze from the
 global reset, so users on Windows 11 with "Show animation effects"
 toggled off — or any other path to a `prefers-reduced-motion:
 reduce` CSS resolution — saw a static donut instead of a spinner
 AND a static gradient instead of a sliding shimmer. Loading
 spinners and skeleton shimmers are both essential feedback: WCAG
 2.3.3 Animation from Interactions explicitly exempts essential
 motion (motion that conveys functionality or information from
 stops being communicated without it), and every major design
 system (GitHub Primer, Adobe Spectrum, Material UI, Bootstrap, …)
 keeps loading indicators animating regardless of motion
 preference. The chrome's *motion-of-state* (drawer slide-in,
 toast slide-in, chevron rotations) honours the global reset
 correctly — the busy spinner and the skeleton shimmer are the
 documented exceptions. Regression guard:
 `web/tests/e2e/specs/flows/loading-animations.spec.ts`
 samples both the spinner's `getComputedStyle(::after).transform`
 AND the shimmer's
 `getComputedStyle(.skel).backgroundPositionX` at multiple frame
 boundaries under `reducedMotion: 'reduce'` and asserts the
 values change across samples (the only Playwright-tractable way
 to assert "the animation is actually running" — checking
 `animationDuration` would catch the specific CSS regression but
 miss future `animation-play-state: paused` overrides).
- `class="skeleton"` (singular `.skeleton`) on a drawer / lazy-pane
 placeholder block → the CSS rule has always been `.skel`. Pre-fix
 `renderDrawerLoading()` in `theme.js` emitted `class="skeleton"`,
 which had no matching rule, so the drawer header skeleton rows
 rendered transparent and the drawer read as "just blank" for the
 entire `bans.detail` in-flight window. The fix is single-character:
 `class="skel"`. Regression guard:
 `web/tests/e2e/specs/flows/drawer-loading-indicator.spec.ts`'s
 `getComputedStyle(el).backgroundImage` probe (asserts
 `linear-gradient(...)`; the missing rule leaves it at the UA
 default `none`). Same shape applies to any new skeleton surface:
 reuse the `.skel` class from `theme.css`; don't roll a new
 `.skeleton-*` rule.
- Removing the inline anti-FOUC bootloader from `<head>` of
 `web/themes/default/core/header.tpl` ("theme.js already does this
 on boot, why does it have to be inline?") → theme.js loads from
 `core/footer.tpl` (the document tail), so its boot-time
 `applyTheme(currentTheme())` runs AFTER the parser reaches `</body>`.
 By that point the browser has already painted the entire body in
 light mode (the `:root` tokens default to light), and theme.js's
 class flip triggers a full repaint the user perceives as a white
 flash + content flicker on every page navigation (#1367 — the
 reporter's exact symptom: "the page briefly renders in light mode
 for a split second before switching back to dark"). The bootloader
 is the inline-script-in-`<head>` pattern every modern theme-toggle
 implementation uses (Tailwind docs, Next.js docs, GitHub, Vercel)
 — it has to run BEFORE the body parses, which means it has to be
 inline (no external `<script src=…>` because the network round-trip
 would defeat the point) and it has to be in `<head>` (so the parser
 reaches it before the body tags). Regression guard:
 `web/tests/e2e/specs/flows/theme-fouc.spec.ts` uses `page.route`
 to stall the `theme.js` network request, then asserts the `dark`
 class is present (or absent, in light mode) on `<html>` WHILE
 theme.js is held — proving the bootloader, not theme.js, did
 the class flip. Pre-fix the dark / system arms read `false` (no
 class because theme.js was stalled and was the only path); post-fix
 they read `true` because the inline bootloader runs in `<head>`
 long before the parser reaches `<script src="theme.js">`.
- Letting the inline bootloader's resolution logic drift from
 `theme.js`'s `applyTheme(currentTheme())` (e.g., adding a new
 `'high-contrast'` mode to theme.js without mirroring in the
 bootloader, or vice versa) → the first paint resolves to one
 mode, theme.js's boot-time call resolves to a different mode,
 the user sees a flicker on every navigation. The bootloader is
 the read-only mirror of `applyTheme()`'s resolution rule — same
 `THEME_KEY` ('sbpp-theme'), same default ('system'), same
 dark-resolution predicate. The bootloader's only difference is
 it doesn't `localStorage.setItem(...)` (theme.js still owns
 persistence). Any change to theme.js's resolution logic has
 to land a paired bootloader update in the same PR.
- Moving the bootloader to an external `<script src="…">` ("inline
 scripts are smelly, let's externalize") → an external script adds
 a network round-trip BEFORE the bootloader can run, and the
 browser will paint in light mode in the meantime. The whole point
 of the inline shape is that the script's execution is bound to
 parse time, not to network completion. Same reason `<head>` is
 the load-bearing location: a `<script>` at the bottom of `<body>`
 (or `defer`/`async`) wouldn't help. The script is ~10 lines of
 ES5 — well under any "inline scripts are bad for caching"
 threshold.
- `[data-skeleton]` on a placeholder block nested inside a `hidden`
 ancestor (lazy tabpanels, off-screen drawers, collapsed
 `<details>`) → the `_base.ts` page-load waiter blocks until
 `'[data-loading="true"], [data-skeleton]:not([hidden])'` returns
 no nodes. `:not([hidden])` only checks the matched element's own
 attribute, not its ancestors, so a `[data-skeleton]` block inside
 a hidden tabpanel still matches the selector and stalls every
 page-load wait that runs AFTER the drawer opens (silent 30s
 timeout in CI). Keep `[data-skeleton]` reserved for surfaces
 where the marker itself (or its direct container) carries the
 visibility toggle. The lazy-pane skeletons use `[data-pane-empty]`
 + `aria-busy="true"` as the testability hooks instead.
- Top-level `class Foo {}` (global namespace) in `web/includes/`
  → all classes there live under `Sbpp\…` (see "Namespacing" in
  Conventions for the per-class table). The only intentional
  exception is `LightOpenID` in `Auth/openid.php` (third-party,
  also excluded from PHPStan). Issue #1290 phase B. The legacy
  global names (`Database`, `CUserManager`, `Log`, …) still resolve
  because each namespaced file emits a `class_alias(\Sbpp\…\X::class,
  'X')` below the class declaration; a follow-up PR will burn those
  shims as call sites adopt the namespaced names. New code consumes
  the namespaced names directly via `use Sbpp\Db\Database;` etc.
- Removing the eager `require_once` chain at the top of `web/init.php`
  / `web/tests/bootstrap.php` / `web/phpstan-bootstrap.php` "now that
  PSR-4 autoloading exists" → the autoloader fires on the
  **namespaced** name (`Sbpp\Db\Database`); the `class_alias` shim that
  registers the legacy global name (`Database`) is a runtime call
  inside the file. Without the explicit `require_once`, procedural
  code that says `new Database()` triggers an autoload lookup for
  global `Database`, finds nothing (the autoloader resolves the
  namespaced name, not the alias), and dies. The `require_once`
  chain is the bridge that runs the `class_alias` calls eagerly so
  both names resolve from request entry. Drop them only when the
  follow-up PR has burned every legacy global-name call site in the
  codebase. (init.php, phpstan-bootstrap.php, and tests/bootstrap.php
  each load all 14 namespaced legacy classes — Crypto, CSRF, JWT,
  NormalAuthHandler, SteamAuthHandler, Auth, Host, UserManager,
  AdminTabs, Database, Config, Log, ApiError, Api — keep the three
  lists in sync. Asymmetry is the latent regression: a class loaded
  by phpstan-bootstrap.php but not init.php would pass static
  analysis and die at runtime in any code path the autoload hadn't
  already triggered.)
- `@param int $x` / `@return string` docblocks where PHP can express
  the type natively → use the native parameter / return type
  declaration instead. The docblock stays only when the type carries
  refinement PHP can't express (e.g. `list<array{slug: string, …}>`).
  Removed wholesale across legacy classes by issue #1290 phase A.
- Non-`final` classes in `web/includes/` that nothing extends → mark
  `final class`. Same applies in `web/includes/Auth/Handler/` and
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
- ADOdb → use `Sbpp\Db\Database` (PDO; legacy `Database` alias still
  resolves via `class_alias`).
- MooTools / React / a runtime bundler → vanilla JS in `web/scripts/`.
- `web/scripts/sourcebans.js` (the v1.x ~1.7k-line bulk file shipping
  `ShowBox`, `DoLogin`, `LoadServerHost`, `selectLengthTypeReason`, …)
  → removed at v2.0.0 (#1123 D1). Page-tail helpers are inlined as
  self-contained vanilla JS per page (see `web/pages/admin.edit.ban.php`
  / `admin.edit.comms.php` for canonical examples); toasts go through
  `window.SBPP.showToast` from the theme JS.
- `onclick="if (typeof <Helper> === 'function') <Helper>(...)"`
  legacy-helper presence guards in templates (the v1.x sourcebans.js
  defensiveness pattern that survived the #1123 D1 deletion of the
  bulk JS file) → wire to the JSON API via `data-action` + a page-tail
  vanilla-JS dispatcher per the canonical confirm-modal shape under
  "Add a confirm + reason modal" in "Where to find what". Pre-#1352 the
  trash-can button on `?p=admin&c=admins` carried
  `onclick="if (typeof RemoveAdmin === 'function') RemoveAdmin(...)"`;
  the `typeof X === 'function'` test silently resolved to `false`
  (sourcebans.js was deleted with v2.0.0 — there's no `RemoveAdmin`
  anywhere) so every click was a no-op with no console error / no
  toast / no API call. The class of bug is invisible by design (the
  guard exists precisely to swallow the missing-helper case), so
  there's no runtime gate; every call site needs the structural fix.
  When migrating: drop the inline `onclick`, mark the trigger with
  `data-action="<surface>-<verb>"` + `data-<id>` + `data-name` +
  `data-fallback-href`, ship a `<dialog>` for the confirm + optional
  reason field, and a page-tail script that dispatches to
  `sb.api.call(Actions.PascalName, …)`. The `Actions.PascalName`
  shape (NOT a string literal) catches typos at api-contract
  regen time. Search anchor for the cleanup sweep:
  `rg "typeof \w+ === ['\"]function['\"]" web/themes/`.
- `web/scripts/contextMenoo.js` / `sb.contextMenu` / global
  `AddContextMenu` → removed at #1306. The vanilla shims were
  back-compat scaffolding for the MooTools-era right-click menu the
  legacy `LoadServerHost` helper wired onto each player row on the
  public Servers page (`page_servers.tpl`). `LoadServerHost` was
  deleted with `sourcebans.js` at #1123 D1 and the v2.0.0
  `page_servers.tpl` rewrite never re-registered the menu, leaving
  the helpers as dead code. **#PLAYER_CTX_MENU** restored the menu
  itself under a new contract: `web/scripts/server-context-menu.js`
  attaches a single `document.addEventListener('contextmenu', …)`
  filtered by `closest('[data-context-menu="server-player"]')` and
  reads SteamIDs from the `data-steamid` attribute on each `<li
  data-testid="server-player">` row that `server-tile-hydrate.js`
  emits. The SteamIDs themselves come from a paired RCON `status`
  round-trip per server cached via `Sbpp\Servers\RconStatusCache`
  (sid-keyed, ~30s TTL, negative-caches failures so an unreachable
  server costs one probe per window). `api_servers_host_players`
  attaches the SteamID side-channel ONLY when the caller holds
  `WebPermission::Owner | WebPermission::AddBan` AND has per-server
  RCON access via `_api_servers_admin_can_rcon` — the action stays
  publicly registered (anonymous viewers still see hostname / map /
  online count); the SteamID surfacing is what's permission-gated.
  The anti-pattern that stays anti is the MooTools-era plumbing —
  `sb.contextMenu`, `AddContextMenu`, the global helpers, the
  separate `contextMenoo.js` file. Reach for the documented
  data-attribute hooks instead. Don't reintroduce the help text
  without the wiring (`page_servers.tpl`'s hint copy is now gated
  on `$can_use_context_menu` so anonymous viewers don't see it).
- `web/install/scripts/sourcebans.js` + `web/install/template/*.php`
  procedural-PHP-template wizard (the v1.x install surface that
  rendered through `header.php` + `page.<N>.php` + `footer.php`,
  pulling MooTools and a wizard-local `sourcebans.js` for `ShowBox()`
  / `$E()` / `$('id')` helpers) → removed at #1332. Every script
  reference was already broken at #1123 D1 (the sister files in
  `web/scripts/` were deleted), so popups / Enter-to-submit shortcuts
  / keyboard nav hints had been silently dead for two minor versions.
  The new wizard renders through typed `Sbpp\View\Install\Install*View`
  DTOs + Smarty templates under `web/themes/default/install/`,
  reuses the panel's `theme.css` design tokens, and uses vanilla JS
  only where strictly necessary (the license-accept checkbox guard
  on step 1, the admin form's cross-field password-match check on
  step 5, and the auto-submit handoff form between step 2 and 3).
  The admin form's empty / short-password / bad-SteamID / bad-email
  cases ride the form's native `required` / `minlength="8"` /
  `pattern` / `type="email"` attrs (no `novalidate`) so the
  browser surfaces the popover before our JS handler runs — only
  password-match is left to the JS guard because it's the one
  validation native HTML can't express. Re-introducing a separate
  JS bundle for the wizard
  is an anti-pattern: the wizard has no logged-in user, no DB on
  step 1, no `Config::get`, and no `$userbank` — it cannot use the
  panel's chrome JS (`theme.js` / palette / command-K), and a parallel
  bundle would diverge from the design-system tokens the wizard
  visually shares with the panel.
- `$_SERVER['HTTP_HOST'] != "localhost"` exemption on the panel
  runtime's install/ + updater/-presence guard → removed at #1335 C1.
  Pre-fix `web/init.php` exempted any panel reachable via a
  `localhost` Host header (port-forward, SSH tunnel, ngrok,
  Cloudflare Tunnel) from the post-install / post-upgrade safety
  check, which was a complete panel-takeover path — anyone hitting
  the panel with a forged Host could re-run the wizard over the
  live install (combined with #1335 C2's missing wizard-side gate)
  and silently bypass the README's "delete `install/` directory"
  step. The guard is now unconditional; the docker dev stack rides
  the explicit `SBPP_DEV_KEEP_INSTALL` constant instead (defined
  by `docker/php/dev-prepend.php` via `auto_prepend_file`). Don't
  reach for `HTTP_HOST` magic on either side of the guard. Don't
  add a `$_SERVER`-driven exemption ("trusted reverse proxy
  network" etc.) — the guard's job is to refuse to boot when the
  install/ + updater/ directories are still on disk; the only
  legitimate dev workflow is the loud-named explicit-define
  escape hatch. Regression guard:
  `web/tests/integration/InstallGuardTest.php`.
- Allowing the wizard to start over a panel where `config.php`
  exists → removed at #1335 C2. Pre-fix anyone reaching `/install/`
  after a successful wizard run could walk the entire flow again,
  overwriting `config.php` (when writable), creating a new admin
  account, and re-pointing the panel at a different DB —
  panel-takeover. The wizard now refuses to start when
  `config.php` exists in the panel root, surfacing the
  `web/install/already-installed.php` page (pure inline HTML + CSS,
  mirror of `recovery.php`'s contract) with a link to `/` and
  instructions for the rare "I really do want to reinstall" path
  (delete `config.php` first). Don't introduce a confirm-dialog
  bypass (`?confirm-reinstall=1`, etc.) — the explicit
  delete-`config.php` step is the only safe path because it
  forces the operator to acknowledge the impact before the
  wizard touches any state. Regression guard:
  `web/tests/integration/InstallGuardTest.php`.
- Bare-text `die('SourceBans++ is not installed')` /
  `die('Please delete the install directory')` /
  `die('Compose autoload not found')` in `web/init.php` →
  removed at #1335 M1. The CTA on the wizard's done page sends
  the operator straight to `/`, and these die paths emit a stark
  white 200-response with no chrome / no link to docs / no
  explanation — read like a server crash to a non-technical
  self-hoster. The replacements live in `web/init-recovery.php`
  (`sbpp_render_install_blocked_page()`); the missing-config
  case redirects to `/install/` instead of dying. Anti-pattern:
  reintroducing bare-text `die()` for any pre-bootstrap error
  path in `web/init.php` — every such surface is an operator
  failure mode that deserves chrome.
- Surfacing the raw `PDOException` message on the wizard's
  database step → removed at #1335 m4. Pre-fix the wizard
  emitted `SQLSTATE[HY000] [1045] Access denied for user
  'sourcebans'@'192.168.96.5' (using password: YES)` verbatim
  — gibberish to non-DBAs, plus the IP is the panel-as-seen-by-DB
  internal address (minor information disclosure). The
  `sbpp_install_translate_pdo_error()` helper translates the
  four common error codes (1045 / 2002 / 1049 / 1044) and falls
  back to the raw message for unrecognised codes so debugging
  stays possible. Anti-pattern: surfacing `$e->getMessage()`
  directly to operator-facing error banners.
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
- Reusing the same `:name` PDO placeholder more than once inside a
  query while calling `bind(':name', …)` only ONCE → the panel runs
  PDO with `EMULATE_PREPARES => false` (default since #1124 / #1167's
  `LIMIT '0','30'` MariaDB regression), so the MySQL driver expands
  every `:name` occurrence into its own positional `?` slot in the
  prepared statement. A single `bind()` leaves the others unbound
  and `execute()` raises `SQLSTATE[HY093] Invalid parameter number`
  (#1314 — `admin.srvadmins.php`'s `:sid` / `:sid` / `bind(':sid', …)`
  shape, which Just Worked under emulated prepares pre-#1124 and
  fataled on every page load post-#1124). Either rename each
  occurrence (`:sid` + `:sid_inner`) and `bind()` each, or pass the
  values via `resultset(['sid' => …, 'sid_inner' => …])`. The
  `:prefix_` literal is rewritten by `Database::setPrefix()` before
  `prepare()`, so reuse there is harmless and stays out of this
  rule. Re-flipping `EMULATE_PREPARES` back to `true` to mask the
  bug is a sibling anti-pattern — it would silently reintroduce the
  `LIMIT '0','30'` trap (`page.banlist.php` / `page.commslist.php`
  rejected by MariaDB strict mode). See "Database" under Conventions.
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
  `web/includes/Auth/openid.php` (third-party). Issue #1290 phase G.
- `array(…)` literal constructor → `[…]` short-array syntax. PHP 5.4+
  shape; the only reason `array(…)` survived this long was nobody got
  around to it. Excluded: `web/includes/Auth/openid.php` and
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
- Discarded return values from `Api::redirect()` or `CSRF::validate()`
  → these carry `#[\NoDiscard]` (PHP 8.5). The return is the
  meaningful signal — `Api::redirect()`'s envelope IS the navigation
  (callers must `return Api::redirect(...)` so the dispatcher honours
  it), and `CSRF::validate()`'s bool IS the verdict (callers either
  branch on it or use the higher-level `rejectIfInvalid()` helper).
  PHPStan's `method.resultDiscarded` rule fails the build on a
  discarded site. Issue #1290 phase K.1.
- Hardcoded chrome-navigation lists in `theme.js` (the pre-#1304
  `NAV_ITEMS` array shape) → the command palette's "Navigate" entries
  are server-rendered + permission-filtered via
  `Sbpp\View\PaletteActions::for($userbank)` and emitted as a JSON
  blob inside `<script type="application/json" id="palette-actions">`
  in `core/footer.tpl`. theme.js consumes the blob via `loadNavItems()`
  with a fail-empty fallback. The pre-fix array showed `Admin panel`
  and `Add ban` to logged-out / partial-permission users, who got
  bounced off the "you must be logged in" / 403 surface when they
  clicked through (#1304). New chrome-navigation entries land in
  `PaletteActions::entries()` next to the existing rows; never inline
  a parallel hardcoded list client-side. See "Filtered chrome
  navigation surfaces" in Conventions for the per-surface contract.
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
- Viewport-based `@media` queries for hiding `.table` columns
  (`@media (max-width: 1535px) { .col-tier-2 { display: none; } }`
  shape) → use `@container tablescroll (max-width: …)` rules
  against the container context `.table-scroll` carries (post-#1363
  it ships `container-type: inline-size; container-name: tablescroll;`).
  The viewport-keyed predecessors silently missed the page-cap on
  every list page — bans / comms cap their outer wrapper at 1700px,
  most other list pages cap at 1400px, and at viewport `>=1688px` the
  painted card is the same fixed width regardless of how wide the
  screen is. A 1920px monitor saw the same scroll-required layout as
  a 1535px laptop because the viewport breakpoint kept tier-2 / tier-3
  visible at both even though the painted card was identical (1352px
  on the 1400px-capped pages). Container queries on `.table-scroll`
  see the actual painted width and react accordingly. See
  "Responsive desktop-table chrome" in Conventions for the full
  contract; the regression guards are
  `web/tests/e2e/specs/flows/banlist-table-columns.spec.ts` (asserts
  Remove button reachability at 1280 / 1440 / 1920px) and
  `web/tests/e2e/specs/flows/banlist-ip-column.spec.ts` (asserts the
  IP column — tier-3 — is visible at 1920px).
- Lifting an arbitrary list page's outer-wrapper `max-width` past
  1400px without auditing every column's per-row content cost →
  bans / comms specifically lifted to 1700px at #1363 because (a)
  the columns include the row-actions cell which can't shrink past
  the action-button text labels, and (b) the SecondsToString-built
  Length column is the single biggest contributor to the table
  min-content. The lift only paid off because the column-tier
  hiding migrated to container queries in the same commit so the
  wider card actually surfaces tier-3 columns at viewport
  `>=1788px`. A naive "raise the cap on every list page so users
  with wide monitors see more" loses the value the cap provides
  (line lengths in cell content stay readable; page chrome doesn't
  feel windswept on ultrawide monitors) AND, on pages where the
  column-tier classes were never added (admin reports, audit log,
  etc.), exposes the row to clipping or the in-card horizontal
  scroll the wrapper was supposed to be a safety net for, not a
  primary scrolling surface. The 1700px cap is justified per page;
  most list pages should stay at 1400px.
- Non-wrapping `display: flex` on `.table .row-actions` (the
  pre-#1359 shape: `display: flex; gap: 0.25rem; justify-content:
  flex-end` with NO `flex-wrap: wrap`) → with 3+ buttons carrying
  text labels (Edit / Unban / Re-apply / Copy / Remove on the
  banlist; Edit / Unmute / Re-apply / Remove on the commslist) the
  cell's natural width is ~340-440px on a single line; combined
  with the rest of a 9-10-column list at the default desktop
  viewport (1280px → main ~1000px usable after the sidebar) the
  table's natural width pushes well past the panel even after
  tier-2 / tier-3 columns hide. `.table-scroll`'s `overflow-x: auto`
  then triggers an in-card horizontal scrollbar and the rightmost
  Remove button silently sits off the visible card edge until the
  user discovers the scroll (#1359 — the user-reported regression
  on the banlist after #1354's row-action parity sweep added the
  text labels, but the same shape lurks on commslist too). The
  contract is `flex-wrap: wrap` on the desktop table's
  `.row-actions` so buttons stack onto a second line when there's
  no horizontal room — same shape the mobile `.ban-card__actions`
  (line ~1446) and the mobile `details.queue-row > summary >
  .row-actions` (line ~1351) already use. The cell still carries
  `white-space: nowrap` so each individual button's content stays
  on one line; only the BETWEEN-button gap wraps. Regression
  guard: `web/tests/e2e/specs/flows/banlist-table-columns.spec.ts`'s
  "Remove button is reachable without horizontal scroll on a
  realistic row" assertion. Don't reach for a different fix shape
  (icon-only buttons, viewport-keyed display: none on individual
  actions, overflow menus) without first asking whether the
  established `flex-wrap` pattern is enough — every existing
  row-action surface in the panel already converges on it.
- Surfacing per-row admin-authored comments behind a silent count
  badge (`<span class="text-xs text-muted">[N]</span>`) without an
  affordance → that's the v2.0 RC regression that wiped per-ban
  comment discoverability after the #1123 D1 sourcebans.js cutover
  deleted the `mooaccordion` inline panel (#BANLIST-COMMENTS).
  The page handlers (`page.banlist.php` line 766 / `page.commslist.php`
  line 734) still build per-row `commentdata`, but a non-interactive
  badge with no visual affordance reads as decorative — users
  reasonably conclude the comments are gone, and the commslist
  case is worse because there's no drawer fallback either. New /
  reworked surfaces with per-row comment data must use the inline
  `<details data-testid="ban-comments-inline">` disclosure shape
  (`page_bans.tpl` / `page_comms.tpl` reference). The drawer's
  `[data-testid="drawer-comments"]` mirror is the secondary surface
  on the banlist; on the commslist it's the only display the public
  table cell offers. Don't reach for a `<button>` that opens the
  drawer "to save bytes" — every row already carries the data
  payload the disclosure renders, so the byte cost is fixed; the
  drawer round-trip is a per-click latency spike for admins
  scanning a list.
- Bare HTML-entity glyphs (`&#9998;` ✎ / `&#10003;` ✓ / `&#8634;` ↺
  / `&#128203;` 📋 / `&#10005;` ✕ / etc.) as row-action button labels
  → use Lucide icons (`<i data-lucide="pencil|check|rotate-ccw|copy|trash-2|x">`)
  with an optional visible text label. The bare-entity shape was
  what the v2.0 banlist row-actions cell shipped while the sibling
  commslist row used Lucide icons + text labels — the inconsistency
  read as broken icons / a different app, the icon-only buttons
  gave no visual affordance, and screen readers announced the
  glyph code rather than the action. The shared button class chain
  (`.btn--ghost btn--sm` for Edit/Copy/Remove, `.btn--secondary btn--sm`
  for Unban/Re-apply with a danger color override on Remove) is what
  page_comms.tpl established as the canonical reference and what
  page_bans.tpl now mirrors. Icons should be 13×13 inline-sized
  (`style="width:13px;height:13px"`) for visual parity with the
  rest of the panel chrome. Pinned by
  `web/tests/integration/PublicBanListRegressionTest::testBanlistRowActionsUseLucideIconsNotEntityGlyphs`.
- `onclick="event.stopPropagation()"` on a `[data-copy]` button →
  the document-level COPY BUTTONS delegate in `theme.js` listens on
  the bubble phase, so an element-level `stopPropagation` silently
  kills it (no toast, no clipboard write, no console error — exactly
  the symptom in #1308 Defect A). The desktop banlist row's drawer
  trigger is the player-name anchor, NOT a row-level delegate, so a
  bubbling click from a sibling button has nothing to confuse. If a
  future row-level click handler is genuinely needed, switch the
  delegate to capture phase (`addEventListener('click', …, true)`)
  rather than re-adding stopPropagation; capture fires top-down
  before any element-level stop can intervene.
- `if (navigator.clipboard) navigator.clipboard.writeText(value);
  showToast({kind:'success', title:'Copied'});` — the unconditional
  success toast lies on plain HTTP / non-secure contexts where
  `navigator.clipboard` is `undefined` (typical self-hoster behind a
  TLS-terminating reverse proxy where the panel sees plain HTTP).
  Same shape, different wreckage: `writeText()`'s Promise can reject
  (permission denied, focus stolen) and the success toast still
  fires. Copy affordances must (1) feature-detect both
  `navigator.clipboard` AND `window.isSecureContext`, (2) chain
  `.then(success, fallback)` on the Promise so a rejection drops
  to a fallback, (3) reach for the `copyFallback()` hidden-textarea
  + `document.execCommand('copy')` shape on either failure path. The
  COPY BUTTONS delegate in `theme.js` is the single source for every
  `[data-copy]` surface; mirror its shape (and `handlePaletteCopyShortcut`
  for the Ctrl/Cmd+Enter palette path) when adding new copy hooks
  outside the document delegate (#1308 Defect B).
- Reason-less, no-confirm unban / unmute / ungag (or any other
  irreversible state-flip on a row) → the JSON action AND the
  legacy GET fallback both must require a non-empty `ureason`,
  and the row's affordance must open a confirm modal that prompts
  for it. v1.x had both safeguards via sourcebans.js's
  `UnbanBan()` / `UnMuteBan()` / `UnGagBan()` helpers; v2.0
  silently accepted `ureason=''` for ~18 months and the audit
  log lost the *why* behind every block lift (#1301). The
  reference shape is `#bans-unban-dialog` / `#comms-unblock-dialog`
  in `page_bans.tpl` / `page_comms.tpl` (see "Add a confirm +
  reason modal …" in "Where to find what"). `Log::add(LogType::Message,
  "Player Unbanned", "$name … Reason: $ureason")` is the
  audit-trail shape — drop the reason in the message, never the
  bare "Player X has been unbanned." that v2.0 shipped.
- Native `required` on the textarea inside a confirm + reason
  `<dialog>` form → use `aria-required="true"` only. The native
  `required` constraint fires the browser's own validation
  popover BEFORE the form's `submit` event reaches our handler,
  swallowing the inline-error UX (the testid we surface for
  empty-reason inline errors stays `hidden` because our
  `e.preventDefault(); showError('Please leave a comment.')`
  path never runs). `aria-required` keeps assistive tech in the
  loop without arming the native gate; the JS submit handler is
  the client-side error display, and the server is the
  load-bearing gate.
- Inert `[data-hydrate="<field>"]` placeholders (or any other
  hydration-target attribute) on a `[data-testid="server-tile"]`
  card without the `web/scripts/server-tile-hydrate.js` script
  include + `data-server-hydrate="auto"` wrapper attribute → the
  cells stay at the em-dash forever, which is exactly the regression
  #1313 fixed on the admin Server Management list. Both card-grid
  surfaces (public + admin) ride the shared helper; new surfaces
  with the same card shape MUST emit the canonical testids
  (`[data-testid="server-{status,map,players,host}"]`) and pull
  the helper rather than copy-paste a fresh inline `<script>` block.
  See "Hydrate server-tile cards…" under "Where to find what" for
  the full selector + container contract.
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
| Edit a docs page or add a new one (the Astro + Starlight site published at sbpp.github.io) | `docs/src/content/docs/<group>/<slug>.md` (or `.mdx` when the page uses tabs / cards / asides — e.g. `getting-started/quickstart.mdx`, `setup/mariadb.mdx`). New pages also need a sidebar entry in `docs/astro.config.mjs` (the `sidebar:` array). Site config + theme tokens live in `docs/astro.config.mjs` + `docs/src/styles/sbpp.css`. The Starlight chrome ships from `@astrojs/starlight`; layout overrides land under `docs/src/components/` (see `ThemeProvider.astro` for the canonical override shape). Local dev: `cd docs && npm install && npm run dev`. CI gates: `.github/workflows/docs-build.yml` (per-PR build), `docs-deploy-trigger.yml` (main → repository_dispatch into sbpp.github.io), `docs-screenshots.yml` (gated on the `affects-ui` label, runs `docs/scripts/capture.mjs`). Source of truth is here; sbpp.github.io is the deploy shell only (#1333). |
| Refresh installer / panel screenshots used in docs pages | `docs/scripts/capture.mjs` (Playwright; `npm run capture` in `docs/`). Output lands under `docs/src/assets/auto/{install,panel}/<stable-slug>.png` so docs pages keep referencing the same path across runs. CI does this automatically on PRs labelled `affects-ui`; locally run after `./sbpp.sh up`. STEAM_API_KEY is the all-zero dummy `00000000000000000000000000000000`. |
| Add a JSON action                      | `web/api/handlers/_register.php` + `web/api/handlers/<topic>.php` |
| Add or rename a permission             | `web/configs/permissions/web.json`, then regen contract  |
| Render a page                          | `web/pages/<page>.php` + `web/includes/View/*View.php`   |
| Gate compute that only feeds legacy theme-fork output (e.g. `wantsLegacyAdminCounts()`) | `web/includes/Theme.php` (`Sbpp\Theme`) — predicates page handlers ask before they pay for DTO fields the shipped default theme doesn't render. Default returns `false`; forks opt back in via `define('<flag>', true)` in their `theme.conf.php`. First user (#1270): `Sbpp\Theme::wantsLegacyAdminCounts()` keeps the 9-COUNT subquery + `getDirSize(SB_DEMOS)` walk off `web/pages/page.admin.php`'s default-theme path. New compute-paying-for-fork-only-output surfaces add a sibling `wants<X>()` predicate; the regression test in `web/tests/integration/AdminHomePerformanceTest.php` is the reference shape (resets `Sbpp\Theme::legacyComputeCount()`, asserts the gated branch did NOT fire on the default theme). |
| Edit a template                        | `web/themes/default/*.tpl`                               |
| Reuse the moderation-queue card layout (admin submissions / protests, mobile-stacked summary rows) | `web/themes/default/css/theme.css` (`.queue-row`, `.queue-row__body`, `.queue-row__date` — #1207 PUB-2). Apply by adding `class="queue-row …"` to the outer `<details>` and dropping the inline `flex` / `flex-shrink:0` styles from the summary children. |
| Add visible row actions to a table-rendered admin list (Edit / Unmute / Remove buttons + responsive mobile-card mirror) | `web/themes/default/page_comms.tpl` (#1207 ADM-5) is the canonical reference: `<button class="btn btn--secondary btn--sm">` / `<a class="btn btn--ghost btn--sm">` inside a `.row-actions` cell, plus `.ban-card__actions` row of identical-data-action buttons in the mobile card. Wire destructive / state-changing buttons via `data-action="…"` + `data-bid` + `data-fallback-href`; the inline page-tail JS calls `sb.api.call(Actions.PascalName)` and falls back to the GET URL if the JSON dispatcher is absent. The public banlist (`web/themes/default/page_bans.tpl`) follows the same shape — same chrome (Lucide icon + visible text label inside `.btn--ghost` / `.btn--secondary btn--sm`), same `.ban-card__actions` mobile row, same `data-action` / `data-fallback-href` wiring (`bans-unban` / `bans-delete`). The Remove affordance points at the legacy GET handler (`?p=banlist&a=delete&id=…&key=…` at the top of `page.banlist.php`) because no JSON `bans.delete` action exists yet — the inline JS `confirm()`-prompts then navigates, mirroring commslist's flow without adding a new handler / snapshot / permission-matrix entry. |
| Add a confirm + reason modal for an irreversible row-level action (unban, lift comm block, delete admin, …) | `web/themes/default/page_bans.tpl` (`#bans-unban-dialog`, `Actions.BansUnban`) and `web/themes/default/page_comms.tpl` (`#comms-unblock-dialog`, `Actions.CommsUnblock`) are the canonical reference (#1301), with `web/themes/default/page_admin_admins_list.tpl` (`#admins-delete-dialog`, `Actions.AdminsRemove`, #1352) as the third reference for the optional-reason variant. Shape: a `<dialog hidden>` with a `<form method="dialog">` carrying a `<textarea aria-required="true">` (or `aria-required="false"` for the optional-reason variant — see admins-delete) (NOT the native `required` — that lets the browser block the form submit before our handler runs, swallowing the inline-error UX), a Cancel button, and a Confirm submit button. The page-tail JS opens the dialog via `showModal()` on `[data-action]` clicks, validates the trimmed reason on submit (load-bearing gate is server-side), forwards `ureason` to the JSON action, and on success flips the row in place via the same `flipRowToUnbanned`/`flipRowToUnmuted` helper the legacy single-click flow used (or removes the row outright + decrements the count badge for the admins-delete variant where there's no "now-unbanned" state to render). The legacy GET fallback (`?p=banlist&a=unban&id=…&key=…&ureason=…` / `?p=commslist&a=ungag…&ureason=…`) is the no-JS / hand-edited-URL path; both halves now reject empty `ureason` server-side so the audit log carries the *why*. The admins-delete variant has no legacy GET handler — `RemoveAdmin()` always went through the JSON dispatcher pre-#1123 D1 — so its `data-fallback-href` lands the operator back at the admins list as a graceful no-op when the JSON dispatcher is missing entirely (third-party theme stripping `api.js`); the audit-log "Reason: …" suffix is only emitted when `ureason` is non-empty (vs always-emitted on the bans / comms variants where reason is required). **Do not** put `onclick="event.stopPropagation()"` on the trigger button — `document.addEventListener('click')` is how the dialog opener picks the click up, and stopPropagation would silently swallow it (the action button isn't inside any `[data-drawer-href]` ancestor anyway, so the defensiveness was a copy-paste from the row-name anchor that doesn't apply here). The submit button MUST flip through `setBusy(submitBtn, true)` BEFORE `sb.api.call(...)` leaves the page and clear via `setBusy(submitBtn, false)` on every non-navigating response branch — see "Loading state on action buttons" in Conventions for the contract, the inline-script local wrapper shape, and the regression guard. |
| Add a loading indicator to an action button that fires `sb.api.call(...)` without a page refresh | `window.SBPP.setBusy(btn, busy)` (`web/themes/default/js/theme.js`) writes the `data-loading="true"` + `aria-busy="true"` + `disabled` triple atomically; the CSS spinner lives in `web/themes/default/css/theme.css` under `.btn[data-loading="true"]` + the `sbpp-btn-spin` keyframe. Inline page-tail scripts inside `.tpl` files define a local `setBusy(btn, busy)` wrapper that delegates to `window.SBPP.setBusy` when present and falls back to `btn.disabled = busy` so third-party themes that strip `theme.js` still gate against double-clicks. Canonical reference shapes: the three confirm-dialog flows (`page_comms.tpl` / `page_bans.tpl` / `page_admin_admins_list.tpl`), the form-submit flows (`page_admin_groups_list.tpl` / `page_admin_groups_add.tpl` / `page_admin_bans_add.tpl` / `page_admin_bans_email.tpl` / `page_youraccount.tpl` / `page_lostpassword.tpl` / `page_login.tpl`), the row-action flows (`page_admin_servers_list.tpl` / `page_admin_bans_protests.tpl` / `page_admin_bans_protests_archiv.tpl` / `page_admin_bans_submissions.tpl` / `page_admin_bans_submissions_archiv.tpl`), and the drawer Notes paths (`theme.js`'s `submitNoteForm` / `deleteNote`). Comment edit on the banlist (`web/scripts/banlist.js`) carries the same pattern for the `sb.api.call(BansEditComment)` round-trip. Regression guards: `web/tests/e2e/specs/flows/action-loading-indicator.spec.ts` (stalls `Actions.CommsUnblock` via `page.route`, asserts the busy-attribute triple on the submit button while in flight, releases the route, and confirms the row flips in-place; the second test counts requests to prove the disabled gate blocks a double-click) **plus** `web/tests/e2e/specs/flows/loading-animations.spec.ts` (#1362 — samples `getComputedStyle(::after).transform` at multiple frame boundaries under both `reducedMotion: 'reduce'` AND `'no-preference'`, asserts the matrix values change across samples; catches the v2.0 RC1 regression where the global `prefers-reduced-motion: reduce` reset froze the spinner under reduced motion). |
| Add a loading indicator to the player drawer or one of its lazy panes (so the chrome doesn't read as blank while the JSON action is in flight) | `renderDrawerLoading()` (header skeleton for the in-flight `bans.detail`) and `renderPaneSkeleton()` (placeholder for History / Comms / Notes activation) in `web/themes/default/js/theme.js`. Both lean on the `.skel` CSS rule in `theme.css` (linear-gradient + `shimmer` keyframe + dark-mode override + the `@media (prefers-reduced-motion: reduce)` per-rule override that keeps the shimmer sliding even under reduced motion, #1362). The header skeleton carries `[data-testid="drawer-loading"]` + `aria-busy="true"` + per-block `[data-skeleton]` (terminal markers under `#drawer-root[data-loading="true"]`); the lazy-pane skeleton carries `[data-pane-empty]` + `aria-busy="true"` and deliberately omits `[data-skeleton]` because the panel parent's `hidden` attribute doesn't compose into `[data-skeleton]:not([hidden])` and a nested marker would stall every page-load waiter that runs after the drawer opens. Class name is `.skel` (singular) — NOT `.skeleton`; the pre-fix `class="skeleton"` typo had no matching rule and the shimmer rows rendered as transparent zero-background divs (the user-visible "drawer is blank" regression). Regression guards: `web/tests/e2e/specs/flows/drawer-loading-indicator.spec.ts` (stalls `bans.detail` then `bans.player_history` via `page.route`, asserts the skeleton header is visible + the `.skel` block paints a `linear-gradient` background via `getComputedStyle(el).backgroundImage`, releases the routes, and confirms the drawer flips to `renderDrawerBody` / the pane fills with content) **plus** `web/tests/e2e/specs/flows/loading-animations.spec.ts` (#1362 — samples `getComputedStyle(.skel).backgroundPositionX` at multiple frame boundaries under both `reducedMotion: 'reduce'` AND `'no-preference'`, asserts the values change across samples; catches the v2.0 RC1 regression where the global reset froze the shimmer alongside the spinner). |
| Surface unban-reason / removed-by inline on a public-list row (admin-lifted bans / comms — banlist-ureason or commslist-ureason inline) | `web/themes/default/page_bans.tpl` + `web/themes/default/page_comms.tpl` (#1315). Reason cell on the desktop table emits a `<div class="text-xs text-faint mt-1" data-testid="ban-unban-meta">` (or `comm-unban-meta` for comms) with "Unbanned by `<admin>`: `<reason>`" when `$ban.state == 'unbanned'` (or `$comm.state == 'unmuted'`); mobile cards mirror with the `-mobile` testid suffix. Always gated on `!$hideadminname` so anonymous viewers under a hidden-admins config don't get the admin name leaked. The `ureason` / `removedby` row fields come from the page handler's existing data path (`page.banlist.php` lines 635-643, `page.commslist.php` lines 626-635) — read-only render, no write-side overlap with #1301 / #1323's unban-reason flow. The commslist surface is higher-priority than the banlist (no drawer fallback on `<tr data-testid="comm-row">`); banlist users have the drawer as the canonical detail view. |
| Re-apply (Reban) affordance on the public banlist for expired / unbanned rows | `web/themes/default/page_bans.tpl`. The desktop row-actions cell emits `<a class="btn btn--secondary btn--sm" data-testid="row-action-reapply" href="index.php?p=admin&c=bans&section=add-ban&rebanid={$ban.bid}&key={$admin_postkey}">…<i data-lucide="rotate-ccw">…Re-apply</a>` when `$can_add_ban && ($ban.state == 'expired' \|\| $ban.state == 'unbanned')`. The smart-default block on `admin.bans.php`'s `add-ban` section detects `?rebanid=…` and pre-populates the form via `BansPrepareReban`. Mobile parity (this PR) — the mobile card was restructured from a single wrapping `<a>` to the `page_comms.tpl` shape (`<div data-testid="ban-card">` wrapping `<a class="ban-card__summary" data-testid="drawer-trigger">` + a sibling `<div class="row-actions ban-card__actions">`), so the same Re-apply button surfaces under `data-testid="row-action-reapply-mobile"`. The drawer trigger stays the inner anchor's `data-drawer-href` + `data-testid="drawer-trigger"` so the existing responsive spec selector keeps working. |
| Wrap a public-list legacy advanced-search form (banlist / commslist) in a default-collapsed `<details class="filters-details">` disclosure | `web/themes/default/page_bans.tpl` + `web/themes/default/page_comms.tpl` (#1315). The same `.filters-details` rules in `web/themes/default/css/theme.css` cover the chrome (summary chevron, `[open]` state, hover bg, focus ring, count badge); the public-list usage adds a `.filters-details__body > form.card` rule that suppresses the inner card framing because the disclosure body wraps a `{load_template file="admin.bans.search"}` (or `…comms.search`) — i.e. a sibling page-render that emits its own `<form class="card">`. The View DTO (`Sbpp\View\BanListView` / `Sbpp\View\CommsListView`) carries a `bool $is_advanced_search_open` set by the page handler from `isset($_GET['advSearch']) && (string) $_GET['advSearch'] !== ''` so the legacy `?advSearch=…&advType=…` URL shim auto-opens the disclosure. Bare `?p=banlist` / `?p=commslist` and simple-bar filters (`?searchText=` / `?server=` / `?time=`) leave it closed so the unfiltered list reaches above the fold. Selectors anchor on `[data-testid="banlist-advsearch-disclosure"]` / `[data-testid="commslist-advsearch-disclosure"]` (the `<details>`) + the `…-toggle` (the `<summary>`) + the `…-active` count badge. The same `.filters-details` chrome was introduced for admin-admins by #1303 — both surfaces share the CSS so a future tweak is single-source. |
| Surface admin-authored per-ban / per-comm comments inline on the public banlist + commslist (`<details data-testid="ban-comments-inline">` / `comm-comments-inline`) | `web/themes/default/page_bans.tpl` + `web/themes/default/page_comms.tpl` (#BANLIST-COMMENTS, this PR). Both desktop tables render a native `<details class="ban-comments-inline">` next to the player-name cell whose `<summary>` doubles as the count chip (icon + tabular-nums count + "comment(s)" label) and whose body lists each comment with `<strong>{$com.comname}</strong>` + timestamp + `<div data-testid="ban-comment-text">{$com.commenttxt nofilter}</div>`. The `nofilter` is load-bearing because `$com.commenttxt` is server-built HTML produced by `encodePreservingBr` (per-segment `htmlspecialchars`, only `<br/>` survives) plus the URL-wrap regex that wraps already-escaped `https?://...` strings in `<a>` tags — same trust contract as the existing comment-edit-mode "Other comments" foreach at the top of `page_bans.tpl`. The mobile cards emit a non-interactive `<div data-testid="ban-comments-count-mobile">` count indicator instead — the card wraps every cell in a single `<a data-drawer-href>`, so a nested `<details>` would be invalid HTML (interactive content inside interactive content); mobile users tap through to the drawer (`renderOverviewPane` paints the same comments under `[data-testid="drawer-comments"]`). Both surfaces gate on `$view_comments` (mirrors the page handler's `Config::getBool('config.enablepubliccomments') \|\| $userbank->is_admin()` check at `page.banlist.php` line 766 / `page.commslist.php` line 734). The drawer surface (`api_bans_detail.comments_visible` + `api_bans_detail.comments`) is unchanged — both surfaces share the same data path and same gate so there's no leak risk. The commslist regression was worse than the banlist's because there's no drawer fallback on `<tr data-testid="comm-row">`; the inline disclosure is the ONLY on-page way for admins to see comm-block comment text. Regression guards: `web/tests/integration/BanlistCommentsVisibilityTest.php` (admin sees disclosure regardless of flag, anonymous gated correctly, drawer mirrors disclosure via `bans.detail`) + `web/tests/e2e/specs/flows/banlist-comments-visibility.spec.ts` (desktop disclosure renders + opens, drawer paints same data, mobile count indicator is a `<div>` not a `<details>`). The fix restores the v1.x `mooaccordion` discoverability that was lost when the v2.0 rewrite of `page_bans.tpl` collapsed the inline panel down to a silent `<span>[N]</span>` count badge with no affordance. |
| Filter the public banlist by ban state (`?p=banlist&state=permanent\|active\|expired\|unbanned`) — server-side rowset narrowing for a chip strip | `web/pages/page.banlist.php` (#1352). The `?state=` value is allowlisted (`['permanent','active','expired','unbanned']`, anything else falls through to "All"), composed into the existing `$publicFilterAnd` / `$publicFilterWheren` SQL fragments via the per-state predicates documented inline. **Symmetric defensive shape across the four arms**: `permanent` / `active` BOTH require `RemovedOn IS NULL` (otherwise a pre-2.0 admin-lifted row with `ends > now` would match BOTH `?state=active` AND `?state=unbanned`); `expired` carries THREE arms (`RemoveType = 'E'` post-migration shape + `RemoveType IS NULL AND length > 0 AND ends < now AND RemovedOn IS NULL` for pre-475 installs the prune writer never touched + `RemoveType IS NULL AND RemovedOn IS NOT NULL AND length > 0 AND (RemovedBy IS NULL OR RemovedBy = 0)` for the fork-divergence shape `810.php` pass 2 backfills); `unbanned` carries TWO arms (`RemoveType IN ('D', 'U')` post-migration shape + `RemovedOn IS NOT NULL AND RemoveType IS NULL AND RemovedBy IS NOT NULL AND RemovedBy > 0` for pre-2.0 admin-lifts that `810.php` pass 1 backfills). The `RemovedBy > 0` vs `RemovedBy IS NULL OR = 0` split between unbanned / expired is the **load-bearing distinction** — natural expiry sets `RemovedBy = 0` (PruneBans) or NULL (pre-475); admin lifts set `RemovedBy = <aid>`. The View DTO (`Sbpp\View\BanListView`) carries `string $active_state` + `string $chip_base_link`; the chip strip in `web/themes/default/page_bans.tpl` renders as real `<a href="index.php?p=banlist{$chip_base_link}&state=…">` anchors with `aria-current="true"` (NOT `aria-pressed` — only valid on role=button; axe rejects it on `<a>` as `aria-allowed-attr`) and `data-active="true"` server-rendered on the matching chip — never `<button onclick=…>` (the legacy `web/scripts/banlist.js applyStateFilter` row-hide layer was a client-side filter on top of server-side pagination, so on installs with thousands of bans the unbanned chip silently rendered an empty page 1). The "Hide inactive" toggle is suppressed in the template when `$active_state !== ''` so the two predicates (`hideinactive` is `RemoveType IS NULL`; `?state=expired` / `unbanned` ask for the OPPOSITE) don't visually fight. Pagination URLs preserve `&state=` via `$stateFilterLink`; the per-row state classifier in the same file mirrors the SQL filter (a row pulled in by `?state=unbanned` renders the Unbanned pill, never the legacy mis-classified "Active" pill). The JSON API parity surface is `api_bans_detail` + `api_bans_player_history` in `web/api/handlers/bans.php` — same `isPre2AdminLift` defensive branch so the drawer's detail view + the history pane don't visibly contradict the SQL filter. Regression guards: `web/tests/integration/BanListStateFilterTest.php` (per-state row inclusion / exclusion + chip render contract + suppressed Hide-inactive toggle + symmetric `expired` arm 3 contract), `web/tests/integration/UpdaterBackfillRemoveTypeTest.php` (the migration's idempotency + two-pass cross-contamination contract), `web/tests/api/BansTest.php::testDetailReportsUnbannedForPre2AdminLiftWithRemoveTypeNull` (API parity), `web/tests/e2e/specs/responsive/banlist.spec.ts` + `web/tests/e2e/specs/responsive/filters.spec.ts` (mobile chip click navigates + asserts `aria-current` rather than `history.replaceState` + `aria-pressed`'ing). |
| Edit the player-detail drawer (open trigger, tabs, panes, lazy loaders) | `web/themes/default/js/theme.js` (`renderDrawerBody` / `loadPaneIfNeeded`). The drawer handles two focal kinds via `drawerKind` (`'ban'` / `'comm'`): the bans list ships `data-drawer-bid` / `data-drawer-href` on row anchors, the comms list ships `data-drawer-cid`. `loadDrawer({kind, id})` dispatches to `Actions.BansDetail` (bid → `bans.detail`) or `Actions.CommsDetail` (cid → `comms.detail`) and stamps the response into `drawerDetail`. `loadPaneIfNeeded` then keys lazy panes off the focal kind: bans-focal History sends `{bid}` (handler excludes the focal via `BA.bid <> ?`); comm-focal History sends `{authid: drawerDetail.player.steam_id}` (no focal to exclude — different table); bans-focal Comms sends `{bid}` (resolves to authid; no comm to exclude); comm-focal Comms sends `{cid}` (handler excludes the focal cid via `C.bid <> ?` — sister contract to the bans-focal History exclusion so the Overview pane and the Comms tab don't render the same record twice). The Notes tab is admin-only and shared across both focal kinds (keys off `player.steam_id`). |
| Add a comms-list player drawer parity surface (mirror the banlist's `data-drawer-href` row anchor with a comm-focal equivalent) | The desktop `<tr data-testid="comm-row">` and mobile `<div data-testid="comm-card">` rows in `web/themes/default/page_comms.tpl` carry a player-name anchor with `data-drawer-cid="{$comm.cid}"` + `data-testid="drawer-trigger"`. The `href` falls back to a useful no-JS surface (`?p=commslist&id=…` desktop / `?p=commslist&searchText=…` mobile) so the affordance still leads somewhere when JS is off / `theme.js` is stripped by a third-party theme. The drawer JS (`theme.js`'s document `click` delegate at `[data-drawer-bid], [data-drawer-cid], [data-drawer-href]`) routes the click through `keyFromTrigger(trigger)` which returns `{kind: 'comm', id}` for cid triggers; downstream `loadDrawer` dispatches `Actions.CommsDetail` and the renderer branches on `drawerKind === 'comm'` for the header chip ("Comm #N") and the Overview pane's focal-block grid (`[data-testid="drawer-block"]` with `Type` / `Reason` / `Started` / `Ends` rows — vs `[data-testid="drawer-ban"]` on the bans-focal path). The handler is `api_comms_detail` in `web/api/handlers/comms.php` (sister to `api_bans_detail`, same envelope shape modulo `cid` instead of `bid` / `block` instead of `ban` / `'unmuted'` instead of `'unbanned'` in the state vocab — both `api_comms_detail` AND `api_comms_player_history` use `'unmuted'` for `RemoveType IN ('U', 'D')` rows so the drawer's Overview pane and Comms tab don't render contradictory state labels for the same player). Public action; field-level hide-* gating mirrors `bans.detail`. Pill CSS lives next to `.pill--unbanned` in `theme.css` (`.pill--unmuted` carries the same success-bg + emerald colour treatment because admin-lifted is admin-lifted regardless of the focal kind). The drawer JS's `stateLabel()` switch in `theme.js` carries the matching `'unmuted' → 'Unmuted'` arm. Regression guards: `web/tests/api/CommsTest.php` (snapshot + state vocab + lifted-block branch + permanent-block branch + `comms.player_history` cid path with focal exclusion + 404 on unknown cid + lone-focal empty-feed shape) and `web/tests/e2e/specs/flows/ui/comms-drawer.spec.ts` (desktop) + `web/tests/e2e/specs/responsive/drawer.spec.ts` (mobile — clicking a `.ban-cards [data-testid="drawer-trigger"]` opens the comm-focal drawer, header reads "Comm #N", Type row in Overview pane). The desktop spec mirrors `player-drawer.spec.ts`'s isolation strategy — NO `truncateE2eDb` between tests, unique authids per (subtest × project × worker), and `seedCommOrAccept` / `seedBanOrAccept` helpers that tolerate `already_blocked` / `already_banned` so a Playwright retry on the same worker reuses the existing row. Adding a per-test truncate would only widen the cross-file race window where a concurrent worker's API call lands during another worker's truncate→reseed gap and gets a `forbidden` cascade; the comms-drawer tests are read-shaped (open the drawer, assert the chrome) so authid-namespacing is enough. |
| Render the per-server map thumbnail in the expanded public server card | `web/themes/default/page_servers.tpl` (`<img data-testid="server-map-img" hidden>` slot inside `[data-testid="server-players-panel"]`) + `web/scripts/server-tile-hydrate.js`'s `applyData()` (patches `src` from `r.data.mapimg`, toggles `hidden` on `load` / `error`). The lookup is feature-detected via the testid so the admin Server Management list (which does NOT ship the slot) silently no-ops. The URL itself comes from global helper `\GetMapImage()` in `web/includes/system-functions.php` (falls back to `images/maps/nomap.jpg` when the file is missing); the bundled `nomap.jpg` placeholder ships under `web/images/maps/`. The slot must default to `hidden` and stay hidden on the `error` branch — fork installs without `nomap.jpg` would otherwise paint a broken-image icon. Sizing (#1375): the inline style is `display:block;width:100%;max-width:340px;height:auto;margin:0 auto 0.5rem` — `max-width: 340px` matches the natural source width of the bundled `*.jpg` thumbnails (340×255, ~4:3) so the box never upscales and never exceeds the source dimensions; `height: auto` derives the proportional height from the rendered width so the rendered box matches the source aspect ratio exactly. Pre-#1375 the slot ran `width:100%;max-height:140px;object-fit:cover` which clamped the box to a ~2.86:1 strip on a 28rem card and `object-fit:cover` cropped the middle horizontal band of a 4:3 source — operators perceived the result as "stretched horizontally". Don't reintroduce `max-height` or `object-fit:cover` here; let `height: auto` carry the proportional sizing. Regression guards: `web/tests/integration/ServerMapImageRenderTest.php` (template ships the slot + helper carries the wiring + handler still emits `mapimg`, AND `testMapImgSlotPreservesNaturalAspectRatio` pins the new `max-width: 340px` / `height: auto` shape + the absence of `max-height` / `object-fit`) + `web/tests/e2e/specs/flows/server-map-thumbnail.spec.ts` (runtime visibility under success / 404 / connect-error). #1312 restored this surface after the #1123 D1 redesign dropped the legacy `<img id="mapimg_{$server.sid}">`; #1313 moved the wiring out of the inline `<script>` block into the shared helper; #1375 fixed the squashed aspect ratio. |
| Hydrate server-tile cards with live A2S data (status pill / map / players / hostname / refresh) on the public servers list, the admin Server Management list, AND the dashboard's Servers widget | `web/scripts/server-tile-hydrate.js` (`window.SBPP.hydrateServerTiles`) — auto-runs on first paint for every container marked `data-server-hydrate="auto"`. Consumed by `web/themes/default/page_servers.tpl` (public, full chrome), `web/themes/default/page_admin_servers_list.tpl` (admin, #1313 — same chrome minus the map thumbnail + players panel), and `web/themes/default/page_dashboard.tpl` (dashboard Servers widget, #1375 — hostname slot only, every other testid hook deliberately omitted). Selector contract per tile: `[data-testid="server-tile"]` outer card + `data-id="<sid>"` + `[data-testid="server-{status,map,players,host}"]` cells + optional `[data-testid="server-{refresh,toggle}"]` / `[data-players-bar]` / `[data-testid="server-players-panel"]` / `[data-testid="server-map-img"]` (every cell beyond `data-id` is feature-detected; the dashboard widget ships only `[data-testid="server-host"]` and the helper no-ops every other branch). Disabled tiles carry `data-server-skip="1"` so the helper leaves them at the server-rendered placeholder. Never copy-paste the hydration code into a new template — wire the testids and `data-server-hydrate="auto"` instead and the helper picks the surface up automatically. The dashboard widget is the canonical reference for "minimal-testid integration": it ships ONE optional testid + the outer `[data-testid="server-tile"]` + `data-id="<sid>"`, and the helper still hydrates the hostname per the same `Actions.ServersHostPlayers` round-trip. The per-surface truncation knob (`data-trunchostname="<n>"` on the wrapping container) is the right place to dial the hostname length per surface — `70` on the full-width public + admin cards, `40` on the cramped dashboard widget so a long hostname doesn't trip `truncate`'s ellipsis prematurely. |
| Tune the server card grid layout (column min-width, mobile single-column collapse) shared between the public + admin Server Management lists | `.servers-grid` rule in `web/themes/default/css/theme.css` (#1316). Single-source `repeat(auto-fill, minmax(28rem, 1fr))` — both `page_servers.tpl` and `page_admin_servers_list.tpl` apply the class to their grid container instead of an inline `style="grid-template-columns:..."`. The 28rem (448px) min replaced the pre-#1316 20rem (320px) min that packed cards into ~340px columns even on a 31" 4K monitor (both pages cap their content area at 1400px via `.page-section` / inline `max-width:1400px`, so wider viewports got zero benefit from the wider screen — that's the bug #1316 fixed). With the 1rem grid gap factored in: 1280px laptop ≈ 2 cols × ~488px each; 1400px+ desktop ≈ 2 cols × ~668px each. At <=768px the sibling `@media` rule collapses to `minmax(0, 1fr)` (NOT bare `1fr`, which would inflate the track to the card's `truncate`-nowrap min-content and overflow the viewport) so a phone-portrait viewport never overflows horizontally. Don't reach for a different column-min on a per-template basis — the class is the unified knob; theme forks override the rule wholesale. Regression guard: `web/tests/e2e/specs/responsive/server-cards.spec.ts` walks four desktop viewports (1280/1920/2560/3840) plus iPhone-13-like 390px and asserts both surfaces apply the class, the card width floor is ≥28rem, and the mobile collapse holds. |
| Edit the command palette (icon-only trigger, ⌘K binding, result rows, kbd hints, Ctrl+Enter copy) | `web/themes/default/js/theme.js` (`openPalette` / `closePalette` / `renderPaletteResults` / `applyPlatformHints` / `handlePaletteCopyShortcut`) + `core/title.tpl` (the `.topbar__search` icon button) + the `.palette__row*` rules in `web/themes/default/css/theme.css`. Player rows carry `data-drawer-bid="<bid>"` (bare Enter / click → `loadDrawer`, palette closes itself) + `data-steamid="<steam>"` (`Ctrl/Cmd+Enter` → `navigator.clipboard.writeText` + `showToast`). The kbd glyphs are server-rendered in non-Mac form (`Enter`, `Ctrl`); `applyPlatformHints` swaps `[data-enterkey]` → ⏎ and `[data-modkey]` → ⌘ on Mac/iOS at boot and after every render (#1184, #1207 DET-2). |
| Add or edit a palette "Navigate" entry (the icon-label-href rows the palette renders alongside player results) | `web/includes/View/PaletteActions.php` (`Sbpp\View\PaletteActions::for($userbank)` — catalog + filter). The catalog's `entries()` method declares each entry as `{icon, label, href, permission, config?}`; `for()` drops entries the user can't reach (admin entries gated via `HasAccess` with `ADMIN_OWNER` OR'd in; public entries optionally gated on a `config.enable*` toggle) and emits the public `{icon, label, href}` triple. The filtered list is JSON-encoded by `web/pages/core/footer.php` (with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` so the content can never escape its `<script>` wrapper) and emitted by `core/footer.tpl` inside `<script type="application/json" id="palette-actions" data-testid="palette-actions">`. `theme.js`'s `loadNavItems()` reads + `JSON.parse`s the blob at boot. Pre-#1304 the entry list was a hardcoded `NAV_ITEMS` array in `theme.js` with no permission check, leaking admin entries to logged-out + partial-permission users; the regression guard is `web/tests/integration/PaletteActionsTest.php` (server-side filter) plus `web/tests/e2e/specs/flows/ui/command-palette-permissions.spec.ts` (end-to-end blob → DOM contract). |
| Add a "copy this value" affordance to a panel surface (single-source clipboard wiring) | Mark the trigger with `data-copy="<value>"` (`<button type="button">` is the canonical shape; the drawer uses `<button>` inside a `<dd>`, the banlist row uses `<button>` inside `.row-actions`). The document-level COPY BUTTONS delegate in `web/themes/default/js/theme.js` handles every `[data-copy]` site: secure-context callers go through `navigator.clipboard.writeText` with a `.then(success, fallback)` chain, non-secure callers (plain HTTP behind a TLS-terminating proxy) drop to `copyFallback()` — a hidden-textarea + `document.execCommand('copy')` that's the only portable option outside HTTPS (#1308). NEVER add an inline `onclick="event.stopPropagation()"` to a `[data-copy]` button — the bubble-phase stop kills the document delegate (Defect A, #1308). NEVER assume `navigator.clipboard` exists or that `writeText()` resolves — both fall through to the same execCommand fallback (Defect B, #1308). |
| Add admin-only per-player notes | `web/api/handlers/notes.php` (CRUD) — Notes tab is gated by `bans.detail`'s `notes_visible` flag |
| Add or extend the server-player right-click context menu on `?p=servers` (View Profile / Copy SteamID / Kick / Ban / Block Comms) | `web/scripts/server-context-menu.js` (event-delegate menu, single `document.addEventListener('contextmenu')` filtered by `closest('[data-context-menu="server-player"]')`) + `web/scripts/server-tile-hydrate.js` (`renderPlayers()` emits the `data-context-menu` / `data-steamid` / `data-name` / `data-server-sid` / `data-can-ban-player` hooks on each `<li>`) + `web/api/handlers/servers.php` (`api_servers_host_players` attaches SteamIDs via `RconStatusCache::fetch($sid)` only when the caller has `WebPermission::Owner \| WebPermission::AddBan` AND per-server RCON access; `can_ban_player` boolean signals whether to render the kick/ban/block items) + `web/includes/Servers/RconStatusCache.php` (`Sbpp\Servers\RconStatusCache::fetch($sid, $ttl=30)` — per-sid on-disk cache under `SB_CACHE/srvstatus/`, mirrors `SourceQueryCache` shape; calls `rcon('status', $sid, true)` with the silent flag so passive probes don't spam the audit log). The admin hint copy in `page_servers.tpl` + the `<script src="./scripts/server-context-menu.js">` include are both gated on `$can_use_context_menu` (= `Perms::for($userbank)['can_add_ban']`) so anonymous viewers don't pay for either. SteamID3 / SteamID2 → SteamID64 conversion happens client-side (`STEAM_X:Y:Z` → `76561197960265728 + Z*2 + Y`; `[U:1:N]` → `76561197960265728 + N`). The integration test (`web/tests/integration/ServerListHintRegressionTest.php`) is the post-restoration contract — it asserts the hint and the JS include both ship for admins and both stay absent for anonymous viewers (the pre-#1306 contract is superseded). Regression guards: `web/tests/api/ServersTest.php` (handler-shape coverage for the SteamID side-channel + the `can_ban_player` flag), `web/tests/integration/RconStatusCacheTest.php` (cache shape + silent-flag contract), `web/tests/e2e/specs/flows/server-player-context-menu.spec.ts` (end-to-end menu open / item visibility / Escape close / no-steamid no-menu). |
| Cache an A2S `GetInfo + GetPlayers` round-trip / add another public server-query handler | `web/includes/Servers/SourceQueryCache.php` (`Sbpp\Servers\SourceQueryCache::fetch($ip, $port, $ttl=30)` — per-`(ip, port)` on-disk cache under `SB_CACHE/srvquery/`, atomic tempfile + `rename()` writes mirroring `system.check_version`'s release cache; both success and failure cache so an unreachable server costs ONE A2S probe per ~30s window). The sibling `Sbpp\Servers\RconStatusCache` (`SB_CACHE/srvstatus/`) follows the same shape for RCON `status` round-trips — used by `api_servers_host_players` to surface per-player SteamIDs to admins (see the context-menu row above). Every public handler under `web/api/handlers/servers.php` (`api_servers_host_players` / `host_property` / `host_players_list` / `players`) goes through this — never call `new SourceQuery()` directly from a handler. The cache stamps user-agnostic data only; the handler stamps per-caller fields (`is_owner`, `can_ban`, the per-call `trunchostname`) on top. Per-tile JS debounce on the public servers page lives in `web/themes/default/page_servers.tpl` (`loadTile()` flips `tile.__sbppLoading` + the Re-query button's `disabled` attr while a probe is in flight, releases both in the success / error tails). The matching JS gate on the toggle button has been the precedent since v2.0.0; #1311 brought the refresh button onto the same shape. Tests: `web/tests/integration/SourceQueryCacheTest.php` (cache shape + coalescing + TTL + invalidation, drives `setProbeOverrideForTesting()` so the assertion is deterministic without UDP) + `testHostPlayersCoalescesRapidRepeatCallsViaCache` / `testHostPlayersNegativeCachesUnreachableServers` in `web/tests/api/ServersTest.php` (handler-shape coverage). E2E: `web/tests/e2e/specs/flows/server-refresh-debounce.spec.ts`. |
| Render admin-authored Markdown to safe HTML | `web/includes/Markup/IntroRenderer.php` (`Sbpp\Markup`) |
| Build / extend the anonymous opt-out daily telemetry payload (#1126) | `web/includes/Telemetry/Telemetry.php` (`Sbpp\Telemetry\Telemetry` — `tickIfDue`, `collect`, `send`) + `web/includes/Telemetry/Schema1.php` (`Sbpp\Telemetry\Schema1::payloadFieldNames()`, drives the extractor parity test) + `web/includes/Telemetry/schema-1.lock.json` (vendored from [sbpp/cf-analytics](https://github.com/sbpp/cf-analytics) — manual sync via `make sync-telemetry-schema`). Tick is registered at the tail of `init.php` via `register_shutdown_function`; on FPM, `fastcgi_finish_request()` flushes the response BEFORE the cURL POST so telemetry never delays a panel page. Slot reservation is atomic (`UPDATE :prefix_settings WHERE CAST(value AS UNSIGNED) <= :threshold`) at the START of the attempt, so a flapping endpoint costs one ping/day, not one ping/request. Audit-log only enable/disable transitions, never individual pings. The in-panel disclosure surface is the help-icon copy in `page_admin_settings_features.tpl`; the upgrade-time disclosure lives in `docs/src/content/docs/updating/1.8-to-2.0.mdx` (no first-login modal). |
| Add a cross-repo JSON contract (vendored schema lock + reader + extractor parity test) | `web/includes/Telemetry/Schema1.php` is the reference shape (`payloadFieldNames(): list<string>` over a Draft-7 JSON Schema lock file). Pair with one PHPUnit extractor parity test (collect() vs. lock file in both directions). The schema lock file is the single source of truth — don't mirror the field list into a markdown doc paired with a separate parity test, that pattern was tried for telemetry and removed because the duplication paid for the drift risk it created. Sync via a manual `make sync-<subsystem>-schema` target — no scheduled auto-PR. See "Cross-repo JSON contracts" under Conventions. |
| Display a user's own permission flags grouped by category | `Sbpp\View\PermissionCatalog::groupedDisplayFromMask($mask)` (`web/includes/View/PermissionCatalog.php`). Adding a new flag to `web/configs/permissions/web.json` requires a paired entry in `WEB_CATEGORIES`; `PermissionCatalogTest` enforces it. |
| Live-preview Markdown in a settings textarea | `system.preview_intro_text` JSON action + `web/themes/default/page_admin_settings_settings.tpl` (`.dash-intro-editor` / `.dash-intro-preview`) |
| Build an empty-state surface (first-run vs filtered, primary/secondary CTAs) | `.empty-state` rules in `web/themes/default/css/theme.css` + reference shapes in `page_servers.tpl`, `page_dashboard.tpl`, `page_bans.tpl`, `page_comms.tpl`, `page_admin_audit.tpl`, `page_admin_bans_protests.tpl`, `page_admin_bans_submissions.tpl` |
| Subdivide an admin route into `?section=<slug>` URLs (servers, mods, groups, comms, settings, **admins**, **bans**) | `web/pages/admin.settings.php` is the long-standing reference; #1239 brought servers / mods / groups / comms onto the same shape; #1259 unified the chrome on the Settings-style vertical sidebar; #1275 brought admins (`admins` / `add-admin` / `overrides`) and bans (`add-ban` / `protests` / `submissions` / `import` / `group-ban`) onto the same shape, deleting the page-level ToC (`page_toc.tpl`) along the way so `?section=` is now the **only** sub-route nav contract. The shared partial is `web/themes/default/core/admin_sidebar.tpl` (parameterized on `tabs` / `active_tab` / `sidebar_id` / `sidebar_label`); `web/includes/View/AdminTabs.php` (`Sbpp\View\AdminTabs`) opens `<div class="admin-sidebar-shell">`, emits the `<aside>` + link list, opens `<div class="admin-sidebar-content">`, and the page handler closes both wrappers (`echo '</div></div>'`) AFTER `Renderer::render(...)`. Each `$sections` entry carries `slug` + `name` + `permission` + `url` + `icon` (Lucide name); the link emits `<a href="?p=admin&c=<page>&section=<slug>" data-testid="admin-tab-<slug>" aria-current="page">` — never `<button onclick="openTab(...)">` (the JS handler was deleted at #1123 D1). See "Sub-paged admin routes" in Conventions. |
| Render sub-views inside a Pattern A section (e.g. protests / submissions current-vs-archive) | `?view=<slug>` query param + a server-rendered `.chip-row` of real anchors (each carries `data-active="true|false"` + `aria-selected`). Reference: the protests / submissions chip rows in `web/pages/admin.bans.php` (`?section=protests&view=archive` / `?section=submissions&view=archive`). Pre-#1275 the chips called `Swap2ndPane()` — a `web/scripts/sourcebans.js` helper deleted at #1123 D1, leaving them dead — and the page rendered both views simultaneously. The new shape only renders the active view's data path; back/forward and link sharing both work. |
| Lay out a sub-paged admin route's chrome (the 14rem vertical sidebar at `>=1024px`, the `<details open>` accordion at `<1024px`) | `web/themes/default/core/admin_sidebar.tpl` (the partial) + the `.admin-sidebar-shell` / `.admin-sidebar` / `.admin-sidebar__details` / `.admin-sidebar__summary` / `.admin-sidebar__nav` / `.admin-sidebar__link` / `.admin-sidebar-content` rules in `web/themes/default/css/theme.css` (#1259). The active link reuses the shared `.sidebar__link[aria-current="page"]` rule from the main app shell so the dark-pill-in-light / brand-orange-in-dark treatment is single-source. |
| Render the trailing "Back" link on edit-* admin pages (the only surface that calls `new AdminTabs([], …)`) | `web/themes/default/core/admin_tabs.tpl` is the back-link-only partial (it still has a defensive `{foreach}` for legacy themes, but `web/includes/View/AdminTabs.php` only routes here when `$tabs === []`). Page handlers like `admin.edit.ban.php` / `admin.rcon.php` / `admin.email.php` call `new AdminTabs([], $userbank, $theme)` and the partial emits the right-aligned Back anchor (`.admin-tabs__back` in theme.css). |
| Add or rename an admin-admins advanced-search filter | `web/pages/admin.admins.php` (filter-building loop + active-filter map for pagination) + `web/pages/admin.admins.search.php` (DTO population + `$active_filter_count` increment for the new slot) + `web/includes/View/AdminAdminsSearchView.php` (`active_filter_*` properties) + `web/themes/default/box_admin_admins_search.tpl` (input + pre-fill). The form is single-submit AND-semantics with a backward-compat shim for legacy `advType=…&advSearch=…` URLs (#1207 ADM-4); cover new filters in `web/tests/integration/AdminAdminsSearchTest.php`. |
| Wrap a filter `<form>` in a default-collapsed `<details>` disclosure (admin-admins advanced search; the public banlist / commslist filter bars are candidates for the same shape per #1303's notes) | `.filters-details` rules in `web/themes/default/css/theme.css` + reference shape in `web/themes/default/box_admin_admins_search.tpl` (`<details class="card filters-details" {if $has_active_filters}open{/if}>` with a `<summary data-testid="…-toggle">` carrying the title + chevron + optional "N active" count badge). The View carries paired `int $active_filter_count` + `bool $has_active_filters` properties (#1303); the page handler increments the count once per populated value slot, NEVER per match-mode toggle. The disclosure auto-expands on a post-submit paint so the Clear-filters affordance stays one click away. Visual vocabulary mirrors `core/admin_sidebar.tpl`'s mobile `<details open>` accordion (chevron + `prefers-reduced-motion: reduce` override). |
| Add a shared "1 of these required" badge for an either/or input pair | `web/themes/default/page_submitban.tpl` (`data-required-group="…"` + the inline guard script — vanilla JS `// @ts-check`, blocks submit when both are empty) |
| Bootstrap (paths, autoload, theme)     | `web/init.php`                                           |
| Routing (`?p=…&c=…&o=…`)               | `web/includes/page-builder.php` — unrecognised admin `c=…` returns the 404 page slot via `web/pages/page.404.php` + `Sbpp\View\NotFoundView` (#1207 ADM-1) |
| Resolve the panel version (`SB_VERSION`, `data-version="…"` footer hook) | `web/includes/Version.php` (`Sbpp\Version::resolve()`) — three-tier fallback: `configs/version.json` → `git describe` → the `'dev'` sentinel (#1207 CC-5) |
| Auth / JWT cookie                      | `web/includes/Auth/` (`Sbpp\Auth\*` — `Auth.php`, `JWT.php`, `UserManager.php`, `Host.php`, `Handler/{Normal,Steam}AuthHandler.php`; `openid.php` is third-party LightOpenID and stays in the global namespace) |
| CSRF                                   | `web/includes/Security/CSRF.php` (`Sbpp\Security\CSRF`)  |
| Schema                                 | `web/install/includes/sql/struc.sql`                     |
| Wrap a `:prefix_*` column with a backed PHP enum (log letter codes, ban types, removal-type tags, web permissions) | `web/includes/LogType.php` / `LogSearchType.php` / `BanType.php` / `BanRemoval.php` / `WebPermission.php` (global namespace; loaded by `init.php` + `tests/bootstrap.php`). Pass `$enum->value` at every SQL bind site so the dba plugin sees the column-typed primitive; use `WebPermission::mask(…)` to assemble multi-flag bitmasks for `HasAccess()`. Issue #1290 phase D. |
| Seed `sb_settings` rows for fresh installs | `web/install/includes/sql/data.sql`                  |
| Add a one-off DB upgrade for existing installs | `web/updater/data/<N>.php` + `web/updater/store.json` |
| Test fixtures                          | `web/tests/Fixture.php`, `web/tests/ApiTestCase.php`     |
| Populate the dev DB with realistic synthetic data (banlist > 1 page, drawer history, moderation queues, audit log) | `./sbpp.sh db-seed` → `web/tests/scripts/seed-dev-db.php` (CLI driver) → `web/tests/Synthesizer.php` (`Sbpp\Tests\Synthesizer`). Dev-only: refuses any `DB_NAME` other than `sourcebans` (so `sourcebans_test` / `sourcebans_e2e` stay untouched). Idempotent; deterministic given a fixed `--seed` (default `Synthesizer::DEFAULT_SEED`). Does NOT share plumbing with `Fixture::truncateAndReseed` — the e2e hot path stays minimal. |
| API wire-format snapshots              | `web/tests/api/__snapshots__/<topic>/<scenario>.json`    |
| Action -> permission lock              | `web/tests/api/PermissionMatrixTest.php`                 |
| Trap PHP 8.1 null-into-scalar deprecations at runtime (the bits PHPStan can't see) | `web/tests/integration/Php82DeprecationsTest.php` (#1273) — process-isolated render harness with a stub Smarty + `set_error_handler` that promotes `E_DEPRECATED` / `E_USER_DEPRECATED` to `\ErrorException`. Mirrors the LostPasswordChromeTest stub-Smarty pattern; each test method runs in a separate process because the page handlers declare top-level helpers (`setPostKey()` etc.) that PHP can't redeclare in one process. Add a marquee route here whenever a new high-traffic page handler ships, especially if it reads nullable `:prefix_*` columns or `$_POST` / `$_GET` lookups. |
| Pin the "every `:name` PDO placeholder needs as many `bind()` calls as occurrences" contract under native prepares | `web/tests/integration/SrvAdminsPdoParamTest.php` (#1314) — two methods. `testReusedNamedPlaceholderUnderNativePreparesIsRejected` issues a tiny `SELECT 1 ... WHERE aid = :sid OR aid = :sid` against `Sbpp\Db\Database` with one `bind()` and asserts it throws `HY093`; this is the contract pin (also a regression guard if anyone re-flips `EMULATE_PREPARES` back to `true`). `testAdminSrvadminsPageRendersWithoutPdoException` is the page-level regression guard for the actual #1314 fatal — process-isolated `require` of `pages/admin.srvadmins.php` with `?id=0` asserting no `PDOException` escapes. Mirrors the Php82DeprecationsTest stub-Smarty + process-isolation shape. |
| Add an E2E spec                        | `web/tests/e2e/specs/<smoke|flows|a11y|responsive>/...` + `web/tests/e2e/pages/...` |
| Add a route to the screenshot gallery  | `web/tests/e2e/specs/_screenshots.spec.ts` (`ROUTES` array) |
| Tweak mobile (<=768px) chrome layout   | `web/themes/default/css/theme.css` — see the `#1207` `@media (max-width: 768px)` blocks for the canonical shapes (icon-only topbar search, full-width drawer + scroll lock). Sub-paged admin routes (servers / mods / groups / comms / settings / admins / bans) use the `<details open>` accordion in the `#1259` `@media (min-width: 1024px)` block (sidebar inline at `<1024px`, sticky 14rem rail at `>=1024px`); see "Sub-paged admin routes" in Conventions. |
| Hide non-essential desktop-table columns when the card is too narrow to fit every cell without horizontal scroll | `.col-tier-2` (hide via `@container tablescroll (max-width: 1200px)`) and `.col-tier-3` (hide via `@container tablescroll (max-width: 1500px)`) in `web/themes/default/css/theme.css` (next to `.table-scroll`). Apply to BOTH the `<th>` AND the matching `<td>` so the column hides as a unit. Tier-3 hides FIRST despite the lower tier-number because the wider trio (IP / Length / Banned / Started, ~552px) reclaims more room than tier-2 (Server / Admin, ~219px). Tier-1 columns are always visible — Player, SteamID, Reason (banlist) / Type+Player (commslist), Status, Actions; the minimum row still answers "who, why, what state, what can I do". The breakpoints are `@container tablescroll (...)` rules — they react to the painted width of `.table-scroll` (which carries `container-type: inline-size; container-name: tablescroll;`), NOT the viewport. This lets the breakpoints see the page-cap (1400px on most lists, 1700px on bans / comms post-#1363) — pre-#1363 the predecessors were viewport-keyed (`@media (max-width: 1535px)`) and a 1920px monitor saw the same scroll-required layout as a 1535px laptop because both painted an identical 1352px card under the 1400px page-cap. `.table-scroll` stays wrapped around the table as the runtime overflow safety net. The mobile card layout (`.ban-cards`) takes over at `<=768px`, so the tier classes only collapse the desktop table at intermediate viewports. Reference: banlist `<th>` row in `page_bans.tpl` (Server / Admin → tier-2; IP / Length / Banned → tier-3); commslist row in `page_comms.tpl` (Server / Admin → tier-2; Length / Started → tier-3). See "Responsive desktop-table chrome" in Conventions for the full pattern. |
| Surface the full reason on a truncated row (banlist Reason column / mobile card reason line / unban-reason inline span) | `title="…"` attribute on the truncated element. The browser's native tooltip fires on hover (desktop) / long-press (mobile) and exposes the un-truncated text; no JS needed. Reference: `page_bans.tpl` desktop reason `<td>` (gates on `!empty($ban.reason)` so empty rows don't get a useless empty `title=""`), the mobile-card reason line, the `[data-testid="ban-unban-reason"]` span, the Server cell, and the matching `[data-testid="comm-unban-reason"]` span on `page_comms.tpl`. Don't use `title=""` empty-string fallbacks — the conditional gate is the contract. |
| Stop mobile browsers auto-linking SteamIDs / IPs as phone numbers | `web/themes/default/core/header.tpl` (`<meta name="format-detection" content="telephone=no…">` + `<meta name="x-apple-data-detectors">`) and the defensive `.drawer a[href^="tel:"]` reset in `theme.css` |
| Lock page scroll while a modal-style chrome is open | `web/themes/default/css/theme.css` (`html:has(#drawer-root[data-drawer-open="true"]) { overflow: hidden; }` — pure-CSS, gates on the same `data-drawer-open` mirror theme.js sets, applies at every viewport so the drawer-open contract is symmetric desktop/mobile per the Linear/Vercel/Notion modal idiom) |
| Keep the main sidebar sticky-pinned across the full document scroll (`<aside class="sidebar">`) | The structural half of #1271 lives in `web/themes/default/core/footer.tpl`: `<footer class="app-footer">` is rendered as the LAST flex column item of `<div class="main">`, INSIDE `<div class="app">`. `.sidebar`'s sticky containing block is `.app`; if the footer were a body-level sibling of `.app` (the pre-fix shape), `.app`'s height would fall short of the document by `footerHeight` and the sidebar would release at the bottom — brand cut off, on barely-tall pages (`docHeight - viewport ≤ footerHeight`, e.g. `?p=admin&c=audit` on the bare e2e seed) the entire scroll range would be in the release phase and the sidebar would track the scroll. Keeping the footer inside `.app` makes the sticky CB extend to the full document. The CSS half (`.sidebar { align-self: flex-start; }` from #1278) is defensive parity with `.admin-sidebar` and is RETAINED but not load-bearing on its own. The footer's `margin-top: auto` (`.app-footer` rule in `theme.css`) is the classic "sticky footer" pattern — pushes the footer to the bottom of `.main`'s flex column on short pages so the credit doesn't float halfway up the viewport. Regression guard: `web/tests/e2e/specs/responsive/sidebar-sticky.spec.ts` asserts strict `top===0` at scroll=`document.scrollHeight` on `?p=admin&c=bans` (the canonical tall page) AND on `?p=admin&c=audit` (the barely-tall page that historically presented the bug most visibly). |
| Disable the chrome's slide-in / fade animations for `prefers-reduced-motion` users | `web/themes/default/css/theme.css` (`@media (prefers-reduced-motion: reduce)` global block — see the matching note in "Playwright E2E specifics" / Conventions). The block applies universally to `*, *::before, *::after` and is the right shape for *motion-of-state* (drawer slide-in, toast slide-in, chevron rotation). Two documented exceptions live next to their rules: the busy-button spinner (`.btn[data-loading="true"]::after`) and the skeleton shimmer (`.skel`), both essential feedback per WCAG 2.3.3 — without rotation the donut reads as a decorative ring, without sliding the gradient reads as a permanent placeholder. Each rule carries its own per-rule `@media (prefers-reduced-motion: reduce)` override that re-enables the animation with `!important` longhands so specificity wins over the universal `*::after` / `*` reset (#1362). If you ship a new animation, default to honouring the global reset; the per-rule exception only applies to motion that is itself the load-bearing feedback (without it, the affordance is silently broken — not just less lively). Regression guard for both exceptions: `web/tests/e2e/specs/flows/loading-animations.spec.ts`. |
| Tell the browser to paint native UA surfaces (`<select>` dropdown panels, native scrollbars, `<input type="date|time|color">` pickers, autofill highlighting) in the matching scheme | `web/themes/default/css/theme.css` — the two `color-scheme` declarations on `:root` (`light`) and `html.dark` (`dark`) (#1309). Without these the chrome's dark tokens swap correctly for DOM-rendered surfaces, but anything painted in the browser's top-layer system UI ignores `html.dark` and renders light — most jarring on mobile where the native `<select>` picker full-screens. Regression guard: `web/tests/e2e/specs/a11y/color-scheme.spec.ts`. |
| Apply the persisted theme to `<html>` BEFORE first paint (no FOUC on every page navigation) | `web/themes/default/core/header.tpl` — the inline `<script>` block in `<head>`, immediately above `<link rel="stylesheet">` (#1367). Reads `localStorage['sbpp-theme']` (mirror of `THEME_KEY` in `theme.js`), resolves dark via the same predicate as `applyTheme(currentTheme())`, adds `class="dark"` to `<html>` synchronously before `<body>` parses. Pre-fix theme.js (loaded from the document tail via `core/footer.tpl`) was the only thing flipping the class — by then the body had already painted in light mode and the class flip triggered a full repaint the user perceived as a white flash + content flicker on every page navigation (the reporter's exact symptom on #1367). The bootloader's resolution logic must stay byte-equivalent to `theme.js`'s `applyTheme(currentTheme())` minus the `localStorage.setItem(...)` write — drift between the two means the first paint resolves to one theme, theme.js's boot-time call resolves to another, and the user sees flicker even with the bootloader present. See "Anti-FOUC theme bootloader" in Conventions. Regression guard: `web/tests/e2e/specs/flows/theme-fouc.spec.ts` uses `page.route` to stall the `theme.js` network request and asserts the state of `<html>`'s class list WHILE theme.js is held — proving the bootloader, not theme.js, did the class flip. Three arms cover the three branches of the resolution logic (dark-pinned must read `class="dark"`, light-pinned must NOT, system + emulated OS-dark via `colorScheme: 'dark'` on a fresh `chromium.newContext()` must read `class="dark"` via the matchMedia branch). Releasing the route then lets theme.js boot normally so the post-load shape is asserted too. |
| Edit a step of the install wizard (chrome, form, schema-apply, admin-create, AMXBans import) | Page handlers under `web/install/pages/page.<N>.php` (1=license, 2=DB details, 3=requirements, 4=schema apply, 5=admin form + final config write, 6=optional AMXBans import). Each handler builds a `Sbpp\View\Install\Install*View` DTO from `web/includes/View/Install/` and renders the matching template under `web/themes/default/install/`. Shared step-handler helpers (prefix validation, raw-PDO probe before instantiating `\Database`, KeyValues quoting, friendly PDO error translation, filesystem-check detail strings) live in `web/install/includes/helpers.php` (`sbpp_install_validate_prefix` / `sbpp_install_open_db` / `sbpp_install_kv_escape` / `sbpp_install_translate_pdo_error` / `sbpp_install_describe_filesystem_check`) — required eagerly from `web/install/bootstrap.php` so every step page has them in scope without its own require. Every step (3-6) re-runs `sbpp_install_validate_prefix` at the top of its handler before any SQL substitution; step 6 also validates `amx_prefix` (operator input on that page itself). The `_chrome.tpl` / `_chrome_close.tpl` partials wrap every step (header + progress stepper + footer); they own the install-only inline CSS (`.install-shell`, `.install-alert`, `.install-pill`, `.install-grid`) since the wizard reuses the panel's `theme.css` design tokens but doesn't pull in the panel's chrome JS (`theme.js`, `lucide.min.js`, command palette, etc. — the wizard has no logged-in user / no Config / no `$userbank`). Steps with per-page tail scripts: step 1 (vanilla JS validating the license-accept checkbox), step 5 (#1335 M3: client-side validation for SteamID format + email shape + password match — saves the round-trip-with-wiped-passwords path on the common form-error case); the handoff template carries an inline auto-submit script. Navigation is plain HTML `<form action="?step=N">` everywhere else. Test-IDs follow `install-<step>-<field>` consistently (#1335 m3 standardised step 2's `install-db-*` shape onto the wider `install-database-*` pattern). Anti-pattern: reintroducing MooTools / `web/install/scripts/sourcebans.js` / `ShowBox()` / `$E()` / inline `onclick="next()"` — every legacy hook is dead post-#1123 D1, the rewrite at #1332 dropped them all (#1332). |
| Recover from a missing `web/includes/vendor/` at install time | `web/install/recovery.php` is the self-contained "vendor/ missing" surface — pure inline HTML + CSS, NO Composer / Smarty / `Sbpp\…` dependency (#1332 C3). `web/install/index.php`'s lifecycle is paths-init (`init.php`) → C2 already-installed guard (`already-installed.php`, #1335) → vendor/-check (short-circuit to `recovery.php` if missing) → composer + Smarty bootstrap (`bootstrap.php`) → step dispatch (`includes/routing.php` → `pages/page.<N>.php`). The recovery surface is gated by `file_exists(PANEL_INCLUDES_PATH . '/vendor/autoload.php')` BEFORE any namespaced class is referenced. Direct visits with vendor present 302 to `/install/` instead of always emitting the 503 page (#1335 m1). The release artifact (post-#1332 Workstream A) bundles `vendor/` so this surface is the safety net for git checkouts and partial uploads, never the happy path. |
| Display a friendly error page when the panel boots with `install/` still present, `updater/` still present, or `vendor/` missing | `web/init-recovery.php` (`sbpp_check_install_guard()` + `sbpp_render_install_blocked_page()`, #1335 M1). Pure inline HTML + CSS like `recovery.php`, runs upstream of Composer / Smarty. `web/init.php` calls the helper for all three scenarios; the missing-`config.php` case redirects to `/install/` instead of dying. Pre-#1335 these were three bare `die('plain text')` calls that read like a server crash to a non-technical operator who clicked the wizard's "Open the panel" CTA before completing post-install cleanup. Regression test: `web/tests/integration/InstallGuardTest.php`. |
| Refuse to start the wizard over an already-installed panel (panel-takeover prevention) | `web/install/already-installed.php` (`sbpp_install_is_already_installed()` + `sbpp_install_render_already_installed_page()`, #1335 C2). Pure inline HTML + CSS, same shape as `recovery.php`. Loaded BEFORE the vendor/-autoload check from `install/index.php` so the guard is independent of Composer. Sister-guard to the runtime-side `web/init-recovery.php`; both key off `config.php` so the contract is symmetric. Regression test: `web/tests/integration/InstallGuardTest.php`. |
| Translate raw `PDOException` connect errors into operator-friendly messages on the wizard's database step | `sbpp_install_translate_pdo_error()` in `web/install/includes/helpers.php` (#1335 m4). Pattern-matches the four error codes a non-technical operator is most likely to hit — 1045 (access denied), 2002 (host unreachable), 1049 (unknown database), 1044 (denied for user on database) — and emits a friendlier translation; falls back to the raw message for unrecognised codes so debugging stays possible. Pre-fix the wizard surfaced `SQLSTATE[HY000] [1045] Access denied for user 'sourcebans'@'192.168.96.5' (using password: YES)` verbatim, which is gibberish to non-DBAs and includes the panel-as-seen-by-DB internal IP (minor information disclosure). Regression test: `web/tests/integration/InstallGuardTest.php::testPdoErrorTranslationCoversCommonCodes`. |
| Run a stack in parallel with another worktree | Worktree-local `docker-compose.override.yml` (see "Parallel stacks") |
| Local dev stack details                | `docker/README.md`                                       |
