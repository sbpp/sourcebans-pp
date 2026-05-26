<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.
/**
 * E2E `:prefix_settings` row setter.
 *
 * Some E2E specs need to flip a feature toggle that ships disabled
 * in `data.sql` (e.g. `config.enablegroupbanning`) so the surface
 * under test actually renders. The PHP unit tests do this inline
 * with a `REPLACE INTO sb_settings` (see `BansTest.php`); this shim
 * is the e2e-equivalent shell-out so a TypeScript spec can flip the
 * setting before navigating to the page, then revert in afterEach.
 *
 * Why not just call `Actions.SettingsSave`? The dispatcher action
 * requires CSRF + a logged-in Owner, and the change would fan out
 * through `Config::init()` + audit-log writes on every flip — way
 * more moving pieces than the spec needs. The SQL REPLACE is the
 * narrow shape; same pattern as `BansTest.php` for the same reason.
 *
 * This shim is e2e-only: refuses any DB other than the e2e schema
 * (default `sourcebans_e2e`). Same shape and guardrails as the
 * sister `seed-comms-e2e.php` / `seed-comments-e2e.php` shims.
 *
 * Usage (inside the web container):
 *
 *   echo '{"setting":"config.enablegroupbanning","value":"1"}' \
 *     | php set-setting-e2e.php
 *
 * The JSON payload is a single dict (one setting per invocation
 * is enough — most specs only need to flip one feature flag).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "set-setting-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to set settings against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "set-setting-e2e.php: empty stdin payload.\n");
    exit(2);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "set-setting-e2e.php: stdin is not a JSON object.\n");
    exit(2);
}

$setting = (string)($decoded['setting'] ?? '');
$value   = (string)($decoded['value']   ?? '');

if ($setting === '') {
    fwrite(STDERR, "set-setting-e2e.php: missing 'setting' key.\n");
    exit(2);
}

$GLOBALS['PDO']->query(
    "REPLACE INTO `:prefix_settings` (`setting`, `value`) VALUES (?, ?)"
)->execute([$setting, $value]);

fwrite(STDOUT, "set $setting=$value on " . DB_NAME . "\n");
