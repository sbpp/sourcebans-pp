<?php

class Config
{
    private static $config = [];
    private static $dbh = null;

    public static function init(Database $dbh)
    {
        self::$dbh = $dbh;
        self::$config = self::getAll();
    }

    public static function get($setting)
    {
        return array_key_exists($setting, self::$config) ? self::$config[$setting] : null;
    }

    public static function getBool($setting)
    {
        return (bool)self::get($setting);
    }

    public static function time($timestamp)
    {
        $format = self::get('config.dateformat');
        $format = (!empty($format) && !is_null($format)) ? $format : 'Y-m-d H:i:s';
        return date($format, $timestamp);
    }

    private static function getAll()
    {
        self::$dbh->query("SELECT * FROM `:prefix_settings`");
        foreach(self::$dbh->resultset() as $data) {
            $config[$data['setting']] = $data['value'];
        }
        return $config;
    }
}
