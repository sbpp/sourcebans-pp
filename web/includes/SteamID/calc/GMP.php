<?php

namespace SteamID\calc;

class GMP
{
    public static function Steam2toSteam3($steamid)
    {
        $steamid = explode(':', $steamid);
        $id = gmp_add(gmp_mul($steamid[2], 2), $steamid[1]);
        return "[U:1:$id]";
    }

    public static function Steam2toSteam64($steamid)
    {
        $steamid = explode(':', $steamid);
        $id = gmp_add(gmp_add(gmp_mul($steamid[2], 2), '76561197960265728'), $steamid[1]);
        return $id;
    }

    public static function Steam3toSteam2($steamid)
    {
        $steamid = explode(':', trim($steamid, '[]'));
        $y = gmp_mod($steamid[2], 2);
        $z = gmp_div($steamid[2], 2);
        return "STEAM_0:$y:$z";
    }

    public static function Steam3toSteam64($steamid)
    {
        $steamid = explode(':', trim($steamid, '[]'));
        $id = gmp_add($steamid[2], '76561197960265728');
        return $id;
    }

    public static function Steam64toSteam2($steamid)
    {
        $y = gmp_mod($steamid, 2);
        $z = gmp_div(gmp_sub($steamid, '76561197960265728'), 2);
        return "STEAM_0:$y:$z";
    }

    public static function Steam64toSteam3($steamid)
    {
        $z = gmp_sub($steamid, '76561197960265728');
        return "[U:1:$z]";
    }
}
