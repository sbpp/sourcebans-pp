<div id="admin-page-content">
<?php
/*************************************************************************
This file is part of SourceBans++

Copyright � 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

SourceBans++ is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright � 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
global $theme;
if (!isset($_GET['id'])) {
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No server id specified. Please only follow links
</div>';
    die();
}
$_GET['id'] = (int) $_GET['id'];

$server = $GLOBALS['db']->GetRow("SELECT * FROM " . DB_PREFIX . "_servers WHERE sid = {$_GET['id']}");
if (!$server) {
    $log = new CSystemLog("e", "Getting server data failed", "Can't find data for server with id '" . $_GET['id'] . "'");
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
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
        if (!validate_ip($_POST['address']) && !is_string($_POST['address'])) {
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

    $ip = RemoveCode($_POST['address']);

    // Check for dublicates afterwards
    if ($error == 0) {
        $chk = $GLOBALS['db']->GetRow('SELECT sid FROM `' . DB_PREFIX . '_servers` WHERE ip = ? AND port = ? AND sid != ?;', array(
            $ip,
            (int) $_POST['port'],
            $_GET['id']
        ));
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
        $sg   = $GLOBALS['db']->GetAll("SELECT * FROM " . DB_PREFIX . "_servers_groups WHERE server_id = {$_GET['id']}");
        foreach ($sg as $s) {
            $GLOBALS['db']->Execute("DELETE FROM " . DB_PREFIX . "_servers_groups WHERE server_id = " . (int) $s['server_id'] . " AND group_id = " . (int) $s['group_id']);
        }
        if (!empty($_POST['groups'])) {
            foreach ($_POST['groups'] as $t) {
                $addtogrp = $GLOBALS['db']->Prepare("INSERT INTO " . DB_PREFIX . "_servers_groups (`server_id`, `group_id`) VALUES (?,?)");
                $GLOBALS['db']->Execute($addtogrp, array(
                    $_GET['id'],
                    (int) $t
                ));
            }
        }
        $enabled = (isset($_POST['enabled']) && $_POST['enabled'] == "on" ? 1 : 0);

        $edit = $GLOBALS['db']->Execute(
            "UPDATE " . DB_PREFIX . "_servers SET
            `ip` = ?,
            `port` = ?,
            `modid` = ?,
            `enabled` = ?
            WHERE `sid` = ?",
            array(
                $ip,
                (int) $_POST['port'],
                (int) $_POST['mod'],
                $enabled,
                (int) $_GET['id']
            )
        );

        // don't change rcon password if not changed
        if ($_POST['rcon'] != '+-#*_') {
            $edit = $GLOBALS['db']->Execute(
                "UPDATE " . DB_PREFIX . "_servers SET
                `rcon` = ?
                WHERE `sid` = ?",
                array(
                    $_POST['rcon'],
                    (int) $_GET['id']
                )
            );
        }

        echo "<script>ShowBox('Server updated', 'The server has been updated successfully', 'green', 'index.php?p=admin&c=servers');TabToReload();</script>";
    }
}

$modlist   = $GLOBALS['db']->GetAll("SELECT mid, name FROM `" . DB_PREFIX . "_mods` WHERE `mid` > 0 AND `enabled` = 1 ORDER BY name ASC");
$grouplist = $GLOBALS['db']->GetAll("SELECT gid, name FROM `" . DB_PREFIX . "_groups` WHERE type = 3 ORDER BY name ASC");

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
    <form action="" method="post" name="editserver">
<?php $theme->display('page_admin_servers_add.tpl'); ?>
</form>
<script>
<?php
if (!isset($_POST['address'])) {
    $groups = $GLOBALS['db']->GetAll("SELECT group_id FROM `" . DB_PREFIX . "_servers_groups` WHERE server_id = {$_GET['id']}");
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
