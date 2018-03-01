<?php

Flight::db()->query("SELECT COUNT(name) AS stopped FROM `:prefix_banlog`");
$total = Flight::db()->single();

Flight::db()->query("SELECT COUNT(bid) AS bans FROM `:prefix_bans`");
$total += Flight::db()->single();

Flight::db()->query("SELECT COUNT(bid) AS comms FROM `:prefix_comms`");
$total += Flight::db()->single();

Flight::db()->query(
    "SELECT bl.name, time, bl.sid, bl.bid, b.type, b.authid, b.ip
    FROM `:prefix_banlog` AS bl
    LEFT JOIN `:prefix_bans` AS b ON b.bid = bl.bid
    ORDER BY time DESC LIMIT 10"
);

$bans = Flight::db()->resultset();
foreach ($bans as $key => $ban) {
}




var_dump($total);
//Flight::db()->query("");
