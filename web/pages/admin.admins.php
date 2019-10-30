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
global $userbank;

new AdminTabs([
    ['name' => 'List admins', 'permission' => ADMIN_OWNER|ADMIN_LIST_ADMINS],
    ['name' => 'Add new admin', 'permission' => ADMIN_OWNER|ADMIN_ADD_ADMINS],
    ['name' => 'Overrides', 'permission' => ADMIN_OWNER|ADMIN_ADD_ADMINS]
], $userbank);

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
    $value = substr($_GET['advSearch'], 1, -1);
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
            $GLOBALS['PDO']->query("SELECT aid FROM `:prefix_admins` WHERE aid > 0");
            $alladmins = $GLOBALS['PDO']->resultset();

            foreach($alladmins as $admin) {
                if ($userbank->HasAccess($flagstring, $admin["aid"])) {
                    if (!isset($accessaid)) {
                        $accessaid = $admin["aid"];
                    }
                    $accessaid .= ",".$admin["aid"];
                }
            }
            $where = " AND ADM.aid IN(".$accessaid.")";
            break;
        case "admsrvflag":
            $findflags = explode(",", $value);
            foreach ($findflags as $flag) {
                $flags[] = constant($flag);
            }
            $GLOBALS['PDO']->query("SELECT aid, authid FROM `:prefix_admins` WHERE aid > 0");
            $alladmins = $GLOBALS['PDO']->resultset();
            foreach($alladmins as $admin) {
                foreach ($flags as $fla) {
                    if ($userbank->HasAccess($fla, $admin["authid"])) {
                        if (!isset($accessaid)) {
                            $accessaid = $admin["aid"];
                        }
                        $accessaid .= ",".$admin["aid"];
                    }
                }
                if ($userbank->HasAccess(SM_ROOT, $admin["authid"])) {
                    if (!isset($accessaid)) {
                        $accessaid = $admin["aid"];
                    }
                    $accessaid .= ",".$admin["aid"];
                }
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
$GLOBALS['PDO']->query("SELECT * FROM `:prefix_admins` AS adm $join WHERE adm.aid > 0 $where ORDER BY user LIMIT ${($page-1) * $AdminsPerPage}, $AdminsPerPage");
$admins = $GLOBALS['PDO']->resultset();
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

$GLOBALS['PDO']->query("SELECT COUNT(adm.aid) AS cnt FROM `:prefix_admins` AS adm $join WHERE adm.aid > 0 $where");
$query = $GLOBALS['PDO']->single();
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

    $GLOBALS['PDO']->query("SELECT COUNT(authid) AS num FROM `:prefix_bans` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $admin['aid']);
    $num               = $GLOBALS['PDO']->single();
    $admin['bancount'] = $num['num'];

    $GLOBALS['PDO']->query(
        "SELECT COUNT(b.bid) AS num FROM `:prefix_bans` AS b WHERE aid = :aid AND NOT EXISTS (SELECT d.demid FROM `:prefix_demos` AS d WHERE d.demid = b.bid)"
    );
    $GLOBALS['PDO']->bind(':aid', $admin['aid']);
    $nodem                = $GLOBALS['PDO']->single();
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
$GLOBALS['PDO']->query("SELECT * FROM `:prefix_groups` WHERE type = 3");
$group_list              = $GLOBALS['PDO']->resultset();
$GLOBALS['PDO']->query("SELECT * FROM `:prefix_servers`");
$servers                 = $GLOBALS['PDO']->resultset();
$GLOBALS['PDO']->query("SELECT * FROM `:prefix_srvgroups`");
$server_admin_group_list = $GLOBALS['PDO']->resultset();
$GLOBALS['PDO']->query("SELECT * FROM `:prefix_groups` WHERE type != 3");
$server_group_list       = $GLOBALS['PDO']->resultset();
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
                    $GLOBALS['PDO']->query("DELETE FROM `:prefix_overrides` WHERE id = :id");
                    $GLOBALS['PDO']->bind(':id', $id);
                    $GLOBALS['PDO']->execute();
                    continue;
                }

                // Check for duplicates
                $GLOBALS['PDO']->query("SELECT * FROM `:prefix_overrides` WHERE name = :name AND type = :type AND id != :id");
                $GLOBALS['PDO']->bindMultiple([
                    ':name' => $_POST['override_name'][$index],
                    ':type' => $_POST['override_type'][$index],
                    ':id' => $id
                ]);
                $chk = $GLOBALS['PDO']->resultset();
                if (!empty($chk)) {
                    $edit_errors .= "&bull; There already is an override with name \\\"" . htmlspecialchars(addslashes($_POST['override_name'][$index])) . "\\\" from the selected type.<br />";
                    continue;
                }

                // Edit the override
                $GLOBALS['PDO']->query("UPDATE `:prefix_overrides` SET name = :name, type = :type, flags = :flags WHERE id = :id");
                $GLOBALS['PDO']->bindMultiple([
                    ':name' => $_POST['override_name'][$index],
                    ':type' => $_POST['override_type'][$index],
                    ':flags' => trim($_POST['override_flags'][$index]),
                    ':id' => $id
                ]);
                $GLOBALS['PDO']->execute();
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
            $GLOBALS['PDO']->query("SELECT * FROM `:prefix_overrides` WHERE name = :name AND type = :type");
            $GLOBALS['PDO']->bind(':name', $_POST['new_override_name']);
            $GLOBALS['PDO']->bind(':type', $_POST['new_override_type'])
            $chk = $GLOBALS['PDO']->resultset();
            if (!empty($chk)) {
                throw new Exception("There already is an override with that name from the selected type.");
            }

            // Insert the new override
            $GLOBALS['PDO']->query("INSERT INTO `:prefix_overrides` (type, name, flags) VALUES (:type, :name, :flags)");
            $GLOBALS['PDO']->bindMultiple([
                ':type' => $_POST['new_override_type'],
                ':name' => $_POST['new_override_name'],
                ':flags' => trim($_POST['new_override_flags'])
            ]);
            $GLOBALS['PDO']->execute();
        }

        $overrides_save_success = true;
    }
} catch (Exception $e) {
    $overrides_error = $e->getMessage();
}

$GLOBALS['PDO']->query("SELECT * FROM `:prefix_overrides`");
$overrides_list = $GLOBALS['PDO']->resultset();

$theme->assign('overrides_list', $overrides_list);
$theme->assign('overrides_error', $overrides_error);
$theme->assign('overrides_save_success', $overrides_save_success);
$theme->assign('permission_addadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_ADMINS));
$theme->display('page_admin_overrides.tpl');
