#!/usr/bin/env bash
# Convenience wrapper around docker compose for the SourceBans++ dev stack.
# Run from the repo root. Pass -h to see commands.

set -euo pipefail

cd "$(dirname "$0")"

usage() {
    cat <<'EOF'
Usage: ./sbpp.sh <command> [args...]

Lifecycle:
  up              Build (if needed) and start the stack in the background.
  down            Stop and remove containers (volumes preserved).
  reset           Tear everything down AND drop all volumes (DB + vendor + cache).
  rebuild         Rebuild the web image from scratch and restart.
  status          Show service status.
  logs [svc]      Tail logs. Optional service name (web|db|adminer|mailpit).

Run things inside containers:
  shell [svc]     Open a shell. Default svc=web (root). svc=db opens mysql client.
  composer ...    Run composer inside the web container.
  phpstan         Run phpstan from web/phpstan.neon inside the web container.
  test [args...]  Run PHPUnit (web/phpunit.xml) inside the web container.
  ts-check        Run tsc --checkJs over web/scripts (npm-installs typescript on demand).
  e2e [args...]   Run the Playwright E2E suite inside the web container.
                  Lazily npm-installs @playwright/test + chromium on first run;
                  forwards args to `npx playwright test` (e.g. `--grep @screenshot`).
  upgrade-e2e [args...]
                  Run the v1.x → v2.0 upgrade harness (web/tests/e2e/specs/upgrade/).
                  Stages fixtures/upgrade/<version>.sql.gz into the web container,
                  drives the upgrade flow against throwaway sourcebans_upgrade_*
                  DBs, asserts schema/settings parity + idempotency. Forwards
                  args to playwright; defaults to `--grep @upgrade`.
  exec <cmd...>   Run an arbitrary command in the web container.
  mysql           Open a mysql client connected to the dev DB.

Database:
  db-dump [file]  Dump the DB to file (default: dump-YYYYMMDD-HHMMSS.sql).
  db-load <file>  Load a SQL dump into the dev DB.
  db-reset        Drop the DB volume and re-seed (faster than full reset).
  db-seed [args]  Populate the dev DB with realistic synthetic data.
                  Accepts --scale=small|medium|large (default medium) and
                  --seed=<int> (default pinned in code). Idempotent: every
                  run truncates synth-owned tables first. Refuses to touch
                  any DB other than `sourcebans`.

URLs once "up":
  http://localhost:${SBPP_WEB_PORT:-8080}        SourceBans++ panel  (admin / admin)
  http://localhost:${SBPP_ADMINER_PORT:-8081}    Adminer
  http://localhost:${SBPP_MAILPIT_UI_PORT:-8025} Mailpit (captured email)
EOF
}

dc() { docker compose "$@"; }

cmd="${1:-help}"
shift || true

case "$cmd" in
    up)
        dc up -d --build
        echo
        echo "panel:    http://localhost:${SBPP_WEB_PORT:-8080}  (admin / admin)"
        echo "adminer:  http://localhost:${SBPP_ADMINER_PORT:-8081}"
        echo "mailpit:  http://localhost:${SBPP_MAILPIT_UI_PORT:-8025}"
        ;;
    down)
        dc down
        ;;
    reset)
        dc down -v
        ;;
    rebuild)
        dc build --no-cache web
        dc up -d
        ;;
    status)
        dc ps
        ;;
    logs)
        dc logs -f --tail=200 "$@"
        ;;
    shell)
        svc="${1:-web}"
        case "$svc" in
            db) dc exec db mariadb -usourcebans -psourcebans sourcebans ;;
            *)  dc exec "$svc" bash ;;
        esac
        ;;
    composer)
        dc exec web composer "$@"
        ;;
    phpstan)
        # The project baseline assumes `web/config.php` is absent (CI's state).
        # Our entrypoint generated one, which makes a couple of "file not
        # found" warnings disappear and triggers PHPStan's
        # `reportUnmatchedIgnoredErrors`. Stash config.php for the duration of
        # the analysis so locally we get the same result CI does.
        #
        # phpstan-dba (#1100) needs a live MariaDB to introspect schema; the
        # dev `db` service is reachable from inside the web container as
        # `db:3306`. Set PHPSTAN_DBA_DISABLE=1 to bypass when working
        # offline — the bootstrap also degrades gracefully if the connection
        # fails for any other reason.
        dc exec \
            -e DBA_HOST=db -e DBA_PORT=3306 \
            -e DBA_NAME=sourcebans \
            -e DBA_USER=sourcebans -e DBA_PASS=sourcebans \
            -e DBA_PREFIX=sb -e DBA_CHARSET=utf8mb4 \
            -e PHPSTAN_DBA_DISABLE="${PHPSTAN_DBA_DISABLE:-}" \
            web bash -lc '
            cd /var/www/html/web
            cleanup() { [ -f config.php.devstash ] && mv config.php.devstash config.php; }
            trap cleanup EXIT
            [ -f config.php ] && mv config.php config.php.devstash
            includes/vendor/bin/phpstan analyse "$@"
        ' -- "$@"
        ;;
    test)
        # Behavioral gate added in #1081 alongside the xajax→JSON migration.
        # Each test runs against a dedicated `sourcebans_test` database so it
        # never stomps the dev data in `sourcebans`.
        dc exec \
            -e DB_HOST=db -e DB_PORT=3306 \
            -e DB_NAME=sourcebans_test \
            -e DB_USER=sourcebans -e DB_PASS=sourcebans \
            -e DB_PREFIX=sb -e DB_CHARSET=utf8mb4 \
            web includes/vendor/bin/phpunit -c /var/www/html/web/phpunit.xml --testdox "$@"
        ;;
    ts-check)
        # JS type-checking gate added in #1098. We `npm install` lazily on
        # first run so a fresh dev container doesn't pay the cost up front;
        # the install is a no-op once node_modules/ is populated thanks to
        # `--prefer-offline`.
        dc exec web bash -lc 'cd /var/www/html/web && npm install --silent --no-audit --no-fund --prefer-offline && npm run --silent ts-check'
        ;;
    e2e)
        # Playwright E2E gate added in #1124. Mirrors `ts-check`'s
        # lazy-install pattern: first run apt-installs chromium's
        # system deps via `playwright install --with-deps chromium`,
        # subsequent runs are sub-second to start.
        #
        # We auto-bring-up the dev stack if it's not already running
        # so `./sbpp.sh e2e` works from a fresh checkout. The suite
        # then runs INSIDE the web container and hits Apache on the
        # in-container port 80 (E2E_BASE_URL=http://localhost). The
        # DB seeder (web/tests/e2e/fixtures/db.ts) flips
        # E2E_IN_CONTAINER=1 so it calls `php` directly instead of
        # `docker compose exec` (we're already inside the container,
        # the Docker socket isn't reachable from here).
        if [ -z "$(dc ps -q web 2>/dev/null)" ]; then dc up -d; fi
        # Idempotent grant: ensures `sourcebans_e2e` is writable by
        # the panel user even on dev stacks whose db-init/ ran before
        # this script started provisioning the e2e DB. Fresh stacks
        # already get the grant from docker/db-init/00-render-schema.sh.
        dc exec -T db mariadb -uroot -proot -e "
            GRANT ALL PRIVILEGES ON \`sourcebans_e2e\`.* TO 'sourcebans'@'%';
            GRANT CREATE, DROP ON *.* TO 'sourcebans'@'%';
            FLUSH PRIVILEGES;" >/dev/null
        # Pin the panel at the e2e DB for the duration of the run, then
        # restore on exit. docker/php/web-entrypoint.sh renders config.php
        # once at container start with `define('DB_NAME', 'sourcebans')`
        # — the dev DB. Apache+mod_php holds that constant for the life
        # of the container, so the `-e DB_NAME=sourcebans_e2e` below only
        # affects the bash shell that drives `npx playwright test` (and
        # the truncate shim it shells into). Without this swap the panel
        # the test browser hits writes to `sourcebans` while the fixture
        # truncates `sourcebans_e2e`, and any spec that mutates DB state
        # is non-hermetic across runs (#1124 Slice 3 was the first slice
        # to exercise the write path; Slice 0's smoke specs only login,
        # which masked the gap). Apache re-reads config.php on every
        # request so an in-place sed takes effect immediately with no
        # restart; the trap restores the dev-DB value so the developer's
        # browser session is unaffected once the suite finishes.
        e2e_db="${E2E_DB_NAME:-sourcebans_e2e}"
        dc exec -T web bash -c "
            set -e
            cfg=/var/www/html/web/config.php
            stash=/var/www/html/web/config.php.e2e-stash
            [ ! -f \$stash ] && cp \$cfg \$stash
            sed -i \"s/define('DB_NAME', '[^']*');/define('DB_NAME', '${e2e_db}');/\" \$cfg
        "
        restore_panel_db() {
            dc exec -T web bash -c '
                cfg=/var/www/html/web/config.php
                stash=/var/www/html/web/config.php.e2e-stash
                if [ -f $stash ]; then mv $stash $cfg; fi
            ' 2>/dev/null || true
        }
        trap restore_panel_db EXIT INT TERM
        dc exec -T \
            -e E2E_BASE_URL="${E2E_BASE_URL:-http://localhost}" \
            -e E2E_IN_CONTAINER=1 \
            -e SCREENSHOTS="${SCREENSHOTS:-}" \
            -e CI="${CI:-}" \
            -e DB_HOST=db -e DB_PORT=3306 \
            -e DB_NAME="${e2e_db}" \
            -e DB_USER=sourcebans -e DB_PASS=sourcebans \
            -e DB_PREFIX=sb -e DB_CHARSET=utf8mb4 \
            web bash -lc '
                set -e
                cd /var/www/html/web/tests/e2e
                if [ ! -d node_modules/@playwright/test ]; then
                    npm install --silent --no-audit --no-fund --prefer-offline
                fi
                # Browser install is also lazy. The marker file is
                # what `playwright install` drops once it has finished
                # provisioning chromium + the Debian deps under
                # ~/.cache/ms-playwright. If the user nukes the cache,
                # the next run reinstalls.
                if [ ! -d "$HOME/.cache/ms-playwright" ] || [ -z "$(ls "$HOME/.cache/ms-playwright" 2>/dev/null)" ]; then
                    npx playwright install --with-deps chromium
                fi
                exec npx playwright test "$@"
            ' -- "$@"
        ;;
    upgrade-e2e)
        # v1.x -> v2.0 upgrade harness (#1269). Mirrors `e2e`'s
        # auto-bring-up + lazy npm/chromium install, but:
        #
        #   - Stages `fixtures/upgrade/<v>.sql.gz` + `config.<v>.php`
        #     into the web container's `/tmp/` so the in-container
        #     `upgrade-db.php` helper can read them. The fixtures live
        #     OUTSIDE `web/` (per #1268) so they don't ship in the
        #     release tarball; the bind mount can't see them.
        #   - Grants the panel user CREATE/DROP on `*.*` so the helper
        #     can drop+recreate the throwaway `sourcebans_upgrade_*`
        #     schemas. The same pattern the regular `e2e` command uses
        #     for `sourcebans_e2e`.
        #   - Targets only the upgrade specs by appending
        #     `--grep '@upgrade'` to playwright args (or honouring the
        #     caller's filter if they passed one).
        #   - Stash + restore config.php exactly like `e2e` does, so
        #     the dev panel keeps pointing at `sourcebans` after the
        #     run — even if the spec aborts mid-flight.
        if [ -z "$(dc ps -q web 2>/dev/null)" ]; then dc up -d; fi
        dc exec -T db mariadb -uroot -proot -e "
            GRANT ALL PRIVILEGES ON \`sourcebans_upgrade_170\`.* TO 'sourcebans'@'%';
            GRANT CREATE, DROP ON *.* TO 'sourcebans'@'%';
            FLUSH PRIVILEGES;" >/dev/null
        # Stage the 1.7.0 fixture into the container. Idempotent — the
        # spec re-copies via `copyFixture.ts` if a future per-spec
        # destination is needed. Doing it once here keeps the host->
        # container hop out of the test's hot path.
        for v in 1.7.0; do
            dc cp "fixtures/upgrade/${v}.sql.gz"     "web:/tmp/sbpp-upgrade-${v}.sql.gz"
            dc cp "fixtures/upgrade/config.${v}.php" "web:/tmp/sbpp-upgrade-config-${v}.php"
        done
        # Snapshot config.php BEFORE we mutate it, then sed the
        # panel's DB_NAME to `sourcebans_e2e` so playwright's
        # `globalSetup` (which mints storage state by logging in)
        # talks to the same DB the e2e fixture seeds. The upgrade
        # spec opts out of that storage state (`test.use({
        # storageState: { cookies: [], origins: [] } })`) and rewrites
        # config.php again in its own `beforeAll` to point at the
        # throwaway `sourcebans_upgrade_*` schema. The trap below
        # puts the original `sourcebans` config.php back on exit so
        # the dev panel keeps working for browser-side dev sessions.
        upgrade_e2e_db="${UPGRADE_E2E_DB_NAME:-sourcebans_e2e}"
        dc exec -T web bash -c "
            set -e
            cfg=/var/www/html/web/config.php
            stash=/var/www/html/web/config.php.upgrade-e2e-stash
            [ ! -f \$stash ] && cp \$cfg \$stash
            sed -i \"s/define('DB_NAME', '[^']*');/define('DB_NAME', '${upgrade_e2e_db}');/\" \$cfg
            # /updater/index.php wipes web/cache/ contents. If a previous
            # aborted run left the dir at root:root 0755 (init.php's
            # is_writable(SB_CACHE) check then fails and the panel
            # 500s with 'Theme Error: cache MUST be writable' before
            # globalSetup can find the login form), nudge it back to
            # the entrypoint's intended state. Idempotent — a healthy
            # cache dir already matches.
            mkdir -p /var/www/html/web/cache /var/www/html/web/templates_c
            chown -R www-data:www-data /var/www/html/web/cache /var/www/html/web/templates_c 2>/dev/null || true
            chmod -R u+rwX,g+rwX,o+rwX /var/www/html/web/cache /var/www/html/web/templates_c 2>/dev/null || true
        "
        restore_panel_db() {
            dc exec -T web bash -c '
                cfg=/var/www/html/web/config.php
                stash=/var/www/html/web/config.php.upgrade-e2e-stash
                if [ -f $stash ]; then mv $stash $cfg; fi
                # Also nuke the spec-level stash if a crash left it.
                rm -f /var/www/html/web/config.php.upgrade-stash
            ' 2>/dev/null || true
        }
        trap restore_panel_db EXIT INT TERM
        # Honour an explicit --grep from the caller if present;
        # otherwise default to `--grep @upgrade` so the wrapper
        # doesn't drag in the regular suite. Pin to the desktop
        # chromium project so the spec doesn't re-run on
        # mobile-chromium — the upgrade harness mutates config.php
        # serially and two parallel browsers (one per project)
        # would race on the same panel state. The harness isn't a
        # UI/responsive test; one project is sufficient.
        upgrade_args=("$@")
        has_grep=0
        has_project=0
        for a in "$@"; do
            case "$a" in
                --grep|--grep=*) has_grep=1 ;;
                --project|--project=*) has_project=1 ;;
            esac
        done
        if [ "$has_grep" -eq 0 ]; then
            upgrade_args=("--grep" "@upgrade" "${upgrade_args[@]}")
        fi
        if [ "$has_project" -eq 0 ]; then
            upgrade_args=("--project=chromium" "${upgrade_args[@]}")
        fi
        dc exec -T \
            -e E2E_BASE_URL="${E2E_BASE_URL:-http://localhost}" \
            -e E2E_IN_CONTAINER=1 \
            -e CI="${CI:-}" \
            -e DB_HOST=db -e DB_PORT=3306 \
            -e DB_NAME="${upgrade_e2e_db}" \
            -e DB_USER=sourcebans -e DB_PASS=sourcebans \
            -e DB_PREFIX=sb -e DB_CHARSET=utf8mb4 \
            web bash -lc '
                set -e
                cd /var/www/html/web/tests/e2e
                if [ ! -d node_modules/@playwright/test ]; then
                    npm install --silent --no-audit --no-fund --prefer-offline
                fi
                if [ ! -d "$HOME/.cache/ms-playwright" ] || [ -z "$(ls "$HOME/.cache/ms-playwright" 2>/dev/null)" ]; then
                    npx playwright install --with-deps chromium
                fi
                exec npx playwright test "$@"
            ' -- "${upgrade_args[@]}"
        ;;
    exec)
        dc exec web "$@"
        ;;
    mysql)
        dc exec db mariadb -usourcebans -psourcebans sourcebans
        ;;
    db-dump)
        out="${1:-dump-$(date +%Y%m%d-%H%M%S).sql}"
        dc exec -T db mariadb-dump -uroot -proot sourcebans >"$out"
        echo "wrote $out"
        ;;
    db-load)
        [[ $# -eq 1 ]] || { echo "usage: ./sbpp.sh db-load <file>"; exit 2; }
        dc exec -T db mariadb -uroot -proot sourcebans <"$1"
        ;;
    db-reset)
        dc rm -fsv db
        docker volume rm "$(basename "$PWD")_dbdata" 2>/dev/null || true
        dc up -d db
        ;;
    db-seed)
        # Dev-only synthetic data populator (#1238). Mirrors `e2e`'s
        # auto-bring-up shim so `./sbpp.sh db-seed` works from a fresh
        # checkout. The CLI driver lives at
        # `web/tests/scripts/seed-dev-db.php`; the synthesizer is
        # `Sbpp\Tests\Synthesizer`. Always truncate+reseed by design;
        # `--scale=small|medium|large` and `--seed=<int>` are the only
        # accepted flags. DB_NAME is pinned to `sourcebans` (the dev
        # panel's DB) — the driver refuses any other value, including
        # `sourcebans_test` / `sourcebans_e2e`.
        if [ -z "$(dc ps -q web 2>/dev/null)" ]; then dc up -d; fi
        dc exec \
            -e DB_HOST=db -e DB_PORT=3306 \
            -e DB_NAME=sourcebans \
            -e DB_USER=sourcebans -e DB_PASS=sourcebans \
            -e DB_PREFIX=sb -e DB_CHARSET=utf8mb4 \
            web php /var/www/html/web/tests/scripts/seed-dev-db.php "$@"
        ;;
    -h|--help|help|"")
        usage
        ;;
    *)
        echo "unknown command: $cmd" >&2
        usage
        exit 2
        ;;
esac
