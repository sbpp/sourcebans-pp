<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

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

new AdminTabs([], $userbank, $theme);

if ($_GET['key'] != $_SESSION['banlist_postkey']) {
    echo '<script>ShowBox("Error", "Possible hacking attempt (URL Key mismatch)!", "red", "index.php?p=admin&c=comms");</script>';
    PageDie();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<script>ShowBox("Error", "No block id specified. Please only follow links!", "red", "index.php?p=admin&c=comms");</script>';
    PageDie();
}
$_GET['id'] = (int) $_GET['id'];

$GLOBALS['PDO']->query("SELECT bid, ba.type, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, ad.gid
    FROM `:prefix_comms` AS ba
    LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
    WHERE bid = :bid");
$GLOBALS['PDO']->bind(':bid', $_GET['id']);
$res = $GLOBALS['PDO']->single();

if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS) && (!$userbank->HasAccess(ADMIN_EDIT_OWN_BANS) && $res['aid'] != $userbank->GetAid()) && (!$userbank->HasAccess(ADMIN_EDIT_GROUP_BANS) && $res['gid'] != $userbank->GetProperty('gid'))) {
    echo '<script>ShowBox("Error", "You don\'t have access to this!", "red", "index.php?p=admin&c=comms");</script>';
    PageDie();
}

isset($_GET["page"]) ? $pagelink = "&page=" . urlencode($_GET["page"]) : $pagelink = "";

$errorScript = "";

if (isset($_POST['name'])) {
    $_POST['steam'] = \SteamID\SteamID::toSteam2(trim($_POST['steam']));
    $_POST['type']  = (int) $_POST['type'];

    // Form Validation
    $error = 0;
    // If they didn't type a steamid
    if (empty($_POST['steam'])) {
        $error++;
        $errorScript .= "$('steam.msg').innerHTML = 'You must type a Steam ID or Community ID';";
        $errorScript .= "$('steam.msg').setStyle('display', 'block');";
    } elseif (!\SteamID\SteamID::isValidID($_POST['steam'])) {
        $error++;
        $errorScript .= "$('steam.msg').innerHTML = 'Please enter a valid Steam ID or Community ID';";
        $errorScript .= "$('steam.msg').setStyle('display', 'block');";
    }

    // Didn't type a custom reason
    if ($_POST['listReason'] == "other" && empty($_POST['txtReason'])) {
        $error++;
        $errorScript .= "$('reason.msg').innerHTML = 'You must type a reason';";
        $errorScript .= "$('reason.msg').setStyle('display', 'block');";
    }

    // prune any old bans
    PruneComms();

    if ($error == 0) {
        // Check if the new steamid is already banned
        $GLOBALS['PDO']->query("SELECT count(bid) AS count FROM `:prefix_comms` WHERE authid = :authid AND RemovedBy IS NULL AND type = :type AND bid != :bid AND (length = 0 OR ends > UNIX_TIMESTAMP())");
        $GLOBALS['PDO']->bindMultiple([
            ':authid' => $_POST['steam'],
            ':type'   => (int) $_POST['type'],
            ':bid'    => (int) $_GET['id'],
        ]);
        $chk = $GLOBALS['PDO']->single();
        if ((int) $chk['count'] > 0) {
            $error++;
            $errorScript .= "$('steam.msg').innerHTML = 'This SteamID is already blocked';";
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
    }

    $reason        = $_POST['listReason'] == "other" ? $_POST['txtReason'] : $_POST['listReason'];

    if (!$_POST['banlength']) {
        $_POST['banlength'] = 0;
    } else {
        $_POST['banlength'] = (int) $_POST['banlength'] * 60;
    }
    // Show the new values in the form
    $res['name']   = $_POST['name'];
    $res['authid'] = $_POST['steam'];

    $res['length'] = $_POST['banlength'];
    $res['type']   = $_POST['type'];
    $res['reason'] = $reason;

    // Only process if there are still no errors
    if ($error == 0) {
        $GLOBALS['PDO']->query("SELECT length, authid, type FROM `:prefix_comms` WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $_GET['id']);
        $lengthrev = $GLOBALS['PDO']->single();

        $GLOBALS['PDO']->query(
            "UPDATE `:prefix_comms` SET
            `name` = :name, `type` = :type, `reason` = :reason, `authid` = :authid,
            `length` = :length,
            `ends` 	 =  `created` + :ends
            WHERE bid = :bid"
        );
        $GLOBALS['PDO']->bindMultiple([
            ':name'   => $_POST['name'],
            ':type'   => $_POST['type'],
            ':reason' => $reason,
            ':authid' => $_POST['steam'],
            ':length' => $_POST['banlength'],
            ':ends'   => $_POST['banlength'],
            ':bid'    => (int) $_GET['id'],
        ]);
        $GLOBALS['PDO']->execute();

        if ($_POST['banlength'] != $lengthrev['length']) {
            Log::add("m", "Block edited", "Block for ({$lengthrev['authid']}) has been updated."
                . " Before: length ({$lengthrev['length']}), type ({$lengthrev['type']});"
                . " Now: length ({$_POST['banlength']}), type ({$_POST['type']}).");
        }
        echo '<script>ShowBox("Block updated", "The block has been updated successfully", "green", "index.php?p=commslist' . $pagelink . '");</script>';
    }
}

if (!$res) {
    echo '<script>ShowBox("Error", "There was an error getting details. Maybe the block has been deleted?", "red", "index.php?p=commslist' . $pagelink . '");</script>';
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminCommsEditView(
    ban_name: (string) $res['name'],
    ban_authid: trim((string) $res['authid']),
));
?>
<script type="text/javascript">window.addEvent('domready', function(){
<?=$errorScript?>
});
function changeReason(szListValue)
{
    $('dreason').style.display = (szListValue == "other" ? "block" : "none");
}
// `selectLengthTypeReason` is the post-mount hydrator that picks the
// existing block's type / length / reason on the <select>s. The legacy
// default theme inherits it from web/scripts/sourcebans.js, but the
// sbpp2026 chrome doesn't load sourcebans.js (and #1123 D1 deletes that
// file outright). Inline a self-contained vanilla version so the call
// below works on both legs of the dual-theme matrix and keeps working
// post-D1 — without it, sbpp2026 would throw ReferenceError, leave the
// type/length/reason at their defaults, and silently clobber the row
// when the admin clicks Save.
function selectLengthTypeReason(length, type, reason) {
    var banlength = document.getElementById('banlength');
    if (banlength) {
        for (var i = 0; i < banlength.options.length; i++) {
            if (banlength.options[i].value === String(length / 60)) {
                banlength.options[i].selected = true;
                break;
            }
        }
    }
    var ttype = document.getElementById('type');
    if (ttype && ttype.options[type]) ttype.options[type].selected = true;

    var list = document.getElementById('listReason');
    if (list) {
        for (var i = 0; i < list.options.length; i++) {
            if (list.options[i].innerHTML === reason) {
                list.options[i].selected = true;
                break;
            }
            if (list.options[i].value === 'other') {
                var txt = document.getElementById('txtReason');
                var dre = document.getElementById('dreason');
                if (txt) txt.value = reason;
                if (dre) dre.style.display = 'block';
                list.options[i].selected = true;
                break;
            }
        }
    }
}
selectLengthTypeReason('<?=(int) $res['length']?>', '<?=(int) $res['type'] - 1?>', '<?=addslashes($res['reason'])?>');
</script>
