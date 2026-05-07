#!/usr/bin/env bash
# Capture a "fresh install of <version> + scale data" snapshot for the
# v2.0.0 upgrade dry-run (issue #1166).
#
# Usage:
#   ./capture.sh 1.7.0       # captures fixtures/upgrade/1.7.0.sql.gz
#   ./capture.sh 1.8.4       # captures fixtures/upgrade/1.8.4.sql.gz
#   ./capture.sh --all       # captures both
#
# What this script does, end to end:
#   1. Downloads the requested version's webpanel-only release artifact
#      from GitHub (cached under fixtures/upgrade/capture/.cache/).
#   2. Extracts struc.sql + data.sql + config.php.template.
#   3. Spins up an ephemeral mariadb:10.11 container on a one-off
#      docker network. The DB is loaded with utf8mb4 end-to-end (the
#      v1.x default of `utf8` was a 3-byte alias and silently mangled
#      4-byte UTF-8; #1108 forced the panel onto utf8mb4 — operators
#      who keep their DB on `utf8` after the upgrade is exactly the
#      scenario #1108 was filed against, so we capture against
#      utf8mb4 and the upgrade dry-run validates the round-trip).
#   4. Loads struc.sql + data.sql verbatim — this is what the version's
#      `install/index.php` wizard would have written if a real operator
#      had walked it; we skip the wizard's HTTP gymnastics in favour of
#      direct SQL loading because (a) it is reproducible and (b) the
#      wizard's only DB-affecting step beyond "load these two files" is
#      the admin-row insert, which we replicate below.
#   5. Runs seed.php (inside an ephemeral php:8.2-cli container on the
#      same docker network) to seed scale data: 200 admins / 5 groups /
#      30 servers / 5000 bans / 500 comms / 50 protests / 80 submissions
#      / 200 comments / 1000 banlog. Real-ish player names include
#      4-byte UTF-8 (CJK / emoji / supplementary plane) per #1108.
#   6. Dumps the schema + data with `mariadb-dump` and gzips it into
#      fixtures/upgrade/<version>.sql.gz.
#   7. Renders the version's config.php.template into
#      fixtures/upgrade/config.<version>.php with dev-placeholder
#      credentials (no real secrets in git).
#   8. Tears down the ephemeral DB + network.
#
# Idempotent: re-running with the same version arg overwrites the prior
# output. The cache directory under .cache/ persists the downloaded
# tarballs so subsequent runs skip the network pull.
#
# Requires: docker, gzip, gunzip, sed, awk, python3 (for unzip on the
# 1.8.4 .zip — the 1.7.0 release ships .tar.gz but 1.8.4 only ships
# webpanel-only.zip).

set -euo pipefail

usage() {
    cat <<'EOF'
Usage: ./capture.sh <version>

  <version>    one of "1.7.0", "1.8.4", "--all"

Examples:
  ./capture.sh 1.7.0
  ./capture.sh 1.8.4
  ./capture.sh --all     # captures both, sequentially

Environment overrides:
  SBPP_CAPTURE_RNG=11660    deterministic RNG seed for the seeder
  SBPP_CAPTURE_NET=...      docker network name (default: sbpp-1166-capture-net)
  SBPP_CAPTURE_DB=...       ephemeral mariadb container name
                            (default: sbpp-1166-capture-mariadb)
EOF
}

if [[ $# -ne 1 ]]; then
    usage
    exit 2
fi

case "$1" in
    -h|--help|help)
        usage
        exit 0
        ;;
esac

# Resolve paths relative to this script so the capture works no matter
# where it is invoked from (worktree root, capture/ dir, or a CI runner).
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &>/dev/null && pwd)"
FIXTURES_DIR="$(cd -- "$SCRIPT_DIR/.." &>/dev/null && pwd)"
CACHE_DIR="$SCRIPT_DIR/.cache"
mkdir -p "$CACHE_DIR"

NET_NAME="${SBPP_CAPTURE_NET:-sbpp-1166-capture-net}"
DB_NAME="${SBPP_CAPTURE_DB:-sbpp-1166-capture-mariadb}"
DB_ROOT_PASS="capture-rootpass"
DB_USER="sourcebans"
DB_USER_PASS="sourcebans"
DB_DBNAME="sourcebans"
DB_PREFIX="sb"
SEED_RNG="${SBPP_CAPTURE_RNG:-11660}"

# ---------------------------------------------------------------------------
# Per-version metadata. Add a row here when capturing against a new 1.x
# patch release; everything else generalises automatically.
#
# Field    | Meaning
# ---------|-------------------------------------------------------------
# url      | Direct download URL of the webpanel-only release artifact.
# archive  | "tar.gz" or "zip" (we handle both because 1.8.4's release
#          | only ships .zip while 1.7.0 ships both).
# top_dir  | The single directory the extracted archive contains.
release_url() {
    case "$1" in
        1.7.0) echo "https://github.com/sbpp/sourcebans-pp/releases/download/1.7.0/sourcebans-pp-1.7.0.webpanel-only.tar.gz" ;;
        1.8.4) echo "https://github.com/sbpp/sourcebans-pp/releases/download/1.8.4/sourcebans-pp-1.8.4.webpanel-only.zip" ;;
        *) return 1 ;;
    esac
}
release_archive_format() {
    case "$1" in
        1.7.0) echo "tar.gz" ;;
        1.8.4) echo "zip" ;;
        *) return 1 ;;
    esac
}
release_top_dir() {
    case "$1" in
        1.7.0) echo "sourcebans-pp-1.7.0.webpanel-only" ;;
        1.8.4) echo "sourcebans-pp-1.8.4.webpanel-only" ;;
        *) return 1 ;;
    esac
}

cleanup_capture_db() {
    docker rm -f "$DB_NAME" >/dev/null 2>&1 || true
    docker network rm "$NET_NAME" >/dev/null 2>&1 || true
}

trap cleanup_capture_db EXIT

ensure_capture_db() {
    cleanup_capture_db
    docker network create "$NET_NAME" >/dev/null
    docker run -d --rm --name "$DB_NAME" --network "$NET_NAME" \
        -e MARIADB_ROOT_PASSWORD="$DB_ROOT_PASS" \
        -e MARIADB_DATABASE="$DB_DBNAME" \
        -e MARIADB_USER="$DB_USER" \
        -e MARIADB_PASSWORD="$DB_USER_PASS" \
        -e MARIADB_CHARSET=utf8mb4 \
        -e MARIADB_COLLATION=utf8mb4_unicode_ci \
        --health-cmd="mariadb-admin ping -uroot -p$DB_ROOT_PASS --silent" \
        --health-interval=2s \
        --health-retries=30 \
        mariadb:10.11 \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci \
        --skip-name-resolve \
        >/dev/null

    echo "==> waiting for $DB_NAME to become healthy"
    for i in $(seq 1 60); do
        status="$(docker inspect -f '{{.State.Health.Status}}' "$DB_NAME" 2>/dev/null || echo "starting")"
        if [[ "$status" == "healthy" ]]; then
            return 0
        fi
        sleep 2
    done
    echo "ERROR: $DB_NAME never became healthy" >&2
    docker logs "$DB_NAME" || true
    exit 1
}

run_mariadb_client() {
    # Reads stdin and pipes it into the mariadb client inside the
    # capture network. Using a one-off container keeps the host
    # mariadb-client requirement out of the picture.
    docker run --rm -i --network "$NET_NAME" \
        mariadb:10.11 \
        mariadb -h "$DB_NAME" -uroot -p"$DB_ROOT_PASS" "$DB_DBNAME"
}

run_mariadb_dump() {
    docker run --rm --network "$NET_NAME" \
        mariadb:10.11 \
        mariadb-dump -h "$DB_NAME" -uroot -p"$DB_ROOT_PASS" \
            --default-character-set=utf8mb4 \
            --skip-comments --no-tablespaces --skip-tz-utc \
            --add-drop-table --single-transaction --quick \
            "$DB_DBNAME"
}

extract_archive() {
    local version="$1"
    local archive_path="$2"
    local dest="$3"
    local fmt
    fmt="$(release_archive_format "$version")"
    rm -rf "$dest"
    mkdir -p "$dest"
    case "$fmt" in
        tar.gz)
            tar -xzf "$archive_path" -C "$dest"
            ;;
        zip)
            python3 -c "import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])" \
                "$archive_path" "$dest"
            ;;
        *) echo "unknown archive format: $fmt" >&2; return 1 ;;
    esac
}

capture_one() {
    local version="$1"
    echo
    echo "==============================================================="
    echo "Capturing fixture for SourceBans++ $version"
    echo "==============================================================="

    local url archive_path extract_dir top_dir struc_sql data_sql cfg_template
    url="$(release_url "$version")"
    local fmt
    fmt="$(release_archive_format "$version")"
    archive_path="$CACHE_DIR/sbpp-${version}.${fmt}"
    extract_dir="$CACHE_DIR/sbpp-${version}-extracted"
    top_dir="$(release_top_dir "$version")"

    if [[ ! -f "$archive_path" ]]; then
        echo "==> downloading $url"
        curl -fsSL --retry 3 -o "$archive_path" "$url"
    else
        echo "==> using cached $archive_path"
    fi

    extract_archive "$version" "$archive_path" "$extract_dir"
    struc_sql="$extract_dir/$top_dir/install/includes/sql/struc.sql"
    data_sql="$extract_dir/$top_dir/install/includes/sql/data.sql"
    cfg_template="$extract_dir/$top_dir/config.php.template"

    if [[ ! -f "$struc_sql" ]]; then
        echo "ERROR: $struc_sql not present in extracted archive" >&2
        exit 1
    fi
    if [[ ! -f "$data_sql" ]]; then
        echo "ERROR: $data_sql not present in extracted archive" >&2
        exit 1
    fi
    if [[ ! -f "$cfg_template" ]]; then
        echo "ERROR: $cfg_template not present in extracted archive" >&2
        exit 1
    fi

    ensure_capture_db

    # Render schema. {prefix} -> sb, {charset} -> utf8mb4. The data.sql
    # in the v1.x line still has {prefix} only; struc.sql carries both.
    echo "==> loading struc.sql + data.sql (rendered: prefix=$DB_PREFIX, charset=utf8mb4)"
    sed -e "s/{prefix}/$DB_PREFIX/g" -e "s/{charset}/utf8mb4/g" "$struc_sql" \
        | run_mariadb_client
    sed -e "s/{prefix}/$DB_PREFIX/g" -e "s/{charset}/utf8mb4/g" "$data_sql" \
        | run_mariadb_client

    # Sanity check — was the prefix actually applied? `sb_settings`
    # should now hold the seed rows.
    local row_count
    local count_sql="SELECT COUNT(*) FROM \`${DB_PREFIX}_settings\`;"
    row_count="$(printf '%s\n' "$count_sql" \
        | docker run --rm -i --network "$NET_NAME" mariadb:10.11 \
            mariadb -h "$DB_NAME" -uroot -p"$DB_ROOT_PASS" -N -B "$DB_DBNAME" 2>/dev/null \
        | tr -d '[:space:]' || true)"
    if [[ -z "$row_count" || "$row_count" -lt 30 ]]; then
        echo "ERROR: data.sql apparently failed to load -- :prefix_settings has '$row_count' rows" >&2
        exit 1
    fi
    echo "    sb_settings rows: $row_count"

    echo "==> seeding scale data (deterministic, RNG=$SEED_RNG)"
    # The official php:8.2-cli image ships without pdo_mysql; build it
    # in-place. Doing it as part of the seed run keeps the script
    # self-contained — a future maintainer doesn't need a sidecar
    # Dockerfile checked in next to capture.sh.
    docker run --rm --network "$NET_NAME" \
        -v "$SCRIPT_DIR/seed.php:/seed.php:ro" \
        -e DB_HOST="$DB_NAME" \
        -e DB_PORT=3306 \
        -e DB_NAME="$DB_DBNAME" \
        -e DB_USER="root" \
        -e DB_PASS="$DB_ROOT_PASS" \
        -e DB_PREFIX="$DB_PREFIX" \
        -e SEED_VERSION="$version" \
        -e SEED_RNG="$SEED_RNG" \
        php:8.2-cli \
        bash -c '
            set -e
            docker-php-ext-install pdo_mysql >/dev/null
            exec php /seed.php
        '

    local out="$FIXTURES_DIR/${version}.sql.gz"
    echo "==> dumping to $out"
    run_mariadb_dump | gzip -9n > "$out"
    echo "    wrote $(wc -c < "$out") bytes"

    # Render the matching config.php with redacted dev placeholders.
    #
    # NOTE: config.php.template in the 1.7.0 / 1.8.4 release tarballs
    # is NOT the wizard's brace-substitution template (that lives in
    # install/template/page.5.php and is rendered at install-time into
    # ../config.php). config.php.template is just the literal default
    # config a fresh extract ships with; the wizard rewrites it. We
    # take the same starting point and patch the lines that an operator
    # would set during install, so the captured file is a faithful
    # representation of "an operator on $version installed in our dev
    # docker stack". DB credentials match the dev stack (sourcebans/
    # sourcebans on 'db:3306'); the secret slots are intentionally
    # placeholdered so the snapshot can be committed to git without
    # leaking anything dev. SB_SECRET_KEY is set to a stable
    # placeholder rather than the empty string the template ships
    # with — an empty SB_SECRET_KEY would short-circuit web/upgrade.php
    # via `if (defined('SB_SECRET_KEY')) exit('Upgrade not needed.');`
    # and fail JWT signing on first request. Operators driving the
    # dry-run who want the "config has no SB_SECRET_KEY at all" path
    # can `sed -i '/SB_SECRET_KEY/d'` after copying.
    local cfg_out="$FIXTURES_DIR/config.${version}.php"
    echo "==> writing $cfg_out"
    sed -E \
        -e "s|define\\('DB_HOST', '[^']*'\\)|define('DB_HOST', 'db')|" \
        -e "s|define\\('DB_USER', '[^']*'\\)|define('DB_USER', 'sourcebans')|" \
        -e "s|define\\('DB_PASS', '[^']*'\\)|define('DB_PASS', 'sourcebans')|" \
        -e "s|define\\('DB_NAME', '[^']*'\\)|define('DB_NAME', 'sourcebans')|" \
        -e "s|define\\('DB_PREFIX', '[^']*'\\)|define('DB_PREFIX', 'sb')|" \
        -e "s|define\\('DB_PORT', '[^']*'\\)|define('DB_PORT', '3306')|" \
        -e "s|define\\('DB_CHARSET', '[^']*'\\)|define('DB_CHARSET', 'utf8mb4')|" \
        -e "s|define\\('STEAMAPIKEY', '[^']*'\\)|define('STEAMAPIKEY', 'REPLACE_WITH_REAL_STEAM_API_KEY')|" \
        -e "s|define\\('SB_EMAIL', '[^']*'\\)|define('SB_EMAIL', 'admin@example.test')|" \
        -e "s|define\\('SB_SECRET_KEY', '[^']*'\\)|define('SB_SECRET_KEY', 'REPLACE_WITH_BASE64_47_BYTES_PLACEHOLDER_DO_NOT_USE_IN_PROD')|" \
        "$cfg_template" > "$cfg_out"

    echo "==> $version capture complete"
}

case "$1" in
    1.7.0|1.8.4)
        capture_one "$1"
        ;;
    --all)
        capture_one "1.7.0"
        capture_one "1.8.4"
        ;;
    *)
        echo "unknown version: $1" >&2
        usage
        exit 2
        ;;
esac

echo
echo "Done. Snapshots and matching config.php files are in:"
ls -la "$FIXTURES_DIR"/*.sql.gz "$FIXTURES_DIR"/config.*.php 2>/dev/null || true
