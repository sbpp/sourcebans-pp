<?php
declare(strict_types=1);

// Step 4 of the install wizard — schema install.
//
// Reads validated DB credentials from POST, runs install/includes/sql/struc.sql
// against the live DB (replacing {prefix} + {charset} placeholders),
// and renders the success/failure result.

use Sbpp\View\Install\InstallSchemaView;
use Sbpp\View\Renderer;

require_once PANEL_INCLUDES_PATH . '/View/Install/InstallSchemaView.php';
require_once PANEL_INCLUDES_PATH . '/View/Renderer.php';
require_once PANEL_INCLUDES_PATH . '/Db/Database.php';

$server   = trim((string) ($_POST['server']   ?? ''));
$portRaw  = trim((string) ($_POST['port']     ?? '3306'));
$port     = (int) ($portRaw === '' ? '3306' : $portRaw);
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password']      ?? '');
$database = trim((string) ($_POST['database'] ?? ''));
$prefix   = trim((string) ($_POST['prefix']   ?? 'sb'));
$apikey   = trim((string) ($_POST['apikey']   ?? ''));
$email    = trim((string) ($_POST['sb-email'] ?? ''));

if ($server === '' || $username === '' || $database === '' || $prefix === '') {
    header('Location: ?step=2');
    exit;
}

// Re-validate the prefix on every step (#1332 review: critical).
// {prefix} substitution in struc.sql is plain str_replace, not
// parameterised — an unvalidated prefix carries arbitrary DDL/DML
// straight into the schema-install pass.
if (!sbpp_install_validate_prefix($prefix)) {
    header('Location: ?step=2');
    exit;
}

$success = false;
$errorsText = '';
$tablesCreated = 0;
$charset = 'utf8mb4';

try {
    $db = sbpp_install_open_db($server, $port, $database, $username, $password, $prefix);

    $db->query('SELECT VERSION() AS version');
    $row = $db->single();
    $sqlVersion = (string) ($row['version'] ?? '');
    if ($sqlVersion !== '' && version_compare($sqlVersion, '5.5.3', '<')) {
        $charset = 'utf8';
    }

    $strucPath = INCLUDES_PATH . '/sql/struc.sql';
    if (!file_exists($strucPath)) {
        throw new \RuntimeException('Schema file missing: ' . $strucPath);
    }

    $sql = (string) file_get_contents($strucPath);
    $sql = str_replace(['{prefix}', '{charset}'], [$prefix, $charset], $sql);

    $stmts = explode(';', $sql);
    $errCount = 0;
    foreach ($stmts as $stmt) {
        $trimmed = trim($stmt);
        if (strlen($trimmed) <= 2) {
            continue;
        }
        $db->query($trimmed);
        if (!$db->execute()) {
            $errCount++;
            continue;
        }
        if (preg_match('/^\s*CREATE\s+TABLE/i', $trimmed) === 1) {
            $tablesCreated++;
        }
    }

    if ($errCount > 0) {
        $errorsText = $errCount . ' statement(s) failed during schema creation.';
    } else {
        $success = true;
    }
} catch (\Throwable $e) {
    $errorsText = $e->getMessage();
}

// @phpstan-ignore variable.undefined
Renderer::render($theme, new InstallSchemaView(
    page_title:   'Schema',
    step:         4,
    step_title:   'Database schema',
    step_count:   5,
    step_label:   'Schema',
    success:        $success,
    errors_text:    $errorsText,
    tables_created: $tablesCreated,
    charset:        $charset,
    val_server:   $server,
    val_port:     (string) $port,
    val_username: $username,
    val_password: $password,
    val_database: $database,
    val_prefix:   $prefix,
    val_apikey:   $apikey,
    val_email:    $email,
));
