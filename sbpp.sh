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
  exec <cmd...>   Run an arbitrary command in the web container.
  mysql           Open a mysql client connected to the dev DB.

Database:
  db-dump [file]  Dump the DB to file (default: dump-YYYYMMDD-HHMMSS.sql).
  db-load <file>  Load a SQL dump into the dev DB.
  db-reset        Drop the DB volume and re-seed (faster than full reset).

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
        dc exec -T \
            -e E2E_BASE_URL="${E2E_BASE_URL:-http://localhost}" \
            -e E2E_IN_CONTAINER=1 \
            -e SCREENSHOTS="${SCREENSHOTS:-}" \
            -e CI="${CI:-}" \
            -e DB_HOST=db -e DB_PORT=3306 \
            -e DB_NAME="${E2E_DB_NAME:-sourcebans_e2e}" \
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
    -h|--help|help|"")
        usage
        ;;
    *)
        echo "unknown command: $cmd" >&2
        usage
        exit 2
        ;;
esac
