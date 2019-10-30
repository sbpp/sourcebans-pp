<?php
include_once("init.php");
if (!$userbank->HasAccess(ADMIN_OWNER) && !Config::getBool('config.exportpublic')) {
    echo "Don't have access to this feature.";
} else if (!isset($_GET['type'])) {
    echo "You have to specify the type. Only follow links!";
} else {
    if ($_GET['type'] == 'steam') {
        header('Content-Type: application/x-httpd-php php');
        header('Content-Disposition: attachment; filename="banned_user.cfg"');

        $GLOBALS['PDO']->query("SELECT authid FROM `:prefix_bans` WHERE length = 0 AND RemoveType IS NULL AND type = 0");
        foreach ($GLOBALS['PDO']->resultset() as $ban) {
            print("banid 0 $ban[authid]"."\r\n");
        }
    } elseif ($_GET['type'] == 'ip') {
        header('Content-Type: application/x-httpd-php php');
        header('Content-Disposition: attachment; filename="banned_ip.cfg"');

        $GLOBALS['PDO']->query("SELECT ip FROM `:prefix_bans` WHERE length = 0 AND RemoveType IS NULL AND type = 1");
        foreach($GLOBALS['PDO']->resultset() as $ban) {
            print("addip 0 $ban[ip]"."\r\n");
        }
    }
}
