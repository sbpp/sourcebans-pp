<?php
declare(strict_types=1);

// Step 5 of the install wizard — admin account + final config write.
//
// Two-phase page:
//   - GET (or POST without `postd=1`): render the admin form.
//   - POST with `postd=1`: validate inputs, write config.php, run
//     data.sql, INSERT the admin row, render the success page.
//
// The config write is best-effort. If the panel root isn't writable
// for the PHP user (common on hosts where the operator chowned the
// tree before the install), the success page surfaces the rendered
// config text for manual copy-paste.

use Sbpp\View\Install\InstallAdminView;
use Sbpp\View\Install\InstallDoneView;
use Sbpp\View\Renderer;

require_once PANEL_INCLUDES_PATH . '/View/Install/InstallAdminView.php';
require_once PANEL_INCLUDES_PATH . '/View/Install/InstallDoneView.php';
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
$sbEmail  = trim((string) ($_POST['sb-email'] ?? ''));
$charset  = trim((string) ($_POST['charset']  ?? 'utf8mb4'));

$uname    = trim((string) ($_POST['uname'] ?? ''));
$steam    = trim((string) ($_POST['steam'] ?? ''));
$email    = trim((string) ($_POST['email'] ?? ''));
$pass1    = (string) ($_POST['pass1'] ?? '');
$pass2    = (string) ($_POST['pass2'] ?? '');

$posted = (string) ($_POST['postd'] ?? '') === '1';

if ($server === '' || $username === '' || $database === '' || $prefix === '') {
    header('Location: ?step=2');
    exit;
}

// Re-validate the prefix on every step (#1332 review: critical).
// {prefix} substitution in data.sql + the :prefix replacement in
// the admin INSERT both flow through plain str_replace, not
// parameterised binds — an unvalidated prefix carries arbitrary
// DDL/DML straight into the seed pass.
if (!sbpp_install_validate_prefix($prefix)) {
    header('Location: ?step=2');
    exit;
}

if ($posted) {
    $error = '';

    if ($uname === '' || $steam === '' || $email === '' || $pass1 === '' || $pass2 === '') {
        $error = 'All fields are required.';
    } elseif (strlen($pass1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif (preg_match('/^STEAM_[01]:[01]:[0-9]+$/', $steam) !== 1) {
        $error = 'Steam ID must be in STEAM_0:X:NNNNNNN format.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Email address is invalid.';
    }

    if ($error === '') {
        try {
            $db = sbpp_install_open_db($server, $port, $database, $username, $password, $prefix);

            // 1) Insert the admin row. Authid normalised STEAM_1 →
            //    STEAM_0 to match the legacy installer's behaviour;
            //    the panel's runtime expects the STEAM_0 form.
            $authid = str_replace('STEAM_1', 'STEAM_0', $steam);
            $db->query(
                'INSERT INTO `:prefix_admins` '
                . '(user, authid, password, gid, email, extraflags, immunity) '
                . 'VALUES (:user, :authid, :password, :gid, :email, :extraflags, :immunity)'
            );
            $db->bind(':user',       $uname);
            $db->bind(':authid',     $authid);
            $db->bind(':password',   password_hash($pass1, PASSWORD_BCRYPT));
            $db->bind(':gid',        -1);
            $db->bind(':email',      $email);
            $db->bind(':extraflags', 1 << 24); // ADMIN_OWNER
            $db->bind(':immunity',   100);
            $db->execute();

            // 2) Run data.sql to seed sb_settings + sb_mods +
            //    CONSOLE admin row.
            $dataPath = INCLUDES_PATH . '/sql/data.sql';
            if (!file_exists($dataPath)) {
                throw new \RuntimeException('Seed file missing: ' . $dataPath);
            }
            $sql = (string) file_get_contents($dataPath);
            $sql = str_replace('{prefix}', $prefix, $sql);

            foreach (explode(';', $sql) as $stmt) {
                $trimmed = trim($stmt);
                if (strlen($trimmed) <= 2) {
                    continue;
                }
                $db->query($trimmed);
                $db->execute();
            }

            // 3) Build + write config.php (best-effort).
            $configText = sbpp_install_render_config(
                $server, $port, $username, $password, $database,
                $prefix, $charset, $apikey, $sbEmail
            );
            $configPath = PANEL_ROOT . 'config.php';
            $configWritable = is_writable($configPath)
                || (is_writable(PANEL_ROOT) && !file_exists($configPath));
            if ($configWritable) {
                file_put_contents($configPath, $configText);
            }

            // 4) Build the SourceMod databases.cfg snippet for the
            //    operator to paste into their gameserver config.
            $databasesCfg = sbpp_install_render_databases_cfg(
                $server, $port, $username, $password, $database
            );

            // @phpstan-ignore variable.undefined
            Renderer::render($theme, new InstallDoneView(
                page_title:  'Done',
                step:        5,
                step_title:  'Installation complete',
                step_count:  5,
                step_label:  'Done',
                config_writable:    $configWritable,
                config_text:        $configText,
                databases_cfg:      $databasesCfg,
                show_local_warning: strtolower($server) === 'localhost' || $server === '127.0.0.1',
                val_server:   $server,
                val_port:     (string) $port,
                val_username: $username,
                val_password: $password,
                val_database: $database,
                val_prefix:   $prefix,
            ));
            return;
        } catch (\Throwable $e) {
            $error = 'Could not finalise install: ' . $e->getMessage();
        }
    }

    // @phpstan-ignore variable.undefined
    Renderer::render($theme, new InstallAdminView(
        page_title:   'Admin',
        step:         5,
        step_title:   'Create admin account',
        step_count:   5,
        step_label:   'Admin',
        error:        $error,
        val_uname:    $uname,
        val_steam:    $steam,
        val_email:    $email,
        val_server:   $server,
        val_port:     (string) $port,
        val_username: $username,
        val_password: $password,
        val_database: $database,
        val_prefix:   $prefix,
        val_apikey:   $apikey,
        val_sb_email: $sbEmail,
        val_charset:  $charset,
    ));
    return;
}

// First arrival from step 4 (no `postd=1`) — render the empty form.
// @phpstan-ignore variable.undefined
Renderer::render($theme, new InstallAdminView(
    page_title:   'Admin',
    step:         5,
    step_title:   'Create admin account',
    step_count:   5,
    step_label:   'Admin',
    error:        '',
    val_uname:    '',
    val_steam:    '',
    val_email:    '',
    val_server:   $server,
    val_port:     (string) $port,
    val_username: $username,
    val_password: $password,
    val_database: $database,
    val_prefix:   $prefix,
    val_apikey:   $apikey,
    val_sb_email: $sbEmail,
    val_charset:  $charset,
));

/**
 * Render the panel's config.php content with the validated values
 * baked in.
 *
 * String literals use single quotes around the value. The values
 * themselves are escaped against `'` and `\` to prevent breakouts;
 * the validation in step 2 already rejected most weird shapes
 * (prefix is `[A-Za-z0-9_]`, port is int) but defence in depth
 * is cheap.
 */
function sbpp_install_render_config(
    string $server,
    int $port,
    string $username,
    string $password,
    string $database,
    string $prefix,
    string $charset,
    string $apikey,
    string $sbEmail
): string {
    $esc = static fn(string $v): string => str_replace(["\\", "'"], ["\\\\", "\\'"], $v);

    $secret = base64_encode(random_bytes(47));

    return "<?php\n"
        . "// Generated by the SourceBans++ install wizard.\n"
        . "define('DB_HOST', '"      . $esc($server)   . "');\n"
        . "define('DB_USER', '"      . $esc($username) . "');\n"
        . "define('DB_PASS', '"      . $esc($password) . "');\n"
        . "define('DB_NAME', '"      . $esc($database) . "');\n"
        . "define('DB_PREFIX', '"    . $esc($prefix)   . "');\n"
        . "define('DB_PORT', '"      . $port           . "');\n"
        . "define('DB_CHARSET', '"   . $esc($charset)  . "');\n"
        . "define('STEAMAPIKEY', '"  . $esc($apikey)   . "');\n"
        . "define('SB_EMAIL', '"     . $esc($sbEmail)  . "');\n"
        . "define('SB_NEW_SALT', '\$5\$');\n"
        . "define('SB_SECRET_KEY', '" . $esc($secret) . "');\n";
}

/**
 * Render the SourceMod-side databases.cfg snippet.
 *
 * Indented by one tab so it slots into a parent
 * `"Databases" { ... }` block without reformatting.
 *
 * String values run through sbpp_install_kv_escape() so embedded `"`
 * and `\` characters are properly escaped per SourceMod KeyValues
 * quoting rules (#1332 review: major). Without escaping, a password
 * like `p"a"ss` rendered as `"pass"     "p"a"ss"` — five unescaped
 * quotes — and the gameserver's KeyValues parser silently
 * mis-loads the file (or rejects it outright).
 */
function sbpp_install_render_databases_cfg(
    string $server,
    int $port,
    string $username,
    string $password,
    string $database
): string {
    return "\t\"sourcebans\"\n"
        . "\t{\n"
        . "\t\t\"driver\"   \"default\"\n"
        . "\t\t\"host\"     \"" . sbpp_install_kv_escape($server)   . "\"\n"
        . "\t\t\"database\" \"" . sbpp_install_kv_escape($database) . "\"\n"
        . "\t\t\"user\"     \"" . sbpp_install_kv_escape($username) . "\"\n"
        . "\t\t\"pass\"     \"" . sbpp_install_kv_escape($password) . "\"\n"
        . "\t\t\"port\"     \"" . $port     . "\"\n"
        . "\t}\n";
}
