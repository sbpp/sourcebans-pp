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

# `pipefail` ensures a failure midway through a `foo | bar` pipeline
# propagates the failure rather than being masked by the trailing
# command's exit (LOW-1 of the #1381 review). The first_boot_install
# pass pipes a `sed | run_sql` heredoc; without pipefail, a sed
# failure (e.g. corrupted struc.sql) would be silently masked by the
# run_sql success on an empty stream, and we'd boot against an
# half-loaded schema.
#
# `pipefail` is a POSIX-2024 addition that the surrounding image's
# shell (Debian dash 0.5.11+ and Alpine busybox ash 1.31+) both
# support — the "no bash-isms" promise above still holds in
# practice. shellcheck doesn't know that yet, hence the disable.
# shellcheck disable=SC3040
set -euo pipefail

WEB_ROOT="/var/www/html/web"
LOG_PREFIX="[prod-entrypoint]"
SBPP_AUTO_INSTALL="${SBPP_AUTO_INSTALL:-1}"

# Path to the bundled PHP helpers (sb-db.php). Ships under
# /usr/local/lib/sbpp/ from the Dockerfile so the entrypoint can
# reach them without taking a dependency on the panel's WEB_ROOT
# (which gets a `rm -rf install/+updater/` halfway through boot).
SBPP_LIB_DIR="${SBPP_LIB_DIR:-/usr/local/lib/sbpp}"

log()  { printf '%s %s\n' "$LOG_PREFIX" "$*" >&2; }
warn() { printf '%s WARN: %s\n'  "$LOG_PREFIX" "$*" >&2; }
die()  { printf '%s ERROR: %s\n' "$LOG_PREFIX" "$*" >&2; exit 1; }

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
        # Read the file, then strip trailing CR/LF from the LAST line
        # only. Operators routinely create secret files with
        # `echo "value" > /run/secrets/foo` which appends a `\n`, and
        # Docker Desktop on Windows often serialises secrets with CRLF
        # line endings — neither byte belongs in the secret. NIT-2 of
        # the #1381 review. We use `printf '%s'` (no newline) around
        # `tr` instead of stripping in the eval below so a secret that
        # legitimately ends in whitespace on an inner line survives.
        eval "$name=\$(printf '%s' \"\$(cat \"\$file_path\")\" | tr -d '\r\n')"
        # MED-1 of the #1381 review: an empty *_FILE points at the
        # wrong path (typo, missing volume mount, secret not synced
        # yet) and silently leaves the var at its old / default value
        # — which for DB_PASS is "" and silently weakens the panel's
        # auth surface. Refuse to start instead.
        eval "_value=\${$name}"
        if [ -z "$_value" ]; then
            die "${file_var}=${file_path} resolved to an empty value (file missing trailing newline OK; truly empty file is not). Fix the secret payload or unset ${file_var} to fall back to ${name}."
        fi
        # `export "$name"` works (POSIX `export` accepts an expanded
        # variable name as an arg), but shellcheck flags it as SC2163
        # because the static analysis can't see the deferred expansion.
        # Wrapping the value in `${var?}` is the documented silencing
        # shape and also gates against `$name` being unset, so the
        # behaviour is strictly tighter than the bare form.
        export "${name?refusing to export an unnamed secret}"
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
        # `%40`, a space as `%20`, etc.). The previous shape was
        # `printf '%b' "$(echo "$value" | sed 's/%/\\x/g')"` — that's
        # a no-op on Debian's dash (CRIT-1 of the #1381 review): only
        # bash's builtin `printf` expands `\xHH` sequences. Every DB
        # password with URL-reserved chars (`@`, `:`, `/`, `?`, `#`,
        # `&`, `%`, `+`, space) was silently mangled — the panel
        # ended up authenticating against `p\x40ssword` instead of
        # `p@ssword`.
        #
        # PHP is already in the runtime image; `urldecode()` is its
        # one-liner. The `--` separator tells PHP's option parser
        # that the values that follow are positional argv, not PHP
        # options — important when the user/pass starts with a dash.
        # shellcheck disable=SC2016
        # ^ single-quote intentional — `$argv[1]` is PHP's argv,
        #   not a shell variable; double-quoting would let the shell
        #   try (and fail) to expand it before php sees the string.
        DB_USER="$(php -r 'echo urldecode((string) ($argv[1] ?? ""));' -- "$_DB_USER")"
        # shellcheck disable=SC2016
        DB_PASS="$(php -r 'echo urldecode((string) ($argv[1] ?? ""));' -- "$_DB_PASS")"
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
# CRIT-6: identifier validation
# ---------------------------------------------------------------------------
#
# Several env vars flow into shell `sed` substitutions, SQL string
# literals, and SQL identifiers (the bareword between backticks in
# `:prefix_admins`). A value with `/`, `&`, `\n`, `'`, `;`, `"`, or a
# backtick would either break the sed expression's delimiter or escape
# SQL string context — depending on the call site, that's a syntax
# error at best and a SQL-injection vector at worst.
#
# The wizard's `sbpp_install_validate_prefix()` already pins the
# `prefix` shape to `^[A-Za-z0-9_]+$`. We mirror the contract here so
# the entrypoint path has the same guarantee BEFORE any substitution
# runs. The shapes:
#
#   - DB_PREFIX, DB_NAME, DB_USER — SQL identifiers (DB_NAME is the
#     name of the database between `USE` / `mysql --database` /
#     `dbname=` in the DSN; DB_USER is the auth user). Allow
#     `[A-Za-z0-9_]+`.
#   - DB_HOST — hostname or IPv4 literal. Allow `[A-Za-z0-9._-]+`.
#     (IPv6 isn't supported by the URL parser yet; operators using
#     IPv6 set DB_HOST + DB_PORT directly and the literal
#     `2001:db8::1` form would tickle the colon delimiter — for now
#     we reject it loud rather than silently mis-parse.)
validate_identifiers() {
    # Allow this function to "fail" via the regex case-statement
    # without nuking the shell under `set -e`.
    case "${DB_PREFIX:-}" in
        ''|*[!A-Za-z0-9_]*)
            die "DB_PREFIX='${DB_PREFIX:-}' must match [A-Za-z0-9_]+ — used as the literal table-name prefix in SQL identifiers."
            ;;
    esac
    case "${DB_NAME:-}" in
        ''|*[!A-Za-z0-9_]*)
            die "DB_NAME='${DB_NAME:-}' must match [A-Za-z0-9_]+ — used as the SQL database name in DDL."
            ;;
    esac
    case "${DB_USER:-}" in
        ''|*[!A-Za-z0-9_]*)
            die "DB_USER='${DB_USER:-}' must match [A-Za-z0-9_]+."
            ;;
    esac
    case "${DB_HOST:-}" in
        ''|*[!A-Za-z0-9._-]*)
            die "DB_HOST='${DB_HOST:-}' must match [A-Za-z0-9._-]+ (hostnames + IPv4; IPv6 literals not supported here — set DB_HOST + DB_PORT explicitly without the bracketed form)."
            ;;
    esac
    case "${DB_PORT:-}" in
        ''|*[!0-9]*)
            die "DB_PORT='${DB_PORT:-}' must be numeric."
            ;;
    esac
    case "${DB_CHARSET:-}" in
        ''|*[!A-Za-z0-9_]*)
            die "DB_CHARSET='${DB_CHARSET:-}' must match [A-Za-z0-9_]+."
            ;;
    esac
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
#
# HIGH-3 of the #1381 review: the spec explicitly forbids shipping
# `default-mysql-client` in the runtime image. Pre-fix the entrypoint
# reached for `mysqladmin ping` here and `mysql < schema.sql` in
# `first_boot_install` — both removed. The PHP-side replacement is
# `docker/php/sb-db.php`, copied into the image at
# /usr/local/lib/sbpp/sb-db.php (see Dockerfile.prod for the COPY
# line) and called with the `ping` / `exec` / `has-version-row`
# subcommands.
wait_for_db() {
    log "step 3: waiting for DB at ${DB_HOST}:${DB_PORT} (user=${DB_USER}) ..."
    tries=60
    while [ "$tries" -gt 0 ]; do
        if php "${SBPP_LIB_DIR}/sb-db.php" ping 2>/dev/null; then
            log "step 3: DB is up"
            return
        fi
        tries=$((tries - 1))
        sleep 1
    done
    die "DB at ${DB_HOST}:${DB_PORT} never came up — giving up after 60s"
}

# Run a SQL command via the panel's DB user. Reads its body from
# stdin. Replaces the legacy `mysql < … `-shaped helper.
run_sql() {
    php "${SBPP_LIB_DIR}/sb-db.php" exec
}

# ---------------------------------------------------------------------------
# Step 4: render config.php
# ---------------------------------------------------------------------------
render_config() {
    if [ -s "${SBPP_CONFIG_PATH}" ]; then
        # MED-2 of the #1381 review: a partial / corrupted config.php
        # left over from a crashed render run would otherwise propagate
        # to every subsequent boot as a fatal at request time (Apache
        # children syntax-error on every page load). Catching the
        # syntax error here surfaces the failure in the boot logs the
        # operator is already watching, and `die` triggers the
        # orchestrator's restart loop with a clear message — better
        # than a panel that returns 500 on every request.
        if ! php -l "${SBPP_CONFIG_PATH}" >/dev/null 2>&1; then
            die "${SBPP_CONFIG_PATH} exists but has a PHP syntax error (corrupted? hand-edited?). Delete it to let the entrypoint regenerate, or fix the syntax. \`php -l ${SBPP_CONFIG_PATH}\` shows the line."
        fi
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

    # MED-5 of the #1381 review: if the operator set SBPP_CONFIG_PATH
    # to a path whose parent directory isn't mounted (typo in the
    # bind-mount target, a Docker secret that didn't sync, etc.),
    # mkdir -p will silently create the dir on the container's
    # writable layer and the freshly-rendered config.php will vanish
    # the next time the operator recreates the container — a subtle
    # "why does my panel keep losing its config?" trap. Detect the
    # two pathologies and surface them loud:
    #
    #   1. Parent dir missing when SBPP_CONFIG_PATH was explicitly
    #      set: die — refuse to create a config the operator expected
    #      to land on a volume that isn't there.
    #
    #   2. Parent dir on the same st_dev as `/` when SBPP_CONFIG_PATH
    #      was explicitly set: warn — heuristic check that the path
    #      isn't a mount. Common false-positive: the operator
    #      intentionally writes config.php into the writable layer
    #      and pairs that with an explicit SB_SECRET_KEY in env. So
    #      this stays a warn, not a die.
    #
    # The default case (SBPP_CONFIG_PATH unset → ${WEB_ROOT}/config.php)
    # is on the writable layer by design; skip the check.
    if [ "${SBPP_CONFIG_PATH}" != "${WEB_ROOT}/config.php" ]; then
        if [ ! -d "$cfg_dir" ]; then
            die "SBPP_CONFIG_PATH=${SBPP_CONFIG_PATH} but its parent directory ${cfg_dir} does not exist. Mount the directory (or its containing volume / secret), or unset SBPP_CONFIG_PATH to write config.php into the image's writable layer."
        fi
        if [ "$(stat -c %d "$cfg_dir" 2>/dev/null)" = "$(stat -c %d / 2>/dev/null)" ]; then
            warn "SBPP_CONFIG_PATH=${SBPP_CONFIG_PATH} is on the container's writable layer (same st_dev as /). config.php will NOT persist across container recreations. If this is intentional, also set SB_SECRET_KEY explicitly so JWT cookies survive."
        fi
    else
        [ -d "$cfg_dir" ] || mkdir -p "$cfg_dir"
    fi

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
# Fresh DBs get the schema + seed pass below. Existing DBs (sentinel
# present) skip — even if INITIAL_ADMIN_* env vars are set, we never
# re-create the admin (would clobber the existing one's password /
# Steam ID and silently lock the operator out of their own panel).
#
# `SBPP_AUTO_INSTALL=0` opts OUT of this entirely (e.g. for an
# operator pointing the panel at a managed DB they've already
# populated by hand, OR for the operator who wants to run the
# install wizard manually). In that mode we also SKIP the
# install/+updater/ strip in step 7 so the wizard surface stays
# reachable — see strip_install_dirs.
first_boot_install() {
    if [ "$SBPP_AUTO_INSTALL" != "1" ]; then
        log "step 5: SBPP_AUTO_INSTALL=0 — skipping first-boot install (operator opted out)"
        log "step 5: install/ + updater/ will NOT be stripped in step 7 either — the wizard is reachable at /install/"
        return
    fi

    # MED-3 of the #1381 review: pre-fix the sentinel was "does the
    # {prefix}_admins table exist?". That created a first-boot install
    # race: struc.sql creates :prefix_admins *before* :prefix_settings
    # is fully seeded by data.sql. If the entrypoint crashed mid-
    # `data.sql` (e.g. an OOM), a restart would see the admins table
    # present, skip the install pass, and the panel would boot against
    # a half-seeded :prefix_settings — every Config::get(...) lookup
    # returning the schema's column default.
    #
    # The correct sentinel is the `:prefix_settings.config.version`
    # row, which is the second-to-last INSERT in data.sql — its
    # presence means data.sql ran to completion. sb-db.php has-version-row
    # treats a missing table (PDOException 42S02) as "not present" so
    # a truly fresh DB still triggers the install pass.
    if php "${SBPP_LIB_DIR}/sb-db.php" has-version-row "${DB_PREFIX}" 2>/dev/null; then
        log "step 5: :${DB_PREFIX}_settings.config.version row exists — panel already installed, skipping schema bootstrap"
        return
    fi
    log "step 5: first-boot install (schema + data + seed admin)"

    schema_dir="${WEB_ROOT}/install/includes/sql"
    if [ ! -f "${schema_dir}/struc.sql" ] || [ ! -f "${schema_dir}/data.sql" ]; then
        die "first-boot install requested but schema files missing under ${schema_dir} — image is broken?"
    fi

    # HIGH-5 of the #1381 review: pre-fix this was a soft `log` line
    # ("log in as the CONSOLE row only") that left the operator
    # locked out — the CONSOLE row carries an empty password and
    # NormalAuthHandler rejects empty auth, so the "log in as CONSOLE"
    # nudge was impossible to follow. With SBPP_AUTO_INSTALL=1, the
    # operator opted INTO headless install — refuse to bring the
    # panel up half-installed.
    if [ -z "$INITIAL_ADMIN_NAME" ] \
       || [ -z "$INITIAL_ADMIN_STEAM" ] \
       || [ -z "$INITIAL_ADMIN_EMAIL" ] \
       || [ -z "$INITIAL_ADMIN_PASSWORD" ]; then
        die "SBPP_AUTO_INSTALL=1 requires all four INITIAL_ADMIN_{NAME,STEAM,EMAIL,PASSWORD} env vars to be set so a headless install can seed an Owner-flagged admin. Either set them in your deploy env, or set SBPP_AUTO_INSTALL=0 to skip the headless install and run the wizard manually at /install/."
    fi

    # Pipe schema with substitutions (mirror docker/db-init/00-render-schema.sh
    # exactly — same prefix, same charset, same render order). The
    # `validate_identifiers` call at boot already restricted DB_PREFIX +
    # DB_CHARSET to [A-Za-z0-9_]+, so the sed replacement can't break
    # the schema's grammar.
    log "step 5: loading schema (prefix=${DB_PREFIX}, charset=${DB_CHARSET})"
    sed -e "s/{prefix}/${DB_PREFIX}/g" -e "s/{charset}/${DB_CHARSET}/g" \
        "${schema_dir}/struc.sql" | run_sql
    sed -e "s/{prefix}/${DB_PREFIX}/g" -e "s/{charset}/${DB_CHARSET}/g" \
        "${schema_dir}/data.sql"  | run_sql

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

    # shellcheck disable=SC2016
    # ^ the single-quoted body is intentional — `$argv[1]` is PHP's
    #   argv, not a shell variable. Wrapping in single quotes keeps
    #   the shell from rewriting the literal `$argv` before php sees
    #   it. The password itself is passed as a CLI argument
    #   (`"$INITIAL_ADMIN_PASSWORD"`), so it never reaches the shell's
    #   word-splitting / glob phases — `password_hash()` receives the
    #   literal byte sequence the operator set.
    pwhash="$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$INITIAL_ADMIN_PASSWORD")"
    if [ -z "$pwhash" ]; then
        die "password_hash() returned empty for INITIAL_ADMIN_PASSWORD — refusing to seed admin"
    fi

    # gid=-1, extraflags=16777216 (1<<24 = ADMIN_OWNER), immunity=100.
    # Same shape as page.5.php's INSERT.
    #
    # sb-db.php exec connects via the panel's runtime user/pass that
    # was already verified by wait_for_db. The password hash and the
    # admin name come from operator env vars, so they're trusted by
    # definition (the operator set them). The authid is regex-
    # normalised above. The email is validated by the panel UI later
    # but here we just splat it in. The single-quote escape in
    # `sql_escape` defends the string literal against `'` / `\`.
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
    #
    # CRIT-5 of the #1381 review: pre-fix the inline PHP script
    # ALWAYS returned exit 0 even when a migration failed midway.
    # Updater::update() records "Error executing: /updater/data/<N>.php.
    # Stopping Update!" / "Update <b>Failed</b>!" into its message
    # stack but never throws — and the surrounding script didn't
    # inspect the stack, so the shell-side `if [ "$rc" -ne 0 ]`
    # check was dead code. Result: a half-migrated schema booted
    # silently, and the panel's first request hit a column that
    # didn't yet exist.
    #
    # Post-fix: after the `Updater::update()` call returns, inspect
    # the message-stack lines for either marker and `exit(1)` if any
    # match. The shell `die` below now actually fires when the PHP
    # detects a failure.
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

try {
    $updater = new \Updater($pdo);
} catch (\Throwable $e) {
    fwrite(STDERR, "[prod-entrypoint][step 6] Updater constructor threw: " . $e->getMessage() . "\n");
    exit(1);
}

$failed = false;
foreach ($updater->getMessageStack() as $line) {
    $text = strip_tags((string) $line);
    fwrite(STDERR, "[prod-entrypoint][step 6] " . $text . "\n");
    // Updater::update() emits one of these two markers when a
    // per-script require returns falsy or a file is missing on
    // disk. Substring-match both because the upstream stack
    // strings include `<b>...</b>` formatting we already stripped.
    //
    // Source-of-truth: web/updater/Updater.php's `update()` method
    // — search for "Error executing" and "Update Failed!".
    if (str_contains($text, 'Error executing:')
        || str_contains($text, 'Update Failed!')) {
        $failed = true;
    }
}

if ($failed) {
    fwrite(STDERR, "[prod-entrypoint][step 6] one or more migrations failed — refusing to continue with a partially-upgraded schema\n");
    exit(1);
}
PHP

    rc=$?
    if [ "$rc" -ne 0 ]; then
        die "updater run failed (exit $rc) — refusing to start panel against a partially-upgraded schema"
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
    # HIGH-5 partner: when SBPP_AUTO_INSTALL=0, the operator opted
    # OUT of the headless install in step 5 — typically because they
    # want to drive the wizard manually OR because they're pointing
    # at a pre-populated managed DB and the wizard's already-installed
    # guard will refuse to start once their config.php lands. In
    # either case, install/ MUST stay on disk for the wizard to be
    # reachable.
    #
    # The panel-runtime install/-presence guard is the friction
    # surface that keeps the operator honest: hitting `/` lands on
    # the recovery page that tells them to delete install/ once
    # post-install cleanup is done. We deliberately don't reach for
    # SBPP_DEV_KEEP_INSTALL here — that constant is named loudly so
    # it's visibly wrong in production, and we want the operator to
    # SEE the guard fire (and the friendly recovery copy) until they
    # complete the wizard and clean up.
    if [ "$SBPP_AUTO_INSTALL" != "1" ]; then
        log "step 7: SBPP_AUTO_INSTALL=0 — leaving install/ + updater/ in place (wizard reachable at /install/)"
        return
    fi
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
    validate_identifiers    # step 1d  (CRIT-6: identifier shapes)
    configure_apache        # step 2
    wait_for_db             # step 3
    render_config           # step 4
    first_boot_install      # step 5
    run_pending_migrations  # step 6
    strip_install_dirs      # step 7
    ensure_writable         # step 8

    # CRIT-2 of the #1381 review: every env var that carries a
    # secret (DB password, JWT signing key, the initial-admin
    # password seed) MUST be unset BEFORE `exec apache2-foreground`.
    # The exec'd process inherits the entrypoint's environment, and
    # every Apache child PHP request can read those values through
    # `$_ENV` / `$_SERVER` / `getenv()` / `phpinfo()` — a stored
    # `<?php phpinfo();` upload or any debug surface would leak the
    # cleartext to anyone with `?p=phpinfo` access.
    #
    # config.php is the canonical source of truth for these values
    # at request time (the panel reads them as `const DB_PASS` /
    # `const SB_SECRET_KEY`). The env vars are scaffolding only; we
    # rendered them into config.php in step 4 and the runtime no
    # longer needs them.
    #
    # Why this list:
    #   - INITIAL_ADMIN_*: only consumed by step 5; never persists.
    #     A stored phpinfo with these visible is a credential leak.
    #   - DB_PASS / DB_PASS_FILE: in config.php, exposed in env was
    #     redundant.
    #   - SB_SECRET_KEY / SB_SECRET_KEY_FILE: in config.php; the JWT
    #     signing secret.
    #   - DATABASE_URL: carries DB_PASS embedded as a userinfo
    #     component (`mysql://user:PASS@host/db`).
    #   - STEAMAPIKEY: in config.php; the Steam Web API key.
    #
    # We deliberately keep the non-secret connection knobs
    # (DB_HOST/PORT/NAME/USER, PORT, SBPP_TRUSTED_PROXIES) in the
    # env — they're either in config.php anyway (DB_*) or used by
    # Apache's own env-var substitution (PORT, RemoteIPInternalProxy).
    unset INITIAL_ADMIN_NAME INITIAL_ADMIN_STEAM INITIAL_ADMIN_EMAIL INITIAL_ADMIN_PASSWORD \
          INITIAL_ADMIN_NAME_FILE INITIAL_ADMIN_STEAM_FILE INITIAL_ADMIN_EMAIL_FILE INITIAL_ADMIN_PASSWORD_FILE \
          DB_PASS DB_PASS_FILE \
          SB_SECRET_KEY SB_SECRET_KEY_FILE \
          STEAMAPIKEY STEAMAPIKEY_FILE \
          DATABASE_URL

    log "boot complete — handing off to: $*"
    exec "$@"
}

main "$@"
