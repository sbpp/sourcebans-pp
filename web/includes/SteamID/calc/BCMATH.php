<?php

namespace SteamID\calc;

class BCMATH
{
    public static function Steam2toSteam3($steamid)
    {
        $steamid = explode(':', $steamid);
        $sid = bcadd(bcmul($steamid[2], 2), $steamid[1]);
        return "[U:1:$sid]";
    }

    public static function Steam2toSteam64($steamid)
    {
        $steamid = explode(':', $steamid);
        $sid = bcadd(bcadd(bcmul($steamid[2], 2), '76561197960265728'), $steamid[1]);
        return $sid;
    }

    public static function Steam3toSteam2($steamid)
    {
        $steamid = explode(':', trim($steamid, '[]'));
        $idy = bcmod($steamid[2], 2);
        $idz = bcdiv($steamid[2], 2);
        return "STEAM_0:$idy:$idz";
    }

    public static function Steam3toSteam64($steamid)
    {
        $steamid = explode(':', trim($steamid, '[]'));
        $sid = bcadd($steamid[2], '76561197960265728');
        return $sid;
    }

    public static function Steam64toSteam2($steamid)
    {
        $idy = bcmod($steamid, 2);
        $idz = bcdiv(bcsub($steamid, '76561197960265728'), 2);
        return "STEAM_0:$idy:$idz";
    }

    public static function Steam64toSteam3($steamid)
    {
        $idz = bcsub($steamid, '76561197960265728');
        return "[U:1:$idz]";
    }
}
