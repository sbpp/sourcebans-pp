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
| Change the CLA text, the `web/**` paths filter on the CLA workflow, the allowlist, or the sign-comment phrase | `CLA.md` + `.github/workflows/cla.yml` + `CONTRIBUTING.md` (rationale / how-to-sign) + the "Contributor License Agreement gate" Conventions block in `AGENTS.md`. Sign-comment phrase is duplicated in three places (workflow `if:`, `custom-pr-sign-comment`, CLA.md §10) — keep them byte-identical. |
| Change a `./sbpp.sh` command surface                        | `AGENTS.md` (Dev commands) + `docker/README.md`       |
| Introduce a new convention or pattern (e.g. View DTOs)      | `AGENTS.md` (Conventions) + `ARCHITECTURE.md` if it's an architectural shift |
| Remove a legacy pattern                                     | `AGENTS.md` (Anti-patterns) + `ARCHITECTURE.md` (Legacy patterns being phased out) |
| Change auth, CSRF, or permissions semantics                 | `ARCHITECTURE.md` (the relevant subsystem) + `AGENTS.md` (Conventions) if the rule changes |
| Add a new permission flag                                   | `web/configs/permissions/web.json`, regen API contract; doc only if the **role** of the flag affects conventions |
| Change the local dev stack (Docker, db-init, env vars)      | `docker/README.md` first, link from `ARCHITECTURE.md` if it changes the dev mental model |
| Edit user-facing install/quickstart                         | `docs/src/content/docs/getting-started/quickstart.mdx` (tarball flow) OR `quickstart-docker.mdx` (Docker flow). Keep the `<Tabs syncKey="install-path">` arms in `overview.mdx` + `prerequisites.mdx` consistent across the two paths (the README is a tiny landing page that links to docs — don't grow it back into a manual). |
| Add or change a wizard step (page handler / View / template / shared helper) | `AGENTS.md` (Install wizard convention block) + the "Edit a step of the install wizard" row in "Where to find what" |
| Touch `docker/Dockerfile.prod`, `docker/php/prod-*`, `docker/apache/sbpp-prod.conf`, `docker-compose.prod.yml`, `.env.example.prod`, `docker/caddy/Caddyfile.example`, or `web/health.php` | `AGENTS.md` (Quality gates: `docker-image.yml` row; "Where to find what": the "Build / extend the production Docker image" + "Deploy / configure the production Docker stack" rows) + `docs/src/content/docs/getting-started/quickstart-docker.mdx` (the operator-facing doc) + `docker/README.md` (dev-vs-prod pointer). The `Plugin build specifics` block in AGENTS.md has a sibling `Prod Docker image specifics` block — keep them in sync with the workflow's tag mapping / sign step. The Docker-image gate is **release-only** (fires on `*.*.*` tag pushes + manual `workflow_dispatch` reruns); contributors who edit the Dockerfile / entrypoint MUST run the `docker buildx build` command from the Quality gates row locally before opening the PR — there is no per-PR CI gate to catch a broken image build. |
| Change a user-facing install / upgrade / troubleshooting flow (PHP or SourceMod version requirements, installer wizard steps, `config.php` behavior, `web/updater/` runner output, plugin `databases.cfg` / `sourcebans.cfg` shape, error messages a self-hoster will see) | The relevant page under `docs/src/content/docs/` (the Starlight site published at sbpp.github.io). |
| Add or remove a config knob a self-hoster sets (`config.php` keys, `databases.cfg` fields, plugin convars users tune) | `docs/` page that documents that knob, plus the matching `docs/src/content/docs/updating/*.mdx` page if it's a breaking change between releases |
| Ship a new feature with a self-hoster-visible setup step (Discord forwarder, demos, theming, etc.) | New page or section under the right `docs/` group + sidebar entry in `docs/astro.config.mjs` |
| Publish or amend a project announcement (the admin-only home-dashboard banner) | `docs/public/announcements.json` (Astro publishes it as a static asset at `https://sbpp.github.io/announcements.json`). One-file source of truth — don't reach for an admin endpoint, an in-panel composer, or a separate git repo. Schema + sort + expiry rules are in `docs/src/content/docs/configuring/announcements.mdx` (operator-facing) and the "Project announcements feed" Conventions block in `AGENTS.md` (agent-facing). The starter file is `[]` (empty array); the deploy chain (`docs-deploy-trigger.yml`) ships the updated file within minutes of merge to `main`. |
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

CI runs six gates on every PR. Match them locally before opening one.
A seventh gate (the production Docker image) runs **only on release
tag pushes** — see the row's note + the `Prod Docker image specifics`
block below for the rationale and the contributor-side responsibility.

| Gate           | Local                                | CI workflow            |
| -------------- | ------------------------------------ | ---------------------- |
| PHPStan        | `./sbpp.sh phpstan`                  | `phpstan.yml`          |
| PHPUnit        | `./sbpp.sh test`                     | `test.yml`             |
| ts-check       | `./sbpp.sh ts-check`                 | `ts-check.yml`         |
| API contract   | `./sbpp.sh composer api-contract`    | `api-contract.yml`     |
| Playwright E2E | `./sbpp.sh e2e`                      | `e2e.yml`              |
| Plugin build   | `(cd game/addons/sourcemod/scripting && spcomp -i include sbpp_*.sp)` | `plugin-build.yml`     |
| Prod Docker image (release-only) | `docker buildx build --platform linux/amd64,linux/arm64 -f docker/Dockerfile.prod .` | `docker-image.yml` (tag pushes + `workflow_dispatch` only — no per-PR run; contributor MUST run the local command before merging Dockerfile / entrypoint changes) |

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

Plugin build specifics:

- Path-filtered to `game/addons/sourcemod/scripting/**` plus the
  workflow file itself, so a PR that only touches `web/` or docs
  doesn't pay for a SourcePawn round-trip. PRs that touch a plugin
  source — `.sp`, `.inc`, or anything nested like
  `sbpp_admcfg/sbpp_admin_groups.sp` — fire the gate.
- Compiles **only the top-level `.sp` files** (shallow `*.sp` glob
  in `scripting/`). Sub-files like `sbpp_admcfg/sbpp_admin_groups.sp`
  and `sbpp_admcfg/sbpp_admin_users.sp` are `#include`d by their
  parent (`sbpp_admcfg.sp` line 49-50) and don't compile standalone;
  using a recursive `**/*.sp` glob would surface phantom failures.
- Pinned to SourcePawn `1.12.x` via `rumblefrog/setup-sp@master` —
  same compiler version `release.yml`'s build-plugin step uses, so
  the gate exercises exactly what the next release tag will
  compile. Bumping the floor (e.g. to 1.13.x) requires updating
  `release.yml` in the same PR; the two must stay in lockstep.
- Loop continues on individual failures and emits one
  `::error file=…::` annotation per broken plugin so a PR that
  breaks more than one (#1378's auto-DB-reconnection rewrite of
  `sbpp_sleuth.sp` AND `sbpp_checker.sp` together is the canonical
  multi-file shape) sees every error in one CI run instead of
  fix-push-fix-push round-trips.
- No local `./sbpp.sh` wrapper. The dev stack doesn't ship spcomp
  (it's not relevant to panel work), so plugin contributors install
  spcomp themselves and run the literal command from the table
  above. Untouched-plugin PRs: trust the gate.

Prod Docker image specifics:

- **Trigger surface is deliberately narrow: tag pushes + manual
  `workflow_dispatch` only.** No `push: branches: [main]`, no
  `pull_request:`. A multi-arch (amd64 + qemu-emulated arm64) build
  is the most expensive job in this repo's CI matrix (~8-15 minutes
  per run); pre-narrow this workflow ran on every push to main AND
  every PR touching a long path filter, burning a disproportionate
  share of the project's free Actions minutes for floating `:main` /
  `:sha-<short>` tags that nobody pulls (self-hosters all pin to
  released semver tags per the docs). The image surface is small +
  stable — runtime-affecting changes (Dockerfile, entrypoint, schema
  files, init bootstrap, health.php, trust-proxy + telemetry hooks)
  always ship behind a release tag, so verifying-at-tag is sufficient
  AND well-aligned with when self-hosters actually pull a new image.
  The trade-off the project explicitly accepts: a Dockerfile /
  entrypoint regression that landed via a green PR isn't caught by
  CI until the next `*.*.*` tag push. **Contributors who edit any
  file the runtime image bakes in (Dockerfile, entrypoint, php.ini,
  Apache conf, sb-db.php, health.php, schema files, init bootstrap,
  Auth/Host.php, Telemetry.php) MUST run the literal `docker buildx
  build --platform linux/amd64,linux/arm64 -f docker/Dockerfile.prod .`
  command from the Quality gates table locally before opening the
  PR — the local command IS the per-PR gate.**
- Multi-arch (linux/amd64 + linux/arm64) via `docker/setup-qemu-action@v3`
  + `docker/setup-buildx-action@v3`. The arm64 leg runs under qemu
  on the amd64 GitHub-hosted runner — roughly 2x build time vs
  native, acceptable for the release-only publish cadence.
- Tag mapping via `docker/metadata-action@v5`: `X.Y.Z` tag → `:X.Y.Z`
  + `:X.Y` + `:X` + `:latest`. `:latest` is gated on
  `startsWith(github.ref, 'refs/tags/')` so a `workflow_dispatch`
  from a non-tag ref can't accidentally claim it. There are no
  rolling `:main` / `:sha-<short>` tags — see the table in
  `docs/src/content/docs/getting-started/quickstart-docker.mdx`
  for the operator-facing tag list. A `workflow_dispatch` from a
  non-tag ref produces an empty tag set and `docker push` fails
  loudly (the documented "rebuilding a non-released ref isn't a
  meaningful operation" gate).
- Sigstore cosign signs each tag against the immutable digest
  (`<image>@<digest>`, not the mutable `<image>:<tag>`) in keyless
  / OIDC mode. The job-level `id-token: write` permission is what
  enables the OIDC token request; without it cosign can't get a
  Fulcio cert and signing fails closed. Verifiers pin both the
  identity (workflow path) and the issuer (GitHub Actions OIDC
  endpoint) — see `docs/src/content/docs/getting-started/quickstart-docker.mdx`
  for the canonical `cosign verify` command. The cosign step is
  always-on (no PR exemption is needed — PRs don't trigger the
  workflow at all under the tag-only contract).
- No local `./sbpp.sh` wrapper. The dev stack doesn't ship a way
  to invoke `docker buildx` from inside the dev container itself
  (no Docker-in-Docker), and the prod image build is a host-side
  workflow. Contributors verify a local build with the literal
  command from the gates table.

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
| `Sbpp\View\Toast`                        | server-side toast emitter (`emit(kind, title, body, ?redirect)` — replaces v1.x `ShowBox(...)`) |
| `Sbpp\View\*`                            | view DTOs (page-level + partials)     |
| `Sbpp\Servers\SourceQueryCache`          | per-`(ip, port)` on-disk cache around the xPaw A2S probe (#1311) |
| `Sbpp\Servers\RconStatusCache`           | per-`sid` on-disk cache around the RCON `status` command (#PLAYER_CTX_MENU) |
| `Sbpp\Upload\UploadHandler`              | shared file-upload handler (size + extension allowlist + filename sanitiser) for the three popup upload pages — see `goals#5` |
| `Sbpp\Markup\IntroRenderer`              | admin-authored Markdown renderer      |
| `Sbpp\Mail\Mail` / `Sbpp\Mail\Mailer` / `Sbpp\Mail\EmailType` | `Mail::send(...)` entry point + Symfony Mailer SMTP wrapper + email-type enum |
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
- **SteamID inputs**: ALWAYS gate `SteamID::toSteam2($raw)` (or any
  other `SteamID::*` conversion that calls `resolveInputID` internally)
  with an explicit strict-shape `preg_match` check first, and throw
  `ApiError('validation', '…', '<field>')` on the fail branch.
  `SteamID::resolveInputID()` throws a generic `\Exception` for
  unrecognised input shapes; without the gate that exception escapes
  the handler and the dispatcher's `Throwable` fallback wraps it as a
  generic `server_error` envelope (HTTP 500), which is unhelpful both
  to the client-side `r.error.field` branching AND to operators
  triaging a "the form silently broke" report (#1420). The pattern:

  ```php
  $rawSteam = trim((string)($params['steam'] ?? ''));
  if ($rawSteam === '') {
      throw new ApiError('validation', 'You must type a Steam ID or Community ID', 'steam');
  }
  if (!preg_match(SteamID::HANDLER_STRICT_REGEX, $rawSteam)) {
      throw new ApiError('validation', 'Please enter a valid Steam ID or Community ID', 'steam');
  }
  $steam = SteamID::toSteam2($rawSteam);
  ```

  `SteamID::HANDLER_STRICT_REGEX` (#1423 follow-up #4) is the single
  source of truth the per-handler `preg_match` calls consume — see
  `api_comms_add` / `api_bans_add` / `api_admins_add` for the
  canonical reference shape. The constant's docblock spells out the
  contract: byte-for-byte symmetry with the form template's
  `pattern="STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17}"`, the load-bearing
  `D` modifier (without it `STEAM_0:0:1\n` slips past the gate and
  500s on `toSteam2()`), and the deliberate asymmetry with
  `ID_PATTERNS` (bracketless Steam3 `U:1:N` is excluded from the
  handler gate — it stays a library-side convenience for the
  conversion path but isn't an accepted panel-input shape because
  the form template's `pattern` doesn't accept it either). Don't
  hand-roll a copy of the regex literal at the handler call site —
  the pre-#1423-follow-up-#4 hand-rolled copies silently missed the
  `D` modifier, producing the newline-bypass class. The client-side
  native validation in the corresponding form template uses the
  matching `pattern="STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17}"` (HTML's
  `pattern` attribute is implicitly anchored `^…$`, so the PHP
  regex carries explicit `^…$`); the browser-native popover is the
  UX-first gate that fires BEFORE the IIFE calls `sb.api.call`; the
  server-side `preg_match` is the load-bearing security gate for
  curl-driven / third-party-theme callers that bypass it.

  The pre-#1420 `SteamID::isValidID($raw)` was structurally unsafe
  — the bundled helper's regexes were unanchored with loose character
  classes (`STEAM_[0|1]:[0:1]:\d*` — the `|` inside `[...]` is a
  literal pipe, not alternation, the missing `^`/`$` anchors left
  them as substring matchers, and `\d*` accepted zero digits).
  Three concrete bypass shapes the loose gate accepted:
   - `'STEAM_0:0:'` (empty Z) passed `isValidID` AND round-tripped
     through `toSteam2()` unchanged, storing an invalid SteamID in
     `:prefix_admins.authid` / `:prefix_bans.authid` /
     `:prefix_comms.authid`.
   - `'asdfSTEAM_0:0:123'` (substring-embedded suffix) passed
     `isValidID` AND `toSteam2()` returned the input verbatim
     (the resolveInputID switch matched the SteamID branch via
     substring, then re-extracted via the same regex which returned
     the full input string), corrupting downstream SourceMod admin
     matching and the panel's per-row UI.
   - `'asdf 76561197960265728 garbage'` (embedded 17-digit Steam64)
     passed `isValidID` AND `toSteam2()` emitted a corrupt canonical
     form (`'STEAM_0:0:-38280598980132864'` — negative Z from the
     parser eating the surrounding bytes during numeric conversion).

  The library tightening landed in #1423 follow-up #1: a shared
  `SteamID::ID_PATTERNS` constant carries the four accepted shapes
  with `^…$` anchors, the `D` modifier (strictly anchors `$` to
  end-of-string, closing the `STEAM_0:0:1\n` newline-bypass
  sibling), `[01]` character classes, and `\d+` quantifiers; both
  `isValidID()` and `resolveInputID()` consume the same table so
  they cannot drift. The per-handler `preg_match` documented above
  is now true defence-in-depth (the two layers agree on the
  accepted shape) — KEEP both. Reaching for `SteamID::isValidID()`
  alone as the server-side gate is fine after the library fix; the
  per-handler regex stays in `api_comms_add` / `api_bans_add` /
  `api_admins_add` as the load-bearing structural-shape contract
  (the JSON wire is hostile-input-shaped; the library is one
  refactor away from someone "simplifying" the shared table back
  to a loose form, and the defense-in-depth means that refactor
  doesn't re-open the bypass class). Regression coverage for both
  layers: `web/tests/integration/SteamIDValidationTest.php` pins
  the library's shape contract (every bypass above is asserted as
  rejected, the new `HANDLER_STRICT_REGEX` constant's `D`-modifier
  + bracketless-Steam3-rejection contract is pinned by
  `testHandlerStrictRegexIsExposedAndCarriesDModifier` /
  `testHandlerStrictRegexAgreesWithIdPatternsOnAcceptableShapes` /
  `testHandlerStrictRegexRejectsBracketlessSteam3` /
  `testHandlerStrictRegexRejectsNewlineBypass`, the OpenID
  regex tightening in `SteamAuthHandler` is pinned by
  `testSteamAuthHandlerOpenIdRegexAcceptsOnly17DigitsStartingWith7`,
  and the install wizard's regex is pinned by
  `testInstallWizardSteamIdRegexCarriesDModifier`);
  `web/tests/api/CommsTest.php::testAddRejectsInvalidSteamIdShape`
  + `BansTest.php` + `AdminsTest.php` pin the handler-side
  `ApiError('validation', …, 'steam')` envelope (each test now
  includes the `STEAM_0:0:1\n` / `[U:1:1]\n` / `76561197960265728\n`
  newline-bypass cases as #1423 follow-up #4 regression guards);
  `web/tests/api/BansTest.php::testAddIpTypeAlwaysWritesEmptyAuthid`
  pins the IP-type empty-`authid` contract (valid Steam input +
  garbage + newline-bypass all write empty); the kickit / blockit
  `SteamID::compare()` pre-`isValidID()` gate is pinned by
  `KickitTest::testKickPlayerReturnsNotFoundForMalformedSteamId` /
  `testKickPlayerReturnsNotFoundForMalformedIp` and
  `BlockitTest::testBlockPlayerReturnsNotFoundForMalformedSteamId`.

  **Page-handler form-POST surfaces** (`page.submit.php`,
  `admin.edit.{ban,comms,admindetails}.php`,
  `admin.bans.php`'s `importBans` branch) carry the same
  convert-before-validate bug class as the JSON handlers, but the
  failure mode is different: the converter's
  `Exception('Invalid SteamID input!')` escapes the page handler
  unhandled, the chrome's `PageDie()` never fires, and the user
  gets a generic 500 page render instead of the inline per-field
  error message on the form. The fix shape is symmetric to the
  JSON-handler one but the error-surfacing differs by surface:
   - `admin.edit.{ban,comms,admindetails}.php` push the validation
     error into the existing `$validationErrors[]` / `$errorFields[]`
     array and re-render the form via the page-tail script (Option
     B per "Add a confirm + reason modal" — matches the established
     pattern for empty / duplicate / invalid SteamID, and preserves
     the operator's raw input on the bounce so they see exactly
     what they typed and can correct the typo without re-typing
     everything else).
   - `admin.edit.ban.php` ON IP-TYPE bans hard-codes
     `$_POST['steam'] = ''` regardless of whatever the operator
     typed in the Steam ID field — the column is the *steam id*
     of the banned player; on an IP-type ban there is no steam id,
     so the canonical value is the schema's `NOT NULL default ''`
     empty string (matching `api_bans_add`'s same-PR fix). The
     pre-#1423-follow-up-#4 shape (the `82e8c3d2` "canonicalise on
     IP-type" nit) preserved the canonical-on-valid case but
     failed to suppress the raw-on-invalid case AND continued
     writing the canonicalised SteamID into `:authid` for IP-only
     bans — both shapes are wrong. The form-side input remains
     visible on the IP-type bounce path (re-emit through the
     template `placeholder`, NOT a stale value), but the DB write
     is divorced from it.
   - `page.submit.php` doesn't call `SteamID::toSteam2()` at all —
     it stores the raw user-input verbatim in `:prefix_submissions`
     and the moderation queue resolves the canonical form on
     accept. The library tightening (follow-up #1) closes the bypass
     here without any handler edit; the template-side strict
     `pattern="…"` is the front-line defense.
   - `admin.bans.php`'s `importBans` branch validates each
     `banid <duration> <STEAMID>` line via `SteamID::isValidID()`
     BEFORE `toSteam2()`, skipping (and counting) malformed lines
     instead of throwing. The pre-fix abort-on-first-bad-line shape
     left the operator with no signal as to which line broke or
     how many of the preceding inserts committed (no transaction
     wrapper). The success toast now carries the skipped-line
     count alongside the imported count. Note: no transaction
     wrapper is in place — partial commits are still possible.
     The skipped-count surfaces in the success toast string so the
     operator gets actionable feedback; full atomic-import work
     (transaction + per-line audit trail) is a sister follow-up.
   - `SteamID::compare($a, $b)` (used by `api_kickit_kick_player`
     / `api_blockit_block_player` to per-player-match A2S
     responses) routes through `toSteam64()` and throws on
     invalid input. Pre-#1423-follow-up-#4 the iframe handlers
     reached `compare()` with the operator-controlled `$check`
     value unvalidated — a hostile caller (or a typo'd deep-link
     URL) reliably 500'd the iframe; the loop renderer had no way
     to tell "no match" apart from "your input was garbage".
     Gate `$check` with `SteamID::isValidID()` (Steam-type) or
     `filter_var(FILTER_VALIDATE_IP)` (IP-type) BEFORE the
     `compare()` call and surface the structured `not_found`
     envelope the iframe expects on the fail branch.

  The validate-before-convert order is THE structural contract:
  call `SteamID::isValidID($raw)` first, surface the error on the
  fail branch, ONLY THEN call `SteamID::toSteam2($raw)`. With the
  library tightening from follow-up #1 every input that passes
  `isValidID()` is guaranteed to round-trip through `toSteam2()`
  without throwing, so the per-handler `try/catch` defensiveness
  that some pre-fix code accidentally relied on (`empty()` short-
  circuits in `to()`, etc.) is no longer needed. Don't ship a
  `try/catch (Exception)` around `toSteam2()` as a substitute for
  the upstream gate — it papers over the bug class without fixing
  the underlying ordering and weakens the contract on the next
  refactor. Regression coverage:
  `web/tests/integration/SteamIDValidationOrderTest.php` static-
  shape-pins the validate-then-convert order across every page
  handler. The form templates also carry the same
  `pattern="STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17}"` +
  actionable `title="…"` as the JSON-flow add-form templates so
  the browser-native popover surfaces the same error message
  pre-flight.

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

### Server-side toast emission (`Sbpp\View\Toast::emit`)

PHP page handlers that need to surface a toast (success / error
confirmation) to the user — either as the only signal on a
GET-fallback path or alongside a `PageDie()`-rendered chrome
footer — emit through `Sbpp\View\Toast::emit($kind, $title, $body,
?$redirect, ?$duration_ms)`. Single source for the wire format the
chrome's `flushPendingToasts` consumer in
`web/themes/default/js/theme.js` reads on `DOMContentLoaded`.

Lifted at #1403 from the inline `emitSubmitToast()` helper in
`page.submit.php` after an audit found six PHP page handlers
(35 sites total) still echoing raw `<script>ShowBox(...)</script>`
blobs from server-side branches. `ShowBox` lived in
`web/scripts/sourcebans.js`, deleted at #1123 D1 (v2.0.0) — every
legacy caller silently threw `ReferenceError` and (worse, when the
handler ran upstream of `PageDie()`) suppressed the template body
too, leaving the user on a literally blank white page. The
marquee user-reported regression: `?p=lostpassword` success path
silently emailed the new password while showing nothing to the
user, leading to "I clicked Reset Password three times because
nothing happened" double-submits that burned three validation
tokens. See the matching Anti-patterns entry for the full repro.

Wire format (`$kind` ∈ `'info' | 'success' | 'warn' | 'error'`;
`redirect` and `duration_ms` are both optional — see "Redirect
coalescing" and "Duration semantics" below for the contract on each):

```html
<script type="application/json" class="sbpp-pending-toast">
{"kind":"success","title":"Password reset","body":"...","redirect":"?p=login"}
</script>
<script type="application/json" class="sbpp-pending-toast">
{"kind":"error","title":"Ban NOT Deleted","body":"...","duration_ms":0}
</script>
```

- `<script type="application/json">` is parsed as text content;
  the browser does NOT execute it. CSP-friendly (a future
  `script-src 'self'` policy would reject inline executable
  shapes outright). Sibling pattern to the `palette-actions`
  blob in `core/footer.tpl` (#1304); single source for "embed
  structured data the chrome consumes at boot".
- Class, not id: multiple `Toast::emit(...)` calls in the same
  response stack cleanly — the chrome iterates the full set.
  No `data-testid` on the wire-format block by design — a
  multi-emit response would emit several blocks with the same
  testid and Playwright's `getByTestId(...)` strict mode rejects
  multi-match. E2E specs anchor on the painted
  `[data-testid="toast"]` element (set by `showToast` after the
  chrome JS picks up the blob) or the painted role attribute
  (`[role="alert"]` for `kind === 'error'`, `[role="status"]`
  otherwise — see "ARIA role contract" below); wire-layer specs
  probe the response body directly for
  `class="sbpp-pending-toast"`.
- `body` is plain text — `theme.js`'s `escapeHtml` escapes it
  before inserting into the DOM. HTML tags surface as visible
  literal text; convert at the call site with the canonical
  `preg_replace` shape (see `page.protest.php` /
  `page.submit.php` for the reference). NEVER reach for `nofilter`
  on a `Toast::emit` body — the chrome side is the single
  escape contract, and the wire-format JSON encoder is the
  load-bearing escape for everything else.

Encoder flags (`Sbpp\View\Toast::emit`'s `json_encode` call):

```
JSON_THROW_ON_ERROR
| JSON_INVALID_UTF8_SUBSTITUTE
| JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
```

- The four `JSON_HEX_*` flags hex-escape every `<>&'"` so the
  blob cannot break out of its `<script>` wrapper regardless of
  caller payload (a `</script>` in `body` is encoded as
  `\u003C/script\u003E`).
- `JSON_INVALID_UTF8_SUBSTITUTE` is the load-bearing
  fault-tolerance flag. Player names on `:prefix_bans.name` /
  `:prefix_comms.name` (interpolated into the toast body on the
  GET-fallback unban / unmute / delete paths in `page.banlist.php`
  / `page.commslist.php`) CAN carry malformed UTF-8 — historical
  Latin-1-on-utf8 truncation shape from pre-#1108 / #765 installs
  whose plugin-side insert path wrote bytes the post-#1108
  utf8mb4 migration did not retroactively repair. Without this
  flag `JSON_THROW_ON_ERROR` raises `JsonException` on every such
  row and the user gets a 500 instead of the unban confirmation
  — and worse, the unban / delete SQL has ALREADY committed by
  the time `Toast::emit` fires, so the audit log shows the action
  succeeded while the operator sees a server error. With this
  flag the offending bytes substitute to U+FFFD (the Unicode
  REPLACEMENT CHARACTER) and the toast paints. Well-formed
  payloads are unaffected.
- `JSON_THROW_ON_ERROR` surfaces other malformed input loudly
  instead of silently emitting `false` (which `echo` would
  render as the empty string — invisible regression vector).

Redirect coalescing (FIRST wins; suppressed by persistent toasts):

`$redirect` is optional. When passed, `theme.js`'s
`flushPendingToasts` honours it AFTER the toasts paint, with a
~1500ms post-paint settle delay so the user can read what just
happened before the navigation lands. If several `Toast::emit`
calls in the same response carry a redirect, the FIRST one wins
(`if (redirectTo === null && typeof data.redirect === 'string'
&& data.redirect !== '') redirectTo = data.redirect;`). In
practice a single request never emits more than one redirect —
the GET-fallback paths in `page.banlist.php` / `page.commslist.php`
/ `admin.edit.comms.php` all bounce back to the same list page
regardless of the success/error branch — but FIRST-wins is the
safer default if a future caller emits a sibling toast first.
The 1500ms settle is a fixed constant in `theme.js`'s
`flushPendingToasts`; long-form bodies that need more reading
time should NOT carry a redirect (the user is leaving the page
anyway, the post-redirect surface can re-surface the same toast
if persistent).

Persistent + redirect mutual exclusion (#1409): when ANY toast in
the drained queue carries `duration_ms: 0` (persistent), the
chrome SUPPRESSES the redirect setTimeout entirely — the
auto-navigation would otherwise tear down the toast ~1500ms after
paint, defeating the whole point of the persistent semantic
(operator never gets to read or dismiss it). The call-site
contract is to pass `null` for `$redirect` when emitting
`duration_ms: 0`; the chrome's whole-drain inhibit is
defence-in-depth so a future caller forgetting that rule
doesn't silently regress to broken UX. Both halves of the
contract ship together (the static gate in
`ToastEmitRegressionTest::testNotStarBranchesPassPersistentDurationMs`
asserts the call-site half; the chrome-side inhibit is exercised
end-to-end by `toast-persistent-duration.spec.ts`); neither half
is sufficient on its own. Whole-drain (not per-block) inhibit:
if a mixed queue carries persistent + non-persistent toasts, the
non-persistent toasts still paint with their auto-dismiss
timers, but the redirect is gated on the user's explicit X-click
on the persistent toast. (No in-tree caller emits a mixed queue
today; the contract is conservative for future surfaces.)

Duration semantics (#1409):

`$duration_ms` is optional and OMITTED from the wire format when
the caller didn't pass an override — the chrome's `showToast`
falls through to its `SHOWTOAST_DEFAULT_DURATION` constant
(`4000` in `theme.js`). Three values are meaningful:

- `null` (default) — omit the field. The toast auto-dismisses
  after `SHOWTOAST_DEFAULT_DURATION` (~4000ms). The right
  choice for every routine info / success / warn confirmation.
- `0` — persistent. The chrome does NOT schedule an auto-dismiss
  timer at all; the only way the toast disappears is the user
  clicking the X button. Reserved for severe-error
  "this destructive operation FAILED and the operator must
  acknowledge before moving on" branches. The five canonical
  call sites are the NOT-* (capital NOT) branches in
  `page.banlist.php` / `page.commslist.php`:
  - `page.banlist.php` — "Player NOT Unbanned", "Ban NOT Deleted"
  - `page.commslist.php` — "Player NOT UnGagged" (ungag failure),
    "Player NOT UnGagged" (unmute failure), "Ban NOT Deleted"
- `> 0` — explicit override in milliseconds. Currently no in-tree
  caller uses this; the contract exists so a future surface
  (e.g. a long-form Markdown-rendered toast) can lengthen the
  read window without resorting to persistent display.

Negative values are programmer error — the helper throws
`\InvalidArgumentException` rather than silently coercing.
The "Fail closed" instinct applies: a negative arrival means
the caller's arithmetic is broken; coercion would mask the
bug AND silently flip the toast to persistent (the
worst-of-both outcome).

`SHOWTOAST_DEFAULT_DURATION` lives in `theme.js`'s TOASTS
block — single source for the chrome-side default. Don't
mirror the literal `4000` into other tests or call sites;
reference the PHP-side `null` default and trust the chrome.
The 4000ms default is itself a constant the project doesn't
tune lightly — see the "Passing duration_ms = 0 for routine
info / success toasts" Anti-patterns entry for the
counter-direction (don't reach for `0` to dodge the timer
on casual confirmations).

The consumer-side gate in `flushPendingToasts` is
`typeof data.duration_ms === 'number'`. A hostile / malformed
payload sending a string or boolean must NOT pass through
unchecked — `showToast` would then schedule
`setTimeout(..., "0")` (Number-coerced to 0, auto-dismisses
immediately) or `setTimeout(..., true)` (coerced to 1ms).
The typeof gate keeps the chrome's behaviour deterministic
regardless of upstream noise; the encoder side already
enforces an `int|null` PHP type so the only way a non-number
lands on the wire is a hand-rolled malformed payload.

ARIA role contract (#1409 review):

The painted `[data-testid="toast"]` element carries a
kind-aware `role` attribute:

- `role="alert"` for `kind === 'error'` (assertive — screen
  readers INTERRUPT the current announcement to surface the
  toast).
- `role="status"` for every other kind (`info` / `success` /
  `warn` — polite; the announcement waits for the user to
  finish what they're listening to).

The distinction matters most for the persistent error toasts
(`duration_ms: 0`): a polite `role="status"` announcement on
a "destructive action FAILED, you MUST acknowledge before
moving on" toast can be silently missed by a screen-reader
user who's focused elsewhere — exactly the population least
likely to notice the visual chrome change. `role="alert"`
maps to the W3C ARIA spec's `aria-live="assertive"`
semantic, which is the canonical answer for "this is
serious and the user needs to know NOW". Apple HIG,
Material Design, and Bootstrap all converge on the same
error/non-error split.

The role is set on the painted DOM element by `showToast`
in `theme.js` — `el.setAttribute('role', kind === 'error'
? 'alert' : 'status')`. The wire-format `<script>` block
deliberately carries no role attribute (it's not a live
region; the chrome's painted element is the announcement
target). E2E specs that anchor on `role="status"` would
miss error toasts under the kind-aware shape; use
`[data-testid="toast"]` as the kind-independent anchor and
filter by `data-kind="error"` if the spec specifically
wants the error variant.

PHP-side call site:

```php
\Sbpp\View\Toast::emit('success', 'Password reset', 'Email sent.', '?p=login');
PageDie();

// Severe-error: persistent toast (`duration_ms: 0`) so the
// operator has to acknowledge before it disappears. `$redirect`
// MUST be `null` — persistent + redirect are mutually exclusive
// per "Redirect coalescing" above. The page handler continues
// rendering the underlying surface (no PageDie() here) so the
// operator stays on a valid page while reading the toast.
\Sbpp\View\Toast::emit(
    'error',
    'Ban NOT Deleted',
    "The ban for '" . $name . "' had an error while being removed.",
    null,
    0,
);
```

Always FQN — no `use Sbpp\View\Toast;` shim per call site, no
`class_alias` to a global name. The FQN call shape is what the
`ToastEmitRegressionTest` static gate keys on; legacy procedural
code adopts the FQN at the same time it adopts every other
`Sbpp\...` namespaced API.

Always pair with `PageDie()` (or `exit`) when the handler's
"render the page body" path is no longer meaningful (e.g. the
form's input failed validation; a "not found" SELECT returned
no row). Pre-#1403 the legacy `<script>ShowBox(...)</script>`
shape carried its own `window.location` navigation that beat
the page render to the user's eyeballs; post-lift the toast
honours the redirect via the helper's 4th arg + the 1500ms
settle, so a "the SELECT returned no row, the form template
will dereference false" branch MUST `PageDie()` to cut the
render before the borked form skeleton paints. See the
`admin.edit.comms.php` "block not found" branch for the
canonical reference shape.

Regression guards:
- `web/tests/integration/ToastEmitRegressionTest.php` — static
  grep against `web/pages/*.php` (PHP page handlers only; the
  template-side `<script>ShowBox(...)` regression is sister
  #1402's surface), wire-format contract, JSON-escape contract,
  UTF-8-substitute contract, FIRST-wins-redirect contract,
  `duration_ms` contract (omitted by default, included when set,
  rejects negatives, JSON-escape guarantees preserved when
  present, the 5 NOT-* call sites in `page.banlist.php` /
  `page.commslist.php` still pass `duration_ms: 0`),
  "every audited page still calls Toast::emit" call-site
  contract.
- `web/tests/e2e/specs/flows/lostpassword-toast.spec.ts` (the
  marquee user-reported regression — drives the success +
  error branches end-to-end, asserts the password-reset email
  lands in mailpit AND the chrome footer renders AND the toast
  paints), `protest-toast.spec.ts`, `banlist-getfallback-toast.spec.ts`,
  `commslist-getfallback-toast.spec.ts`, `admin-edit-comms-toast.spec.ts`,
  `toast-persistent-duration.spec.ts` (#1409 — drives a NOT-*
  branch's wire-format payload through the chrome and asserts
  the toast is STILL visible past the default ~4000ms window;
  the X-button dismiss is the only way out).

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

### Project announcements feed (`Sbpp\Announce\AnnouncementFetcher`)

Anonymous, opt-out-by-default daily fetch of project news + security
advisories from a JSON file the maintainers publish at
`https://sbpp.github.io/announcements.json`. The dashboard renders
the freshest non-expired entry as a slim disclosure strip between
the stat cards and the recent-activity panels — visible only to
logged-in admins. Same opt-out shape, lifecycle, and never-fail-the-
request contract as `Sbpp\Telemetry\Telemetry` — the two share the
"daily background tick fired from `register_shutdown_function` after
the response flushes" pattern.

The contract:

- **Source of truth** is in this repo at
  `docs/public/announcements.json` (a JSON array sorted newest-first;
  Astro publishes everything under `docs/public/` as a static asset
  at the docs site root, proven by `favicon.svg`). Maintainers ship
  announcements via PRs against this file — full review, full git
  history, no separate CMS or admin endpoint. The
  `.github/workflows/docs-deploy-trigger.yml` workflow lands the
  updated file at `https://sbpp.github.io/announcements.json` within
  a few minutes of merge to `main`.
- **Schema** per entry: `id` (string ≤64 chars, **required**),
  `title` (string, **required**), `body_md` (optional CommonMark),
  `url` (optional `http(s)://` only — javascript:/data: are rejected
  at the parser), `published_at` (optional ISO-8601 or unix int —
  drives sort order), `expires_at` (optional, drops the entry from
  the cache once past). The parser drops malformed / expired /
  duplicate-id entries and surfaces the first valid one to the
  dashboard.
- **Lifecycle** (mirrors `Telemetry::tickIfDue`): page render reads
  the cache only — never blocks on the network. A
  `register_shutdown_function([AnnouncementFetcher::class, 'tickIfDue'])`
  registered at the tail of `init.php` fires on every panel + JSON
  API request; the 24h TTL gate inside the body keeps the actual
  outbound call to at most one per install per day. On FPM,
  `fastcgi_finish_request()` flushes the response BEFORE the
  upstream call so the user's TCP socket closes first.
- **Cache shape** (mirrors `system.check_version`'s
  `_api_system_release_save_cache`): atomic tempfile + `rename()`
  write under `SB_CACHE/announcements.json`, persisted as the raw
  upstream body verbatim (parsing happens at read time so the
  on-disk file is byte-identical to what the upstream served — easier
  to triage a "wrong content rendered" report). Stale-while-error:
  if the upstream call fails, the previous cache stays served
  indefinitely until a successful fetch overwrites it. The cache
  mtime is the TTL anchor, so a flapping upstream costs one fetch
  attempt per 24h regardless of request volume.
- **Wire layer**: 5s combined connect+read timeout, 256 KiB hard cap
  on the response body (enforced by the stream wrapper's `length`
  parameter AND re-asserted post-read so a future swap of the read
  layer doesn't bypass the limit), `User-Agent: SourceBans++/<ver>
  (announcements)` mirroring `system.check_version`. No query
  parameters, no cookies, no tracking pixels — the GET is a plain
  static-file fetch. The PHP stream wrapper exposes a single
  `timeout` knob covering both legs; if a future cURL-based reshape
  arrives, split into 3s connect + 5s total like the telemetry
  fetcher does and add paired tests for both legs.
- **Scheme guard**: `resolveUpstreamUrl()` only returns the
  configured URL when it starts with `http://` or `https://`.
  Anything else (`file://`, `php://`, `phar://`, `data://`, … —
  every stream wrapper `file_get_contents` honours by default)
  short-circuits to the empty-string air-gap branch. The
  operator-overridable constant means even a misconfiguration
  shouldn't be able to land arbitrary local files in the cache; the
  scheme guard is the defence-in-depth that closes the gap.
- **Air-gap escape hatch**: `define('SB_ANNOUNCEMENTS_URL', '')` in
  `config.php`. The `if (!defined(...))` gate in `init.php` lets the
  operator override before the default fires; an empty string short-
  circuits `tickIfDue()` before it flushes the response or touches
  the network. There is no in-panel toggle by design — the JSON
  feed is intentionally low-frequency + audit-friendly, so the only
  sensible "off" position is the documented config.php-side
  escape hatch in `docs/src/content/docs/configuring/announcements.mdx`.
- **Surface gating**: the page handler in `web/pages/page.home.php`
  short-circuits to `null` for anonymous + non-admin viewers
  (`$userbank->is_admin() ? AnnouncementFetcher::latest() : null`);
  the template (`page_dashboard.tpl`) gates the entire `<aside>`
  block on a truthy `$announcement` so the strip never paints for
  visitors who can't act on the content.
- **Markdown rendering**: `body_md` goes through
  `Sbpp\Markup\IntroRenderer::renderIntroText()` (CommonMark with
  `html_input: 'escape'` + `allow_unsafe_links: false`). The
  rendered HTML lands on the `Announcement` DTO as `body_html` and
  the template emits it with `{nofilter}` — the only "this is
  already safe HTML" exit point the panel supports. Reach for any
  other Markdown renderer here and you re-open the #1113-class
  stored-XSS vector. See "Admin-authored display text" below for
  the contract this inherits.
- **Test override**: `AnnouncementFetcher::_setHttpFetcherForTests`
  swaps the upstream call with a closure that returns the canned
  body (or `null` to simulate failure). Mirrors
  `Sbpp\Servers\SourceQueryCache::setProbeOverrideForTesting`.
  Production never sets it.

Regression guards:

- `web/tests/integration/AnnouncementFetcherTest.php` — cache shape,
  atomic write, stale-while-error, body-cap enforcement, TTL gate,
  expired-entry filter, malformed-JSON rejection, IntroRenderer
  integration (literal `<script>` in body_md is escaped), URL
  scheme rejection (javascript: dropped), duplicate-id de-dup,
  newest-by-published_at sort.
- `web/tests/integration/HomeDashboardAnnouncementTest.php` — the
  page handler's gate. Three tests: admin + populated cache yields
  the announcement, anonymous + populated cache yields null,
  admin + cold cache yields null. Process-isolated render with
  a stub Smarty (mirrors `Php82DeprecationsTest`) so the
  `HomeDashboardView`'s `announcement` property gets captured
  without rendering the actual `.tpl`.
- `web/tests/e2e/specs/flows/dashboard-announcement.spec.ts` —
  end-to-end: `[data-testid="dashboard-announcement"]` mounts when
  the cache is seeded, the disclosure expands on click, the body
  paints the IntroRenderer-rendered HTML, the external link carries
  `target="_blank" rel="noopener noreferrer"`, axe-core passes.
  Anonymous-storage-state arm asserts the strip never paints. The
  cache-seeding shim is `web/tests/e2e/scripts/seed-announcements-e2e.php`
  (shell-out via `seedAnnouncementsE2e` in `fixtures/db.ts`); each
  test cleans up via `clearAnnouncementsCacheE2e` in `afterEach` so
  sibling specs don't bleed.

The starter `docs/public/announcements.json` ships as `[]` (empty
array) so the panel renders "no announcement" until the maintainers
land an entry. This validates the deploy chain end-to-end before
any real content goes out — same shape `Sbpp\Telemetry\Schema1`'s
lock file uses (file shape pinned by tests, content can change
freely).

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

### Contributor License Agreement gate (`web/**`)

Pull requests that touch `web/**` are gated on a signed Contributor
License Agreement. The web panel is dual-licensable (free for hobby /
community use under CC BY-NC-SA 3.0; separate commercial licence for
production use by hosting providers), and the CLA is the mechanism
that lets the maintainer relicense future contributions without
contacting every contributor individually.

- Agreement text: [`CLA.md`](CLA.md). Ten sections, ~1 page. Contributor
  keeps copyright; maintainer gets a perpetual, irrevocable, worldwide,
  royalty-free, sublicensable licence with the **explicit right to
  relicense under any terms, including proprietary or commercial**
  (§3(b)). Same legal shape as GitLab / Discourse / Plex.
- Workflow: [`.github/workflows/cla.yml`](.github/workflows/cla.yml).
  Uses `contributor-assistant/github-action@v2.6.1`. Triggers:
  `pull_request_target` with `paths: ['web/**', 'CLA.md',
  '.github/workflows/cla.yml']`, plus `issue_comment` for the
  "I have read the CLA Document and I hereby sign the CLA" sign flow.
  Job-level `if:` gates execution so unrelated comments / non-web PRs
  don't burn action minutes. Permissions: `actions: write`,
  `contents: write`, `pull-requests: write`, `statuses: write` — all
  load-bearing for writing to the signatures branch and posting the
  PR comment / status check.
- Signature storage: orphan branch `cla-signatures` in this repo at
  `signatures/cla.json`. The action creates the branch on its first
  successful run; do NOT precreate it manually. Each entry pins the
  contributor's GitHub login, ID, the PR that recorded the signature,
  and a timestamp.
- Allowlist: the maintainer (`rumblefrog`) plus `*[bot]` (covers
  Dependabot and any future GitHub App bot). The allowlist lives in
  the workflow file's `allowlist:` field — single source of truth.
  Onboarding an additional maintainer means adding their login there
  in the same PR as any docs update naming them.
- Scope is `web/**`. Plugin-only PRs (`game/addons/sourcemod/**`)
  stay GPLv3 and intentionally skip the gate — copyleft already
  blocks quiet relicensing, so layering a CLA on top would only add
  friction. Mixed PRs (web/ + plugins) trigger the gate because of
  the web/ half; one signature unblocks both.
- The sign phrase ("I have read the CLA Document and I hereby sign
  the CLA") is duplicated in three places: the workflow's job-level
  `if:`, the workflow's `custom-pr-sign-comment:` field, and the
  CLA.md §10 acceptance section. Keep all three byte-identical — the
  action matches the contributor's comment against
  `custom-pr-sign-comment`, the `if:` matches against the same string
  to gate execution, and CLA.md §10 is what the contributor is told
  to type. Drift between any two silently breaks the signing flow.
- Historical contributors haven't signed; their pre-CLA web-panel
  contributions aren't covered by the new grant. Retroactive sign-off
  is a separate piece of work (opt-in follow-up) and isn't blocked
  by the workflow being in place.

## Anti-patterns (do NOT reintroduce)

- The 17-line legacy SourceBans 1.4.11 attribution header at the
 top of every PHP / Smarty file (the
 `/*****…  Copyright © 2007-2014 SourceBans Team … *****/` block)
 → use the 4-line v2.0 header instead:

 ```php
 <?php
 // SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
 // Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
 // See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.
 ```

 Smarty `.tpl` files use the `{* … *}` shape with the same three
 lines. The 17-line block conflated three problems: it inflated
 per-file overlap percentages in audit tooling (every file looked
 like a 35% match against a 1.4.11 source-tree even when the body
 was 100% v2.0 expression), it shipped two parallel year ranges
 (`2014-2024` and `2014-2026`) with no automated drift gate, and
 it pointed at `creativecommons.org/licenses/by-nc-sa/3.0/` for
 the licence text but `LICENSE.md` is the actual licence. The
 source-of-truth attribution surface for upstream lineage
 (SourceBans 1.4.x, SourceComms, InterWave Studios theme.conf,
 LightOpenID, TinyMCE) is `THIRD-PARTY-NOTICES.txt`. Issue
 `goals#5` swept all 36 files; new files take the 4-line shape
 from day one. Files with their own licence (`web/includes/Auth/openid.php`,
 `web/includes/tinymce/`) keep their original headers.
- Inline `echo '<form action="…">'` HTML blobs at the top of
 admin page handlers (`web/pages/admin.edit.<x>.php`) → build a
 typed `Sbpp\View\AdminEdit<X>View` DTO + `Renderer::render()` a
 Smarty template under `web/themes/default/page_admin_edit_<x>.tpl`.
 The legacy shape interleaved PHP control flow, raw HTML, and
 Smarty expressions in one file (`echo "<form action='?p=admin&c=…'>";`
 followed by `echo '<input ' . $is_owner . '>';`) and silently
 reintroduced XSS surfaces every time a developer forgot to
 `htmlspecialchars()` a user-controlled value. The View DTO →
 template path is auto-escaped by Smarty (`$theme->setEscapeHtml(true)`
 is set in `init.php`); the only escape hatch is `nofilter` which
 carries its own annotation rule (see "`nofilter` discipline" in
 Conventions). The `admin.edit.*` cluster was migrated wholesale
 in `goals#5`; new edit pages follow the same shape from day one.
- `echo '<div id="msg-red">…';` / `echo '<div id="msg-green">…';`
 inline-PHP error / success banners → use Smarty's
 `{if $error}<div class="alert alert--error">{$error|escape}</div>{/if}`
 pattern in the template + a `?string $error` property on the
 View DTO, OR (for form-submission feedback) the page-tail
 `sbpp_admin_edit_emit_tail_script()` helper from
 `web/pages/_admin_edit_helpers.php`, which writes errors into
 named `<id>.msg` divs and fires `window.SBPP.showToast()` on
 success. The legacy `<div id="msg-red">` markup relied on JS
 listeners (`ShowBox()`, `setStyle('display','block')`) deleted
 with sourcebans.js at #1123 D1, so the banner painted as a
 silent invisible div forever. The legacy markup also escaped
 nothing — `echo '<div id="msg-red">' . $username . ' is taken</div>'`
 was a stored-XSS surface for any field that survives validation.
 Migrated wholesale in `goals#5`. Search anchor for any future
 sweep: `rg "msg-(red|green)" web/`.
- Legacy 1.4.11 JS handler names — `ButtonOver(…)`,
 `ProcessEditAdminPermissions(…)`, `ProcessEditGroup(…)`,
 `ProcessEditMod(…)`, `ProcessEditServer(…)`, `errorScript(…)`
 — referenced from inline `onmouseover="…"` / `onclick="…"`
 attributes on edit-page form inputs → the JS that defined these
 handlers (`web/scripts/sourcebans.js`) was deleted at #1123 D1.
 Every `onclick="ProcessEditAdminPermissions();"` / `onmouseover="ButtonOver('p1')"`
 attribute that survived the deletion was a silent no-op (the
 page rendered, the button looked clickable, the click did
 nothing — not even a console error). The legacy handler names
 are documented here defensively because `rg` searches against
 third-party theme forks may still surface them; the
 `goals#5` sweep removed every reference under `web/themes/default/`,
 but a fork that copy-pasted the templates pre-#1123 D1 will
 still carry them. Wire to `sb.api.call(Actions.PascalName, …)`
 via `data-action` + a page-tail vanilla-JS dispatcher per the
 canonical confirm-modal shape under "Add a confirm + reason
 modal" in "Where to find what".
- MooTools `$('id').value` / `$('id').checked` / `$('id').setStyle(…)`
 idioms in inline page-tail scripts → MooTools is gone (deleted
 with sourcebans.js at #1123 D1). Use vanilla DOM:
 `document.getElementById('id').value` /
 `document.getElementById('id').checked` /
 `el.style.display = 'block'`. The `$()` shim happened to no-op
 silently because nothing defined it post-#1123, so old `$('id').value`
 reads returned `undefined.value` → `TypeError`. Page-tail
 scripts inside templates use plain DOM access plus the
 `window.SBPP.showToast` / `setBusy` chrome helpers. The
 `goals#5` admin.edit.* sweep removed every surviving call site;
 don't reintroduce. (The `sb.$id` / `sb.$idRequired` helpers in
 `web/scripts/sb.js` are the canonical shape for code that ships
 with the panel; inline page-tail scripts can use either shape.)
- The DELETE-then-INSERT loop on
 `:prefix_admins_servers_groups` (and the parallel one on
 `:prefix_servers_groups`) without a transaction wrapper → wrap
 in `Sbpp\Db\Database::beginTransaction()` /
 `endTransaction()` / `cancelTransaction()`. The legacy shape
 (delete every existing row, then insert each new row in a
 separate `execute()`) was non-atomic — a connection drop or
 PHP fatal between the DELETE and the last INSERT would leave
 the admin partially de-assigned, the server partially de-grouped,
 etc. The schema doesn't carry a UNIQUE on the (admin_id,
 server_group_id) / (server_id, group_id) pairs, so collapsing
 to `INSERT … ON DUPLICATE KEY UPDATE` would need a paired
 schema migration (out of `goals#5`'s scope; tracked as a
 follow-up). Until then, transactions are the contract — see
 `web/pages/admin.edit.adminservers.php` and
 `web/pages/admin.edit.server.php` for the canonical shape.
- Hand-rolled per-page upload handling — `move_uploaded_file($_FILES['x']['tmp_name'], $dst)`
 with manual extension checks, manual `Log::add()` calls, and
 manual `<script>window.opener.<callback>(...)</script>` blob
 emission → use `Sbpp\Upload\UploadHandler::handle()`. The class
 wraps every step (CSRF, permission gate, extension allowlist,
 filename sanitisation via `sanitiseName()`, the `move_uploaded_file()`
 call, the audit-log entry, the success / error popup chrome).
 The pre-`goals#5` shape duplicated all of this across the three
 popup upload pages (`admin.uploaddemo.php`, `admin.uploadicon.php`,
 `admin.uploadmapimg.php`) and trusted `$_FILES[…]['name']`
 verbatim — so a `name=../../etc/passwd` upload could escape
 the destination directory on the icon and mapimage paths. The
 sanitiser basenames + strips backslashes + trims leading dots
 to defend the path. New popup-upload surfaces wire through
 `UploadHandler::handle()`; never `move_uploaded_file()`
 directly.
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
  `window.SBPP.showToast` from the theme JS. The trailing per-row
  `<script>LoadServerHost('SID', …)</script>` echo on the Add Admin
  form (`admin.admins.php`) and the per-group
  `<script>LoadServerHostPlayersList('<sids>', …)</script>` echo on
  the Server Groups list (`admin.groups.php`) survived the v2.0
  cutover and raised one `ReferenceError` per server / per server
  group on every page load with no visible effect (the static `<span
  id="saSID">IP:port</span>` fallback / the literal "Servers populate
  via the legacy LoadServerHostPlayersList hook." placeholder masked
  the symptom).   Both went at #1404 along with the orphan
  `AdminAdminsAddView::server_script` ctor param and the
  `<div id="servers_{gid}">` hydration slot; `DeadJsCallSitesTest` is
  the per-file forbidden-substring gate so a fork pasting back the
  v1.x shape gets caught at PR time. Hydration follow-up for the
  Add Admin per-server access list LANDED at #1405 — it wires the
  per-server checkbox grid onto the shared
  `web/scripts/server-tile-hydrate.js` helper (the same one driving
  the dashboard Servers widget); no new View property, no new JSON
  action, no new cache layer. Sister surface still tracked: #1406
  (Server Groups per-group server cards).
- Raw `<script>ShowBox(...)</script>` (or `<script>showBox(...)</script>` /
  `<script type="text/javascript">ShowBox(...)`) blobs echoed from a
  PHP page handler → use `\Sbpp\View\Toast::emit($kind, $title,
  $body, ?$redirect)`. `ShowBox` was deleted at #1123 D1 (v2.0.0)
  — every legacy caller silently threw `ReferenceError: ShowBox is
  not defined` in the modern chrome. Worse, several callers ran
  upstream of `PageDie()` (which renders the chrome footer +
  `exit`s), so the template body was suppressed and the user saw a
  literally blank white page on top of the dropped toast. The
  marquee user-reported regression: an admin requested a password
  reset, the success path silently emailed the new password while
  showing nothing to the user, and the user clicked Reset Password
  three times "to make it work" — burning three validation tokens
  while the actual email already landed in their inbox the first
  time (issue #1403, audit follow-up to #1176). 35 sites swept at
  #1403 across `page.lostpassword.php` / `page.protest.php` /
  `page.banlist.php` / `page.commslist.php` / `admin.edit.comms.php`
  / `page.submit.php` (the lift source). `page.home.php`'s
  `$info['popup']` field went at sister #1404 (dead data; pure
  cleanup, no toast-emit conversion needed). The new helper stashes
  the payload in a `<script type="application/json"
  class="sbpp-pending-toast">` blob the chrome consumes on
  `DOMContentLoaded` — see the "Server-side toast emission" block
  in Conventions for the full contract. Regression guards:
  `web/tests/integration/ToastEmitRegressionTest.php` (static
  grep against `web/pages/*.php` — three variant shapes including
  the `<script type="text/javascript">` form, wire-format / JSON-
  escape / UTF-8-substitute / FIRST-wins-redirect contracts, and
  the "every audited page still calls `Toast::emit`" call-site
  contract) plus the five marquee E2E specs under
  `web/tests/e2e/specs/flows/*-toast.spec.ts`. The
  `lostpassword-toast.spec.ts` happy-path test seeds the dev
  stack's mailpit and asserts the password-reset email lands at
  the right address as well as the toast painting.
- Removing `JSON_INVALID_UTF8_SUBSTITUTE` from `Sbpp\View\Toast`'s
  `json_encode` flags ("we never see malformed UTF-8 in practice")
  → player names on `:prefix_bans.name` / `:prefix_comms.name`
  CAN carry malformed UTF-8 from the pre-#1108 / #765 Latin-1-on-utf8
  truncation shape. The GET-fallback unban / delete paths in
  `page.banlist.php` / `page.commslist.php` interpolate
  `$row['name']` directly into the toast body. Without the
  substitute flag `JSON_THROW_ON_ERROR` raises `JsonException`
  and the user gets a 500 instead of the unban confirmation —
  worse, the unban / delete SQL has ALREADY committed by the time
  `Toast::emit` fires, so the audit log shows the action
  succeeded while the operator sees a server error. Substituting
  to U+FFFD is the correct shape; well-formed payloads are
  unaffected. Regression guard:
  `ToastEmitRegressionTest::testToastEmitSubstitutesMalformedUtf8InsteadOfThrowing`.
- Adding `data-testid="pending-toast"` (or any other `data-testid`)
  to the `<script type="application/json" class="sbpp-pending-toast">`
  wire-format block → `Sbpp\View\Toast::emit` is allowed to be
  called multiple times per response (validation-error spray,
  success + diagnostic warning, …). A wire-format testid would
  collide across emit calls and Playwright's `getByTestId(...)`
  strict mode rejects multi-match. E2E specs anchor on the
  *painted* `[data-testid="toast"]` element (chrome-rendered by
  `showToast` after picking up the blob); for a kind-specific
  anchor use `[data-testid="toast"][data-kind="error"]` (the
  CSS class + dataset attribute the chrome stamps regardless
  of ARIA role) rather than `[role="status"]` (which won't
  match error toasts post-#1409 — they carry `role="alert"`).
  Wire-layer specs probe the response body directly for
  `class="sbpp-pending-toast"`. The class is the consumer
  selector; no additional hooks needed. Regression guard:
  `ToastEmitRegressionTest::testToastEmitWireFormatStaysStable`
  explicitly asserts the wire-format block carries NO
  `data-testid` attribute.
- Passing `duration_ms = 0` to `\Sbpp\View\Toast::emit` for routine
  info / success toasts → the persistent flag is for severe-error
  "this destructive operation FAILED and the operator MUST
  acknowledge before moving on" branches (the 5 NOT-* sites in
  `page.banlist.php` / `page.commslist.php` are the canonical
  reference shape). Using it for casual confirmations
  ("Password reset email sent", "Ban added", "Settings saved",
  etc.) creates UI clutter — the user has to click the X button
  on every toast — and trains operators to dismiss reflexively
  without reading, which DEFEATS the purpose of the persistent
  flag the next time it actually matters (operator hits
  "Ban NOT Deleted", reflex-dismisses without reading, and the
  audit-log search five minutes later for "what just happened?"
  comes up empty because the read-the-message-first contract
  was burned through routine overuse). The contract:
  - **Default** (no 5th arg): chrome's `SHOWTOAST_DEFAULT_DURATION`
    (~4000ms). The right choice for every routine path.
  - `0`: severe-error branches ONLY. The five NOT-* sites today
    are the entire set; adding a sixth requires the same
    "destructive operation failed mid-flight, audit log already
    committed, operator needs the *why* before the toast
    disappears" justification.
  - `> 0`: explicit override (no in-tree caller today).
  Negative values throw `\InvalidArgumentException` — the
  "Fail closed" pattern AGENTS.md applies elsewhere. Don't
  silently coerce; surface the programmer error.
  Pinned by `ToastEmitRegressionTest::testToastEmitOmitsDurationMsByDefault`
  / `testToastEmitIncludesDurationMsWhenSet`
  / `testToastEmitRejectsNegativeDuration`
  / `testNotStarBranchesPassPersistentDurationMs` plus the
  `toast-persistent-duration.spec.ts` E2E that asserts the
  NOT-* toast survives past `SHOWTOAST_DEFAULT_DURATION`.
- Mirroring the literal `4000` into a new caller or test
  ("the default is 4 seconds, let me hard-code it") → the
  default lives in `theme.js`'s `SHOWTOAST_DEFAULT_DURATION`
  constant — single source. Reference it as `null` on the
  PHP side (`Toast::emit(...)` with no 5th arg) and trust the
  chrome to fill in the value. The 4000ms number is one
  reduce-this-to-3000ms tweak away from desynchronising every
  hard-coded mirror; the chrome's constant + omission-on-wire
  is what keeps the contract single-source. The matching
  consumer-side gate
  (`if (typeof data.duration_ms === 'number') opts.durationMs = data.duration_ms;`)
  is what makes "field absent → use default" work — a future
  refactor that always serialises `duration_ms` (even when
  null) would silently disable the default fall-through path
  on every install whose chrome is still on the typeof-number
  gate.
- Reading `data.duration_ms` from a `<script class="sbpp-pending-toast">`
  block without the `typeof data.duration_ms === 'number'`
  gate (e.g. truthy check `if (data.duration_ms)` or
  unconditional assignment `opts.durationMs = data.duration_ms`)
  → a hostile / malformed payload sending a string or boolean
  must NOT pass through unchecked. `showToast` would then
  schedule `setTimeout(..., "0")` (Number-coerced to 0,
  auto-dismisses immediately) or `setTimeout(..., true)`
  (coerced to 1ms). The typeof gate keeps the chrome's
  behaviour deterministic regardless of upstream noise. The
  encoder side already enforces an `int|null` PHP type, but
  the chrome can't trust the server in this defence-in-depth
  sense; the gate is the contract.
- `setTimeout(() => el.remove(), 0)` instead of skipping the
  timer entirely when `durationMs === 0` → `setTimeout(fn, 0)`
  schedules the callback for the NEXT tick of the event loop,
  not "never". The persistent toast would auto-dismiss after
  ~4ms (the browser-clamped minimum), which is the OPPOSITE
  of what `0` means in this contract. The chrome-side guard
  is `if (durationMs > 0) setTimeout(...)` — `=== undefined`
  arms have already been resolved to `SHOWTOAST_DEFAULT_DURATION`
  by the time the guard runs, so the only path that hits
  `setTimeout` is the explicit positive case.
- Pairing `duration_ms: 0` with a non-null `$redirect` on a
  `\Sbpp\View\Toast::emit(...)` call → persistent + redirect
  are mutually exclusive (#1409). The chrome's
  `flushPendingToasts` honours the redirect ~1500ms after
  paint; that auto-navigation tears down the persistent toast
  before the operator can read or dismiss it, defeating the
  whole "needs acknowledgement" semantic the persistent flag
  exists for. The call-site contract is to pass `null` for
  `$redirect` when emitting `duration_ms: 0`; the page
  handler's normal "render the body after the foreach loop"
  flow keeps the operator on a valid surface (no `PageDie()`
  before the toast persists). The chrome ALSO carries a
  defence-in-depth inhibit (`flushPendingToasts` skips the
  redirect setTimeout entirely if any drained block had
  `duration_ms: 0`) so a future caller forgetting the rule
  doesn't silently regress, but the call-site half is the
  primary contract — relying on the chrome inhibit alone
  leaves the wire payload carrying a redirect URL the chrome
  silently throws away, which a code reader would flag as a
  bug (and rightly so). Pinned by
  `ToastEmitRegressionTest::testNotStarBranchesPassPersistentDurationMs`
  (strict regex requires the 4th arg to be the literal
  `null`) and exercised end-to-end by
  `web/tests/e2e/specs/flows/toast-persistent-duration.spec.ts`
  (the toast outlasts SHOWTOAST_DEFAULT_DURATION).
- Uniform `role="status"` on every painted toast regardless of
  kind (the pre-#1409-review shape) → `role="status"` is the
  POLITE live-region role (`aria-live="polite"`) — screen
  readers wait for the user to finish what they're listening
  to before announcing. For routine `info` / `success` / `warn`
  toasts that's the right shape (interrupting a screen-reader
  user for "Settings saved" is rude and disruptive). For ERROR
  toasts — especially the persistent `duration_ms: 0` shape —
  it's wrong: the operator MUST acknowledge before moving on,
  and a polite announcement that's quietly queued behind the
  current task can be missed entirely. Screen-reader users are
  the population least likely to notice a visual change without
  an auditory cue; "the destructive operation failed" hitting
  the speech queue silently doesn't help them. The contract
  (#1409 review Suggested #3) is `role="alert"` for
  `kind === 'error'` (assertive, interrupts), `role="status"`
  for every other kind. Apple HIG / Material Design /
  Bootstrap all converge on the same split; the W3C ARIA
  spec is explicit that `role="alert"` is the answer for
  "interrupt the user with critical info". E2E specs that
  anchor on `[role="status"]` to find error toasts would miss
  them under the kind-aware shape; reach for
  `[data-testid="toast"][data-kind="error"]` as the
  kind-specific anchor instead. The role attribute writes are
  centralised in `showToast` in `web/themes/default/js/theme.js`
  — one branch, single source.
- Hand-rolling a `role="status"` / `role="alert"` attribute on
  a custom toast / banner / live region surface without
  matching the chrome's kind-aware contract → the broader
  rule: assertive (`alert`) is for "user MUST acknowledge
  before moving on" (destructive-operation-failed,
  unrecoverable error, security-relevant event); polite
  (`status`) is for everything else (background status
  updates, routine confirmations, ambient information).
  Choosing the wrong role isn't a visual bug — it's a
  screen-reader user accessibility bug, which is the worst
  kind of regression because the people affected are the
  least likely to be running the test suite. When in doubt,
  follow the chrome's `showToast` shape (error → alert; rest
  → status) for consistency across the panel surfaces a
  screen-reader user navigates.
- `onclick="if (typeof <Helper> === 'function') <Helper>(...)"`
  legacy-helper presence guards in templates (the v1.x sourcebans.js
  defensiveness pattern that survived the #1123 D1 deletion of the
  bulk JS file) AND the unguarded sister shape
  `onclick="<Helper>(...)"` that drops the `typeof` test entirely →
  wire to the JSON API via `data-action` + a page-tail vanilla-JS
  dispatcher per the canonical confirm-modal shape under "Add a
  confirm + reason modal" in "Where to find what". Pre-#1352 the
  trash-can button on `?p=admin&c=admins` carried
  `onclick="if (typeof RemoveAdmin === 'function') RemoveAdmin(...)"`;
  the `typeof X === 'function'` test silently resolved to `false`
  (sourcebans.js was deleted with v2.0.0 — there's no `RemoveAdmin`
  anywhere) so every click was a no-op with no console error / no
  toast / no API call. **Pre-#1397** the trash-can button on
  `?p=admin&c=mods` carried the LOUD sister-shape
  `onclick="RemoveMod(this.dataset.modName, this.dataset.modId);"` —
  no `typeof` guard at all — so every click threw
  `ReferenceError: RemoveMod is not defined` (visible in the browser
  console, but no toast / no API call / no row removal; from the
  operator's POV "Delete just doesn't work"). The guarded shape's
  bug-class is invisible by design (the guard exists precisely to
  swallow the missing-helper case); the unguarded shape is loud but
  equally non-functional — both need the structural fix. There's no
  runtime gate beyond the in-process render tests
  (`AdminsDeleteDialogTest`, `ModsDeleteDialogTest`); every call
  site needs the structural fix. When migrating: drop the inline
  `onclick`, mark the trigger with
  `data-action="<surface>-<verb>"` + `data-<id>` + `data-name` +
  `data-fallback-href`, ship a `<dialog>` for the confirm + optional
  reason field, and a page-tail script that dispatches to
  `sb.api.call(Actions.PascalName, …)`. The `Actions.PascalName`
  shape (NOT a string literal) catches typos at api-contract
  regen time. **#1402** swept the rest of the post-#1397 cluster
  (`ProcessMod`, `ProcessAddAdmin`, `LoadGeneratePassword`,
  `update_server` / `update_web`, `LoadGroupBan` /
  `ProcessGroupBan` / `CheckGroupBan` / `TickSelectAll`,
  `RemoveComment`, `window.opener.icon(...)`,
  `window.addEvent('domready', …)`). Constructive form submits
  (`ProcessMod` / `ProcessAddAdmin`) intercept the form's `submit`
  event, validate, then `sb.api.call(Actions.ModsAdd / .AdminsAdd)`
  per the `page_admin_groups_add.tpl` reference. Multi-step
  chains (`LoadGroupBan` → `Actions.BansGroupBan` →
  `Actions.BansBanMemberOfGroup`) live in a page-tail dispatcher
  next to the surface in `web/pages/admin.bans.php`. The trash-
  can-on-a-comment trigger (banlist / commslist / admin.bans
  protests + submissions) is the rare case where the same
  affordance ships on three sibling pages — that one rides a
  **shared `web/scripts/comment-actions.js` dispatcher** loaded
  from `core/footer.tpl` so the four call sites (page.banlist.php,
  page.commslist.php, admin.bans.php protests, admin.bans.php
  submissions) share one source of truth instead of four inlined
  page-tail blocks. The icon-upload callback (`window.opener.icon(...)`
  emitted by `UploadHandler::handle()`) is wired via a per-page
  `window.icon = function (filename) { … }` block in the parent
  template (`page_admin_mods_add.tpl` + `page_admin_edit_mod.tpl`);
  this is the same shape the demo upload uses (`window.demo` on
  `admin.bans.php` / `admin.edit.ban.php`). Search anchors for the
  cleanup sweep:
  `rg "typeof \w+ === ['\"]function['\"]" web/themes/` for the
  guarded shape, and `rg "onclick=\"[A-Z]\w+\(" web/themes/` for
  the unguarded sister shape.
- `window.addEvent('domready', …)` MooTools DOMready idiom in
  inline page-tail blocks (or any `Element.prototype` /
  `$$('css selector')` / `el.addEvent(...)` MooTools method call)
  → use vanilla `document.addEventListener('DOMContentLoaded',
  function () { … })` or — better, since panel templates render
  the page-tail script AFTER the elements it targets — drop the
  wrapper entirely and run synchronously. MooTools was removed
  with `sourcebans.js` at #1123 D1; every `window.addEvent`
  callsite that survived was a silent no-op (`window.addEvent`
  is undefined, the event listener never registered, the body
  never ran). Pre-#1402 `web/pages/admin.edit.comms.php` shipped
  a `$errorScript` blob wrapping its DOM operations in
  `window.addEvent('domready', ...)`; the validation-error toast
  never painted because the wrapper itself threw. Vanilla DOM
  access (`document.getElementById('id').value` /
  `el.style.display = 'block'`) replaces the `$('id').value` /
  `$('id').setStyle(…)` MooTools idioms in the same sweep — both
  go in one PR per file because the body of the
  `window.addEvent('domready', …)` callback almost always uses
  MooTools `$()` too.
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
  The Ban / Block menu items route through the panel-chromed
  smart-default URLs
  (`?p=admin&c=bans&section=add-ban&steam=…&type=0` /
  `?p=admin&c=comms&steam=…&type=0`) so the operator lands on the
  real form with the SteamID pre-populated; Kick is the one
  remaining iframe-routed item (`pages/admin.kickit.php`) because
  it's a one-shot RCON command with no persistent panel surface
  to anchor on (#1395 unified Block onto the panel route — pre-fix
  it went to the same iframe surface as Kick which was actually the
  post-`Actions.CommsAdd` rcon fan-out target, NOT a stand-alone
  operator page; hitting it directly rendered chromeless and POSTed
  to a 404). The anti-pattern that stays anti is the MooTools-era
  plumbing — `sb.contextMenu`, `AddContextMenu`, the global helpers,
  the separate `contextMenoo.js` file. Reach for the documented
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
- Calling `SteamID::toSteam2($raw)` (or any other `SteamID::*`
  conversion that calls `resolveInputID` internally) on operator-
  controlled input WITHOUT a strict-shape `preg_match` gate first
  → `resolveInputID` throws a generic `\Exception` for unrecognised
  shapes, and the dispatcher's `Throwable` fallback in `Api::handle`
  wraps it as a generic `server_error` envelope (HTTP 500). The
  client-side IIFE in the form template surfaces "Block NOT Added —
  Internal server error" via `r.error.message` instead of the
  pointed "Please enter a valid Steam ID or Community ID" that a
  structured `ApiError('validation', …, 'steam')` would emit. Worse,
  the legacy comms-add path pre-#1420 emitted feedback through
  `sb.message.show` against the v1.x `#dialog-placement` chrome
  shell that the v2.0 theme doesn't render — so the 500 was
  silent (reporter's symptom on #1420: "no notification on
  invalid steamID"). The fix is the explicit `preg_match` gate
  documented under "SteamID inputs" in Conventions; landing it at
  the same time as the form template's native `pattern` attribute
  is what closes the loop end-to-end (the `pattern` is the UX-first
  gate; the `preg_match` gate is the security-first gate; both
  ship together). See `api_comms_add` / `api_bans_add` /
  `api_admins_add` for the canonical reference shape and
  regression coverage in `web/tests/api/CommsTest.php::testAddRejectsInvalidSteamIdShape`,
  `web/tests/api/BansTest.php::testAddRejectsInvalidSteamIdShapeForType0`,
  and `web/tests/api/AdminsTest.php::testAddRejectsInvalidSteamIdShape`.
- Loosening `SteamID::ID_PATTERNS` (the shared `[regex, format]`
  table consumed by both `isValidID()` and `resolveInputID()`) —
  e.g. dropping the `^…$` anchors, dropping the `D` modifier,
  using `[0|1]` instead of `[01]` (`|` inside `[…]` is a literal
  pipe, not alternation), or using `\d*` instead of `\d+`. The
  pre-#1420 shape carried every one of those bugs and the three
  concrete bypasses are documented under "SteamID inputs" in
  Conventions (`'STEAM_0:0:'` empty Z, `'asdfSTEAM_0:0:123'`
  substring-bypass, `'asdf 76561197960265728 garbage'` embedded
  Steam64 → `'STEAM_0:0:-38280598980132864'` on `toSteam2()`). The
  shared-table shape is the single source of truth — both
  surfaces consume it so they cannot drift. A future refactor
  that "simplifies" the table back into per-method `switch (true)`
  blocks re-opens the asymmetry bug-class. Regression guard:
  `web/tests/integration/SteamIDValidationTest.php::testIdPatternsConstantIsTheSourceOfTruth`
  introspects the constant and asserts every regex stays
  `^…$/…D…` shaped.
- Removing the per-handler strict `preg_match` from
  `api_comms_add` / `api_bans_add` / `api_admins_add` "now that
  `SteamID::isValidID()` is strict too" → both layers ship and
  agree by design (the "defence-in-depth" contract under "SteamID
  inputs"). The library is one refactor away from someone
  loosening `ID_PATTERNS` back to a substring matcher AND the
  per-handler gate exists so that refactor doesn't silently
  re-open the bypass class. Regression coverage for the handler
  half: `web/tests/api/CommsTest.php::testAddRejectsInvalidSteamIdShape`
  (+ sibling tests in `BansTest.php` / `AdminsTest.php`).
- Calling `SteamID::toSteam2($raw)` (or any other `SteamID::*`
  conversion that funnels through `resolveInputID`) on a page
  handler's raw POST input BEFORE running `SteamID::isValidID($raw)`
  → the converter raises `Exception('Invalid SteamID input!')` on
  any input that fails the shape check post-#1420 (the library
  tightening from follow-up #1 made the throw stricter). The
  exception escapes the page handler unhandled, the chrome's
  `PageDie()` never fires, and the user gets a 500 page render
  instead of the inline per-field "Please enter a valid Steam ID
  or Community ID" message on the form. The fix is the
  validate-then-convert ladder documented under "SteamID inputs"
  in Conventions: trim → empty-check (separate message) →
  `isValidID()` shape check (separate message) → ONLY THEN
  `toSteam2()`. The pre-#1420 shape silently "worked" because the
  loose library accepted everything; with the tighter library the
  same call shape is a 500-page-render-on-typo waiting to happen.
  Affected surfaces swept at #1423 follow-up #2:
  `web/pages/admin.edit.{ban,comms,admindetails}.php` (the form
  re-render path), `web/pages/admin.bans.php`'s `importBans`
  branch (skip-and-count malformed lines instead of aborting
  mid-file). `web/pages/page.submit.php` doesn't call `toSteam2()`
  at all — the public form stores raw input verbatim, and the
  library tightening + template-side `pattern="…"` close the
  bypass without a handler edit. Regression guard:
  `web/tests/integration/SteamIDValidationOrderTest.php` pins the
  validate-then-convert call order across every page handler.
- Wrapping `SteamID::toSteam2($raw)` in `try/catch (\Exception)`
  as a substitute for the upstream `SteamID::isValidID($raw)`
  gate → the catch papers over the bug class without fixing the
  underlying call-order bug. A future refactor of the library
  that swaps the exception shape (e.g. to a typed
  `\InvalidArgumentException`) silently breaks the catch and the
  exception escapes again — back to a 500 page render. The
  contract is "validate first, convert only on a pass" (see
  "SteamID inputs" → page-handler form-POST surfaces); the
  validate-then-convert order is what gives `toSteam2()` its
  cannot-throw guarantee in handler code, so wrapping it in
  `try/catch` is a code smell signalling the upstream gate is
  missing.
- Hand-rolling the strict SteamID-shape regex literal at the
  per-handler `preg_match` call site (the pre-#1423-follow-up-#4
  shape: `preg_match('/^(?:STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17})$/', $raw)`
  — note the missing `D` modifier) → use the single source of
  truth `SteamID::HANDLER_STRICT_REGEX`. The pre-#1423-follow-up-#4
  copies silently missed the `D` modifier; the input
  `STEAM_0:0:1\n` (or any newline-suffixed shape) matched the
  per-handler regex (`$` matches end-of-string OR a final `\n`
  without the modifier) but then FAILED the library's
  `SteamID::isValidID()` / `toSteam2()` (which DO carry the
  modifier post-#1423 follow-up #1), throwing
  `Exception('Invalid SteamID input!')` → `Api::handle`
  `Throwable` fallback → generic 500 envelope. The bug class
  #1420 was supposed to close re-opened by accident on the
  newline shape because the two gate layers drifted on the
  modifier set. The `HANDLER_STRICT_REGEX` constant guarantees
  byte-for-byte sync; a hand-rolled local copy at a future
  handler call site silently invites the same bug. Regression
  guard:
  `web/tests/integration/SteamIDValidationOrderTest.php::testJsonHandlersUseSingleSourceOfTruthRegex`
  (asserts every JSON handler invokes `preg_match(SteamID::HANDLER_STRICT_REGEX, …)`
  literally — not a copy, not a concatenation),
  `web/tests/integration/SteamIDValidationTest.php::testHandlerStrictRegexRejectsNewlineBypass`
  (asserts the newline shape is REJECTED at the gate),
  `web/tests/api/CommsTest.php::testAddRejectsInvalidSteamIdShape`
  + `BansTest.php` + `AdminsTest.php` (the
  `"STEAM_0:0:1\n"` / `"[U:1:1]\n"` / `"76561197960265728\n"`
  cases pin the wire-side behavior).
- Storing the operator-typed Steam ID in `:prefix_bans.authid`
  on an IP-type ban (the `82e8c3d2` "canonicalise valid IDs on
  IP-type bans to match pre-tighter behaviour" nit shape) → the
  `:authid` column is the *steam id* of the banned player. On
  an IP-type ban (`BanType::Ip = 1`) there is no steam id, by
  definition; the canonical "no steam id" value is the schema's
  `NOT NULL default ''` empty string. The `82e8c3d2` nit aimed
  to preserve "pre-tightening behavior" for the case where the
  operator typed a valid-looking SteamID into the form's
  Steam ID box and then flipped the type radio to "IP-type" —
  pre-tightening the unconditional `toSteam2($rawSteam)` would
  have stored the canonicalised value in `:authid` AND the
  unconditional run would have raised on `garbage` and 500'd
  the page. The nit canonicalised the valid case (good
  intention) but didn't suppress the storage path (the bug it
  carried), AND failed to defend the invalid-input branch on
  the IP-type arm (the 500 was still reachable via
  `?type=1&steam=garbage`). The right shape is: hard-code
  `$_POST['steam'] = ''` for `$banType === BanType::Ip` and
  let the operator's form input remain visible only as
  client-side echo (template `placeholder`, NOT a stale value).
  Matches the `api_bans_add` write-side fix in the same PR
  (`$steam = $banType === BanType::Ip ? '' : ...`). The
  asymmetry pre-#1423-follow-up-#4 (page handler stored
  canonicalised, JSON handler stored empty) was its own bug
  class — third-party callers POSTing to the iframe-routed
  page handler vs. the JSON dispatcher produced inconsistent
  DB state for the same logical input. Regression guard:
  `web/tests/api/BansTest.php::testAddIpTypeAlwaysWritesEmptyAuthid`
  (valid Steam input + garbage + newline-bypass all write
  empty `authid` on `type=1`).
- Calling `SteamID::compare($a, $b)` (or any other
  `SteamID::*` method that funnels through `toSteam64()` /
  `resolveInputID()`) with operator-controlled input that
  hasn't been gated through `SteamID::isValidID()` first → same
  bug class as the JSON handler convert-before-validate trap,
  different blast radius. `compare()` is used by
  `api_kickit_kick_player` and `api_blockit_block_player` to
  per-player-match the A2S `status` response against the
  operator's target; on any input that fails the library shape
  gate the `toSteam64()` call throws and the iframe's loop
  renderer gets a generic 500 envelope instead of the
  structured `not_found` envelope that says "no match found
  for that target". The iframe can't tell the two apart, and a
  hostile caller posting `?check=garbage&type=0` reliably 500s
  the panel. The fix is a `SteamID::isValidID($check)` gate
  (Steam-type) or `filter_var($check, FILTER_VALIDATE_IP)`
  gate (IP-type) BEFORE the `compare()` call site, returning
  the structured `not_found` envelope on the fail branch.
  Pinned by
  `web/tests/api/KickitTest.php::testKickPlayerReturnsNotFoundForMalformedSteamId`
  + `testKickPlayerReturnsNotFoundForMalformedIp` and
  `web/tests/api/BlockitTest.php::testBlockPlayerReturnsNotFoundForMalformedSteamId`.
- Tightening `SteamAuthHandler::validate()`'s OpenID-claimed-ID
  regex to anything OTHER than `7\d{16}+` with the `D`
  modifier (e.g. the pre-#1423-follow-up-#4 shape
  `7[0-9]{15,25}+` without `D`) → Steam in practice always
  returns a 17-digit Steam64 in the `openid_claimed_id` URL,
  and the panel's downstream `SteamID::toSteam2()` carries
  the library-side `^\d{17}$D` gate post-#1423 follow-up #1
  (the symmetry contract). If the regex here loosens (accepts
  16 digits or 18-25 digits, or drops the `D` modifier and
  accepts a trailing `\n`), the input slips past `validate()`
  but then fails the library's gate in `check()`'s
  `toSteam2()` call — the exception escapes the constructor
  unhandled and the operator lands on a 500 mid-Steam-login
  round-trip (silent failure mode — there's no `try/catch`
  here and the chrome's `PageDie()` doesn't run on a callback
  redirect). The 17-digit shape is the contract Steam is on
  record committing to (their OpenID 2.0 endpoint hasn't
  emitted any other shape since 2010); a future Steam-side
  change that emits a different shape surfaces here as a
  clean false return (operator sees the
  `m=steam_failed` redirect) instead of as a 500. The defense-
  in-depth `SteamID::isValidID()` gate in `check()` is the
  second line — both halves of the contract ship together.
  Pinned by
  `web/tests/integration/SteamIDValidationTest.php::testSteamAuthHandlerOpenIdRegexAcceptsOnly17DigitsStartingWith7`.
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
- Adding an in-panel toggle to disable the project announcements
  feed (`?p=admin&c=settings` checkbox, an `admin_announcements`
  permission flag, etc.) → the surface is intentionally narrow
  enough that the only sensible "off" position is the air-gap
  escape hatch (`define('SB_ANNOUNCEMENTS_URL', '')` in
  `config.php`, documented in
  `docs/src/content/docs/configuring/announcements.mdx`). An
  in-panel toggle would suggest the feed is per-instance
  configurable in a way that pairs with admin-authored content; it
  isn't. The maintainers ship the JSON, every operator audits it
  via the public diff history of `docs/public/announcements.json`,
  and the only legitimate per-install knob is "off entirely vs. on".
  See "Project announcements feed" under Conventions for the full
  threat model.
- Reaching for `league/commonmark` (or any other Markdown library)
  directly to render announcement bodies → `body_md` MUST go
  through `Sbpp\Markup\IntroRenderer::renderIntroText()`. The
  renderer wraps CommonMark with `html_input: 'escape'` +
  `allow_unsafe_links: false`, so inline HTML lands as visible
  escaped text and `javascript:` / `data:` / `vbscript:` URLs are
  stripped. A bare `CommonMarkConverter` call would re-open the
  #1113-class stored-XSS surface — admin-authored display text and
  upstream-authored display text both ride the same renderer for
  exactly this reason. The `Sbpp\Announce\AnnouncementFetcher::buildAnnouncement`
  call site is the load-bearing one; never sidestep it.
- Editing announcements anywhere other than
  `docs/public/announcements.json` (an admin endpoint, an
  in-panel composer, a separate `sbpp.github.io` repo PR, a
  hand-edited `SB_CACHE/announcements.json` on a deployed panel)
  → there is exactly one source of truth, and it's the file in this
  repo. The deploy chain
  (`.github/workflows/docs-deploy-trigger.yml`) lands the file at
  `https://sbpp.github.io/announcements.json` within minutes of
  merge; the panel's `Sbpp\Announce\AnnouncementFetcher::tickIfDue`
  pulls it once per install per day. Hand-editing the cache file on
  a deployed panel is also wrong: the next shutdown hook would
  overwrite it, and the operator's locally-pinned content would
  silently vanish. The single-file shape is the load-bearing
  audit property — every operator can read every announcement that
  has ever been pushed before it lands on their dashboard.
- Removing the eager `register_shutdown_function([\Sbpp\Announce\AnnouncementFetcher::class, 'tickIfDue'])`
  call at the tail of `init.php` "now that the page handler reads
  the cache directly" → the page handler's `latest()` only reads
  the cache; without the shutdown hook nothing ever populates it.
  The two halves are the same contract: one reads, the other
  writes. Sister anti-pattern to "Removing the eager
  `require_once` chain" — the shutdown hook is the live ledger of
  every background tick the panel runs, and a future reader needs
  to find both halves co-located.
- Discarding the `?array $announcement` property on
  `Sbpp\View\HomeDashboardView` → `SmartyTemplateRule` would
  flag the unused property at static-analysis time, but the
  procedural-handler side (where `Sbpp\View\Renderer::render(...)`
  drops the property onto the Smarty engine) doesn't have a
  static gate — the runtime test
  (`HomeDashboardAnnouncementTest::testHomeDashboardViewCarriesTheAnnouncementProperty`)
  pins the contract for both halves. The dashboard's announcement
  strip gates the entire `<aside>` block on `{if $announcement}`,
  so dropping the View property would silently disable the
  surface without obvious symptoms (the strip just stops
  appearing on every install, including ones with a populated
  cache).
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
| Add a new edit page in the admin.edit.* cluster (e.g. `admin.edit.<x>.php`) | `web/pages/admin.edit.<x>.php` (the page handler — thin "validate input, build View, render" shape) + `web/includes/View/AdminEdit<X>View.php` (typed View DTO) + `web/themes/default/page_admin_edit_<x>.tpl` (template). Shared helpers live in `web/pages/_admin_edit_helpers.php` (`sbpp_admin_edit_die_with_toast()` for permission / not-found guards, `sbpp_admin_edit_emit_tail_script()` for form-success / validation-error feedback that fires `window.SBPP.showToast()` and writes errors into `<id>.msg` divs, `sbpp_admin_edit_collect_rehash_sids()` for the post-save Rehash Admins step). Anti-patterns to avoid: inline `echo '<form>...'` blocks, `echo '<div id="msg-red">…'` banners, MooTools `$('id').value` reads, legacy JS handler names (`ButtonOver`, `ProcessEditAdminPermissions`, etc.) — all swept as part of `goals#5`. CSRF gate every POST via `\CSRF::rejectIfInvalid();` after the `isset($_POST['<sentinel>'])` arm. |
| Wire a `window.opener.<callback>(...)` slot on the parent template of a popup file-upload page (e.g. `window.icon`, `window.demo`, `window.mapimg`) | The parent template defines `window.<callback> = function (filename) { … }` inside an inline `<script>` block (use the `{literal}…{/literal}` Smarty guard so `{` / `}` inside the JS body don't trigger Smarty parsing). The function patches the parent form's hidden input (e.g. `document.getElementById('icon_hid').value = filename;`) and any visible affordances (toggle a thumbnail preview, swap a "Choose file" label for the filename). Reference: `web/themes/default/page_admin_mods_add.tpl` + `page_admin_edit_mod.tpl` for the `window.icon` wiring (#1402), and `web/themes/default/page_admin_bans_add.tpl` / `page_admin_edit_ban.tpl` for the `window.demo` wiring. Without this slot the popup emits `<script>window.opener.icon('foo.jpg')</script>` and the call throws `TypeError: window.opener.icon is not a function` — popup never closes, the parent form's hidden input never updates, the uploaded asset is orphaned on disk. The `UploadHandler` emits the call unconditionally; the parent's job is to be ready for it. |
| Add a popup file-upload page (demo / icon / mapimage / new asset type) | `Sbpp\Upload\UploadHandler::handle()` (`web/includes/Upload/UploadHandler.php`). The page handler at `web/pages/admin.upload<x>.php` is a thin wrapper passing the per-page knobs (`permission` mask, `field` `$_FILES` key, `allowed` extensions, `destDir`, `callback` JS function name on `window.opener`, `auditOk` / `auditFmt` / `errorMsg` / `title` / `formName` / `formats` strings, optional `renameToHash` for demo-style randomised filenames). The handler runs CSRF + permission check, sanitises `$_FILES[…]['name']` via `sanitiseName()` (basename + strip backslashes + trim leading dots — defends LFI on the icon / mapimage paths where the filename hits disk), `move_uploaded_file()`s to the destination, calls `Log::add(LogType::Message, …)`, and on success emits the `<script>window.opener.<callback>(...)</script>` blob the parent page picks up. The three reference call sites (`admin.uploaddemo.php`, `admin.uploadicon.php`, `admin.uploadmapimg.php`) are 30-line wrappers; new asset types should match that line budget. Anti-pattern: hand-rolling the move / log / popup-emission sequence per page (the pre-`goals#5` shape). |
| Edit a template                        | `web/themes/default/*.tpl`                               |
| Reuse the moderation-queue card layout (admin submissions / protests, mobile-stacked summary rows) | `web/themes/default/css/theme.css` (`.queue-row`, `.queue-row__body`, `.queue-row__date` — #1207 PUB-2). Apply by adding `class="queue-row …"` to the outer `<details>` and dropping the inline `flex` / `flex-shrink:0` styles from the summary children. |
| Add visible row actions to a table-rendered admin list (Edit / Unmute / Remove buttons + responsive mobile-card mirror) | `web/themes/default/page_comms.tpl` (#1207 ADM-5) is the canonical reference: `<button class="btn btn--secondary btn--sm">` / `<a class="btn btn--ghost btn--sm">` inside a `.row-actions` cell, plus `.ban-card__actions` row of identical-data-action buttons in the mobile card. Wire destructive / state-changing buttons via `data-action="…"` + `data-bid` + `data-fallback-href`; the inline page-tail JS calls `sb.api.call(Actions.PascalName)` and falls back to the GET URL if the JSON dispatcher is absent. The public banlist (`web/themes/default/page_bans.tpl`) follows the same shape — same chrome (Lucide icon + visible text label inside `.btn--ghost` / `.btn--secondary btn--sm`), same `.ban-card__actions` mobile row, same `data-action` / `data-fallback-href` wiring (`bans-unban` / `bans-delete`). The Remove affordance points at the legacy GET handler (`?p=banlist&a=delete&id=…&key=…` at the top of `page.banlist.php`) because no JSON `bans.delete` action exists yet — the inline JS `confirm()`-prompts then navigates, mirroring commslist's flow without adding a new handler / snapshot / permission-matrix entry. |
| Wire a comment-delete trash icon on any of the four comment-rendering surfaces (banlist, commslist, admin.bans protests, admin.bans submissions) | `web/scripts/comment-actions.js` (#1402). Single document-level dispatcher loaded from `core/footer.tpl` (`<script src="./scripts/comment-actions.js" defer>`) that picks up every `[data-action="comment-delete"]` click, `window.confirm`s the destructive intent, then `sb.api.call(Actions.BansRemoveComment, { cid, ctype, page })`. The four call sites emit the trigger with `data-cid="<int>"` + `data-ctype="<B|C|S|P>"` + `data-page="<int>"` (the page number for client-side row-hide / paginator-aware redirect; `data-page="-1"` is the sentinel for unpaginated moderation queues). The `ctype` letter matches `:prefix_comments.type` (B=ban, C=comm-block, S=submission, P=protest); `api_bans_remove_comment`'s `ctype` arm consumes all four. Don't duplicate the dispatcher inline per page — the single mount point is the contract, otherwise a future bug-fix has to land in four places. |
| Add a confirm + reason modal for an irreversible row-level action (unban, lift comm block, delete admin, delete mod, …) | `web/themes/default/page_bans.tpl` (`#bans-unban-dialog`, `Actions.BansUnban`) and `web/themes/default/page_comms.tpl` (`#comms-unblock-dialog`, `Actions.CommsUnblock`) are the canonical reference (#1301), with `web/themes/default/page_admin_admins_list.tpl` (`#admins-delete-dialog`, `Actions.AdminsRemove`, #1352) and `web/themes/default/page_admin_mods_list.tpl` (`#mod-delete-dialog`, `Actions.ModsRemove`, #1397) as the third and fourth references for the optional-reason variant. Shape: a `<dialog hidden>` with a `<form method="dialog">` carrying a `<textarea aria-required="true">` (or `aria-required="false"` for the optional-reason variant — see admins-delete / mod-delete) (NOT the native `required` — that lets the browser block the form submit before our handler runs, swallowing the inline-error UX), a Cancel button, and a Confirm submit button. The page-tail JS opens the dialog via `showModal()` on `[data-action]` clicks, validates the trimmed reason on submit (load-bearing gate is server-side), forwards `ureason` to the JSON action, and on success flips the row in place via the same `flipRowToUnbanned`/`flipRowToUnmuted` helper the legacy single-click flow used (or removes the row outright + decrements the count badge for the admins-delete / mod-delete variants where there's no "now-unbanned" state to render). The legacy GET fallback (`?p=banlist&a=unban&id=…&key=…&ureason=…` / `?p=commslist&a=ungag…&ureason=…`) is the no-JS / hand-edited-URL path; both halves now reject empty `ureason` server-side so the audit log carries the *why*. The admins-delete and mod-delete variants have no legacy GET handler — `RemoveAdmin()` / `RemoveMod()` always went through the JSON dispatcher pre-#1123 D1 — so their `data-fallback-href` lands the operator back at the list page as a graceful no-op when the JSON dispatcher is missing entirely (third-party theme stripping `api.js`); the audit-log "Reason: …" suffix is only emitted when `ureason` is non-empty (vs always-emitted on the bans / comms variants where reason is required). **Do not** put `onclick="event.stopPropagation()"` on the trigger button — `document.addEventListener('click')` is how the dialog opener picks the click up, and stopPropagation would silently swallow it (the action button isn't inside any `[data-drawer-href]` ancestor anyway, so the defensiveness was a copy-paste from the row-name anchor that doesn't apply here). The submit button MUST flip through `setBusy(submitBtn, true)` BEFORE `sb.api.call(...)` leaves the page and clear via `setBusy(submitBtn, false)` on every non-navigating response branch — see "Loading state on action buttons" in Conventions for the contract, the inline-script local wrapper shape, and the regression guard. |
| Emit a toast from a server-side branch (lostpassword reset success, banlist GET-fallback unban result, admin.edit.* not-found guard, …) | `\Sbpp\View\Toast::emit($kind, $title, $body, ?$redirect, ?$duration_ms)` (`web/includes/View/Toast.php`). Stashes the payload in a `<script type="application/json" class="sbpp-pending-toast">…</script>` block that `theme.js`'s `flushPendingToasts` picks up on `DOMContentLoaded`. Always FQN at call sites (no `use Sbpp\View\Toast;` shim). The chrome JS renders the body through `escapeHtml`; the PHP JSON encoder uses `JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT \| JSON_INVALID_UTF8_SUBSTITUTE \| JSON_THROW_ON_ERROR` so a `</script>` in body cannot break out AND malformed UTF-8 in player names (the #1108 / #765 Latin-1-on-utf8 shape) substitutes to U+FFFD instead of throwing. Multi-toast safe: emit calls stack cleanly; the first non-empty `$redirect` wins (chrome navigates ~1500ms after the toast paints so the user can read it). The optional 5th arg `$duration_ms` (#1409) overrides the chrome's `SHOWTOAST_DEFAULT_DURATION` (~4000ms) — `null` (default) keeps the chrome timing, `0` makes the toast persistent (no auto-dismiss; user must click the X button), `> 0` is an explicit ms override. The 5 NOT-* destructive-action-failed branches in `page.banlist.php` / `page.commslist.php` ("Player NOT Unbanned", "Ban NOT Deleted", "Player NOT UnGagged" × 2, "Ban NOT Deleted") pass `0` so severe-error confirmations don't auto-dismiss before the operator finishes reading. Negative `$duration_ms` throws `\InvalidArgumentException` (Fail closed; see "Duration semantics" in Conventions). Persistent + redirect are mutually exclusive (#1409): pass `null` for `$redirect` when emitting `duration_ms: 0` — the chrome's auto-redirect would otherwise navigate ~1500ms after paint, tearing down the persistent toast before the operator can acknowledge it. The chrome ALSO carries a whole-drain inhibit (`flushPendingToasts` skips the redirect setTimeout entirely when any block had `duration_ms: 0`) as defence-in-depth, but the call-site half (`$redirect=null`) is the primary contract pinned by `testNotStarBranchesPassPersistentDurationMs`'s strict regex (which disambiguates the two `Player NOT UnGagged` sites in `page.commslist.php` by body substring + asserts each site's call shape with `assertSame($expected_count, …)`). Painted ARIA role is kind-aware (#1409 review): `role="alert"` for `kind === 'error'` (assertive — screen readers interrupt to surface), `role="status"` for every other kind (polite). Persistent error toasts especially benefit from `alert` because the screen-reader user is the population least likely to notice a visual change without an auditory cue. Pair with `PageDie()` (or `exit`) whenever the handler's "render the page body" path is no longer meaningful — pre-#1403 the legacy `<script>ShowBox(...)</script>` shape carried its own `window.location` and beat the page render to the user; the lifted helper relies on the explicit redirect + the 1500ms settle. See "Server-side toast emission" in Conventions for the full contract (the "ARIA role contract" subsection covers the kind-aware role rationale). Pinned by `web/tests/integration/ToastEmitRegressionTest.php` (static grep + wire-format + JSON-escape + UTF-8-substitute + FIRST-wins-redirect + `duration_ms` omitted/set/negative/escape contracts + the 5 NOT-* call sites pass `duration_ms: 0` AND `$redirect=null` + call-site contract) and the six marquee E2E specs (`lostpassword-toast.spec.ts`, `protest-toast.spec.ts`, `banlist-getfallback-toast.spec.ts`, `commslist-getfallback-toast.spec.ts`, `admin-edit-comms-toast.spec.ts`, `toast-persistent-duration.spec.ts`). The lostpassword spec seeds the dev stack's mailpit and asserts the password-reset email lands at the right address — the marquee user-reported regression from the audit. The persistent-duration spec (#1409) drives a NOT-* branch payload and asserts the toast is STILL visible past the default ~4000ms window; its sister regression-guard test drives `window.SBPP.showToast(...)` directly (no page-flow / redirect dependency, post-review reshape) and asserts a routine toast auto-dismisses inside the ~4000-5000ms window so a regression bumping or zeroing the default duration fails loudly. |
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
| Hydrate server-tile cards with live A2S data (status pill / map / players / hostname / refresh) on the public servers list, the admin Server Management list, the dashboard's Servers widget, the Add Admin per-server access checkbox grid, AND the admin Server Groups list's per-group card stack | `web/scripts/server-tile-hydrate.js` (`window.SBPP.hydrateServerTiles`) — auto-runs on first paint for every container marked `data-server-hydrate="auto"`. Consumed by `web/themes/default/page_servers.tpl` (public, full chrome), `web/themes/default/page_admin_servers_list.tpl` (admin, #1313 — same chrome minus the map thumbnail + players panel), `web/themes/default/page_dashboard.tpl` (dashboard Servers widget, #1375 — hostname slot only, every other testid hook deliberately omitted), `web/themes/default/page_admin_admins_add.tpl` (Add Admin per-server access checkbox grid, #1405 — same minimal-testid shape as the dashboard widget), and `web/themes/default/page_admin_groups_list.tpl` (admin Server Groups card stack, #1406 — one minimal-integration `[data-testid="server-tile"]` per server bound to the group, hostname slot only, same minimal-testid shape the dashboard widget rides). Selector contract per tile: `[data-testid="server-tile"]` outer card + `data-id="<sid>"` + `[data-testid="server-{status,map,players,host}"]` cells + optional `[data-testid="server-{refresh,toggle}"]` / `[data-players-bar]` / `[data-testid="server-players-panel"]` / `[data-testid="server-map-img"]` (every cell beyond `data-id` is feature-detected; the dashboard widget, the Add Admin grid, and the Server Groups card stack all ship ONLY `[data-testid="server-host"]` and the helper no-ops every other branch). Disabled tiles carry `data-server-skip="1"` so the helper leaves them at the server-rendered placeholder; this is single-source contract across every consumer (admin Server Management, admin Server Groups card stack — `page_admin_groups_list.tpl` pairs the gate with a visible `[data-testid="server-disabled-tag"]` "Disabled" pill so the row reads as "bound but disabled" instead of "probe hasn't resolved yet"). Never copy-paste the hydration code into a new template — wire the testids and `data-server-hydrate="auto"` instead and the helper picks the surface up automatically. The dashboard widget, the Add Admin grid, and the Server Groups card stack are all canonical references for "minimal-testid integration": each ships ONE optional testid (`[data-testid="server-host"]`) + the outer `[data-testid="server-tile"]` + `data-id="<sid>"`, and the helper still hydrates the hostname per the same `Actions.ServersHostPlayers` round-trip. The per-surface truncation knob (`data-trunchostname="<n>"` on the wrapping container) is the right place to dial the hostname length per surface — `70` on the full-width public + admin cards, `40` on the cramped dashboard widget, the Add Admin grid's ~18rem checkbox columns, AND the Server Groups card stack so a long hostname doesn't trip `truncate`'s ellipsis prematurely. The Server Groups stack also ships a `[data-testid="server-host"]`-paired `data-fallback="{ip|escape}:{port}"` attribute the helper re-paints on probe-error (`r.data.error === 'connect'`) — same shape the public Server List ships; the SSR inner-text holds the IP:port literal so the no-JS / cache-cold / helper-missing path stays informative. The per-group `servers` array the template iterates is composed server-side by `web/pages/admin.groups.php` (INNER JOIN against `:prefix_servers` so groups holding dangling `:prefix_servers_groups` rows from deleted servers don't surface broken tiles; `S.enabled` rides the projection so the template's disabled-row gate doesn't need a second query); the View DTO field is `AdminGroupsListView::$server_list[i].servers` (each row shaped `{sid: int, ip: string, port: int, enabled: bool}`) and the integration contract is pinned by `web/tests/integration/AdminServerGroupsServerCardsRenderTest.php` (file-shape) + `web/tests/e2e/specs/flows/admin-groups-server-cards-hydration.spec.ts` (runtime stub-resolve-flip + disabled-row probe-skip + dangling-membership INNER JOIN drop). The E2E spec's dangling arm rides a dedicated `web/tests/e2e/scripts/delete-server-e2e.php` shim that deletes a `:prefix_servers` row via raw SQL — bypassing `api_servers_remove`'s cleanup cascade, which would silently clean up the `:prefix_servers_groups` row in the same transaction and make the orphan condition impossible to produce. Pair with `seed-server-group-e2e.php` (which now accepts a per-server `enabled` flag) when extending coverage. The Add Admin grid restored hostname hydration that was silently broken since v2.0.0 / #1123 D1 — the legacy v1.4.11 `<script>LoadServerHost('SID', …)</script>` per-row feeder was deleted with `sourcebans.js`, raising one `ReferenceError` per configured server on every page load. Sister cleanup #1404 dropped the dead feeder + the orphan `$server_script` View property; #1405 is the additive replacement, no new View property + no new JSON action. |
| Tune the server card grid layout (column min-width, mobile single-column collapse) shared between the public + admin Server Management lists | `.servers-grid` rule in `web/themes/default/css/theme.css` (#1316). Single-source `repeat(auto-fill, minmax(28rem, 1fr))` — both `page_servers.tpl` and `page_admin_servers_list.tpl` apply the class to their grid container instead of an inline `style="grid-template-columns:..."`. The 28rem (448px) min replaced the pre-#1316 20rem (320px) min that packed cards into ~340px columns even on a 31" 4K monitor (both pages cap their content area at 1400px via `.page-section` / inline `max-width:1400px`, so wider viewports got zero benefit from the wider screen — that's the bug #1316 fixed). With the 1rem grid gap factored in: 1280px laptop ≈ 2 cols × ~488px each; 1400px+ desktop ≈ 2 cols × ~668px each. At <=768px the sibling `@media` rule collapses to `minmax(0, 1fr)` (NOT bare `1fr`, which would inflate the track to the card's `truncate`-nowrap min-content and overflow the viewport) so a phone-portrait viewport never overflows horizontally. Don't reach for a different column-min on a per-template basis — the class is the unified knob; theme forks override the rule wholesale. Regression guard: `web/tests/e2e/specs/responsive/server-cards.spec.ts` walks four desktop viewports (1280/1920/2560/3840) plus iPhone-13-like 390px and asserts both surfaces apply the class, the card width floor is ≥28rem, and the mobile collapse holds. |
| Edit the command palette (icon-only trigger, ⌘K binding, result rows, kbd hints, Ctrl+Enter copy) | `web/themes/default/js/theme.js` (`openPalette` / `closePalette` / `renderPaletteResults` / `applyPlatformHints` / `handlePaletteCopyShortcut`) + `core/title.tpl` (the `.topbar__search` icon button) + the `.palette__row*` rules in `web/themes/default/css/theme.css`. Player rows carry `data-drawer-bid="<bid>"` (bare Enter / click → `loadDrawer`, palette closes itself) + `data-steamid="<steam>"` (`Ctrl/Cmd+Enter` → `navigator.clipboard.writeText` + `showToast`). The kbd glyphs are server-rendered in non-Mac form (`Enter`, `Ctrl`); `applyPlatformHints` swaps `[data-enterkey]` → ⏎ and `[data-modkey]` → ⌘ on Mac/iOS at boot and after every render (#1184, #1207 DET-2). |
| Add or edit a palette "Navigate" entry (the icon-label-href rows the palette renders alongside player results) | `web/includes/View/PaletteActions.php` (`Sbpp\View\PaletteActions::for($userbank)` — catalog + filter). The catalog's `entries()` method declares each entry as `{icon, label, href, permission, config?}`; `for()` drops entries the user can't reach (admin entries gated via `HasAccess` with `ADMIN_OWNER` OR'd in; public entries optionally gated on a `config.enable*` toggle) and emits the public `{icon, label, href}` triple. The filtered list is JSON-encoded by `web/pages/core/footer.php` (with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` so the content can never escape its `<script>` wrapper) and emitted by `core/footer.tpl` inside `<script type="application/json" id="palette-actions" data-testid="palette-actions">`. `theme.js`'s `loadNavItems()` reads + `JSON.parse`s the blob at boot. Pre-#1304 the entry list was a hardcoded `NAV_ITEMS` array in `theme.js` with no permission check, leaking admin entries to logged-out + partial-permission users; the regression guard is `web/tests/integration/PaletteActionsTest.php` (server-side filter) plus `web/tests/e2e/specs/flows/ui/command-palette-permissions.spec.ts` (end-to-end blob → DOM contract). |
| Add a "copy this value" affordance to a panel surface (single-source clipboard wiring) | Mark the trigger with `data-copy="<value>"` (`<button type="button">` is the canonical shape; the drawer uses `<button>` inside a `<dd>`, the banlist row uses `<button>` inside `.row-actions`). The document-level COPY BUTTONS delegate in `web/themes/default/js/theme.js` handles every `[data-copy]` site: secure-context callers go through `navigator.clipboard.writeText` with a `.then(success, fallback)` chain, non-secure callers (plain HTTP behind a TLS-terminating proxy) drop to `copyFallback()` — a hidden-textarea + `document.execCommand('copy')` that's the only portable option outside HTTPS (#1308). NEVER add an inline `onclick="event.stopPropagation()"` to a `[data-copy]` button — the bubble-phase stop kills the document delegate (Defect A, #1308). NEVER assume `navigator.clipboard` exists or that `writeText()` resolves — both fall through to the same execCommand fallback (Defect B, #1308). |
| Add admin-only per-player notes | `web/api/handlers/notes.php` (CRUD) — Notes tab is gated by `bans.detail`'s `notes_visible` flag |
| Add or extend the server-player right-click context menu on `?p=servers` (View Profile / Copy SteamID / Kick / Ban / Block Comms) | `web/scripts/server-context-menu.js` (event-delegate menu, single `document.addEventListener('contextmenu')` filtered by `closest('[data-context-menu="server-player"]')`) + `web/scripts/server-tile-hydrate.js` (`renderPlayers()` emits the `data-context-menu` / `data-steamid` / `data-name` / `data-server-sid` / `data-can-ban-player` hooks on each `<li>`) + `web/api/handlers/servers.php` (`api_servers_host_players` attaches SteamIDs via `RconStatusCache::fetch($sid)` only when the caller has `WebPermission::Owner \| WebPermission::AddBan` AND per-server RCON access; `can_ban_player` boolean signals whether to render the kick/ban/block items) + `web/includes/Servers/RconStatusCache.php` (`Sbpp\Servers\RconStatusCache::fetch($sid, $ttl=30)` — per-sid on-disk cache under `SB_CACHE/srvstatus/`, mirrors `SourceQueryCache` shape; calls `rcon('status', $sid, true)` with the silent flag so passive probes don't spam the audit log). The admin hint copy in `page_servers.tpl` + the `<script src="./scripts/server-context-menu.js">` include are both gated on `$can_use_context_menu` (= `Perms::for($userbank)['can_add_ban']`) so anonymous viewers don't pay for either. SteamID3 / SteamID2 → SteamID64 conversion happens client-side (`STEAM_X:Y:Z` → `76561197960265728 + Z*2 + Y`; `[U:1:N]` → `76561197960265728 + N`). **The `player_list` field excludes A2S entries with an empty `Name` (#1396)** — some Source-engine variants and SourceMod plugins emit a "host slot" / "console" stub at the start of `GetPlayers` (`Name = ''`, `Frags = 0`, `Time = 0`) that pre-fix rendered as a phantom `<li data-testid="server-player">` above the first visible player with no `data-context-menu` hooks (the empty name fails the SteamID match gate above). The phantom row was a thin border-bottom strip with a misleading "0 · " meta on the right; users perceived the next real player as "the first player of the list" and right-clicks landing in the phantom row's area silently no-op'd. The filter lives at the same `$name === ''` skip the SteamID-by-name lookup uses, so the JS contract stays simple: every row in `player_list` has a displayable name. Bots, real players whose A2S name didn't match the RCON status output, and anonymous callers still render — the filter is strictly "name is empty string". Ban / Block both route through panel-chromed smart-default URLs (`?p=admin&c=bans&section=add-ban&steam=…&type=0` consumed by `Sbpp\View\AdminBansAddView::prefill_steam` / `?p=admin&c=comms&steam=…&type=0` consumed by `Sbpp\View\AdminCommsAddView::prefill_steam` — #1395 brought Block onto the panel route; pre-fix it pointed at `pages/admin.blockit.php` which is the post-`Actions.CommsAdd` rcon-fan-out iframe target, NOT a stand-alone operator page); Kick stays on the iframe path (`pages/admin.kickit.php`) because it's a one-shot RCON command with no persistent panel surface to anchor on after firing. Both `admin.bans.php` and `admin.comms.php` allowlist the inbound shape via the same regex (`STEAM_X:Y:Z` / `[U:1:N]` / 17-digit SteamID64 / dotted IPv4) so a hostile/malformed referrer can't smuggle arbitrary text into the form's `<input value="…">`; the comms `?type=…` allowlist is `{1,2,3}` (Mute/Gag/Silence) with anything else (including the menu's `?type=0` bridging value, sourced from the bans-menu URL shape where 0=Steam ID) treated as "no pre-selection". The integration test (`web/tests/integration/ServerListHintRegressionTest.php`) is the post-restoration contract — it asserts the hint and the JS include both ship for admins and both stay absent for anonymous viewers (the pre-#1306 contract is superseded). Regression guards: `web/tests/api/ServersTest.php` (handler-shape coverage for the SteamID side-channel + the `can_ban_player` flag + the #1396 empty-name filter via `testHostPlayersFiltersEmptyNameEntries` / `testHostPlayersFiltersAllEmptyNameEntries`), `web/tests/integration/RconStatusCacheTest.php` (cache shape + silent-flag contract), `web/tests/integration/AdminBansAddSmartDefaultTest.php` + `web/tests/integration/AdminCommsAddSmartDefaultTest.php` (server-side prefill allowlist, valid / hostile / bare-section round-trips, type-coercion), `web/tests/e2e/specs/flows/server-player-context-menu.spec.ts` (end-to-end menu open / Ban + Block hrefs both ride the panel route / Kick stays on the iframe route / Escape close / no-steamid no-menu / #1396 first-named-player accepts real `mouse.click({button:'right'})`). |
| Cache an A2S `GetInfo + GetPlayers` round-trip / add another public server-query handler | `web/includes/Servers/SourceQueryCache.php` (`Sbpp\Servers\SourceQueryCache::fetch($ip, $port, $ttl=30)` — per-`(ip, port)` on-disk cache under `SB_CACHE/srvquery/`, atomic tempfile + `rename()` writes mirroring `system.check_version`'s release cache; both success and failure cache so an unreachable server costs ONE A2S probe per ~30s window). The sibling `Sbpp\Servers\RconStatusCache` (`SB_CACHE/srvstatus/`) follows the same shape for RCON `status` round-trips — used by `api_servers_host_players` to surface per-player SteamIDs to admins (see the context-menu row above). Every public handler under `web/api/handlers/servers.php` (`api_servers_host_players` / `host_property` / `host_players_list` / `players`) goes through this — never call `new SourceQuery()` directly from a handler. The cache stamps user-agnostic data only; the handler stamps per-caller fields (`is_owner`, `can_ban`, the per-call `trunchostname`) on top. Per-tile JS debounce on the public servers page lives in `web/themes/default/page_servers.tpl` (`loadTile()` flips `tile.__sbppLoading` + the Re-query button's `disabled` attr while a probe is in flight, releases both in the success / error tails). The matching JS gate on the toggle button has been the precedent since v2.0.0; #1311 brought the refresh button onto the same shape. Tests: `web/tests/integration/SourceQueryCacheTest.php` (cache shape + coalescing + TTL + invalidation, drives `setProbeOverrideForTesting()` so the assertion is deterministic without UDP) + `testHostPlayersCoalescesRapidRepeatCallsViaCache` / `testHostPlayersNegativeCachesUnreachableServers` in `web/tests/api/ServersTest.php` (handler-shape coverage). E2E: `web/tests/e2e/specs/flows/server-refresh-debounce.spec.ts`. |
| Render admin-authored Markdown to safe HTML | `web/includes/Markup/IntroRenderer.php` (`Sbpp\Markup`) |
| Build / extend the anonymous opt-out daily telemetry payload (#1126) | `web/includes/Telemetry/Telemetry.php` (`Sbpp\Telemetry\Telemetry` — `tickIfDue`, `collect`, `send`) + `web/includes/Telemetry/Schema1.php` (`Sbpp\Telemetry\Schema1::payloadFieldNames()`, drives the extractor parity test) + `web/includes/Telemetry/schema-1.lock.json` (vendored from [sbpp/cf-analytics](https://github.com/sbpp/cf-analytics) — manual sync via `make sync-telemetry-schema`). Tick is registered at the tail of `init.php` via `register_shutdown_function`; on FPM, `fastcgi_finish_request()` flushes the response BEFORE the cURL POST so telemetry never delays a panel page. Slot reservation is atomic (`UPDATE :prefix_settings WHERE CAST(value AS UNSIGNED) <= :threshold`) at the START of the attempt, so a flapping endpoint costs one ping/day, not one ping/request. Audit-log only enable/disable transitions, never individual pings. The in-panel disclosure surface is the help-icon copy in `page_admin_settings_features.tpl`; the upgrade-time disclosure lives in `docs/src/content/docs/updating/1.8-to-2.0.mdx` (no first-login modal). |
| Add or edit a project announcement (the admin-only banner on the home dashboard) | Edit `docs/public/announcements.json` (the source of truth — Astro publishes it as a static asset at `https://sbpp.github.io/announcements.json`). Each entry: `id` (≤64 chars, **required**), `title` (**required**), `body_md` (CommonMark, optional — rendered through `Sbpp\Markup\IntroRenderer` so raw HTML is escaped + `javascript:` / `data:` URLs are stripped), `url` (optional `http(s)://` only — non-http schemes rejected at the parser), `published_at` (optional ISO-8601 or unix int — drives the sort order; entries without it sort below dated entries), `expires_at` (optional — the parser drops entries past this timestamp). Sorted newest-first by the panel; convention is to also place the newest entry at the top of the array so reviewers see the most relevant change first. The deploy chain is automatic: a push to `main` that touches `docs/` fires `.github/workflows/docs-deploy-trigger.yml` which lands the file at `https://sbpp.github.io/announcements.json` within minutes. The starter file ships as `[]` (empty array). NEVER write to the cache file (`SB_CACHE/announcements.json`) directly — the panel's shutdown hook owns that path. |
| Build / extend the daily project-announcements feed (banner-side wiring; mirror of telemetry) | `web/includes/Announce/AnnouncementFetcher.php` (`Sbpp\Announce\AnnouncementFetcher` — `latest()`, `tickIfDue()`, `_setHttpFetcherForTests()`) + `web/includes/Announce/Announcement.php` (the readonly DTO). Tick is registered at the tail of `init.php` via `register_shutdown_function`, gated on a non-empty `SB_ANNOUNCEMENTS_URL` constant (with the `if (!defined(...))` shape so `config.php` can override — empty string is the documented air-gap escape hatch; non-`http(s)://` schemes are also treated as air-gap by `resolveUpstreamUrl`'s scheme guard so a `file://` / `php://` / `phar://` / `data://` typo can't pull arbitrary local files into the cache). Cache shape mirrors `system.check_version`'s `_api_system_release_save_cache` (atomic tempfile + rename under `SB_CACHE/announcements.json`, persisted as the upstream body verbatim; the cache load path also caps the read at 256 KiB so a hostile / hand-edited cache file can't OOM the worker before the post-read size check fires). Wire layer mirrors `system.check_version`'s fetch helper: 5s combined connect+read timeout (PHP's stream wrapper exposes a single knob — split into connect + total only if a future cURL reshape lands), `User-Agent: SourceBans++/<ver> (announcements)`, 256 KiB body cap. The page handler in `web/pages/page.home.php` short-circuits to `null` for anonymous + non-admin viewers; the View DTO `HomeDashboardView` carries `?array $announcement`; the template (`page_dashboard.tpl`) gates the entire `<aside>` block on truthy. Markdown rendering goes through `Sbpp\Markup\IntroRenderer::renderIntroText()` and lands as `body_html` on the DTO — the template emits it with `{nofilter}` per the documented contract. Test override: `_setHttpFetcherForTests(?callable)` mirrors `Sbpp\Servers\SourceQueryCache::setProbeOverrideForTesting`. Regression guards: `web/tests/integration/AnnouncementFetcherTest.php` (cache shape, atomic write, stale-while-error, body-cap, expired-entry filter, malformed JSON, IntroRenderer integration, URL-scheme rejection, dedup, sort), `web/tests/integration/HomeDashboardAnnouncementTest.php` (admin sees / anonymous doesn't / cold cache yields null — process-isolated render with stub Smarty), `web/tests/e2e/specs/flows/dashboard-announcement.spec.ts` (end-to-end mount + disclosure expand + `rel="noopener noreferrer"` + axe-clean + anonymous-storage-state arm). E2E cache seeding shim: `web/tests/e2e/scripts/seed-announcements-e2e.php` + `seedAnnouncementsE2e` / `clearAnnouncementsCacheE2e` in `fixtures/db.ts`. See "Project announcements feed" under Conventions. |
| Flip a `:prefix_settings` row (feature toggle) from an E2E spec so the surface under test renders | `setSettingE2e(setting, value)` from `web/tests/e2e/fixtures/db.ts` (#1402). Shells out to `web/tests/e2e/scripts/set-setting-e2e.php` which `REPLACE INTO`s the row — mirror of the `REPLACE INTO sb_settings` shape `BansTest.php` uses to enable `config.enablegroupbanning` for the same reason. Pair with `afterAll(async () => { await setSettingE2e(key, defaultValue); })` because the e2e DB is shared between specs (`Fixture::truncateAndReseed` does NOT reset `sb_settings`). Reference: the group-ban dispatcher spec (`web/tests/e2e/specs/flows/groupban-dispatcher.spec.ts`) flips `config.enablegroupbanning` on in `beforeAll` and reverts in `afterAll`. Don't drive the change through `Actions.SettingsSave` from the spec — that requires CSRF + an Owner-permission + would fan out through `Config::init()` + audit-log writes on every flip, way more moving pieces than the spec needs. |
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
| Pin "dead v1.x JS call sites + their server-side feeders stay deleted" (the `<script>LoadServerHost(...)</script>` / `$data['unban_link']` / `$info['popup']` shapes the v2.0.0 `sourcebans.js` cutover left without a JS landing site) | `web/tests/integration/DeadJsCallSitesTest.php` (#1404) — three methods, 12 assertions. Per-file forbidden-substring map (`page.banlist.php` / `page.commslist.php` / `page.home.php` / `admin.admins.php` / `admin.groups.php`) scanned against the **comment-stripped** source of each handler via `php_strip_whitespace()`, so the cleanup's own `// #1404 — ...` explanatory comments (and pre-existing historical-context comments that name the dead helpers in passing) don't false-fire the gate. The Smarty half (`{$server_script nofilter}`, the `<div id="servers_{$group.gid}">` hydration slot, the "Servers populate via the legacy LoadServerHostPlayersList hook." placeholder copy) is pinned by `testDeadTemplateSidesStayDropped` (also comment-stripped via `{* *}`-regex). The View-DTO side (`Sbpp\View\AdminAdminsAddView::server_script` orphan property) is pinned independently by `testAdminAdminsAddViewDoesNotCarryServerScriptProperty` so the failure points at the View directly instead of cascading through SmartyTemplateRule. Pure file scanning — extends `PHPUnit\Framework\TestCase` (no DB / session / Smarty bring-up). Sister #1402 (rewire dead JS handlers) and #1403 (ShowBox→`window.SBPP.showToast` rewrite) extend the per-file forbidden-substring map as their PRs land. |
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
| Build / extend the production Docker image (multi-stage build, hardened runtime, entrypoint state machine, healthcheck) | `docker/Dockerfile.prod` (multi-stage: `builder` runs `composer install --no-dev` against `web/`; `runtime` carries pdo_mysql + intl + zip + mbstring + gmp ONLY — no nodejs / npm / git / dev-prepend) + `docker/php/prod-entrypoint.sh` (pure POSIX shell state machine: `*_FILE` secret resolution → DATABASE_URL parse → defaults → Apache config (PORT + mod_remoteip from `SBPP_TRUSTED_PROXIES`) → wait-for-DB → render config.php (only when missing) → first-boot install (schema + data + seed admin from `INITIAL_ADMIN_*` env vars) → headless updater migrations → strip install/ + updater/ from writable layer → ensure writable cache/templates_c/demos → `exec apache2-foreground`) + `docker/php/prod-php.ini` (production OPcache: `validate_timestamps=0`, `display_errors=Off`, `log_errors=On`, errors → `/dev/stderr`, UTC default, `expose_php=Off`) + `docker/apache/sbpp-prod.conf` (denies dotfiles + vendor/ + configs/ + includes/ + install/ + updater/ + cache/ + templates_c/ + config.php + composer.{json,lock}; `RemoteIPHeader X-Forwarded-For` for the trusted-proxy chain) + `web/health.php` (DB-aware unauthenticated healthcheck — `init.php` bootstraps the panel; `$GLOBALS['PDO']->query('SELECT 1')` returns 200 OK or 503 + plain-text reason; Cache-Control: no-store + X-Robots-Tag: noindex). The production image MUST NOT define `SBPP_DEV_KEEP_INSTALL`; the entrypoint's `strip_install_dirs` step is what makes the panel-runtime guard pass instead (#1381). |
| Deploy / configure the production Docker stack (compose, env vars, reverse-proxy) | `docker-compose.prod.yml` (pulls `ghcr.io/sbpp/sourcebans-pp:${SBPP_IMAGE_TAG:-latest}` — NOT a build context; DB port NOT exposed by default; commented `caddy:` service block for opt-in TLS) + `.env.example.prod` (every supported env var grouped by required / recommended / first-boot / optional / advanced; documents the `*_FILE` Docker-secret pattern and the `SBPP_CONFIG_PATH` Docker-secret-mount pattern; uses `${VAR:?...}` compose syntax for required vars so a fresh-deploy operator who forgot `SB_SECRET_KEY` / `DB_PASS` / `DB_ROOT_PASS` gets a useful container-startup error) + `docker/caddy/Caddyfile.example` (one-line `reverse_proxy web:80` + zstd/gzip encode + static-asset cache headers). Three persistent volumes: `dbdata` + `demos` (MUST persist — DB rows + uploaded ban-evidence); `cache` + `smarty` (CAN be ephemeral — rebuild on demand). Operators run `docker compose -f docker-compose.prod.yml up -d` from a directory carrying both files; upgrades are `docker compose pull && up -d` (image is immutable, entrypoint runs idempotent migrations on every boot, named volumes survive the swap) (#1381). |
| Honour `SBPP_CONFIG_PATH` so config.php can live outside the panel root (Docker-secret mount) | `sbpp_resolve_config_path()` in `web/init-recovery.php` is the single source of truth; `web/init.php` calls it to resolve the require-site. `web/install/already-installed.php`'s `sbpp_install_is_already_installed()` re-implements the env-var read inline (per its self-contained no-Composer docblock) so the wizard-side and runtime-side guards agree on the install-state sentinel path. Pre-#1381 both halves hard-coded the panel-root path; with a Docker-secret-mounted config the runtime would 302-to-/install/ while the wizard would happily start over. Regression tests: `testResolveConfigPathHonorsEnvVar` + `testWizardGuardHonorsConfigPathEnvVar` in `web/tests/integration/InstallGuardTest.php`. |
| Change the Contributor License Agreement (text, scope, allowlist) or how the CLA bot gates `web/**` PRs | `CLA.md` (the agreement text — 10 sections, web/-scoped, explicit relicensing right in §3(b)) + `.github/workflows/cla.yml` (the `contributor-assistant/github-action` workflow — paths filter, allowlist, sign-comment text, custom not-signed PR comment) + `CONTRIBUTING.md` (rationale + how-to-sign for contributors). Signatures land on the orphan branch `cla-signatures` under `signatures/cla.json`; the action creates the branch on its first successful run. The maintainer plus all `*[bot]` accounts are allowlisted by default. See "Contributor License Agreement gate" in Conventions. |
