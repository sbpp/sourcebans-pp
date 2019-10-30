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

global $userbank, $theme;
if (!Config::getBool('config.enableprotest')) {
    print "<script>ShowBox('Error', 'This page is disabled. You should not be here.', 'red');</script>";
    PageDie();
}
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
if (!isset($_POST['subprotest']) || $_POST['subprotest'] != 1) {
    $Type        = 0;
    $SteamID     = "";
    $IP          = "";
    $PlayerName  = "";
    $UnbanReason = "";
    $Email       = "";
} else {
    $Type        = (int) $_POST['Type'];
    $SteamID     = htmlspecialchars($_POST['SteamID']);
    $IP          = htmlspecialchars($_POST['IP']);
    $PlayerName  = htmlspecialchars($_POST['PlayerName']);
    $UnbanReason = htmlspecialchars($_POST['BanReason']);
    $Email       = htmlspecialchars($_POST['EmailAddr']);
    $validsubmit = true;
    $errors      = "";
    $BanId       = -1;

    if (get_magic_quotes_gpc()) {
        $UnbanReason = stripslashes($UnbanReason);
    }

    if ($Type == 0 && !\SteamID\SteamID::isValidID($SteamID)) {
        $errors .= '* Please type a valid STEAM ID.<br>';
        $validsubmit = false;
    } elseif ($Type == 0) {
        $GLOBALS['PDO']->query("SELECT bid FROM `:prefix_bans` WHERE authid = :authid AND RemovedBy IS NULL AND type = 0");
        $GLOBALS['PDO']->bind(':authid', $SteamID);
        $res = $GLOBALS['PDO']->single();

        if (@count($res) == 0) {
            $errors .= '* That Steam ID is not banned!<br>';
            $validsubmit = false;
        } else {
            $BanId = $res['bid'];
            $GLOBALS['PDO']->query("SELECT pid FROM `:prefix_protests` WHERE bid = :bid");
            $GLOBALS['PDO']->bind(':bid', $res['bid'], \PDO::PARAM_INT);
            $res   = $GLOBALS['PDO']->resultset();
            if (@count($res) > 0) {
                $errors .= '* A protest is already pending for this Steam ID.<br>';
                $validsubmit = false;
            }
        }
    }
    if ($Type == 1 && !filter_var($IP, FILTER_VALIDATE_IP)) {
        $errors .= '* Please type a valid IP.<br>';
        $validsubmit = false;
    } elseif ($Type == 1) {
        $GLOBALS['PDO']->query("SELECT bid FROM `:prefix_bans` WHERE ip = :ip AND RemovedBy IS NULL AND type = 1");
        $GLOBALS['PDO']->bind(':ip', $IP);
        $res = $GLOBALS['PDO']->single();

        if (@count($res) == 0) {
            $errors .= '* That IP is not banned!<br>';
            $validsubmit = false;
        } else {
            $BanId = $res['bid'];
            $GLOBALS['PDO']->query("SELECT pid FROM `:prefix_protests` WHERE bid = :bid");
            $GLOBALS['PDO']->bind(':bid', $res['bid'], \PDO::PARAM_INT);
            $res   = $GLOBALS['PDO']->resultset();
            if (@count($res) > 0) {
                $errors .= '* A protest is already pending for this IP.<br>';
                $validsubmit = false;
            }
        }
    }
    if (strlen($PlayerName) == 0) {
        $errors .= '* You must include a player name<br>';
        $validsubmit = false;
    }
    if (strlen($UnbanReason) == 0) {
        $errors .= '* You must include comments<br>';
        $validsubmit = false;
    }
    if (!filter_var($Email, FILTER_VALIDATE_EMAIL)) {
        $errors .= '* You must include a valid email address<br>';
        $validsubmit = false;
    }

    if (!$validsubmit) {
        print "<script>ShowBox('Error', '$errors', 'red');</script>";
    }

    if ($validsubmit && $BanId != -1) {
        $UnbanReason = trim($UnbanReason);
        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_protests` (bid, datesubmitted, reason, email, archiv, pip)
             VALUES (:bid, UNIX_TIMESTAMP(), :reason, :email, 0, :pip)");
        $GLOBALS['PDO']->bindMultiple([
            ':bid' => $BanId,
            ':reason' => $UnbanReason,
            ':email' => $Email,
            ':pip' => $_SERVER['REMOTE_ADDR']
        ]);
        $GLOBALS['PDO']->execute();
        $protid      = $GLOBALS['PDO']->lastInsertId();

        $GLOBALS['PDO']->query(
            "SELECT ad.user FROM `:prefix_protests` p, `:prefix_admins` ad, `:prefix_bans` b WHERE p.pid = :pid AND b.bid = p.bid AND ad.aid = b.aid");
        $GLOBALS['PDO']->bind(':pid', $protid);
        $protadmin   = $GLOBALS['PDO']->single();

        $Type        = 0;
        $SteamID     = "";
        $IP          = "";
        $PlayerName  = "";
        $UnbanReason = "";
        $Email       = "";

        // Send an email when protest was posted
        $headers = 'From: ' . SB_EMAIL . "\n" . 'X-Mailer: PHP/' . phpversion();

        $GLOBALS['PDO']->query("SELECT aid, user, email FROM `:prefix_admins` WHERE aid = (SELECT aid FROM `:prefix_bans` WHERE bid = :bid)");
        $GLOBALS['PDO']->bind(':bid', $BanId);

        $emailinfo = $GLOBALS['PDO']->single();
        $requri    = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], ".php") + 4);
        if (Config::getBool('protest.emailonlyinvolved') && !empty($emailinfo->fields['email'])) {
            $admins = array(
                array(
                    'aid' => $emailinfo['aid'],
                    'user' => $emailinfo['user'],
                    'email' => $emailinfo['email']
                )
            );
        } else {
            $admins = $userbank->GetAllAdmins();
        }
        foreach ($admins as $admin) {
            $message = "";
            $message .= "Hello " . $admin['user'] . ",\n\n";
            $message .= "A new ban protest has been posted on your SourceBans page.\n\n";
            $message .= "Player: " . $_POST['PlayerName'] . " (" . $_POST['SteamID'] . ")\nBanned by: " . $protadmin['user'] . "\nMessage: " . $_POST['BanReason'] . "\n\n";
            $message .= "Click the link below to view the current ban protests.\n\nhttp://" . $_SERVER['HTTP_HOST'] . $requri . "?p=admin&c=bans#%5E1";
            if ($userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_PROTESTS, $admin['aid']) && $userbank->HasAccess(ADMIN_NOTIFY_PROTEST, $admin['aid'])) {
                mail($admin['email'], "[SourceBans] Ban Protest Added", $message, $headers);
            }
        }

        print "<script>ShowBox('Successful', 'Your protest has been sent.', 'green');</script>";
    }
}

$theme->assign('steam_id', $SteamID);
$theme->assign('ip', $IP);
$theme->assign('player_name', $PlayerName);
$theme->assign('reason', $UnbanReason);
$theme->assign('player_email', $Email);

$theme->display('page_protestban.tpl');
?>
<script type="text/javascript">
function changeType(szListValue)
{
    $('steam.row').style.display = (szListValue == "0" ? "" : "none");
    $('ip.row').style.display    = (szListValue == "1" ? "" : "none");
}
$('Type').options[<?=$Type;?>].selected = true;
changeType(<?=$Type?>);
</script>
