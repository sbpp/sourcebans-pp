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

if ($_GET['key'] != $_SESSION['banlist_postkey']) {
    echo '<script>ShowBox("Error", "Possible hacking attempt (URL Key mismatch)!", "red", "index.php?p=admin&c=bans");</script>';
    PageDie();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<script>ShowBox("Error", "No ban id specified. Please only follow links!", "red", "index.php?p=admin&c=bans");</script>';
    PageDie();
}

$GLOBALS['PDO']->query(
    "SELECT bid, ba.ip, ba.type, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user,
    ad.gid, CONCAT(se.ip,':',se.port), se.sid, mo.icon, (SELECT origname FROM `:prefix_demos` WHERE demtype = 'b' AND demid = :id)
    FROM `:prefix_bans` AS ba
    LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
    LEFT JOIN `:prefix_servers` AS se ON se.sid = ba.sid
    LEFT JOIN `:prefix_mods` AS mo ON mo.mid = se.modid WHERE bid = :id"
);

$GLOBALS['PDO']->bind(':id', $_GET['id']);

$res = $GLOBALS['PDO']->single();

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
            $GLOBALS['PDO']->query(
                "SELECT COUNT(bid) AS count FROM `:prefix_bans` WHERE authid = :authid AND (length = 0 OR ends > UNIX_TIMESTAMP())
                AND RemovedBy IS NULL AND type = 0 AND bid != :bid"
            );
            $GLOBALS['PDO']->bind(':authid', $_POST['steam']);
            $GLOBALS['PDO']->bind(':bid', $_GET['id']);
            $chk = $GLOBALS['PDO']->single();

            if ((int) $chk['count'] > 0) {
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
            $GLOBALS['PDO']->query(
                "SELECT COUNT(bid) AS count FROM `:prefix_bans` WHERE ip = :ip AND (length = 0 OR ends > UNIX_TIMESTAMP())
                AND RemovedBy IS NULL AND type = 1 AND bid != :bid"
            );
            $GLOBALS['PDO']->bind(':ip', $_POST['ip']);
            $GLOBALS['PDO']->bind(':bid', $_GET['id']);
            $chk = $GLOBALS['PDO']->single();

            if ((int) $chk['count'] > 0) {
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
        $GLOBALS['PDO']->query("SELECT length, authid FROM `:prefix_bans` WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $_GET['id']);
        $lengthrev = $GLOBALS['PDO']->single();

        $GLOBALS['PDO']->query(
            "UPDATE `:prefix_bans` SET name = :name, type = :type, reason = :reason,
            authid = :authid, length = :length, ip = :ip, country = '', ends = created + :length
            WHERE bid = :bid"
        );

        $GLOBALS['PDO']->bindMultiple([
            ':name' => $_POST['name'],
            ':type' => $_POST['type'],
            ':reason' => $reason,
            ':authid' => $_POST['steam'],
            ':length' => $_POST['banlength'],
            ':ip' => $_POST['ip'],
            ':bid' => $_GET['id']
        ]);

        $GLOBALS['PDO']->execute();

        // Set all submissions to archived for that steamid
        $GLOBALS['PDO']->query("UPDATE `:prefix_submissions` SET archiv = 3, archivedby = :aid WHERE SteamId = :steamid");
        $GLOBALS['PDO']->bind(':aid', $userbank->GetAid());
        $GLOBALS['PDO']->bind(':steamid', $_POST['steam']);
        $GLOBALS['PDO']->execute();

        if (!empty($_POST['dname'])) {
            $GLOBALS['PDO']->query("SELECT filename FROM `:prefix_demos` WHERE demid = :did");
            $GLOBALS['PDO']->bind(':did', $_GET['id']);
            $demoid = $GLOBALS['PDO']->single();
            @unlink(SB_DEMOS . "/" . $demoid['filename']);

            $GLOBALS['PDO']->query("REPLACE INTO `:prefix_demos` (demid, demtype, filename, origname) VALUES (:did, 'b', :file, :orig)");
            $GLOBALS['PDO']->bindMultiple([
                ':did' => $_GET['id'],
                ':file' => $_POST['did'],
                ':orig' => $_POST['dname']
            ]);
            $GLOBALS['PDO']->execute();
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
