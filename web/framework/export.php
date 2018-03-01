<?php

Flight::map('exportBans', function ($type) {
    if (!Flight::access()->check(ADMIN_OWNER) || !Flight::config()->get('config.exportpublic')) {
        Flight::redirect('/');
    }

    header('Content-Type: application/x-httpd-php php');
    $file = ($type === 'ip') ? 'banned_ip.cfg' : 'banned_user.cfg';
    header('Content-Disposition: attachment; filename="'.$file.'"');
    Flight::db()->query("SELECT :id AS data FROM `:prefix_bans` WHERE length = 0 AND RemoveType IS NULL AND type = :type");
    Flight::db()->bind(':id', ($type === 'ip') ? 'ip' : 'authid');
    Flight::db()->bind(':type', ($type === 'ip') ? 1 : 0);

    $data = Flight::db()->resultset();
    foreach ($data as $ban) {
        print(($type === 'ip') ? 'addip' : 'banid'." 0 ".$ban['data']."\r\n");
    }
});

Flight::map('getDemo', function ($id, $type) {
    Flight::db()->query("SELECT filename, origname FROM `:prefix_demos` WHERE demtype = :type AND demid = :id");
    Flight::db()->bind(':type', $type);
    Flight::db()->bind(':id', $id);

    $demo = Flight::db()->single();
    if (!$demo) {
        throw new Exception("Demo not found.");
    }
    $demo['filename'] = basename($demo['filename']);
    if (!in_array($demo['filename'], scandir(DEMOS)) || !file_exists(DEMOS.'/'.$demo['filename'])) {
        throw new Exception("File not found.");
    }

    header('Content-type: application/force-download');
    header('Content-Transfer-Encoding: Binary');
    header('Content-disposition: attachment; filename="'.$demo['origname'].'"');
    //header("Content-Length: ".filesize(DEMOS."/".$demo['filename']));
    readfile(DEMOS."/".$demo['filename']);
});
