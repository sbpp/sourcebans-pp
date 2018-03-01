<?php

Flight::route('GET /', function () {
    require_once(TEMPLATES.'home.php');
    Flight::buildPage('test', ['title' => 'Home']);
});

Flight::route('GET /login', function () {
});

Flight::route('GET /logout', function () {
});

Flight::route('GET /submit', function () {
});

Flight::route('GET /bans', function () {
});

Flight::route('GET /comms', function () {
});

Flight::route('GET /servers(/@open:[0-9]+)', function ($open) {
    //Templates is misleading more like models
    $servers = require_once(TEMPLATES.'servers.php');
    Flight::buildPage('servers', [
        'title' => 'Serverlist',
        'servers' => $servers,
        'openServer' => (!is_null($open)) ? $open : -1
    ]);
});

Flight::route('GET /protest', function () {
});

Flight::route('GET /account', function () {
});

Flight::route('GET /lostpassword', function () {
});

Flight::route('GET /home', function () {
});

Flight::route('GET /export/@type:ip|steam', function ($type) {
    Flight::exportBans($type);
});

Flight::route('GET /demo/get/@id:[0-9]+/@type:B|S', function ($id, $type) {
    Flight::getDemo($id, $type);
});

Flight::route('GET /debug/serverconnection/@ip/@port(/@rcon)', function ($ip, $port, $rcon) {
    Flight::debugConnection($ip, $port, $rcon);
});
