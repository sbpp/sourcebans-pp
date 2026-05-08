<?php

namespace Sbpp;

use Sbpp\Db\Database;

/**
 * Class Config
 */
final class Config
{
    private static array $config = [];

    private static ?Database $dbh = null;

    public static function init(Database $dbh): void
    {
        self::$dbh = $dbh;
        self::$config = self::getAll();
    }

    public static function get(string $setting): mixed
    {
        return self::$config[$setting] ?? null;
    }


    public static function getMulti(array $keys): array
    {
        $values = [];

        foreach ($keys as $key)
        {
            $values []= self::$config[$key];
        }

        return $values;
    }

    public static function getBool(string $setting): bool
    {
        return (bool)self::get($setting);
    }

    public static function time(int $timestamp): string
    {
        $format = self::get('config.dateformat');
        $format = !empty($format) ? $format : 'Y-m-d H:i:s';
        return date($format, $timestamp);
    }

    private static function getAll(): array
    {
        $config = [];
        self::$dbh->query("SELECT * FROM `:prefix_settings`");
        foreach(self::$dbh->resultset() as $data) {
            $config[$data['setting']] = $data['value'];
        }
        return $config;
    }
}

// Issue #1290 phase B: legacy global-name shim. Procedural code keeps
// using `\Config::get(...)` / `\Config::init(...)` until the call-site
// sweep PR.
class_alias(\Sbpp\Config::class, 'Config');
