<?php

/**
 * Class Config
 */
class Config
{
    /**
     * @var array
     */
    private static $config = [];

    /**
     * @var Database
     */
    private static $dbh = null;

    /**
     * @param Database $dbh
     */
    public static function init(Database $dbh)
    {
        self::$dbh = $dbh;
        self::$config = self::getAll();
    }

    /**
     * @param string $setting
     * @return mixed|null
     */
    public static function get($setting)
    {
        return array_key_exists($setting, self::$config) ? self::$config[$setting] : null;
    }

    /**
     * @param string $setting
     * @return bool
     */
    public static function getBool($setting)
    {
        return (bool)self::get($setting);
    }

    /**
     * @param int $timestamp
     * @return false|string
     */
    public static function time($timestamp)
    {
        $format = self::get('config.dateformat');
        $format = (!empty($format) && !is_null($format)) ? $format : 'Y-m-d H:i:s';
        return date($format, $timestamp);
    }

    /**
     * @return array
     */
    private static function getAll()
    {
        self::$dbh->query("SELECT * FROM `:prefix_settings`");
        foreach(self::$dbh->resultset() as $data) {
            $config[$data['setting']] = $data['value'];
        }
        return $config;
    }
}
