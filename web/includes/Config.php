<?php

/**
 * Class Config
 */
class Config
{
    private static array $config = [];

    private static ?\Database $dbh = null;

    public static function init(Database $dbh): void
    {
        self::$dbh = $dbh;
        self::$config = self::getAll();
    }

    /**
     * @param string $setting
     * @return mixed|null
     */
    public static function get(string $setting): mixed
    {
        return self::$config[$setting] ?? null;
    }


    /**
     * @param array $keys Settings to retrieve
     * @return array
     */
    public static function getMulti(array $keys): array
    {
        $values = [];

        foreach ($keys as $key)
        {
            $values []= self::$config[$key];
        }

        return $values;
    }

    /**
     * @param string $setting
     * @return bool
     */
    public static function getBool(string $setting): bool
    {
        return (bool)self::get($setting);
    }

    /**
     * @param int $timestamp
     * @return string
     */
    public static function time(int $timestamp): string
    {
        $format = self::get('config.dateformat');
        $format = !empty($format) ? $format : 'Y-m-d H:i:s';
        return date($format, $timestamp);
    }

    /**
     * @return array
     */
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
