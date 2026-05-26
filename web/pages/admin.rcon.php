<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $theme, $userbank;

new AdminTabs([], $userbank, $theme);

$sid = (int) $_GET['id'];

// Access on that server?
$GLOBALS['PDO']->query("SELECT `server_id`, `srv_group_id` FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid");
$GLOBALS['PDO']->bind(':aid', $userbank->GetAid());
$servers = $GLOBALS['PDO']->resultset();
$access  = false;
foreach ($servers as $server) {
    if ($server['server_id'] == $sid) {
        $access = true;
        break;
    }
    if ($server['srv_group_id'] > 0) {
        $GLOBALS['PDO']->query("SELECT `server_id` FROM `:prefix_servers_groups` WHERE group_id = :gid");
        $GLOBALS['PDO']->bind(':gid', (int) $server['srv_group_id']);
        $servers_in_group = $GLOBALS['PDO']->resultset();
        foreach ($servers_in_group as $servig) {
            if ($servig['server_id'] == $sid) {
                $access = true;
                break 2;
            }
        }
    }
}

$theme->assign('id', $sid);
$theme->assign('permission_rcon', ($access && $userbank->HasAccess(SM_RCON . SM_ROOT)));
$theme->setLeftDelimiter('-{');
$theme->setRightDelimiter('}-');

$theme->display('page_admin_servers_rcon.tpl');

$theme->setLeftDelimiter('{');
$theme->setRightDelimiter('}');
