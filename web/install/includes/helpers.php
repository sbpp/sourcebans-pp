<?php
declare(strict_types=1);

// Shared helpers for the install wizard step handlers.
//
// Required eagerly from web/install/bootstrap.php so every step
// page (web/install/pages/page.<N>.php) has these in scope without
// its own require_once. Lives in includes/ (not init.php) because
// sbpp_install_open_db() instantiates \Database, which is a
// Composer-loaded class — these helpers are post-vendor only.
//
// Defense-in-depth motivation (#1332 review):
//
//   - sbpp_install_validate_prefix() is single-source-of-truth for
//     the prefix regex / length cap. Step 2's POST handler runs the
//     check on first submit; steps 3-6 forward the validated value
//     in hidden POST fields. A direct request to /install/?step=4
//     with a crafted prefix bypasses step 2 entirely, so EVERY step
//     re-runs the same check at the top of its handler.
//
//   - sbpp_install_open_db() probes connectivity with a raw PDO so
//     a credential typo / network blip surfaces as a catchable
//     PDOException. Without the probe, \Database::__construct
//     die()s on PDOException and dumps a bare-text error to the
//     browser, bypassing the wizard chrome (the panel runtime's
//     legacy "die on connect failure" behaviour, fine in the
//     panel context but wrong for an interactive installer).
//
//   - sbpp_install_kv_escape() is the SourceMod KeyValues quoting
//     helper. databases.cfg is a KeyValues file; values containing
//     `"` or `\` must be backslash-escaped or the parser silently
//     mis-parses (or refuses to load the file). Used by
//     sbpp_install_render_databases_cfg() in page.5.php.

/**
 * Return true if the prefix is a legal SourceBans++ table prefix.
 *
 * Matches step 2's inline validation: 1-9 ASCII letters, digits, or
 * underscores. The SQL files (struc.sql / data.sql) substitute the
 * value into table names via str_replace('{prefix}', ...) and the
 * Database class substitutes via str_replace(':prefix', ...) — both
 * are unparameterised, so a prefix that escapes the regex would
 * inject arbitrary DDL/DML into the schema-install + data.sql passes
 * (#1332 review: critical findings 1-3).
 */
function sbpp_install_validate_prefix(string $prefix): bool
{
    if ($prefix === '' || strlen($prefix) > 9) {
        return false;
    }
    return preg_match('/^[A-Za-z0-9_]+$/', $prefix) === 1;
}

/**
 * Open a Database connection with operator-supplied credentials.
 *
 * Probes connectivity with a raw \PDO first so a connect failure
 * surfaces as a catchable \PDOException — the caller wraps the call
 * in try/catch and surfaces the message in the wizard's chrome.
 * Without the probe, \Database::__construct calls die() on
 * \PDOException and the operator sees a bare-text error page
 * outside the install chrome.
 *
 * @throws \PDOException on connection failure (raw probe).
 */
function sbpp_install_open_db(
    string $host,
    int $port,
    string $database,
    string $username,
    string $password,
    string $prefix
): \Database {
    $dsn = 'mysql:host=' . $host
        . ';port=' . $port
        . ';dbname=' . $database
        . ';charset=utf8mb4';
    new \PDO($dsn, $username, $password, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_TIMEOUT => 5,
    ]);
    // The probe succeeded, so \Database's own \PDO will too — its
    // die() trap is unreachable from here.
    return new \Database($host, $port, $database, $username, $password, $prefix);
}

/**
 * Escape a value for embedding inside a SourceMod KeyValues string.
 *
 * Used by sbpp_install_render_databases_cfg() (page.5.php) when
 * baking operator-supplied DB credentials into the
 * databases.cfg snippet the operator pastes onto each gameserver.
 * KeyValues quotes string values with `"`; embedded `"` and `\`
 * must be backslash-escaped or the parser silently breaks.
 *
 * Order matters: escape `\` first so the `\` we add for `"`
 * doesn't itself get re-escaped on a second pass.
 */
function sbpp_install_kv_escape(string $value): string
{
    return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
}
