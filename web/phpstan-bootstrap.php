<?php
// Loaded by PHPStan only (see phpstan.neon `bootstrapFiles`). Mirrors the
// runtime defines from init.php and config.php so static analysis sees the
// same constant surface the application sees at runtime.

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
    'DB_CHARSET' => 'utf8',
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
