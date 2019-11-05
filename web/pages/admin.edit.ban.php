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

new AdminTabs([], $userbank, $theme);

if ($_GET['key'] != $_SESSION['banlist_postkey']) {
    echo '<script>ShowBox("Error", "Possible hacking attempt (URL Key mismatch)!", "red", "index.php?p=admin&c=bans");</script>';
    PageDie();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<script>ShowBox("Error", "No ban id specified. Please only follow links!", "red", "index.php?p=admin&c=bans");</script>';
    PageDie();
}

$res = $GLOBALS['db']->GetRow("
    				SELECT bid, ba.ip, ba.type, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, ad.gid, CONCAT(se.ip,':',se.port), se.sid, mo.icon, (SELECT origname FROM " . DB_PREFIX . "_demos WHERE demtype = 'b' AND demid = {$_GET['id']})
    				FROM " . DB_PREFIX . "_bans AS ba
    				LEFT JOIN " . DB_PREFIX . "_admins AS ad ON ba.aid = ad.aid
    				LEFT JOIN " . DB_PREFIX . "_servers AS se ON se.sid = ba.sid
    				LEFT JOIN " . DB_PREFIX . "_mods AS mo ON mo.mid = se.modid
    				WHERE bid = {$_GET['id']}");

if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS) && (!$userbank->HasAccess(ADMIN_EDIT_OWN_BANS) && $res[8] != $userbank->GetAid()) && (!$userbank->HasAccess(ADMIN_EDIT_GROUP_BANS) && $res->fields['gid'] != $userbank->GetProperty('gid'))) {
    echo '<script>ShowBox("Error", "You don\'t have access to this!", "red", "index.php?p=admin&c=bans");</script>';
    PageDie();
}

isset($_GET["page"]) ? $pagelink = "&page=" . $_GET["page"] : $pagelink = "";

$errorScript = "";

if (isset($_POST['name'])) {
    $_POST['steam'] = \SteamID\SteamID::toSteam2(trim($_POST['steam']));
    $_POST['type']  = (int) $_POST['type'];

    // Form Validation
    $error = 0;
    // If they didn't type a steamid
    if (empty($_POST['steam']) && $_POST['type'] == 0) {
        $error++;
        $errorScript .= "$('steam.msg').innerHTML = 'You must type a Steam ID or Community ID';";
        $errorScript .= "$('steam.msg').setStyle('display', 'block');";
    } elseif ($_POST['type'] == 0 && !\SteamID\SteamID::isValidID($_POST['steam'])) {
        $error++;
        $errorScript .= "$('steam.msg').innerHTML = 'Please enter a valid Steam ID or Community ID';";
        $errorScript .= "$('steam.msg').setStyle('display', 'block');";
    } elseif (empty($_POST['ip']) && $_POST['type'] == 1) {
        // Didn't type an IP
        $error++;
        $errorScript .= "$('ip.msg').innerHTML = 'You must type an IP';";
        $errorScript .= "$('ip.msg').setStyle('display', 'block');";
    } elseif ($_POST['type'] == 1 && !filter_var($_POST['ip'], FILTER_VALIDATE_IP)) {
        $error++;
        $errorScript .= "$('ip.msg').innerHTML = 'You must type a valid IP';";
        $errorScript .= "$('ip.msg').setStyle('display', 'block');";
    }

    // Didn't type a custom reason
    if ($_POST['listReason'] == "other" && empty($_POST['txtReason'])) {
        $error++;
        $errorScript .= "$('reason.msg').innerHTML = 'You must type a reason';";
        $errorScript .= "$('reason.msg').setStyle('display', 'block');";
    }

    // prune any old bans
    PruneBans();

    if ($error == 0) {
        // Check if the new steamid is already banned
        if ($_POST['type'] == 0) {
            $chk = $GLOBALS['db']->GetRow("SELECT count(bid) AS count FROM " . DB_PREFIX . "_bans WHERE authid = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '0' AND bid != ?", array(
                $_POST['steam'],
                (int) $_GET['id']
            ));

            if ((int) $chk[0] > 0) {
                $error++;
                $errorScript .= "$('steam.msg').innerHTML = 'This SteamID is already banned';";
                $errorScript .= "$('steam.msg').setStyle('display', 'block');";
            } else {
                // Check if player is immune
                $admchk = $userbank->GetAllAdmins();
                foreach ($admchk as $admin) {
                    if ($admin['authid'] == $_POST['steam'] && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity']) {
                        $error++;
                        $errorScript .= "$('steam.msg').innerHTML = 'Admin " . $admin['user'] . " is immune';";
                        $errorScript .= "$('steam.msg').setStyle('display', 'block');";
                        break;
                    }
                }
            }
        } elseif ($_POST['type'] == 1) {
            // Check if the ip is already banned
            $chk = $GLOBALS['db']->GetRow("SELECT count(bid) AS count FROM " . DB_PREFIX . "_bans WHERE ip = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '1' AND bid != ?", array(
                $_POST['ip'],
                (int) $_GET['id']
            ));

            if ((int) $chk[0] > 0) {
                $error++;
                $errorScript .= "$('ip.msg').innerHTML = 'This IP is already banned';";
                $errorScript .= "$('ip.msg').setStyle('display', 'block');";
            }
        }
    }

    $_POST['ip'] = preg_replace('#[^\d\.]#', '', $_POST['ip']); //strip ip of all but numbers and dots
    $reason = $_POST['listReason'] == "other" ? $_POST['txtReason'] : $_POST['listReason'];

    if (!$_POST['banlength']) {
        $_POST['banlength'] = 0;
    } else {
        $_POST['banlength'] = (int) $_POST['banlength'] * 60;
    }

    // Show the new values in the form
    $res['name']   = $_POST['name'];
    $res['authid'] = $_POST['steam'];
    $res['ip']     = $_POST['ip'];
    $res['length'] = $_POST['banlength'];
    $res['type']   = $_POST['type'];
    $res['reason'] = $reason;

    // Only process if there are still no errors
    if ($error == 0) {
        $lengthrev = $GLOBALS['db']->Execute("SELECT length, authid FROM " . DB_PREFIX . "_bans WHERE bid = '" . (int) $_GET['id'] . "'");


        $edit = $GLOBALS['db']->Execute(
            "UPDATE " . DB_PREFIX . "_bans SET
            `name` = ?, `type` = ?, `reason` = ?, `authid` = ?,
            `length` = ?,
            `ip` = ?,
            `country` = '',
            `ends` 	 =  `created` + ?
            WHERE bid = ?",
            array(
                $_POST['name'],
                $_POST['type'],
                $reason,
                $_POST['steam'],
                $_POST['banlength'],
                $_POST['ip'],
                $_POST['banlength'],
                (int) $_GET['id']
            )
        );

        // Set all submissions to archived for that steamid
        $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_submissions` SET archiv = '3', archivedby = '" . $userbank->GetAid() . "' WHERE SteamId = ?;", array(
            $_POST['steam']
        ));

        if (!empty($_POST['dname'])) {
            $demoid = $GLOBALS['db']->GetRow("SELECT filename FROM `" . DB_PREFIX . "_demos` WHERE demid = '" . $_GET['id'] . "';");
            @unlink(SB_DEMOS . "/" . $demoid['filename']);
            $edit         = $GLOBALS['db']->Execute(
                "REPLACE INTO " . DB_PREFIX . "_demos
                (`demid`, `demtype`, `filename`, `origname`)
                VALUES
                (?,
                'b',
                ?,
                ?)",
                array(
                    (int) $_GET['id'],
                    $_POST['did'],
                    $_POST['dname']
                )
            );
            $res['dname'] = $_POST['dname'];
        }

        if ($_POST['banlength'] != $lengthrev->fields['length']) {
            Log::add("m", "Ban length edited", "Ban length for ($lengthrev[authid]) has been updated. Before: $lengthrev[length]; Now: $_POST[banlength]");
        }
        echo '<script>ShowBox("Ban updated", "The ban has been updated successfully", "green", "index.php?p=banlist' . $pagelink . '");</script>';
    }
}

if (!$res) {
    echo '<script>ShowBox("Error", "There was an error getting details. Maybe the ban has been deleted?", "red", "index.php?p=banlist' . $pagelink . '");</script>';
}

$theme->assign('ban_name', $res['name']);
$theme->assign('ban_reason', $res['reason']);
$theme->assign('ban_authid', trim($res['authid']));
$theme->assign('ban_ip', $res['ip']);
$theme->assign('ban_demo', (!empty($res['dname']) ? "Uploaded: <b>" . $res['dname'] . "</b>" : ""));
$theme->assign('customreason', (Config::getBool('bans.customreasons')) ? unserialize(Config::get('bans.customreasons')) : false);

$theme->left_delimiter  = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_admin_edit_ban.tpl');
$theme->left_delimiter  = "{";
$theme->right_delimiter = "}";
?>
<script type="text/javascript">window.addEvent('domready', function(){
<?=$errorScript?>
});
function changeReason(szListValue)
{
    $('dreason').style.display = (szListValue == "other" ? "block" : "none");
}
selectLengthTypeReason('<?=(int) $res['length']?>', '<?=$res['type']?>', '<?=addslashes($res['reason'])?>');
</script>
