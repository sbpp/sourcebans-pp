# SourceBans++ — guide for Claude Code

## Repo layout

- `web/` — PHP 8.2 web panel (Smarty templates, PDO/MariaDB). Entry: `web/index.php`.
- `web/install/` — first-run installer; **not used** in the Docker dev setup (we seed directly).
- `game/addons/` — SourceMod plugin sources.
- `docker/`, `docker-compose.yml`, `sbpp.sh` — local dev stack (see below).

## Local dev stack (Docker)

Everything in `docker/README.md`. The TL;DR for an LLM that wants to run things:

```sh
./sbpp.sh up                          # build + start; idempotent, safe to re-run
./sbpp.sh status                      # are containers up?
./sbpp.sh logs web                    # tail web logs
./sbpp.sh phpstan                     # run static analysis
./sbpp.sh test                        # run PHPUnit (against sourcebans_test DB)
./sbpp.sh ts-check                    # run tsc --checkJs over web/scripts
./sbpp.sh composer api-contract       # regenerate web/scripts/api-contract.js
./sbpp.sh shell                       # bash inside the web container
./sbpp.sh mysql                       # mariadb client on the dev DB
./sbpp.sh db-reset                    # wipe DB volume and re-seed
./sbpp.sh down                        # stop, keep volumes
./sbpp.sh reset                       # stop and drop ALL volumes
```

After `up`, the panel is at <http://localhost:8080>, login **admin / admin**.
Adminer at :8081, Mailpit at :8025, MariaDB exposed on host port 3307.

The web container bind-mounts `./web`, so file edits are picked up on the
next request — no restart needed unless you change `composer.json`
(re-run `./sbpp.sh composer install`) or anything in `docker/` (run
`./sbpp.sh rebuild`).

## Quality gates

PHPStan is the project's static-analysis gate. Inside the dev container:

```sh
./sbpp.sh phpstan
```

`web/phpstan-baseline.neon` captures pre-existing violations so a clean tree
passes; new code should be clean. Regenerate the baseline only when a real
fix removes an entry — see the README for the command.

PHPUnit is the behavioural gate (added in #1081 alongside the JSON API).
Tests live under `web/tests/` and run against a dedicated `sourcebans_test`
database so they never stomp dev data:

```sh
./sbpp.sh test
./sbpp.sh test --filter=SomeTest          # run a single test
./sbpp.sh test tests/api/AccountTest.php  # run a single file
```

`tsc --checkJs` is the JS gate (added in #1098). It runs against the
existing `.js` files in place using `// @ts-check` + JSDoc — no `.ts`
sources, no bundler, nothing in `web/node_modules/` ever ships:

```sh
./sbpp.sh ts-check
```

CI runs all three gates on every PR (`.github/workflows/phpstan.yml`,
`.github/workflows/test.yml`, `.github/workflows/ts-check.yml`), plus a
fourth gate (`.github/workflows/api-contract.yml`) that regenerates
`web/scripts/api-contract.js` and fails on diff — see Conventions below.

## Conventions

- **Don't add new `install/` flow**. The dev container provisions the DB out
  of band; the installer wizard is legacy and stays for production users.
- **Database access** goes through `web/includes/Database.php` (PDO).
  ADOdb has been fully removed (commit `b9c812b2`); do not reintroduce it.
- **CSRF** tokens are required on every state-changing form/JSON call.
  Use `Smarty {csrf_field}` in templates and `CSRF::validate()` /
  `CSRF::rejectIfInvalid()` in handlers. The dispatcher also accepts the
  token via the `X-CSRF-Token` header (set by `sb.api.call` in `api.js`).
- **Server endpoints** live under `web/api/handlers/<topic>.php` and are
  registered in `web/api/handlers/_register.php`. Each handler is a pure
  `function(array $params): array`; throw `ApiError($code, $msg, $field?)`
  to surface a structured client error, return `Api::redirect($url)` to
  navigate. Don't add new functions to a hypothetical `sb-callback.php`
  (it's been removed) or reach for xajax (also removed).
- **Client code** uses vanilla JS through `web/scripts/sb.js` (DOM helpers,
  message box, tabs, tooltips, accordion) and `web/scripts/api.js` (the
  `sb.api.call(action, params)` wrapper). Don't reintroduce MooTools, React,
  or a runtime bundler — self-hosters deploy by unzipping the release.
- **Action names and permission masks** in JS come from
  `web/scripts/api-contract.js`, generated from `_register.php` +
  `configs/permissions/web.json` by `web/bin/generate-api-contract.php`
  (#1097). Always call `sb.api.call(Actions.PascalName, ...)` — never a
  string literal — and reference perms as `Perms.ADMIN_*`. The contract
  file is checked-in source (like a lockfile), not a build artifact:
  release tarballs ship it; self-hosters need no codegen step. Regenerate
  whenever you touch a handler's name, perm mask, or `@param`/`@return`
  docblock, or edit the perm constants:

  ```sh
  ./sbpp.sh composer api-contract
  ```

  Commit the regenerated file in the same PR as the PHP change. CI
  (`api-contract.yml`) re-runs the generator on a clean checkout and
  fails on `git diff`, so a stale file blocks merge.
- **JS files** under `web/scripts/` all carry `// @ts-check` and run
  through `tsc --noEmit` in CI (#1098). Keep `// @ts-check` on any new
  file you add there. Use `sb.$idRequired(id)` when a missing element is
  a programmer error; `sb.$id(id)` returns `HTMLElement | null` and must
  be narrowed. `SbAnyEl` (in `web/scripts/globals.d.ts`) is intentionally
  permissive for legacy form-element access — prefer typed selectors
  (`document.querySelector<HTMLInputElement>(...)`) in new code.
