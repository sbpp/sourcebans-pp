<?php

namespace SteamID\calc;

class SQL
{
    private static $dbs = null;
    public static function setDB(\Database $dbs)
    {
        self::$dbs = $dbs;
    }

    public static function Steam2toSteam3($steamid)
    {
        $steamid = explode(':', $steamid);

        self::$dbs->query("SELECT ($steamid[2] * 2 + $steamid[1]) AS z");
        $sid = self::$dbs->single();

        return "[U:1:$sid[z]]";
    }

    public static function Steam2toSteam64($steamid)
    {
        $steamid = explode(':', $steamid);

        self::$dbs->query("SELECT ($steamid[2] * 2 + 76561197960265728 + $steamid[1]) AS id");
        $sid = self::$dbs->single();

        return $sid['id'];
    }

    public static function Steam3toSteam2($steamid)
    {
        $steamid = explode(':', trim($steamid, '[]'));

        self::$dbs->query("SELECT ($steamid[2] % 2) AS y, ($steamid[2] DIV 2) AS z");
        $sid = self::$dbs->single();

        return "STEAM_0:$sid[y]:$sid[z]";
    }

    public static function Steam3toSteam64($steamid)
    {
        $steamid = explode(':', trim($steamid, '[]'));

        self::$dbs->query("SELECT ($steamid[2] + 76561197960265728) AS id");
        $sid = self::$dbs->single();

        return $sid['id'];
    }

    public static function Steam64toSteam2($steamid)
    {
        self::$dbs->query("SELECT ($steamid % 2) AS y, (($steamid - 76561197960265728) DIV 2) AS z");
        $sid = self::$dbs->single();

        return "STEAM_0:$sid[y]:$sid[z]";
    }

    public static function Steam64toSteam3($steamid)
    {
        self::$dbs->query("SELECT ($steamid - 76561197960265728) AS z");
        $sid = self::$dbs->single();

        return "[U:1:$sid[z]]";
    }
}
