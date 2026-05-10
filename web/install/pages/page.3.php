<?php
declare(strict_types=1);

// Step 3 of the install wizard — environment requirements check.
//
// Reads the DB credentials forwarded from step 2 (via the handoff
// page), runs PHP / database / filesystem checks, renders the
// results table.
//
// Re-uses page.2.php's POST-validation contract: if any required
// DB credentials are missing, send the operator back to step 2.

use Sbpp\View\Install\InstallRequirementsView;
use Sbpp\View\Renderer;

require_once PANEL_INCLUDES_PATH . '/View/Install/InstallRequirementsView.php';
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
    // Forward back to step 2 with a clean form. The operator
    // probably refreshed step 3 directly (no POST) — surface
    // the form again instead of crashing.
    header('Location: ?step=2');
    exit;
}

// Re-validate the prefix on every step (#1332 review: critical).
// Step 2 validates on first submit, but a direct request to
// /install/?step=3 with a crafted hidden POST bypasses that gate.
// The {prefix} substitution in struc.sql / data.sql is plain
// str_replace (not parameterised), so an unvalidated prefix
// injects arbitrary DDL/DML — defence-in-depth at every step.
if (!sbpp_install_validate_prefix($prefix)) {
    header('Location: ?step=2');
    exit;
}

$errors   = 0;
$warnings = 0;

// PHP requirements
$phpRows = [];

// PHPStan sees the analyser's PHP_VERSION as a fixed string (the
// container is locked to 8.5+) so the `version_compare(...)` here
// looks always-true to it. The check still has runtime value: a
// self-hoster on PHP 8.4 hitting this surface is exactly who needs
// the "you're on too old a version" feedback.
$phpVersionOk = version_compare(PHP_VERSION, '8.5', '>=');
$phpRows[] = [
    'label'    => 'PHP version',
    'required' => '8.5 or newer',
    // @phpstan-ignore ternary.alwaysTrue
    'status'   => $phpVersionOk ? 'ok' : 'err',
    'detail'   => PHP_VERSION,
];
// @phpstan-ignore booleanNot.alwaysFalse
if (!$phpVersionOk) {
    $errors++;
}

$fileUploads = (bool) ini_get('file_uploads');
$phpRows[] = [
    'label'    => 'File uploads',
    'required' => 'Enabled',
    'status'   => $fileUploads ? 'ok' : 'err',
    'detail'   => $fileUploads ? 'Enabled' : 'Disabled',
];
if (!$fileUploads) {
    $errors++;
}

foreach (['openssl', 'xml', 'gmp', 'pdo_mysql', 'mbstring'] as $ext) {
    $loaded = extension_loaded($ext);
    $phpRows[] = [
        'label'    => $ext . ' extension',
        'required' => 'Loaded',
        'status'   => $loaded ? 'ok' : 'err',
        'detail'   => $loaded ? 'Loaded' : 'Missing',
    ];
    if (!$loaded) {
        $errors++;
    }
}

$sendmail = (string) ini_get('sendmail_path');
$phpRows[] = [
    'label'    => 'sendmail_path',
    'required' => 'Recommended',
    'status'   => $sendmail !== '' ? 'ok' : 'warn',
    'detail'   => $sendmail !== '' ? $sendmail : 'Empty (email features may not work)',
];
if ($sendmail === '') {
    $warnings++;
}

// Database requirements
$dbRows = [];
$sqlVersion = '';
try {
    $db = sbpp_install_open_db($server, $port, $database, $username, $password, $prefix);
    $db->query('SELECT VERSION() AS version');
    $row = $db->single();
    $sqlVersion = (string) ($row['version'] ?? '');
} catch (\Throwable $e) {
    $errors++;
    $dbRows[] = [
        'label'    => 'Database connection',
        'required' => 'OK',
        'status'   => 'err',
        'detail'   => 'Could not connect: ' . $e->getMessage(),
    ];
}

if ($sqlVersion !== '') {
    $isMariaDb = stripos($sqlVersion, 'mariadb') !== false;
    if ($isMariaDb) {
        $needs = '10.0.5';
        $okVer = version_compare($sqlVersion, $needs, '>=');
    } else {
        $needs = '5.5';
        $okVer = version_compare($sqlVersion, $needs, '>=');
    }
    $dbRows[] = [
        'label'    => 'Database version',
        'required' => $isMariaDb ? 'MariaDB ' . $needs . '+' : 'MySQL ' . $needs . '+',
        'status'   => $okVer ? 'ok' : 'err',
        'detail'   => $sqlVersion,
    ];
    if (!$okVer) {
        $errors++;
    }
}

// Filesystem requirements
$fsRows = [];
$panelRoot = PANEL_ROOT;
$fsTargets = [
    'demos folder'         => $panelRoot . 'demos',
    'cache folder'         => $panelRoot . 'cache',
    'mod icon folder'      => $panelRoot . 'images/games',
    'map image folder'     => $panelRoot . 'images/maps',
];
foreach ($fsTargets as $label => $path) {
    $writable = is_writable($path);
    $exists   = is_dir($path);
    $status   = $exists && $writable ? 'ok' : 'err';
    $detail   = !$exists
        ? 'Missing: ' . $path
        : ($writable ? 'Writable' : 'Not writable: ' . $path);
    $fsRows[] = [
        'label'    => ucfirst($label),
        'required' => 'Writable',
        'status'   => $status,
        'detail'   => $detail,
    ];
    if ($status === 'err') {
        $errors++;
    }
}

$configWritable = is_writable($panelRoot . 'config.php')
    || is_writable($panelRoot);
$fsRows[] = [
    'label'    => 'config.php writable',
    'required' => 'Recommended',
    'status'   => $configWritable ? 'ok' : 'warn',
    'detail'   => $configWritable
        ? 'Writable'
        : 'Not writable — wizard will display the config to copy manually.',
];
if (!$configWritable) {
    $warnings++;
}

$groups = [
    ['title' => 'PHP requirements',        'rows' => $phpRows],
    ['title' => 'Database requirements',   'rows' => $dbRows],
    ['title' => 'Filesystem requirements', 'rows' => $fsRows],
];

// @phpstan-ignore variable.undefined
Renderer::render($theme, new InstallRequirementsView(
    page_title:   'Requirements',
    step:         3,
    step_title:   'Environment check',
    step_count:   5,
    step_label:   'Requirements',
    groups:       $groups,
    can_continue: $errors === 0,
    errors:       $errors,
    warnings:     $warnings,
    val_server:   $server,
    val_port:     (string) $port,
    val_username: $username,
    val_password: $password,
    val_database: $database,
    val_prefix:   $prefix,
    val_apikey:   $apikey,
    val_email:    $email,
));
