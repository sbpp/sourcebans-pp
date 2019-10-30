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
global $theme;

new AdminTabs([], $userbank);

if (!isset($_GET['id'])) {
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	No server id specified. Please only follow links
</div>';
    die();
}
$_GET['id'] = (int) $_GET['id'];

$GLOBALS['PDO']->query("SELECT * FROM `:prefix_servers` WHERE sid = :sid");
$GLOBALS['PDO']->bind(':sid', $_GET['id']);

$server = $GLOBALS['PDO']->single();
if (!$server) {
    Log::add("e", "Getting server data failed", "Can't find data for server with id $_GET[id].");
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	Error getting current data.
</div></div>';
    PageDie();
}

$errorScript = "";

if (isset($_POST['address'])) {
    // Form validation
    $error = 0;

    // ip
    if ((empty($_POST['address']))) {
        $error++;
        $errorScript .= "$('address.msg').innerHTML = 'You must type the server address.';";
        $errorScript .= "$('address.msg').setStyle('display', 'block');";
    } else {
        if (!filter_var($_POST['address'], FILTER_VALIDATE_IP) && !is_string($_POST['address'])) {
            $error++;
            $errorScript .= "$('address.msg').innerHTML = 'You must type a valid IP.';";
            $errorScript .= "$('address.msg').setStyle('display', 'block');";
        }
    }

    // Port
    if ((empty($_POST['port']))) {
        $error++;
        $errorScript .= "$('port.msg').innerHTML = 'You must type the server port.';";
        $errorScript .= "$('port.msg').setStyle('display', 'block');";
    } else {
        if (!is_numeric($_POST['port'])) {
            $error++;
            $errorScript .= "$('port.msg').innerHTML = 'You must type a valid port <b>number</b>.';";
            $errorScript .= "$('port.msg').setStyle('display', 'block');";
        }
    }

    // rcon
    if ($_POST['rcon'] != '+-#*_' && $_POST['rcon'] != $_POST['rcon2']) {
        $error++;
        $errorScript .= "$('rcon2.msg').innerHTML = 'The passwords don't match.';";
        $errorScript .= "$('rcon2.msg').setStyle('display', 'block');";
    }

    $ip = $_POST['address'];

    // Check for dublicates afterwards
    if ($error == 0) {
        $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE ip = :ip AND port = :port AND sid != :sid");
        $GLOBALS['PDO']->bindMultiple([
            ':ip' => $ip,
            ':port' => $_POST['port'],
            ':sid' => $_GET['id']
        ]);
        $chk = $GLOBALS['PDO']->single();
        if ($chk) {
            $error++;
            $errorScript .= "ShowBox('Error', 'There already is a server with that IP:Port combination.', 'red');";
        }
    }

    $enabled = (isset($_POST['enabled']) && $_POST['enabled'] == "on" ? 1 : 0);

    $server['ip']      = $ip;
    $server['port']    = (int) $_POST['port'];
    $server['modid']   = (int) $_POST['mod'];
    $server['enabled'] = $enabled;

    if ($error == 0) {
        $grps = "";
        $GLOBALS['PDO']->query("SELECT * FROM `:prefix_servers_groups` WHERE server_id = :sid");
        $GLOBALS['PDO']->bind(':sid', $_GET['id']);
        $sg   = $GLOBALS['PDO']->resultset();

        $GLOBALS['PDO']->query("DELETE FROM `:prefix_servers_groups` WHERE server_id = :sid AND group_id = :gid");
        foreach ($sg as $s) {
            $GLOBALS['PDO']->bind(':sid', $s['server_id']);
            $GLOBALS['PDO']->bind(':gid', $s['group_id']);
            $GLOBALS['PDO']->execute();
        }
        if (!empty($_POST['groups'])) {
            $GLOBALS['PDO']->query("INSERT INTO `:prefix_servers_groups` (server_id, group_id) VALUES (:sid, :gid)");
            foreach ($_POST['groups'] as $t) {
                $GLOBALS['PDO']->bind(':sid', $_GET['id']);
                $GLOBALS['PDO']->bind(':gid', $t);
                $GLOBALS['PDO']->execute();
            }
        }
        $enabled = (isset($_POST['enabled']) && $_POST['enabled'] == "on" ? 1 : 0);

        $GLOBALS['PDO']->query(
            "UPDATE `:prefix_servers` SET ip = :ip, port = :port, modid = :modid, enabled = :enabled WHERE sid = :sid"
        );

        $GLOBALS['PDO']->bindMultiple([
            ':ip' => $ip,
            ':port' => $_POST['port'],
            ':modid' => $_POST['mod'],
            ':enabled' => $enabled,
            ':sid' => $_GET['id']
        ]);

        $GLOBALS['PDO']->execute();
        // don't change rcon password if not changed
        if ($_POST['rcon'] != '+-#*_') {
            $GLOBALS['PDO']->query("UPDATE `:prefix_servers` SET rcon = :rcon WHERE sid = :sid");
            $GLOBALS['PDO']->bind(':rcon', $_POST['rcon']);
            $GLOBALS['PDO']->bind(':sid', $_GET['id']);
            $GLOBALS['PDO']->execute();
        }

        echo "<script>ShowBox('Server updated', 'The server has been updated successfully', 'green', 'index.php?p=admin&c=servers');TabToReload();</script>";
    }
}

$GLOBALS['PDO']->query("SELECT mid, name FROM `:prefix_mods` WHERE mid > 0 AND enabled = 1 ORDER BY name ASC");
$modlist   = $GLOBALS['PDO']->resultset();

$GLOBALS['PDO']->query("SELECT gid, name FROM `:prefix_groups` WHERE type = 3 ORDER BY name ASC");
$grouplist = $GLOBALS['PDO']->resultset();

$theme->assign('ip', $server['ip']);
$theme->assign('port', $server['port']);
$theme->assign('rcon', '+-#*_'); // Mh, some random string
$theme->assign('modid', $server['modid']);


$theme->assign('permission_addserver', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_SERVER));
$theme->assign('modlist', $modlist);
$theme->assign('grouplist', $grouplist);

$theme->assign('edit_server', true);
$theme->assign('submit_text', "Update Server");
?>
<div id="admin-page-content">
    <form action="" method="post" name="editserver">
<?php $theme->display('page_admin_servers_add.tpl'); ?>
</form>
<script>
<?php
if (!isset($_POST['address'])) {
    $GLOBALS['PDO']->query("SELECT group_id FROM `:prefix_servers_groups` WHERE server_id = :sid");
    $GLOBALS['PDO']->bind(':sid', $_GET['id']);
    $groups = $GLOBALS['PDO']->resultset();
} else {
    if (isset($_POST['groups']) && is_array($_POST['groups'])) {
        $groups = $_POST['groups'];
        foreach ($groups as $k => $g) {
            $groups[$k] = array($g);
        }
    } else {
        $groups = array();
    }
}
foreach ($groups as $g) {
    if ($g) {
        echo "if($('g_" . $g[0] . "')) $('g_" . $g[0] . "').checked = true;";
    }
}
echo $errorScript;
?>
$('enabled').checked = <?=$server['enabled']?>;
if($('mod')) $('mod').value = <?=$server['modid']?>;
</script>
</div>
