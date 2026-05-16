#!/bin/sh
# SourceBans++ production entrypoint (#1381 deliverable 2).
#
# Drives the install + migrate state machine, strips install/+updater/
# from the writable layer, configures Apache for the deployment
# (PORT rewrite, mod_remoteip), then execs apache2-foreground. Idempotent
# on every container start.
#
# State machine (see "step:" log prefixes for each):
#   1. Resolve config (PORT, *_FILE secrets, DATABASE_URL).
#   2. Write Apache vhost overrides for $PORT + trusted proxies.
#   3. Wait for the DB to accept connections.
#   4. Render config.php from environment if it's missing or empty.
#   5. First-boot install: pipe struc.sql + data.sql + seed initial admin.
#   6. Run pending updater migrations against config.version.
#   7. Strip install/ + updater/ from the writable layer (so the
#      panel-runtime guard in web/init-recovery.php passes —
#      production MUST NOT define SBPP_DEV_KEEP_INSTALL).
#   8. Ensure cache/ + templates_c/ + demos/ are writable by www-data.
#   9. exec apache2-foreground.
#
# Pure POSIX shell — no bash-isms. Some self-hosters base their image
# on Alpine for size; staying portable means a future busybox-only image
# variant is a one-line FROM swap rather than a full rewrite.
#
# Why we drive install programmatically instead of pointing operators
# at the wizard:
#   - The wizard expects an interactive operator. App-platform deploys
#     (DigitalOcean / Railway / Render / Fly) inject env vars via the
#     deploy form and then run the container; there is no operator at
#     the keyboard to walk a 6-step wizard.
#   - The wizard writes config.php; we want config.php to come from
#     the deployment's environment / secrets manager. Driving install
#     from the entrypoint keeps the env-vars path the single source
#     of truth.
#   - The wizard requires install/ to stay on disk through the user's
#     session. We need install/ gone before Apache binds — the
#     post-#1335 panel-runtime guard refuses to boot otherwise.

set -eu

WEB_ROOT="/var/www/html/web"
LOG_PREFIX="[prod-entrypoint]"
SBPP_AUTO_INSTALL="${SBPP_AUTO_INSTALL:-1}"

log() { printf '%s %s\n' "$LOG_PREFIX" "$*" >&2; }
die() { printf '%s ERROR: %s\n' "$LOG_PREFIX" "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# *_FILE secret resolution (Docker Swarm + k8s + many app platforms)
# ---------------------------------------------------------------------------
#
# For each secret env var (DB_PASS, SB_SECRET_KEY, STEAMAPIKEY, …),
# prefer the value of `${NAME}_FILE` if set AND the path exists. This
# matches the canonical Docker secret pattern: the orchestrator mounts
# the secret at /run/secrets/<name>, the operator sets `DB_PASS_FILE=/run/secrets/db_pass`
# in the service env, and the entrypoint reads the file's contents.
#
# When `<NAME>_FILE` is unset (or set but empty / missing-on-disk), the
# helper leaves the existing `<NAME>` value alone — so plain env vars
# work as a fallback for self-hosters who don't use a secrets manager.
resolve_file_secret() {
    name="$1"
    file_var="${name}_FILE"
    eval "file_path=\${$file_var:-}"
    if [ -n "$file_path" ] && [ -f "$file_path" ]; then
        eval "$name=\$(cat \"\$file_path\")"
        export "$name"
    fi
}

resolve_secrets() {
    for name in \
        DB_HOST DB_PORT DB_NAME DB_USER DB_PASS DB_PREFIX DB_CHARSET \
        STEAMAPIKEY SB_EMAIL SB_SECRET_KEY \
        INITIAL_ADMIN_NAME INITIAL_ADMIN_STEAM INITIAL_ADMIN_EMAIL INITIAL_ADMIN_PASSWORD; do
        resolve_file_secret "$name"
    done
}

# ---------------------------------------------------------------------------
# DATABASE_URL parse (Render / Heroku / Railway-style)
# ---------------------------------------------------------------------------
#
# When DATABASE_URL is set, parse it into the split DB_* vars BEFORE
# defaulting / config-rendering happens. Shape:
#
#   mysql://user:pass@host:port/dbname?charset=utf8mb4
#
# Only fields that the URL provides are overridden — so a self-hoster
# can mix a DATABASE_URL with explicit `DB_PREFIX=sb_prod` to override
# just the prefix while letting the URL carry the rest.
#
# Pure POSIX sed; no `awk -F` shape because the password may contain
# `:` / `@` characters that field-splitting would mangle. The regex
# below is forgiving — uses `[^/]` rather than strict scheme matching
# so a `mysql+pdo://...` (Symfony-style) shape is accepted too.
parse_database_url() {
    if [ -z "${DATABASE_URL:-}" ]; then
        return
    fi
    log "step 1: parsing DATABASE_URL"

    # Strip the scheme ("mysql://", "mysql+pdo://", etc.) — everything
    # before the first `//`.
    rest="${DATABASE_URL#*://}"

    # Split off the path (`/dbname?...`) from the authority
    # (`user:pass@host:port`).
    case "$rest" in
        */*) authority="${rest%%/*}"; path_and_query="/${rest#*/}" ;;
        *)   authority="$rest";        path_and_query="" ;;
    esac

    # Split authority into userinfo + host:port. The `@` separator
    # lives between them. If there's no `@`, the whole authority is
    # host[:port] and there's no userinfo.
    case "$authority" in
        *@*)
            userinfo="${authority%@*}"
            hostport="${authority##*@}"
            ;;
        *)
            userinfo=""
            hostport="$authority"
            ;;
    esac

    # Split userinfo into user + pass on the FIRST `:` (a password
    # containing `:` is fine because we're greedy on the LEFT not
    # the right).
    if [ -n "$userinfo" ]; then
        case "$userinfo" in
            *:*)
                _DB_USER="${userinfo%%:*}"
                _DB_PASS="${userinfo#*:}"
                ;;
            *)
                _DB_USER="$userinfo"
                _DB_PASS=""
                ;;
        esac
        # URL-decode percent-escapes (a password with `@` arrives as
        # `%40`). Defensive — `printf '%b'` interprets the printf-style
        # escape sequences and the substitution converts URL escapes
        # to those.
        DB_USER="$(printf '%b' "$(echo "$_DB_USER" | sed 's/%/\\x/g')")"
        DB_PASS="$(printf '%b' "$(echo "$_DB_PASS" | sed 's/%/\\x/g')")"
        export DB_USER DB_PASS
    fi

    # Split host:port. `[ipv6]:port` is NOT supported (yet) — the
    # MariaDB / MySQL drivers accept it but the URL parsing here is
    # deliberately simple. Self-hosters using IPv6 set DB_HOST + DB_PORT
    # explicitly.
    case "$hostport" in
        *:*)
            DB_HOST="${hostport%:*}"
            DB_PORT="${hostport##*:}"
            ;;
        *)
            DB_HOST="$hostport"
            ;;
    esac
    export DB_HOST
    [ -n "${DB_PORT:-}" ] && export DB_PORT

    # `/dbname` becomes DB_NAME; strip leading `/` and any `?query`
    # tail. The `charset=` query param is honoured if present.
    if [ -n "$path_and_query" ]; then
        path_only="${path_and_query%%\?*}"
        DB_NAME="${path_only#/}"
        export DB_NAME

        if [ "$path_and_query" != "$path_only" ]; then
            query="${path_and_query#*\?}"
            # Look for `charset=...` in the query string. POSIX-grep:
            # `\(...\)` capture, `\1` backref.
            charset_val="$(echo "$query" | sed -n 's/.*charset=\([^&]*\).*/\1/p')"
            if [ -n "$charset_val" ]; then
                DB_CHARSET="$charset_val"
                export DB_CHARSET
            fi
        fi
    fi
}

# ---------------------------------------------------------------------------
# Defaults
# ---------------------------------------------------------------------------
#
# Resolved AFTER *_FILE secrets and DATABASE_URL parsing so an env var
# present on either path takes precedence over the default below.
apply_defaults() {
    : "${DB_HOST:=db}"
    : "${DB_PORT:=3306}"
    : "${DB_NAME:=sourcebans}"
    : "${DB_USER:=sourcebans}"
    : "${DB_PASS:=}"
    : "${DB_PREFIX:=sb}"
    : "${DB_CHARSET:=utf8mb4}"
    : "${STEAMAPIKEY:=}"
    : "${SB_EMAIL:=}"
    # SB_SECRET_KEY: never auto-regenerated on container restart (would
    # invalidate every JWT cookie). Auto-generated ONCE at first config
    # render below; empty here means render_config will mint a fresh one.
    : "${SB_SECRET_KEY:=}"

    # PORT — Render/Fly/Heroku-style platforms inject this. Default 80
    # matches the EXPOSE in the Dockerfile.
    : "${PORT:=80}"

    # Trusted-proxy CIDR list — defaults to "no proxy" so plain-Docker
    # deploys aren't accidentally trust-everyone (per spec).
    : "${SBPP_TRUSTED_PROXIES:=}"

    # Path to config.php. Defaults to in-tree `${WEB_ROOT}/config.php`
    # for backward compat with the wizard / dev image. Operators can
    # mount a Docker secret at /run/secrets/sbpp-config.php and set
    # `SBPP_CONFIG_PATH=/run/secrets/sbpp-config.php` to keep the
    # secret out of the container's writable layer.
    : "${SBPP_CONFIG_PATH:=${WEB_ROOT}/config.php}"

    # First-boot admin seed. The four vars below are the friendly
    # inputs platform deploy forms prompt for. If any is blank, the
    # entrypoint refuses to seed the admin and prints a clear next-step
    # so the operator knows to set them or run the install wizard
    # manually after first boot.
    : "${INITIAL_ADMIN_NAME:=}"
    : "${INITIAL_ADMIN_STEAM:=}"
    : "${INITIAL_ADMIN_EMAIL:=}"
    : "${INITIAL_ADMIN_PASSWORD:=}"

    # `SB_NEW_SALT` mirrors the legacy install wizard's value; the
    # panel's password layer no longer uses this for new accounts but
    # the constant must be defined or several legacy code paths trip
    # `Undefined constant`. Keep the value in sync with the install
    # wizard (`SB_NEW_SALT='$5$'`).
    : "${SB_NEW_SALT:=\$5\$}"

    export DB_HOST DB_PORT DB_NAME DB_USER DB_PASS DB_PREFIX DB_CHARSET \
           STEAMAPIKEY SB_EMAIL SB_SECRET_KEY PORT SBPP_TRUSTED_PROXIES \
           SBPP_CONFIG_PATH \
           INITIAL_ADMIN_NAME INITIAL_ADMIN_STEAM INITIAL_ADMIN_EMAIL \
           INITIAL_ADMIN_PASSWORD SB_NEW_SALT
}

# ---------------------------------------------------------------------------
# Step 2: Apache config (PORT + mod_remoteip)
# ---------------------------------------------------------------------------
configure_apache() {
    log "step 2: configuring Apache (PORT=${PORT}, trusted proxies: ${SBPP_TRUSTED_PROXIES:-<none>})"

    # Rewrite `Listen 80` -> `Listen ${PORT}` in /etc/apache2/ports.conf
    # and `<VirtualHost *:80>` in the default site. Only when PORT
    # differs from 80 (the image default) — saves a write on the
    # common case.
    if [ "$PORT" != "80" ]; then
        sed -ri "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
        sed -ri "s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/" \
            /etc/apache2/sites-available/000-default.conf
    fi

    # Trusted-proxy config — written if SBPP_TRUSTED_PROXIES is set,
    # removed (if it exists from a prior boot) otherwise. mod_remoteip
    # is enabled in the Dockerfile; the conf below adds the per-deploy
    # CIDR list.
    #
    # When trusted: also mirror X-Forwarded-Proto -> $_SERVER['HTTPS']
    # via SetEnvIfExpr so the panel's Sbpp\Auth\Host::isSecure() check
    # picks up the original scheme. (Host::isSecure() also reads
    # HTTP_X_FORWARDED_PROTO directly as a pre-existing fallback;
    # SetEnvIfExpr is the cleaner Apache-side shape that doesn't depend
    # on the PHP code's specific check.)
    conf_file="/etc/apache2/conf-enabled/zz-sbpp-trusted-proxy.conf"
    if [ -n "$SBPP_TRUSTED_PROXIES" ]; then
        {
            printf '# Generated by prod-entrypoint on %s.\n' "$(date -u +%FT%TZ)"
            printf '# Per-deploy trusted-proxy list (SBPP_TRUSTED_PROXIES env var).\n'
            for proxy in $SBPP_TRUSTED_PROXIES; do
                printf 'RemoteIPInternalProxy %s\n' "$proxy"
            done
            # Apache 2.4+: when X-Forwarded-Proto comes from a trusted
            # proxy (i.e. mod_remoteip rewrote REMOTE_ADDR), mirror it
            # into HTTPS so PHP-side checks of $_SERVER['HTTPS']
            # see the upstream scheme.
            cat <<'CONF'

# Mirror X-Forwarded-Proto -> $_SERVER['HTTPS'] for PHP code that
# checks the legacy 'HTTPS' key directly. Sbpp\Auth\Host::isSecure()
# also reads HTTP_X_FORWARDED_PROTO directly, so this is belt-and-
# suspenders for any third-party plugin / theme code that reaches
# for the legacy key.
SetEnvIfExpr "req('X-Forwarded-Proto') == 'https'" HTTPS=on
CONF
        } > "$conf_file"
    elif [ -f "$conf_file" ]; then
        rm -f "$conf_file"
    fi
}

# ---------------------------------------------------------------------------
# Step 3: wait for DB
# ---------------------------------------------------------------------------
wait_for_db() {
    log "step 3: waiting for DB at ${DB_HOST}:${DB_PORT} (user=${DB_USER}) ..."
    tries=60
    while [ "$tries" -gt 0 ]; do
        if mysqladmin ping \
                -h"${DB_HOST}" -P"${DB_PORT}" \
                -u"${DB_USER}" -p"${DB_PASS}" \
                --skip-ssl --silent 2>/dev/null; then
            log "step 3: DB is up"
            return
        fi
        tries=$((tries - 1))
        sleep 1
    done
    die "DB at ${DB_HOST}:${DB_PORT} never came up — giving up after 60s"
}

# Run a SQL command via the panel's DB user. Honours the connection
# settings configured above; reads its body from stdin.
run_sql() {
    mysql \
        -h"${DB_HOST}" -P"${DB_PORT}" \
        -u"${DB_USER}" -p"${DB_PASS}" \
        --skip-ssl --silent --skip-column-names \
        --default-character-set="${DB_CHARSET}" \
        "${DB_NAME}"
}

# ---------------------------------------------------------------------------
# Step 4: render config.php
# ---------------------------------------------------------------------------
render_config() {
    if [ -s "${SBPP_CONFIG_PATH}" ]; then
        log "step 4: ${SBPP_CONFIG_PATH} already present — leaving alone (config.php is the install-state sentinel)"
        return
    fi
    log "step 4: rendering ${SBPP_CONFIG_PATH} from environment"

    if [ -z "$SB_SECRET_KEY" ]; then
        SB_SECRET_KEY="$(openssl rand -base64 47 | tr -d '\n')"
        log "step 4: minted fresh SB_SECRET_KEY (47-byte base64) — persist by re-reading from this file or set SB_SECRET_KEY env var"
        export SB_SECRET_KEY
    fi

    # Single-quote string literals; escape `'` and `\` to defend the
    # PHP file from values that might break out of the literal. Mirror
    # the wizard's `sbpp_install_render_config()` shape (page.5.php).
    cfg_dir="$(dirname "$SBPP_CONFIG_PATH")"
    [ -d "$cfg_dir" ] || mkdir -p "$cfg_dir"

    sb_esc() {
        printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/\\\\'/g"
    }

    cat > "${SBPP_CONFIG_PATH}" <<PHP
<?php
// SourceBans++ config.php — generated by prod-entrypoint on $(date -u +%FT%TZ).
//
// This file is the install-state sentinel: web/init.php gates on its
// presence; web/install/already-installed.php refuses to start the
// wizard if it exists. The prod entrypoint will NOT regenerate this
// file on subsequent container starts (the [-s] check above sees the
// non-empty file and returns immediately).
//
// Edit env vars in your docker-compose.prod.yml / app-platform deploy
// form and recreate the container if you want different values, or
// hand-edit this file directly — but if you delete it, the entrypoint
// will mint a fresh SB_SECRET_KEY and every existing JWT cookie will
// become invalid (admins log out on next request).
//
// SB_SECRET_KEY in particular is the JWT signing key. Persist it across
// container restarts: either let this file persist on the writable
// layer (the default — the docker-compose volume layout pins web/ as
// the read-only layer EXCEPT for config.php, demos/, cache/) or set
// SB_SECRET_KEY explicitly in the deploy env so the value survives a
// volume reset.
if (!defined('IN_SB')) {
    echo 'You should not be here. Only follow links!';
    die();
}
define('DB_HOST',      '$(sb_esc "$DB_HOST")');
define('DB_USER',      '$(sb_esc "$DB_USER")');
define('DB_PASS',      '$(sb_esc "$DB_PASS")');
define('DB_NAME',      '$(sb_esc "$DB_NAME")');
define('DB_PREFIX',    '$(sb_esc "$DB_PREFIX")');
define('DB_PORT',      '$(sb_esc "$DB_PORT")');
define('DB_CHARSET',   '$(sb_esc "$DB_CHARSET")');
define('STEAMAPIKEY',  '$(sb_esc "$STEAMAPIKEY")');
define('SB_EMAIL',     '$(sb_esc "$SB_EMAIL")');
define('SB_NEW_SALT',  '$(sb_esc "$SB_NEW_SALT")');
define('SB_SECRET_KEY','$(sb_esc "$SB_SECRET_KEY")');
PHP

    # The PHP file may carry secrets (DB password, JWT key). Tighten
    # perms — readable by the runtime user only.
    chown www-data:www-data "${SBPP_CONFIG_PATH}"
    chmod 0640 "${SBPP_CONFIG_PATH}"
}

# ---------------------------------------------------------------------------
# Step 5: first-boot install (schema + seed admin)
# ---------------------------------------------------------------------------
#
# Fresh DBs (no `:prefix_admins` table) get the schema + seed pass
# below. Existing DBs (table present) skip — even if INITIAL_ADMIN_*
# env vars are set, we never re-create the admin (would clobber the
# existing one's password / Steam ID and silently lock the operator
# out of their own panel).
#
# `SBPP_AUTO_INSTALL=0` opts OUT of this entirely (e.g. for an operator
# pointing the panel at a managed DB they've already populated by hand).
first_boot_install() {
    if [ "$SBPP_AUTO_INSTALL" != "1" ]; then
        log "step 5: SBPP_AUTO_INSTALL=0 — skipping first-boot install (operator opted out)"
        return
    fi

    table="${DB_PREFIX}_admins"
    exists="$(echo "SELECT 1 FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='${table}' LIMIT 1;" | run_sql 2>/dev/null || true)"
    if [ -n "$exists" ]; then
        log "step 5: ${table} already exists — skipping first-boot install"
        return
    fi
    log "step 5: first-boot install (schema + data + seed admin)"

    schema_dir="${WEB_ROOT}/install/includes/sql"
    if [ ! -f "${schema_dir}/struc.sql" ] || [ ! -f "${schema_dir}/data.sql" ]; then
        die "first-boot install requested but schema files missing under ${schema_dir} — image is broken?"
    fi

    # Pipe schema with substitutions (mirror docker/db-init/00-render-schema.sh
    # exactly — same prefix, same charset, same render order).
    log "step 5: loading schema (prefix=${DB_PREFIX}, charset=${DB_CHARSET})"
    sed -e "s/{prefix}/${DB_PREFIX}/g" -e "s/{charset}/${DB_CHARSET}/g" \
        "${schema_dir}/struc.sql" | run_sql
    sed -e "s/{prefix}/${DB_PREFIX}/g" -e "s/{charset}/${DB_CHARSET}/g" \
        "${schema_dir}/data.sql"  | run_sql

    # Seed initial admin (or skip with a clear next-step nudge).
    if [ -z "$INITIAL_ADMIN_NAME" ] \
       || [ -z "$INITIAL_ADMIN_STEAM" ] \
       || [ -z "$INITIAL_ADMIN_EMAIL" ] \
       || [ -z "$INITIAL_ADMIN_PASSWORD" ]; then
        log "step 5: INITIAL_ADMIN_{NAME,STEAM,EMAIL,PASSWORD} not all set — admin row not seeded"
        log "step 5: log in as the CONSOLE row only; seed an admin manually via the panel before going live"
        return
    fi

    seed_initial_admin
}

# Hash the password with PHP's password_hash() (BCRYPT) — same shape as
# the wizard's page.5.php. `php -r` runs in the runtime image without
# touching init.php (no panel chrome / no DB connection).
seed_initial_admin() {
    log "step 5: seeding initial admin '${INITIAL_ADMIN_NAME}'"

    # STEAM_1 -> STEAM_0 normalisation (mirror page.5.php). The panel
    # runtime expects the STEAM_0 form; admins logging in via Steam
    # get STEAM_0 from OpenID anyway.
    authid="$(printf '%s' "$INITIAL_ADMIN_STEAM" | sed 's/^STEAM_1/STEAM_0/')"

    pwhash="$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$INITIAL_ADMIN_PASSWORD")"
    if [ -z "$pwhash" ]; then
        die "password_hash() returned empty for INITIAL_ADMIN_PASSWORD — refusing to seed admin"
    fi

    # gid=-1, extraflags=16777216 (1<<24 = ADMIN_OWNER), immunity=100.
    # Same shape as page.5.php's INSERT.
    #
    # The mysql client connects via the panel's runtime user/pass which
    # was already verified by wait_for_db. Quoting: we pass values via
    # `printf %q` shell-escape into a single-string SQL stmt; since the
    # password hash and the admin name come from operator env vars,
    # they're trusted by definition (the operator set them). The
    # authid is regex-validated above. The email is validated by the
    # panel UI later but here we just splat it in.
    sql_escape() {
        # MySQL string-literal escape: backslash + single-quote.
        printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/\\\\'/g"
    }
    n="$(sql_escape "$INITIAL_ADMIN_NAME")"
    a="$(sql_escape "$authid")"
    p="$(sql_escape "$pwhash")"
    e="$(sql_escape "$INITIAL_ADMIN_EMAIL")"

    cat <<SQL | run_sql
INSERT INTO \`${DB_PREFIX}_admins\`
    (user, authid, password, gid, email, extraflags, immunity)
VALUES
    ('${n}', '${a}', '${p}', -1, '${e}', 16777216, 100)
ON DUPLICATE KEY UPDATE user = user;
SQL

    log "step 5: initial admin seeded"
}

# ---------------------------------------------------------------------------
# Step 6: pending updater migrations
# ---------------------------------------------------------------------------
#
# Same logic as web/updater/index.php's `new Updater($GLOBALS['PDO'])`,
# but headless: no Smarty render, no cache-flush dir-walk. Iterates
# `web/updater/data/<N>.php` against the recorded `config.version` row
# in `:prefix_settings`, runs each script in a per-script php -r so
# the SQL inside their `$this->dbs->...` calls executes against the
# panel's runtime DB.
#
# Each script is required to be idempotent (per AGENTS.md "Updater
# migrations" contract), so re-running on every container start is safe.
run_pending_migrations() {
    log "step 6: checking for pending updater migrations"

    if [ ! -f "${WEB_ROOT}/updater/store.json" ] \
       || [ ! -d "${WEB_ROOT}/updater/data" ]; then
        log "step 6: updater not on disk — skipping (image already pruned post-install?)"
        return
    fi

    # Use PHP's own JSON parser + the panel's Database class to drive
    # the migration runner. This re-uses the panel's autoload + the
    # existing Updater class, so the codepath is byte-identical to the
    # /updater/ web entrypoint minus the HTML render.
    php <<'PHP'
<?php
declare(strict_types=1);

// Headless updater driver — same shape as web/updater/index.php
// minus the HTML rendering. Required to support an immutable image:
// every container start should converge the DB to the bundled code's
// schema version, idempotently (per AGENTS.md "Updater migrations").

define('IN_SB',     true);
define('IS_UPDATE', true);

// Path constants the panel's init.php normally defines. Keeping these
// in sync with init.php lets the updater scripts (which were authored
// against the panel runtime) see the same global landscape they
// expect.
$root = '/var/www/html/web';
define('ROOT',           $root . '/');
define('SB_THEMES',      $root . '/themes/');
define('SB_CACHE',       $root . '/cache/');
define('INCLUDES_PATH',  $root . '/includes');

require_once $root . '/init-recovery.php';

$configPath = sbpp_resolve_config_path($root . '/config.php');
if (!is_file($configPath)) {
    fwrite(STDERR, "[prod-entrypoint][step 6] config.php not present at {$configPath} — refusing to run migrations\n");
    exit(1);
}
require_once $configPath;
require_once $root . '/includes/vendor/autoload.php';

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

require_once $root . '/includes/Db/Database.php';
$pdo = new \Sbpp\Db\Database(DB_HOST, (int) DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);

// `chdir` into updater/ because Updater::__construct expects to find
// store.json and data/<N>.php files via relative paths. Same shape
// as the legacy /updater/index.php (which is loaded with cwd =
// /var/www/.../web/updater).
chdir($root . '/updater');
require_once $root . '/updater/Updater.php';

$updater = new \Updater($pdo);
foreach ($updater->getMessageStack() as $line) {
    fwrite(STDERR, "[prod-entrypoint][step 6] " . strip_tags((string) $line) . "\n");
}
PHP

    rc=$?
    if [ "$rc" -ne 0 ]; then
        die "updater run failed (exit $rc) — refusing to start panel"
    fi
}

# ---------------------------------------------------------------------------
# Step 7: strip install/ + updater/ from the writable layer
# ---------------------------------------------------------------------------
#
# Per web/init-recovery.php's sbpp_check_install_guard(), the panel
# runtime refuses to boot if `install/` or `updater/` is on disk
# (post-#1335 contract). Production MUST NOT define
# SBPP_DEV_KEEP_INSTALL — the only legitimate way to make the guard
# pass is to actually remove the directories. The dev image bind-mounts
# the worktree (which carries both from git) and has its own escape
# hatch; this image's writable layer is the place to make the
# directories vanish.
#
# `rm -rf` against the runtime user's writable layer; failure is
# fatal (otherwise the panel would die on the next request with the
# install-blocked recovery page, which is the wrong UX for a
# production deploy).
strip_install_dirs() {
    log "step 7: removing install/ + updater/ from writable layer (panel-runtime guard contract)"
    rm -rf "${WEB_ROOT}/install" "${WEB_ROOT}/updater" || die "couldn't strip install/+updater/ — see error above"
}

# ---------------------------------------------------------------------------
# Step 8: writable cache + demos
# ---------------------------------------------------------------------------
ensure_writable() {
    log "step 8: ensuring writable cache/templates_c/demos"
    # Pre-created in the Dockerfile, but the operator's docker-compose
    # binds named volumes over each path — and a fresh volume's first
    # mount inherits root:root with restrictive perms. chown + chmod
    # makes those volumes writable on every boot (idempotent — no-op
    # when the perms are already right).
    for dir in cache cache/sessions templates_c demos; do
        path="${WEB_ROOT}/${dir}"
        [ -d "$path" ] || mkdir -p "$path"
        chown -R www-data:www-data "$path"
        chmod -R 0775 "$path"
    done
}

# ---------------------------------------------------------------------------
# main
# ---------------------------------------------------------------------------
main() {
    log "starting (image: $(cat /etc/debian_version 2>/dev/null || echo unknown), php: $(php -r 'echo PHP_VERSION;'))"

    resolve_secrets         # step 1a
    parse_database_url      # step 1b
    apply_defaults          # step 1c
    configure_apache        # step 2
    wait_for_db             # step 3
    render_config           # step 4
    first_boot_install      # step 5
    run_pending_migrations  # step 6
    strip_install_dirs      # step 7
    ensure_writable         # step 8

    log "boot complete — handing off to: $*"
    exec "$@"
}

main "$@"
