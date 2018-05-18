<?php

namespace SteamID\calc;

class BCMATH
{
    public static function Steam2toSteam3($steamid)
    {
        $steamid = explode(':', $steamid);
        $id = bcadd(bcmul($steamid[2], 2), $steamid[1]);
        return "[U:1:$id]";
    }

    public static function Steam2toSteam64($steamid)
    {
        $steamid = explode(':', $steamid);
        $id = bcadd(bcadd(bcmul($steamid[2], 2), '76561197960265728'), $steamid[1]);
        return $id;
    }

    public static function Steam3toSteam2($steamid)
    {
        $steamid = explode(':', trim($steamid, '[]'));
        $y = bcmod($steamid[2], 2);
        $z = bcdiv($steamid[2], 2);
        return "STEAM_0:$y:$z";
    }

    public static function Steam3toSteam64($steamid)
    {
        $steamid = explode(':', trim($steamid, '[]'));
        $id = bcadd($steamid[2], '76561197960265728');
        return $id;
    }

    public static function Steam64toSteam2($steamid)
    {
        $y = bcmod($steamid, 2);
        $z = bcdiv(bcsub($steamid, '76561197960265728'), 2);
        return "STEAM_0:$y:$z";
    }

    public static function Steam64toSteam3($steamid)
    {
        $z = bcsub($steamid, '76561197960265728');
        return "[U:1:$z]";
    }
}
