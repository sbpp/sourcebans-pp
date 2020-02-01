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
global $userbank, $theme;

new AdminTabs([
    ['name' => 'List admins', 'permission' => ADMIN_OWNER|ADMIN_LIST_ADMINS],
    ['name' => 'Add new admin', 'permission' => ADMIN_OWNER|ADMIN_ADD_ADMINS],
    ['name' => 'Overrides', 'permission' => ADMIN_OWNER|ADMIN_ADD_ADMINS]
], $userbank, $theme);

$AdminsPerPage = SB_BANS_PER_PAGE;
$page = 1;
$join = "";
$where = "";
$advSearchString = "";
if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page = intval($_GET['page']);
}
if (isset($_GET['advSearch'])) {
    // Escape the value, but strip the leading and trailing quote
    $value = substr($GLOBALS['db']->qstr($_GET['advSearch']), 1, -1);
    $type = $_GET['advType'];
    switch ($type) {
        case "name":
            $where = " AND ADM.user LIKE '%" . $value . "%'";
            break;
        case "steamid":
            $where = " AND ADM.authid = '" . $value . "'";
            break;
        case "steam":
            $where = " AND ADM.authid LIKE '%" . $value . "%'";
            break;
        case "admemail":
            $where = " AND ADM.email LIKE '%" . $value . "%'";
            break;
        case "webgroup":
            $where = " AND ADM.gid = '" . $value . "'";
            break;
        case "srvadmgroup":
            $where = " AND ADM.srv_group = '" . $value . "'";
            break;
        case "srvgroup":
            $where = " AND SG.srv_group_id = '" . $value . "'";
            $join = " LEFT JOIN `" . DB_PREFIX . "_admins_servers_groups` AS SG ON SG.admin_id = ADM.aid";
            break;
        case "admwebflag":
            $findflags = explode(",", $value);
            foreach ($findflags as $flag) {
                $flags[] = constant($flag);
            }
            $flagstring = implode('|', $flags);
            $alladmins = $GLOBALS['db']->Execute("SELECT aid FROM `" . DB_PREFIX . "_admins` WHERE aid > 0");
            while (!$alladmins->EOF) {
                if ($userbank->HasAccess($flagstring, $alladmins->fields["aid"])) {
                    if (!isset($accessaid)) {
                        $accessaid = $alladmins->fields["aid"];
                    }
                    $accessaid .= ",".$alladmins->fields["aid"];
                }
                $alladmins->MoveNext();
            }
            $where = " AND ADM.aid IN(".$accessaid.")";
            break;
        case "admsrvflag":
            $findflags = explode(",", $value);
            foreach ($findflags as $flag) {
                $flags[] = constant($flag);
            }
            $alladmins = $GLOBALS['db']->Execute("SELECT aid, authid FROM `" . DB_PREFIX . "_admins` WHERE aid > 0");
            while (!$alladmins->EOF) {
                foreach ($flags as $fla) {
                    if ($userbank->HasAccess($fla, $alladmins->fields["authid"])) {
                        if (!isset($accessaid)) {
                            $accessaid = $alladmins->fields["aid"];
                        }
                        $accessaid .= ",".$alladmins->fields["aid"];
                    }
                }
                if ($userbank->HasAccess(SM_ROOT, $alladmins->fields["authid"])) {
                    if (!isset($accessaid)) {
                        $accessaid = $alladmins->fields["aid"];
                    }
                    $accessaid .= ",".$alladmins->fields["aid"];
                }
                $alladmins->MoveNext();
            }
            $where = " AND ADM.aid IN(".$accessaid.")";
            break;
        case "server":
            $where = " AND (ASG.server_id = '" . $value . "' OR SG.server_id = '" . $value . "')";
            $join = " LEFT JOIN `" . DB_PREFIX . "_admins_servers_groups` AS ASG ON ASG.admin_id = ADM.aid LEFT JOIN `" . DB_PREFIX . "_servers_groups` AS SG ON SG.group_id = ASG.srv_group_id";
            break;
        default:
            $_GET['advSearch'] = "";
            $_GET['advType'] = "";
            $where = "";
            break;
    }
        $advSearchString = "&advSearch=".$_GET['advSearch']."&advType=".$_GET['advType'];
}
$admins = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_admins` AS ADM".$join." WHERE ADM.aid > 0".$where." ORDER BY user LIMIT " . intval(($page-1) * $AdminsPerPage) . "," . intval($AdminsPerPage));
// quick fix for the server search showing admins mulitple times.
if (isset($_GET['advSearch']) && isset($_GET['advType']) && $_GET['advType'] == 'server') {
    $aadm = array();
    $num = 0;
    foreach ($admins as $aadmin) {
        if (!in_array($aadmin['aid'], $aadm)) {
            $aadm[] = $aadmin['aid'];
        } else {
            unset($admins[$num]);
        }
        $num++;
    }
}

$query = $GLOBALS['db']->GetRow("SELECT COUNT(ADM.aid) AS cnt FROM `" . DB_PREFIX . "_admins` AS ADM".$join." WHERE ADM.aid > 0".$where);
$admin_count = $query['cnt'];

if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page = intval($_GET['page']);
}

$AdminsStart = intval(($page - 1) * $AdminsPerPage);
$AdminsEnd   = intval($AdminsStart + $AdminsPerPage);
if ($AdminsEnd > $admin_count) {
    $AdminsEnd = $admin_count;
}

// List Page
$admin_list = array();
foreach ($admins as $admin) {
    $admin['immunity']     = $userbank->GetProperty("srv_immunity", $admin['aid']);
    $admin['web_group']    = $userbank->GetProperty("group_name", $admin['aid']);
    $admin['server_group'] = $userbank->GetProperty("srv_groups", $admin['aid']);
    if (empty($admin['web_group']) || $admin['web_group'] == " ") {
        $admin['web_group'] = "No Group/Individual Permissions";
    }
    if (empty($admin['server_group']) || $admin['server_group'] == " ") {
        $admin['server_group'] = "No Group/Individual Permissions";
    }
    $num               = $GLOBALS['db']->GetRow("SELECT count(authid) AS num FROM `" . DB_PREFIX . "_bans` WHERE aid = '" . $admin['aid'] . "'");
    $admin['bancount'] = $num['num'];

    $nodem                = $GLOBALS['db']->GetRow("SELECT count(B.bid) AS num FROM `" . DB_PREFIX . "_bans` AS B WHERE aid = '" . $admin['aid'] . "' AND NOT EXISTS (SELECT D.demid FROM `" . DB_PREFIX . "_demos` AS D WHERE D.demid = B.bid)");
    $admin['aid']         = $admin['aid'];
    $admin['nodemocount'] = $nodem['num'];

    $admin['name']               = stripslashes($admin['user']);
    $admin['server_flag_string'] = SmFlagsToSb($userbank->GetProperty("srv_flags", $admin['aid']));
    $admin['web_flag_string']    = BitToString($userbank->GetProperty("extraflags", $admin['aid']));

    $lastvisit = $userbank->GetProperty("lastvisit", $admin['aid']);
    if (!$lastvisit) {
        $admin['lastvisit'] = "Never";
    } else {
        $admin['lastvisit'] = Config::time($userbank->GetProperty("lastvisit", $admin['aid']));
    }
    array_push($admin_list, $admin);
}

if ($page > 1) {
    $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=admins&page=" . ($page - 1) . $advSearchString);
} else {
    $prev = "";
}
if ($AdminsEnd < $admin_count) {
    $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=admins&page=" . ($page + 1) . $advSearchString);
} else {
    $next = "";
}

//=================[ Start Layout ]==================================
$admin_nav = 'displaying&nbsp;' . $AdminsStart . '&nbsp;-&nbsp;' . $AdminsEnd . '&nbsp;of&nbsp;' . $admin_count . '&nbsp;results';

if (strlen($prev) > 0) {
    $admin_nav .= ' | <b>' . $prev . '</b>';
}
if (strlen($next) > 0) {
    $admin_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($admin_count / $AdminsPerPage);
if ($pages > 1) {
    $admin_nav .= '&nbsp;<select onchange="changePage(this,\'A\',\'' . $_GET['advSearch'] . '\',\'' . $_GET['advType'] . '\');">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i == $_GET["page"]) {
            $admin_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
            continue;
        }
        $admin_nav .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $admin_nav .= '</select>';
}

$theme->assign('permission_listadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_ADMINS));
$theme->assign('permission_editadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS));
$theme->assign('permission_deleteadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_ADMINS));
$theme->assign('admin_count', $admin_count);
$theme->assign('admin_nav', $admin_nav);
$theme->assign('admins', $admin_list);
$theme->display('page_admin_admins_list.tpl');

// Add Page
$group_list              = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type = '3'");
$servers                 = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_servers`");
$server_admin_group_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups`");
$server_group_list       = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type != 3");
$server_list             = array();
$serverscript            = "<script type=\"text/javascript\">";
foreach ($servers as $server) {
    $serverscript .= "xajax_ServerHostPlayers('" . $server['sid'] . "', 'id', 'sa" . $server['sid'] . "');";
    $info['sid']  = $server['sid'];
    $info['ip']   = $server['ip'];
    $info['port'] = $server['port'];
    array_push($server_list, $info);
}
$serverscript .= "</script>";

$theme->assign('group_list', $group_list);
$theme->assign('server_list', $server_list);
$theme->assign('server_script', $serverscript);
$theme->assign('server_admin_group_list', $server_admin_group_list);
$theme->assign('server_group_list', $server_group_list);
$theme->assign('permission_addadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_ADMINS));
$theme->display('page_admin_admins_add.tpl');
// Overrides

// Saving changed overrides
$overrides_error        = "";
$overrides_save_success = false;
try {
    if (isset($_POST['new_override_name'])) {
        // Handle old overrides, if there are any.
        if (isset($_POST['override_id'])) {
            // Apply changes first
            $edit_errors = "";
            foreach ($_POST['override_id'] as $index => $id) {
                // Skip invalid stuff?!
                if ($_POST['override_type'][$index] != "command" && $_POST['override_type'][$index] != "group") {
                    continue;
                }

                $id = (int) $id;
                // Wants to delete this override?
                if (empty($_POST['override_name'][$index])) {
                    $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_overrides` WHERE id = ?;", array(
                        $id
                    ));
                    continue;
                }

                // Check for duplicates
                $chk = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_overrides` WHERE name = ? AND type = ? AND id != ?", array(
                    $_POST['override_name'][$index],
                    $_POST['override_type'][$index],
                    $id
                ));
                if (!empty($chk)) {
                    $edit_errors .= "&bull; There already is an override with name \\\"" . htmlspecialchars(addslashes($_POST['override_name'][$index])) . "\\\" from the selected type.<br />";
                    continue;
                }

                // Edit the override
                $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_overrides` SET name = ?, type = ?, flags = ? WHERE id = ?;", array(
                    $_POST['override_name'][$index],
                    $_POST['override_type'][$index],
                    trim($_POST['override_flags'][$index]),
                    $id
                ));
            }

            if (!empty($edit_errors)) {
                throw new Exception("There were errors applying your changes:<br /><br />" . $edit_errors);
            }
        }

        // Add a new override
        if (!empty($_POST['new_override_name'])) {
            if ($_POST['new_override_type'] != "command" && $_POST['new_override_type'] != "group") {
                throw new Exception("Invalid override type.");
            }

            // Check for duplicates
            $chk = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_overrides` WHERE name = ? AND type = ?", array(
                $_POST['new_override_name'],
                $_POST['new_override_type']
            ));
            if (!empty($chk)) {
                throw new Exception("There already is an override with that name from the selected type.");
            }

            // Insert the new override
            $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_overrides` (type, name, flags) VALUES (?, ?, ?);", array(
                $_POST['new_override_type'],
                $_POST['new_override_name'],
                trim($_POST['new_override_flags'])
            ));
        }

        $overrides_save_success = true;
    }
} catch (Exception $e) {
    $overrides_error = $e->getMessage();
}

$overrides_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_overrides`;");

$theme->assign('overrides_list', $overrides_list);
$theme->assign('overrides_error', $overrides_error);
$theme->assign('overrides_save_success', $overrides_save_success);
$theme->assign('permission_addadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_ADMINS));
$theme->display('page_admin_overrides.tpl');
