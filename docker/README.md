# Local development with Docker

A one-command dev environment for the SourceBans++ web panel. Spins up
PHP/Apache, MariaDB, Adminer, and Mailpit, seeds the database, and creates a
ready-to-use admin login. Source under `web/` is bind-mounted, so edits show
up on the next request — no rebuilds needed.

## Prerequisites

- Docker 24+ with the Compose plugin (`docker compose`, not `docker-compose`)
- Ports `8080`, `8081`, `8025`, `1025`, `3307` free on the host (override in `.env`)

## Quick start

```sh
./sbpp.sh up
```

That builds the web image, starts everything in the background, runs
`composer install` on first boot, and seeds the schema + a default admin.

Then:

| Service              | URL                          | Notes                          |
| -------------------- | ---------------------------- | ------------------------------ |
| SourceBans++ panel   | <http://localhost:8080>      | login: **admin** / **admin**   |
| Adminer (DB UI)      | <http://localhost:8081>      | server `db`, user `sourcebans` |
| Mailpit (catch-all)  | <http://localhost:8025>      | SMTP on `mailpit:1025`         |
| MariaDB (host port)  | `localhost:3307`             | user `sourcebans` pw same      |

To stop:

```sh
./sbpp.sh down       # keep volumes
./sbpp.sh reset      # nuke volumes too (fresh DB on next up)
```

## What's in the box

```
Dockerfile               php:8.5-apache + pdo_mysql, gmp, intl, zip, mbstring, opcache, composer
docker-compose.yml       web, db, adminer, mailpit
docker/php/
    web-entrypoint.sh    waits for DB, renders config.php, runs composer install, fixes cache perms
    dev-prepend.php      normalizes HTTP_HOST so init.php's localhost guard accepts :8080
docker/db-init/
    00-render-schema.sh  on first DB init: substitutes {prefix}/{charset}, loads struc/data, seeds admin
sbpp.sh                  thin wrapper around `docker compose` + common dev tasks
```

The web container mounts `./web` from the host, with two named-volume
overlays (`vendor/` and `cache/`) so Composer artifacts and Smarty cache
don't leak onto the host filesystem.

`README.md` is also bind-mounted as a read-only sibling at
`/var/www/html/README.md` so the `TelemetryReadmeParityTest` integration
test (#1126) can reach it from `web/../README.md`. CI gets this for free
via `actions/checkout@v4`; the local stack mounts only `./web`, so the
bind exists to keep the parity surface working without splitting the
test set.

## Common tasks

```sh
./sbpp.sh logs                    # tail everything
./sbpp.sh logs web                # tail one service
./sbpp.sh shell                   # bash in the web container (root)
./sbpp.sh shell db                # mariadb client connected to dev DB
./sbpp.sh composer install        # run composer in the web container
./sbpp.sh phpstan                 # phpstan analyse with the project's phpstan.neon
./sbpp.sh ts-check                # tsc --checkJs gate over web/scripts (mirror of CI)
./sbpp.sh e2e                     # Playwright E2E gate (lazy chromium install) against the running stack
./sbpp.sh db-dump backup.sql      # mysqldump to host file
./sbpp.sh db-load fixtures.sql    # pipe a SQL file into the DB
./sbpp.sh db-reset                # drop just the DB volume and re-seed
./sbpp.sh db-seed                 # populate the dev DB with realistic synthetic data
./sbpp.sh rebuild                 # `--no-cache` rebuild of the web image
```

`db-seed` lights up every data-driven panel surface — banlist + commslist
beyond a single page, dashboard "Latest …" cards, the drawer's history /
comments / notes panes, admin moderation queues (submissions and
protests), admin audit log, multiple groups/admins/servers — without
touching `data.sql` / `struc.sql` (the install path stays minimal).
Idempotent: every run truncates the synthesizer-owned tables first and
re-seeds. Deterministic given `--seed=<int>` so two devs see the same
data; pinned default seed in code. Three scale tiers:

```sh
./sbpp.sh db-seed                 # default scale (~200 bans, ~100 comms)
./sbpp.sh db-seed --scale=small   # ~30 bans, fast iteration
./sbpp.sh db-seed --scale=large   # ~2000 bans, pagination / perf
./sbpp.sh db-seed --seed=42       # alternate RNG seed
```

Refusal guard: the seeder strictly refuses any `DB_NAME` other than
`sourcebans`, so the PHPUnit DB (`sourcebans_test`) and Playwright DB
(`sourcebans_e2e`) cannot be wiped by accident. The Synthesizer source
is at `web/tests/Synthesizer.php` (`Sbpp\Tests\Synthesizer`); the CLI
driver is `web/tests/scripts/seed-dev-db.php`.

Re-login required after each run — the seeder truncates `sb_login_tokens`
along with the rest of the user-data tables, which invalidates any open
browser session against the dev panel. Just hit `/index.php?p=login`
again with `admin` / `admin`.

## Quality gates

Five gates run in CI on every PR; each has a one-shot wrapper for local runs.

```sh
./sbpp.sh phpstan                 # static analysis (web/phpstan.neon, baseline at web/phpstan-baseline.neon)
./sbpp.sh test                    # PHPUnit against the dedicated sourcebans_test DB
./sbpp.sh ts-check                # tsc --checkJs over web/scripts (#1098)
./sbpp.sh composer api-contract   # regenerate web/scripts/api-contract.js (#1112)
./sbpp.sh e2e                     # Playwright + axe against the dev stack (#1124)
```

`ts-check` runs the TypeScript compiler in `--checkJs` mode against the
vanilla JS in `web/scripts/`, using `web/scripts/tsconfig.json` plus the
`@ts-check` directives and JSDoc annotations on each file. The first run
inside a fresh container does an `npm install` (cached afterwards) — total
cold cost is a few seconds, subsequent runs are sub-second. There is no
build step; nothing in `web/node_modules/` ships to production.

`e2e` runs the Playwright suite under `web/tests/e2e/` inside the web
container against a dedicated `sourcebans_e2e` database (so dev data
and PHPUnit's `sourcebans_test` are both untouched). First run installs
`@playwright/test` + the chromium browser + its system dependencies via
`npx playwright install --with-deps chromium`; subsequent runs reuse
the cached install. Forwards args to `npx playwright test`, e.g.
`./sbpp.sh e2e --grep @screenshot` for the per-PR screenshot gallery.

## Static analysis with phpstan-dba

`./sbpp.sh phpstan` runs PHPStan inside the web container with
[`staabm/phpstan-dba`](https://github.com/staabm/phpstan-dba) wired up against
the running `db` service. The wrapper exports `DBA_HOST=db` (plus `DBA_USER`,
`DBA_PASS`, `DBA_NAME`, `DBA_PREFIX`) so `web/phpstan-dba-bootstrap.php` can
introspect the live schema and type-check raw SQL strings — column names,
table names, and statement syntax in every `Database::query(...)` call get
validated against `web/install/includes/sql/struc.sql` as it would be loaded
by the seed script.

To skip the DBA pass (useful when the DB container is down or you're
iterating on unrelated rules), set `PHPSTAN_DBA_DISABLE=1`:

```sh
PHPSTAN_DBA_DISABLE=1 ./sbpp.sh phpstan
```

The bootstrap also degrades gracefully if it can't reach the DB at all, so a
fresh checkout without `./sbpp.sh up` won't break the gate — it just runs the
non-DBA rules.

CI mirrors this: `.github/workflows/phpstan.yml` spins up MariaDB 10.11,
renders `struc.sql` (no `data.sql` — phpstan-dba only needs structure), and
points the same env vars at it. Renaming or removing a column in `struc.sql`
without updating its callers will fail the PHPStan job. CI also sets
`DBA_REQUIRE=1` so a missing service or credentials drift fails the job
loudly instead of silently disabling the SQL checks.

## How the bootstrap works

1. **DB**: MariaDB only runs `/docker-entrypoint-initdb.d/*` on the **first**
   boot of a fresh data volume. Our `00-render-schema.sh` reads
   `web/install/includes/sql/struc.sql` and `data.sql`, replaces `{prefix}`
   with `sb` and `{charset}` with `utf8mb4`, pipes them in, then inserts an
   `admin` row with a pinned bcrypt hash for the password `admin`.
2. **Web**: the entrypoint waits until MariaDB answers, generates
   `web/config.php` from env vars (only if absent or empty), runs
   `composer install` if `vendor/` is missing, then `exec`s Apache.
3. **`HTTP_HOST` shim**: `init.php` blocks the panel when the `install/`
   directory is present unless `HTTP_HOST == "localhost"`. The bind mount
   means we can't delete `install/` from the container, and our forwarded
   port produces `localhost:8080`. `dev-prepend.php` is loaded via
   `auto_prepend_file` and rewrites the host to `localhost` for any loopback
   request, satisfying the guard without weakening it elsewhere.

## Customizing

Drop a `.env` next to `docker-compose.yml` to override published ports — see
`docker/.env.example`. To change DB credentials, edit the `environment:`
blocks in `docker-compose.yml` and `./sbpp.sh reset` to re-seed.

To pre-seed your own data, drop additional `*.sql` or `*.sh` files into
`docker/db-init/`. They'll be picked up on the next fresh init (after
`./sbpp.sh reset`).

To run two stacks side-by-side (e.g. one per git worktree, or one per
parallel agent), drop a worktree-local `docker-compose.override.yml`
that sets a unique top-level `name:`, renames each `container_name`,
and remaps the host ports. The file is auto-loaded by `docker compose`
and gitignored. See [`AGENTS.md` → "Parallel stacks"](../AGENTS.md#parallel-stacks-subagents--multiple-worktrees)
for the canonical template.

## Caveats

- **Dev only.** The `HTTP_HOST` shim, the seeded admin password, and the
  exposed DB port are not safe in production.
- **Composer cache.** First `up` can take a couple of minutes to download
  dependencies. Subsequent boots reuse the `vendor` volume.
- **macOS / Windows file watching.** Bind mounts on non-Linux Docker hosts
  can be slow; OPcache is set to revalidate every request which may amplify
  this. Bump `opcache.revalidate_freq` in `docker/Dockerfile` if it bites.
