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

/**
 * Describe a filesystem-requirement row's detail cell for step 3.
 *
 * Pure function over `(path, is_dir, is_writable)` so the message
 * shape is unit-testable without a real filesystem layout. Returns
 * the string the template renders into `{$row.detail}`.
 *
 * Three cases, three remediations (#1335 M2 review):
 *
 *  - **Missing** (`!$exists`): the release tarball ships a
 *    placeholder for every required folder (`web/demos/.gitkeep`,
 *    `web/cache/`, the bundled `web/images/games/*.png` and
 *    `web/images/maps/*` files), so a `Missing:` status indicates
 *    a partial / broken upload — chmod can't fix something that
 *    isn't there. The hint points at re-uploading from the zip
 *    or creating the directory in the host's File Manager.
 *  - **Not writable** (`$exists && !$writable`): the operator
 *    needs to chmod 0775 (or 0777 on shared hosting where they
 *    don't control the PHP user). Pre-fix this string was just
 *    `'Not writable: <path>'` with no remediation; the chmod hint
 *    pairs with the README's m7 signpost so the two surfaces stay
 *    in sync.
 *  - **OK** (`$exists && $writable`): just the literal `Writable`.
 *
 * Plain text (no inline HTML) — the template renders the value
 * via Smarty's default auto-escape on. Wider hint formatting
 * (paragraph breaks, code styling) belongs in the template, not
 * here.
 */
function sbpp_install_describe_filesystem_check(
    string $path,
    bool $exists,
    bool $writable,
): string {
    if (!$exists) {
        return 'Missing: ' . $path
            . ' — re-upload this folder from the release zip,'
            . ' or create it via your hosting File Manager.';
    }
    if (!$writable) {
        return 'Not writable: ' . $path
            . ' — set permissions to 0775 (or 0777 on shared hosting'
            . ' where you don\'t control the PHP user) via your hosting'
            . ' File Manager, FTP client, or chmod.';
    }
    return 'Writable';
}

/**
 * Translate a PDOException into a human-readable error message for
 * the wizard's step 2 connect form (#1335 m4).
 *
 * Pre-fix the wizard surfaced the raw PDO message verbatim:
 *
 *   Could not connect to the database: SQLSTATE[HY000] [1045] Access
 *   denied for user 'sourcebans'@'192.168.96.5' (using password: YES)
 *
 * `SQLSTATE[HY000] [1045]` is gibberish to non-DBAs, and the IP
 * address is the panel-as-seen-by-DB internal address — minor
 * information disclosure. The helper pattern-matches the four
 * error codes a non-technical operator is most likely to hit
 * (1045 access denied, 2002 host unreachable, 1049 unknown DB,
 * 1044 denied for user on database) and emits a friendlier
 * translation.
 *
 * Unrecognised codes fall through to the raw message so
 * debugging stays possible — the wizard loses nothing by
 * printing the original on the cases the helper doesn't know
 * how to translate.
 *
 * The MySQL driver-specific error code lives in the second slot
 * of `$e->errorInfo` (see https://www.php.net/manual/en/pdo.errorinfo.php
 * — element 1 is the driver-specific code; element 0 is the
 * SQLSTATE letter code).
 */
function sbpp_install_translate_pdo_error(
    \PDOException $e,
    string $server,
    string $username,
    string $database,
): string {
    // PDO sometimes leaves errorInfo NULL on driver-level connect
    // failures (the driver never got far enough to populate it);
    // fall back to parsing the message string in those cases.
    $code = is_array($e->errorInfo ?? null) ? (int) ($e->errorInfo[1] ?? 0) : 0;
    if ($code === 0) {
        // Pull the bracketed driver code out of the message:
        // `SQLSTATE[HY000] [1045] Access denied …` -> 1045.
        if (preg_match('/\[(\d{3,5})\]/', $e->getMessage(), $m) === 1) {
            $code = (int) $m[1];
        }
    }

    return match ($code) {
        1045 => 'Could not connect: the database username or password is wrong. '
            . 'Double-check the values you entered above match the credentials '
            . 'you set when you created the database.',
        2002 => 'Could not reach the database server at "' . $server . '". '
            . 'Verify the hostname and port — most shared hosts use '
            . '"localhost" with port 3306. If your host gave you a different '
            . 'value, paste it exactly as printed.',
        1049 => 'Connected, but the database "' . $database . '" doesn\'t exist. '
            . 'Create it via your hosting control panel (phpMyAdmin / cPanel '
            . '"MySQL Databases" / DirectAdmin / Plesk) before continuing.',
        1044 => 'Connected, but the user "' . $username . '" doesn\'t have '
            . 'permission to use the database "' . $database . '". '
            . 'Grant the user full privileges on that database via your '
            . 'hosting control panel.',
        default => 'Could not connect to the database: ' . $e->getMessage(),
    };
}
