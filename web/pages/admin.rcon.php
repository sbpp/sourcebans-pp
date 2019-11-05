<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2019 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $theme, $userbank;

new AdminTabs([], $userbank, $theme);

$sid = (int) $_GET['id'];

// Access on that server?
$servers = $GLOBALS['db']->GetAll("SELECT `server_id`, `srv_group_id` FROM " . DB_PREFIX . "_admins_servers_groups WHERE admin_id = " . $userbank->GetAid());
$access  = false;
foreach ($servers as $server) {
    if ($server['server_id'] == $sid) {
        $access = true;
        break;
    }
    if ($server['srv_group_id'] > 0) {
        $servers_in_group = $GLOBALS['db']->GetAll("SELECT `server_id` FROM " . DB_PREFIX . "_servers_groups WHERE group_id = " . (int) $server['srv_group_id']);
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
$theme->left_delimiter  = '-{';
$theme->right_delimiter = '}-';

$theme->display('page_admin_servers_rcon.tpl');

$theme->left_delimiter  = '{';
$theme->right_delimiter = '}';
