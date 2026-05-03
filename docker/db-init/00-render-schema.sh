#!/usr/bin/env bash
# Runs once, the first time the MariaDB volume is initialized.
#
# The repo ships SQL templates with `{prefix}` and `{charset}` placeholders
# (see web/install/includes/sql/struc.sql + data.sql). The web installer
# normally substitutes these and pipes them in. We do the same here so the
# panel comes up fully provisioned, no installer wizard required.
#
# Variables expected from the compose env:
#   MYSQL_DATABASE, DB_PREFIX (default sb), DB_CHARSET (default utf8mb4)

set -euo pipefail

PREFIX="${DB_PREFIX:-sb}"
CHARSET="${DB_CHARSET:-utf8mb4}"
DATABASE="${MYSQL_DATABASE:-sourcebans}"

SQL_DIR="/sbpp-sql"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

echo "[db-init] rendering schema (prefix=${PREFIX}, charset=${CHARSET}) into ${DATABASE}"

render() {
    local src="$1" dst="$2"
    sed -e "s/{prefix}/${PREFIX}/g" -e "s/{charset}/${CHARSET}/g" "${src}" >"${dst}"
}

render "${SQL_DIR}/struc.sql" "${TMP}/struc.sql"
render "${SQL_DIR}/data.sql"  "${TMP}/data.sql"

# MariaDB's init dir runs us with the OS user `mysql`, not connected as root,
# so we authenticate explicitly. Either MARIADB_ROOT_PASSWORD or
# MYSQL_ROOT_PASSWORD will be set by the image.
ROOT_PW="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
export MYSQL_PWD="${ROOT_PW}"
MYSQL=(mariadb -uroot --database="${DATABASE}")

"${MYSQL[@]}" <"${TMP}/struc.sql"
"${MYSQL[@]}" <"${TMP}/data.sql"

# Seed a default admin so the panel is usable immediately.
#   user: admin
#   pass: admin
# Hash is bcrypt of "admin" — generated once and pinned so we don't need PHP
# in the DB container. Regenerate with:
#   php -r 'echo password_hash("admin", PASSWORD_BCRYPT), "\n";'
ADMIN_HASH='$2b$10$6wTXM7nLGr6k7uvVr09Yr.9TLOgMd/pU0uNBWLzeP2cfwMYCV5W2q'

"${MYSQL[@]}" <<SQL
INSERT INTO \`${PREFIX}_admins\`
    (user, authid, password, gid, email, validate, extraflags, immunity)
VALUES
    ('admin', 'STEAM_0:0:0', '${ADMIN_HASH}', -1, 'admin@example.test', NULL, 16777216, 100);
SQL

echo "[db-init] done — admin / admin is ready"
