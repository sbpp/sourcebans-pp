<?php
declare(strict_types=1);

// Step 2 of the install wizard — DB connection details.
//
// On GET (or POST without `postd=1`): render an empty form.
// On POST with `postd=1`: validate, attempt a real PDO connection,
// and on success render the auto-submitting handoff form to step 3
// (page.2.handoff.tpl). On failure: render the form with the prior
// values pre-filled and an inline error.

use Sbpp\View\Install\InstallDatabaseHandoffView;
use Sbpp\View\Install\InstallDatabaseView;
use Sbpp\View\Renderer;

require_once PANEL_INCLUDES_PATH . '/View/Renderer.php';
require_once PANEL_INCLUDES_PATH . '/View/Install/InstallDatabaseView.php';
require_once PANEL_INCLUDES_PATH . '/View/Install/InstallDatabaseHandoffView.php';

$server   = trim((string) ($_POST['server']   ?? 'localhost'));
$portRaw  = trim((string) ($_POST['port']     ?? '3306'));
$port     = (int) ($portRaw === '' ? '3306' : $portRaw);
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password']      ?? '');
$database = trim((string) ($_POST['database'] ?? ''));
$prefix   = trim((string) ($_POST['prefix']   ?? 'sb'));
$apikey   = trim((string) ($_POST['apikey']   ?? ''));
$email    = trim((string) ($_POST['sb-email'] ?? ''));

$posted = (string) ($_POST['postd'] ?? '') === '1';
$error  = '';

if ($posted) {
    if ($server === '' || $username === '' || $database === '' || $prefix === '') {
        $error = 'Please fill in the hostname, username, database name, and table prefix.';
    } elseif ($port < 1 || $port > 65535) {
        $error = 'Port must be between 1 and 65535.';
    } elseif (!sbpp_install_validate_prefix($prefix)) {
        $error = 'Table prefix must be 1-9 letters, digits, or underscores.';
    } else {
        try {
            $dsn = 'mysql:host=' . $server . ';port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';
            new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            // Connection OK — emit the auto-submit handoff form
            // to step 3 with the validated values as hidden fields.
            // @phpstan-ignore variable.undefined
            Renderer::render($theme, new InstallDatabaseHandoffView(
                page_title:  'Connecting',
                step:        2,
                step_title:  'Connection successful',
                step_count:  5,
                step_label:  'Continuing',
                next_step:   3,
                val_server:   $server,
                val_port:     (string) $port,
                val_username: $username,
                val_password: $password,
                val_database: $database,
                val_prefix:   $prefix,
                val_apikey:   $apikey,
                val_email:    $email,
            ));
            return;
        } catch (\PDOException $e) {
            // Issue #1335 m4: pre-fix this surfaced the raw PDO
            // message — `SQLSTATE[HY000] [1045] Access denied for
            // user 'sourcebans'@'192.168.96.5' (using password:
            // YES)` — which is gibberish to non-DBAs and the IP is
            // the panel-as-seen-by-DB internal address (minor
            // information disclosure). Translate the common error
            // codes; fall back to the raw message for
            // unrecognised codes so debugging stays possible.
            $error = sbpp_install_translate_pdo_error($e, $server, $username, $database);
        }
    }
}

// @phpstan-ignore variable.undefined
Renderer::render($theme, new InstallDatabaseView(
    page_title:   'Database',
    step:         2,
    step_title:   'Database details',
    step_count:   5,
    step_label:   'Database',
    error:        $error,
    val_server:   $server,
    val_port:     (string) $port,
    val_username: $username,
    val_password: $password,
    val_database: $database,
    val_prefix:   $prefix,
    val_apikey:   $apikey,
    val_email:    $email,
));
