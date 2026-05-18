<?php
declare(strict_types=1);

// Step 6 of the install wizard — optional AMXBans import.
//
// Two-phase: GET / POST without `postd=1` renders the form, POST
// with `postd=1` runs the import and renders the same template
// with `$result_text` populated.

use Sbpp\View\Install\InstallImportView;
use Sbpp\View\Renderer;

require_once PANEL_INCLUDES_PATH . '/View/Install/InstallImportView.php';
require_once PANEL_INCLUDES_PATH . '/View/Renderer.php';
require_once PANEL_INCLUDES_PATH . '/Db/Database.php';

$server   = trim((string) ($_POST['server']   ?? ''));
$portRaw  = trim((string) ($_POST['port']     ?? '3306'));
$port     = (int) ($portRaw === '' ? '3306' : $portRaw);
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password']      ?? '');
$database = trim((string) ($_POST['database'] ?? ''));
$prefix   = trim((string) ($_POST['prefix']   ?? 'sb'));

$amxServer   = trim((string) ($_POST['amx_server']   ?? ''));
$amxPortRaw  = trim((string) ($_POST['amx_port']     ?? '3306'));
$amxPort     = (int) ($amxPortRaw === '' ? '3306' : $amxPortRaw);
$amxUsername = trim((string) ($_POST['amx_username'] ?? ''));
$amxPassword = (string) ($_POST['amx_password']      ?? '');
$amxDatabase = trim((string) ($_POST['amx_database'] ?? ''));
$amxPrefix   = trim((string) ($_POST['amx_prefix']   ?? ''));

if ($server === '' || $username === '' || $database === '' || $prefix === '') {
    header('Location: ?step=2');
    exit;
}

// Re-validate the SourceBans++ prefix on every step (#1332 review:
// critical). The :prefix replacement in the INSERT INTO :prefix_bans
// statement flows through plain str_replace, not parameterised binds.
if (!sbpp_install_validate_prefix($prefix)) {
    header('Location: ?step=2');
    exit;
}

$posted = (string) ($_POST['postd'] ?? '') === '1';
$error      = '';
$resultText = '';

if ($posted) {
    if ($amxServer === '' || $amxUsername === '' || $amxDatabase === '' || $amxPrefix === '') {
        $error = 'Please fill in the AMXBans hostname, username, database, and prefix.';
    } elseif (!sbpp_install_validate_prefix($amxPrefix)) {
        // The amx_prefix field is operator input on this page,
        // not a forwarded value. The :prefix replacement in the
        // SELECT against :prefix_bans on the SOURCE database is
        // also plain str_replace — an unvalidated amx_prefix
        // exfiltrates / mutates rows from any table the source
        // DB user can reach (#1332 review: critical 3).
        $error = 'AMXBans prefix must be 1-9 letters, digits, or underscores.';
    } else {
        try {
            $resultText = sbpp_install_amxbans_import(
                $amxServer, $amxPort, $amxDatabase, $amxUsername, $amxPassword, $amxPrefix,
                $server, $port, $database, $username, $password, $prefix
            );
        } catch (\Throwable $e) {
            $error = 'Import failed: ' . $e->getMessage();
        }
    }
}

// @phpstan-ignore variable.undefined
Renderer::render($theme, new InstallImportView(
    page_title:   'Import',
    step:         6,
    step_title:   'Import AMXBans bans',
    step_count:   5,
    step_label:   'Import (optional)',
    error:        $error,
    result_text:  $resultText,
    val_amx_server:   $amxServer,
    val_amx_port:     (string) $amxPort,
    val_amx_username: $amxUsername,
    val_amx_database: $amxDatabase,
    val_amx_prefix:   $amxPrefix,
    val_server:   $server,
    val_port:     (string) $port,
    val_username: $username,
    val_password: $password,
    val_database: $database,
    val_prefix:   $prefix,
));

/**
 * Streamlined inline AMXBans import.
 *
 * Replaces the legacy converter.inc.php (which `echo`-ed progress
 * and called `die()`) with a structured pure-render that returns
 * a status string the template surfaces in an alert.
 *
 * Returns an HTML snippet that the page renders inside an
 * install-alert--ok block (the snippet only contains controlled
 * status text — `<strong>`-wrapped count + "imported" — never
 * embeds raw user input).
 */
function sbpp_install_amxbans_import(
    string $amxServer, int $amxPort, string $amxDatabase, string $amxUsername, string $amxPassword, string $amxPrefix,
    string $newServer, int $newPort, string $newDatabase, string $newUsername, string $newPassword, string $newPrefix
): string {
    @set_time_limit(0);

    $oldDb = sbpp_install_open_db($amxServer, $amxPort, $amxDatabase, $amxUsername, $amxPassword, $amxPrefix);
    $newDb = sbpp_install_open_db($newServer, $newPort, $newDatabase, $newUsername, $newPassword, $newPrefix);

    // staabm/phpstan-dba checks SQL against the live SourceBans++
    // schema; the AMXBans schema (read-only source DB on a different
    // host) doesn't share its column shape, so the dba rule reports
    // `player_ip` / `player_id` etc. as missing. The columns are
    // correct against an AMXBans :prefix_bans table.
    // @phpstan-ignore sbpp.dba.syntaxError
    $oldDb->query(
        'SELECT `player_ip`, `player_id`, `player_nick`, `ban_created`, '
        . '`ban_length`, `ban_reason`, `admin_ip` FROM `:prefix_bans`'
    );
    $rows = $oldDb->resultset();
    /** @var list<array<string, mixed>> $rows */

    $newDb->query(
        'INSERT INTO `:prefix_bans` (ip, authid, name, created, ends, length, reason, adminIp, aid) '
        . 'VALUES (:ip, :authid, :name, :created, :ends, :length, :reason, :adminIp, :aid)'
    );

    $imported = 0;
    foreach ($rows as $value) {
        $created = (int) ($value['ban_created'] ?? 0);
        $length  = (int) ($value['ban_length']  ?? 0);
        $newDb->bind(':ip',      (string) ($value['player_ip']   ?? ''));
        $newDb->bind(':authid',  (string) ($value['player_id']   ?? ''));
        $newDb->bind(':name',    (string) ($value['player_nick'] ?? ''));
        $newDb->bind(':created', $created);
        $newDb->bind(':ends',    $length === 0 ? 0 : $created + $length);
        $newDb->bind(':length',  $length);
        $newDb->bind(':reason',  (string) ($value['ban_reason']  ?? ''));
        $newDb->bind(':adminIp', (string) ($value['admin_ip']    ?? ''));
        $newDb->bind(':aid',     0);
        $newDb->execute();
        $imported++;
    }

    return 'Imported <strong>' . $imported . '</strong> ban'
        . ($imported === 1 ? '' : 's')
        . ' from AMXBans. You can run this import again later if needed.';
}
