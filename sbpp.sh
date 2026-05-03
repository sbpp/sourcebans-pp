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
        dc exec web bash -lc '
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
