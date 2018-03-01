<?php

Flight::db()->query(
    "SELECT se.sid, se.ip, se.port, se.modid, se.rcon, md.icon
    FROM `:prefix_servers` se
    LEFT JOIN `:prefix_mods` md ON md.mid = se.modid
    WHERE se.sid > 0 AND se.enabled= 1 ORDER BY se.modid, se.sid"
);

$servers = Flight::db()->resultset();

foreach ($servers as $key => $server) {
    $servers[$key]['onClick'] = "window.location = '/servers/".$key."';";
    //Todo: add axios calls
    $servers[$key]['script'] = "";
}

return $servers;
