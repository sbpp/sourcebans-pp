<?php
// Loaded by PHPStan only (see phpstan.neon `bootstrapFiles`). Mirrors the
// runtime defines from init.php and config.php so static analysis sees the
// same constant surface the application sees at runtime.

// Issue #1290 phase B: register the legacy global-name shims for the
// namespaced classes in web/includes/ before any analysis runs. The
// runtime equivalent is the require_once chain at the top of init.php /
// tests/bootstrap.php; PHPStan needs the aliases registered too because
// the procedural code in pages/*.php / api/handlers/*.php / updater/*.php
// still references the global names (`Config::`, `Log::`, `Database`,
// `Host::`, `Crypto::`, `CSRF::`, `Auth::`, `JWT`, `CUserManager`, …)
// and `class_alias()` is a runtime call PHPStan can't statically see.
// Each require_once fires the class_alias() line at the bottom of the
// namespaced file, making both names visible to the analyser. Burned in
// the call-site sweep PR (alongside the runtime aliases) once
// `rg '\b(Database|CUserManager|…)\b' web/pages web/api web/updater` is
// empty.
$_includes = __DIR__ . '/includes';
require_once $_includes . '/Security/Crypto.php';
require_once $_includes . '/Security/CSRF.php';
require_once $_includes . '/Auth/JWT.php';
require_once $_includes . '/Auth/Handler/NormalAuthHandler.php';
require_once $_includes . '/Auth/Handler/SteamAuthHandler.php';
require_once $_includes . '/Auth/Auth.php';
require_once $_includes . '/Auth/Host.php';
require_once $_includes . '/Auth/UserManager.php';
require_once $_includes . '/View/AdminTabs.php';
require_once $_includes . '/Db/Database.php';
require_once $_includes . '/Config.php';
require_once $_includes . '/Log.php';
require_once $_includes . '/Api/ApiError.php';
require_once $_includes . '/Api/Api.php';

// Web/SourceMod permission flags — defined at runtime from JSON in init.php.
foreach (['web.json', 'sourcemod.json'] as $file) {
    $path = __DIR__ . '/configs/permissions/' . $file;
    if (!is_file($path)) {
        continue;
    }
    $flags = json_decode((string) file_get_contents($path), true);
    if (!is_array($flags)) {
        continue;
    }
    foreach ($flags as $name => $perm) {
        if (!defined($name) && isset($perm['value'])) {
            define($name, $perm['value']);
        }
    }
}

// User-config constants written by the installer into the (gitignored)
// config.php. Sentinel values keep PHPStan happy without affecting runtime.
$configConstants = [
    'DB_HOST' => '',
    'DB_USER' => '',
    'DB_PASS' => '',
    'DB_NAME' => '',
    'DB_PREFIX' => '',
    'DB_PORT' => 3306,
    'DB_CHARSET' => 'utf8mb4',
    'STEAMAPIKEY' => '',
    'SB_EMAIL' => '',
    'SB_NEW_SALT' => '',
    'SB_SECRET_KEY' => '',
];
foreach ($configConstants as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}
