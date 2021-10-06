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

use SteamID\SteamID;
use xPaw\SourceQuery\SourceQuery;

require_once 'xajax.inc.php';
require_once 'system-functions.php';
$xajax = new xajax();
//$xajax->debugOn();
$xajax->setRequestURI('./index.php');
global $userbank;

if ($userbank->is_admin()) {
    $xajax->registerFunction("AddMod");
    $xajax->registerFunction("RemoveMod");
    $xajax->registerFunction("AddGroup");
    $xajax->registerFunction("RemoveGroup");
    $xajax->registerFunction("RemoveAdmin");
    $xajax->registerFunction("RemoveSubmission");
    $xajax->registerFunction("RemoveServer");
    $xajax->registerFunction("UpdateGroupPermissions");
    $xajax->registerFunction("UpdateAdminPermissions");
    $xajax->registerFunction("AddAdmin");
    $xajax->registerFunction("SetupEditServer");
    $xajax->registerFunction("AddServerGroupName");
    $xajax->registerFunction("AddServer");
    $xajax->registerFunction("AddBan");
    $xajax->registerFunction("EditGroup");
    $xajax->registerFunction("RemoveProtest");
    $xajax->registerFunction("SendRcon");
    $xajax->registerFunction("EditAdminPerms");
    $xajax->registerFunction("SelTheme");
    $xajax->registerFunction("ApplyTheme");
    $xajax->registerFunction("AddComment");
    $xajax->registerFunction("EditComment");
    $xajax->registerFunction("RemoveComment");
    $xajax->registerFunction("PrepareReban");
    $xajax->registerFunction("ClearCache");
    $xajax->registerFunction("KickPlayer");
    $xajax->registerFunction("PasteBan");
    $xajax->registerFunction("RehashAdmins");
    $xajax->registerFunction("GroupBan");
    $xajax->registerFunction("BanMemberOfGroup");
    $xajax->registerFunction("GetGroups");
    $xajax->registerFunction("BanFriends");
    $xajax->registerFunction("SendMessage");
    $xajax->registerFunction("ViewCommunityProfile");
    $xajax->registerFunction("SetupBan");
    $xajax->registerFunction("CheckPassword");
    $xajax->registerFunction("ChangePassword");
    $xajax->registerFunction("CheckSrvPassword");
    $xajax->registerFunction("ChangeSrvPassword");
    $xajax->registerFunction("ChangeEmail");
    $xajax->registerFunction("CheckVersion");
    $xajax->registerFunction("SendMail");
    $xajax->registerFunction("AddBlock");
    $xajax->registerFunction("PrepareReblock");
    $xajax->registerFunction("PrepareBlockFromBan");
    $xajax->registerFunction("PasteBlock");

    $xajax->registerFunction("generatePassword");
}

$xajax->registerFunction("Plogin");
$xajax->registerFunction("ServerHostPlayers");
$xajax->registerFunction("ServerHostProperty");
$xajax->registerFunction("ServerHostPlayers_list");
$xajax->registerFunction("ServerPlayers");
$xajax->registerFunction("LostPassword");
$xajax->registerFunction("RefreshServer");

global $userbank;
$username = $userbank->GetProperty("user");

/**
 * @param  string $username
 * @param  string $password
 * @param  string $remember
 * @param  string $redirect
 * @return xajaxResponse
 */
function Plogin(string $username, string $password, string $remember = '', string $redirect = '')
{
    global $userbank;
    $objResponse = new xajaxResponse();

    if (empty($password)) {
        $objResponse->addRedirect('?p=login&m=empty_pwd', 0);
        return $objResponse;
    }

    $remember = ($remember === 'true') ? true : false;

    $auth = new NormalAuthHandler($GLOBALS['PDO'], $username, $password, $remember);

    if (!$auth->getResult()) {
        $objResponse->addRedirect("?p=login&m=failed",  0);
        return $objResponse;
    }

    $objResponse->addRedirect("?".$redirect,  0);
    return $objResponse;
}

/**
 * @param  string $email
 * @return xajaxResponse
 */
function LostPassword($email)
{
    $objResponse = new xajaxResponse();
    $GLOBALS['PDO']->query('SELECT aid, user FROM `:prefix_admins` WHERE `email` = :email');
    $GLOBALS['PDO']->bind(':email', $email);
    $result = $GLOBALS['PDO']->single();

    if (empty($result['aid']) || is_null($result['aid'])) {
        $objResponse->addScript(
            "ShowBox('Error', 'The email address you supplied is not registered on the system', 'red', '');"
        );
        return $objResponse;
    }

    $validation = Crypto::recoveryHash();
    $GLOBALS['PDO']->query('UPDATE `:prefix_admins` SET `validate` = :validate WHERE `email` = :email');
    $GLOBALS['PDO']->bind(':validate', $validation);
    $GLOBALS['PDO']->bind(':email', $email);
    $GLOBALS['PDO']->execute();

    $host = Host::complete();

    $message = "
        Hello $result[user],\n
        You have requested to have your password reset for your SourceBans++ account.\n
        To complete this process, please click the following link.\n
        NOTE: If you didnt request this reset, then simply ignore this email.\n\n
        $host/index.php?p=lostpassword&email=$email&validation=$validation
    ";

    $headers = [
        'From' => SB_EMAIL,
        'X-Mailer' => 'PHP/'.phpversion()
    ];

    mail($email, "[SourceBans++] Password Reset", $message, $headers);

    $objResponse->addScript(
        "ShowBox('Check E-Mail', 'Please check your email inbox (and spam) for a link which will help you reset your password.', 'blue', '');"
    );
    return $objResponse;
}

/**
 * @param  int    $aid
 * @param  string $srv_pass
 * @return xajaxResponse
 */
function CheckSrvPassword($aid, $srv_pass)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    $aid = (int)$aid;
    if(!$userbank->is_logged_in() || $aid != $userbank->GetAid()) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        $affectedAid = $userbank->GetProperty('user', $aid);
        Log::add(
            "w",
            "Hacking Attempt",
            "$username tried to check " . $affectedAid . "'s server password, but doesn't have access."
        );
        return $objResponse;
    }
    $res = $GLOBALS['db']->Execute("SELECT `srv_password` FROM `".DB_PREFIX."_admins` WHERE `aid` = '".$aid."'");
    if($res->fields['srv_password'] != null && $res->fields['srv_password'] != $srv_pass) {
        $objResponse->addScript("$('scurrent.msg').setStyle('display', 'block');");
        $objResponse->addScript("$('scurrent.msg').setHTML('Incorrect password.');");
        $objResponse->addScript("set_error(1);");

    }
    else {
        $objResponse->addScript("$('scurrent.msg').setStyle('display', 'none');");
        $objResponse->addScript("set_error(0);");
    }
    return $objResponse;
}

/**
 * @param  int    $aid
 * @param  string $srv_pass
 * @return xajaxResponse
 */
function ChangeSrvPassword($aid, $srv_pass)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    $aid = (int)$aid;
    if(!$userbank->is_logged_in() || $aid != $userbank->GetAid()) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        $affectedAid = $userbank->GetProperty('user', $aid);
        Log::add(
            "w",
            "Hacking Attempt",
            "$username tried to change " . $affectedAid . "'s server password, but doesn't have access."
        );
        return $objResponse;
    }

    if($srv_pass == "NULL") {
        $GLOBALS['db']->Execute(
            "UPDATE `" . DB_PREFIX . "_admins` SET `srv_password` = NULL WHERE `aid` = '" . $aid . "'"
        );
    } else {
        $GLOBALS['db']->Execute(
            "UPDATE `" . DB_PREFIX . "_admins` SET `srv_password` = ? WHERE `aid` = ?", array($srv_pass, $aid)
        );
    }
    $objResponse->addScript(
        "ShowBox('Server Password changed', 'Your server password has been changed successfully.', 'green', 'index.php?p=account', true);"
    );
    Log::add("m", "Srv Password Changed", "Password changed for admin ($aid)");
    return $objResponse;
}

/**
 * @param  int    $aid
 * @param  string $email
 * @param  string $password
 * @return xajaxResponse
 */
function ChangeEmail($aid, $email, $password)
{
    global $userbank, $username;
    $objResponse = new xajaxResponse();
    $aid = (int)$aid;

    if(!$userbank->is_logged_in() || $aid != $userbank->GetAid()) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add(
            "w",
            "Hacking Attempt",
            "$username tried to change " . $userbank->GetProperty('user', $aid) . "'s email, but doesn't have access."
        );
        return $objResponse;
    }

    if(!password_verify($password, $userbank->getProperty('password'))) {
        $objResponse->addScript("$('emailpw.msg').setStyle('display', 'block');");
        $objResponse->addScript("$('emailpw.msg').setHTML('The password you supplied is wrong.');");
        $objResponse->addScript("set_error(1);");
        return $objResponse;
    } else {
        $objResponse->addScript("$('emailpw.msg').setStyle('display', 'none');");
        $objResponse->addScript("set_error(0);");
    }

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $objResponse->addScript("$('email1.msg').setStyle('display', 'block');");
        $objResponse->addScript("$('email1.msg').setHTML('You must type a valid email address.');");
        $objResponse->addScript("set_error(1);");
        return $objResponse;
    } else {
        $objResponse->addScript("$('email1.msg').setStyle('display', 'none');");
        $objResponse->addScript("set_error(0);");
    }

    $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `email` = ? WHERE `aid` = ?", array($email, $aid));
    $objResponse->addScript(
        "ShowBox('E-mail address changed', 'Your E-mail address has been changed successfully.', 'green', 'index.php?p=account', true);"
    );
    Log::add("m", "E-mail Changed", "E-mail changed for admin ($aid).");
    return $objResponse;
}

/**
 * @param  string $name
 * @param  int $type
 * @param  int $bitmask
 * @param  string $srvflags
 * @return xajaxResponse
 */
function AddGroup($name, $type, $bitmask, $srvflags)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_GROUP)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to Add a new group, but doesnt have access.");
        return $objResponse;
    }

    $error = 0;
    $query = $GLOBALS['db']->GetRow("SELECT `gid` FROM `" . DB_PREFIX . "_groups` WHERE `name` = ?", array($name));
    $query2 = $GLOBALS['db']->GetRow("SELECT `id` FROM `" . DB_PREFIX . "_srvgroups` WHERE `name` = ?", array($name));
    if (strlen($name) == 0 || count($query) > 0 || count($query2) > 0) {
        if (strlen($name) == 0) {
            $objResponse->addScript("$('name.msg').setStyle('display', 'block');");
            $objResponse->addScript("$('name.msg').setHTML('Please enter a name for this group.');");
            $error++;
        } elseif (strstr($name, ',')) {
            $objResponse->addScript("$('name.msg').setStyle('display', 'block');");
            $objResponse->addScript("$('name.msg').setHTML('You cannot have a comma \',\' in a group name.');");
            $error++;
        } elseif (count($query) > 0 || count($query2) > 0) {
            $objResponse->addScript("$('name.msg').setStyle('display', 'block');");
            $objResponse->addScript("$('name.msg').setHTML('A group is already named \'" . $name . "\'');");
            $error++;
        } else {
            $objResponse->addScript("$('name.msg').setStyle('display', 'none');");
            $objResponse->addScript("$('name.msg').setHTML('');");
        }
    }
    if ($type == "0") {
        $objResponse->addScript("$('type.msg').setStyle('display', 'block');");
        $objResponse->addScript("$('type.msg').setHTML('Please choose a type for the group.');");
        $error++;
    } else {
        $objResponse->addScript("$('type.msg').setStyle('display', 'none');");
        $objResponse->addScript("$('type.msg').setHTML('');");
    }
    if ($error > 0) {
        return $objResponse;
    }

    $query = $GLOBALS['db']->GetRow("SELECT MAX(gid) AS next_gid FROM `" . DB_PREFIX . "_groups`");
    if ($type == "1") {
        // add the web group
        $query1 = $GLOBALS['db']->Execute(
            "INSERT INTO `" . DB_PREFIX
            . "_groups` (`gid`, `type`, `name`, `flags`) 
            VALUES (" . (int)($query['next_gid'] + 1) . ", '" . (int)$type . "', ?, '" . (int)$bitmask . "')",
            array($name)
        );
    } elseif ($type == "2") {
        if (strstr($srvflags, "#")) {
            $immunity = "0";
            $immunity = substr($srvflags, strpos($srvflags, "#") + 1);
            $srvflags = substr($srvflags, 0, strlen($srvflags) - strlen($immunity) - 1);
        }
        $immunity = (isset($immunity) && $immunity > 0) ? $immunity : 0;
        $add_group = $GLOBALS['db']->Prepare(
            "INSERT INTO " . DB_PREFIX . "_srvgroups(immunity,flags,name,groups_immune)
    VALUES (?,?,?,?)"
        );
        $GLOBALS['db']->Execute($add_group, array($immunity, $srvflags, $name, " "));
    } elseif ($type == "3") {
        // We need to add the server into the table
        $query1 = $GLOBALS['db']->Execute(
            "INSERT INTO `" . DB_PREFIX
            . "_groups` (`gid`, `type`, `name`, `flags`) VALUES (" . ($query['next_gid'] + 1) . ", '3', ?, '0')",
            array($name)
        );
    }

    Log::add("m", "Group Created", "A new group was created ($name).");
    $objResponse->addScript(
        "ShowBox('Group Created', 'Your group has been successfully created.', 'green', 'index.php?p=admin&c=groups', true);"
    );
    $objResponse->addScript("TabToReload();");
    return $objResponse;
}

function RemoveGroup($gid, $type)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_GROUPS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to remove a group, but doesnt have access.");
        return $objResponse;
    }

    $gid = (int)$gid;

    if ($type == "web") {
        $query2 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET gid = -1 WHERE gid = $gid");
        $query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_groups` WHERE gid = $gid");
    } elseif ($type == "server") {
        $query2 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_servers_groups` WHERE group_id = $gid");
        $query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_groups` WHERE gid = $gid");
    } else {
        $query2 = $GLOBALS['db']->Execute(
            "UPDATE `" . DB_PREFIX . "_admins` SET srv_group = NULL WHERE srv_group = (SELECT name FROM `" . DB_PREFIX . "_srvgroups` WHERE id = $gid)"
        );
        $query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_srvgroups` WHERE id = $gid");
        $query0 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE group_id = $gid");
    }

    if (Config::getBool('config.enableadminrehashing')) {
        // rehash the settings out of the database on all servers
        $serveraccessq = $GLOBALS['db']->GetAll("SELECT sid FROM " . DB_PREFIX . "_servers WHERE enabled = 1;");
        $allservers = array();
        foreach ($serveraccessq as $access) {
            if (!in_array($access['sid'], $allservers)) {
                $allservers[] = $access['sid'];
            }
        }
        $rehashing = true;
    }

    $objResponse->addScript("SlideUp('gid_$gid');");
    if($query1) {
        if (isset($rehashing)) {
            $objResponse->addScript(
                "ShowRehashBox('" . implode(",", $allservers) . "', 'Group Deleted', 'The selected group has been deleted from the database', 'green', 'index.php?p=admin&c=groups', true);"
            );
        } else {
            $objResponse->addScript(
                "ShowBox('Group Deleted', 'The selected group has been deleted from the database', 'green', 'index.php?p=admin&c=groups', true);"
            );
        }
        Log::add("m", "Group Deleted", "Group ($gid) has been deleted.");
    }
    else {
        $objResponse->addScript(
            "ShowBox('Error', 'There was a problem deleting the group from the database. Check the logs for more info', 'red', 'index.php?p=admin&c=groups', true);
            ");
    }

    return $objResponse;
}

/**
 * @param int $sid Submission ID
 * @param int $archiv Action to perform.  1 for archive it, 0 for delete, 2 for restore
 * @return xajaxResponse
 */
function RemoveSubmission($sid, $archiv)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_SUBMISSIONS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to remove a submission, but doesnt have access.");
        return $objResponse;
    }
    $sid = (int)$sid;
    if($archiv == "1") { // move submission to archiv
        $query1 = $GLOBALS['db']->Execute(
            "UPDATE `" . DB_PREFIX . "_submissions` SET archiv = '1', archivedby = '" . $userbank->GetAid() . "' WHERE subid = $sid"
        );
        $query = $GLOBALS['db']->GetRow(
            "SELECT count(subid) AS cnt FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0'"
        );
        $objResponse->addScript("$('subcount').setHTML('" . $query['cnt'] . "');");

        $objResponse->addScript("SlideUp('sid_$sid');");
        $objResponse->addScript("SlideUp('sid_" . $sid . "a');");

        if ($query1) {
            $objResponse->addScript(
                "ShowBox('Submission Archived', 'The selected submission has been moved to the archive!', 'green', 'index.php?p=admin&c=bans', true);"
            );
            Log::add("m", "Submission Archived", "Submission ($sid) has been moved to the archive.");
        } else {
            $objResponse->addScript(
                "ShowBox('Error', 'There was a problem moving the submission to the archive. Check the logs for more info', 'red', 'index.php?p=admin&c=bans', true);"
            );
        }
    } else if($archiv == "0") { // delete submission
        $query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_submissions` WHERE subid = $sid");
        $GLOBALS['db']->Execute(
            "DELETE FROM `" . DB_PREFIX . "_demos` WHERE demid = '" . $sid . "' AND demtype = 'S'"
        );
        $query = $GLOBALS['db']->GetRow(
            "SELECT count(subid) AS cnt FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '1'"
        );
        $objResponse->addScript("$('subcountarchiv').setHTML('" . $query['cnt'] . "');");

        $objResponse->addScript("SlideUp('asid_$sid');");
        $objResponse->addScript("SlideUp('asid_" . $sid . "a');");

        if ($query1) {
            $objResponse->addScript("ShowBox('Submission Deleted', 'The selected submission has been deleted from the database', 'green', 'index.php?p=admin&c=bans', true);");
            Log::add("m", "Submission Deleted", "Submission ($sid) has been deleted.");
        } else {
            $objResponse->addScript("ShowBox('Error', 'There was a problem deleting the submission from the database. Check the logs for more info', 'red', 'index.php?p=admin&c=bans', true);");
        }
    } else if($archiv == "2") { // restore the submission
        $query1 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_submissions` SET archiv = '0', archivedby = NULL WHERE subid = $sid");
        $query = $GLOBALS['db']->GetRow("SELECT count(subid) AS cnt FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0'");
        $objResponse->addScript("$('subcountarchiv').setHTML('" . $query['cnt'] . "');");

        $objResponse->addScript("SlideUp('asid_$sid');");
        $objResponse->addScript("SlideUp('asid_" . $sid . "a');");

        if ($query1) {
            $objResponse->addScript("ShowBox('Submission Restored', 'The selected submission has been restored from the archive!', 'green', 'index.php?p=admin&c=bans', true);");
            Log::add("m", "Submission Restored", "Submission ($sid) has been restored from the archive.");
        } else {
            $objResponse->addScript("ShowBox('Error', 'There was a problem restoring the submission from the archive. Check the logs for more info', 'red', 'index.php?p=admin&c=bans', true);");
        }
    }
    return $objResponse;
}

function RemoveProtest($pid, $archiv)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to remove a protest, but doesnt have access.");
        return $objResponse;
    }
    $pid = (int)$pid;
    if($archiv == '0') { // delete protest
        $query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_protests` WHERE pid = $pid");
        $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_comments` WHERE type = 'P' AND bid = $pid;");
        $query = $GLOBALS['db']->GetRow("SELECT count(pid) AS cnt FROM `" . DB_PREFIX . "_protests` WHERE archiv = '1'");
        $objResponse->addScript("$('protcountarchiv').setHTML('" . $query['cnt'] . "');");
        $objResponse->addScript("SlideUp('apid_$pid');");
        $objResponse->addScript("SlideUp('apid_" . $pid . "a');");

        if ($query1) {
            $objResponse->addScript(
                "ShowBox('Protest Deleted', 'The selected protest has been deleted from the database', 'green', 'index.php?p=admin&c=bans', true);"
            );
            Log::add("m", "Protest Deleted", "Protest ($pid) has been deleted.");
        } else {
            $objResponse->addScript(
                "ShowBox('Error', 'There was a problem deleting the protest from the database. Check the logs for more info', 'red', 'index.php?p=admin&c=bans', true);"
            );
        }
    } else if($archiv == '1') { // move protest to archiv
        $query1 = $GLOBALS['db']->Execute(
            "UPDATE `" . DB_PREFIX . "_protests` SET archiv = '1', archivedby = '" . $userbank->GetAid() . "' WHERE pid = $pid"
        );
        $query = $GLOBALS['db']->GetRow(
            "SELECT count(pid) AS cnt FROM `" . DB_PREFIX . "_protests` WHERE archiv = '0'"
        );
        $objResponse->addScript("$('protcount').setHTML('" . $query['cnt'] . "');");
        $objResponse->addScript("SlideUp('pid_$pid');");
        $objResponse->addScript("SlideUp('pid_" . $pid . "a');");

        if ($query1) {
            $objResponse->addScript(
                "ShowBox('Protest Archived', 'The selected protest has been moved to the archive.', 'green', 'index.php?p=admin&c=bans', true);"
            );
            Log::add("m", "Protest Archived", "Protest ($pid) has been moved to the archive.");
        } else {
            $objResponse->addScript(
                "ShowBox('Error', 'There was a problem moving the protest to the archive. Check the logs for more info', 'red', 'index.php?p=admin&c=bans', true);"
            );
        }
    } else if($archiv == '2') { // restore protest
        $query1 = $GLOBALS['db']->Execute(
            "UPDATE `" . DB_PREFIX . "_protests` SET archiv = '0', archivedby = NULL WHERE pid = $pid"
        );
        $query = $GLOBALS['db']->GetRow(
            "SELECT count(pid) AS cnt FROM `" . DB_PREFIX . "_protests` WHERE archiv = '1'"
        );
        $objResponse->addScript("$('protcountarchiv').setHTML('" . $query['cnt'] . "');");
        $objResponse->addScript("SlideUp('apid_$pid');");
        $objResponse->addScript("SlideUp('apid_" . $pid . "a');");

        if ($query1) {
            $objResponse->addScript(
                "ShowBox('Protest Restored', 'The selected protest has been restored from the archive.', 'green', 'index.php?p=admin&c=bans', true);
                ");
            Log::add("m", "Protest Deleted", "Protest ($pid) has been restored from the archive.");
        } else {
            $objResponse->addScript(
                "ShowBox('Error', 'There was a problem restoring the protest from the archive. Check the logs for more info', 'red', 'index.php?p=admin&c=bans', true);"
            );
        }
    }
    return $objResponse;
}

/**
 * @param int $sid Submission ID
 * @return xajaxResponse
 */
function RemoveServer($sid)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_SERVERS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to remove a server, but doesnt have access.");
        return $objResponse;
    }
    $sid = (int)$sid;
    $objResponse->addScript("SlideUp('sid_$sid');");
    $servinfo = $GLOBALS['db']->GetRow("SELECT ip, port FROM `" . DB_PREFIX . "_servers` WHERE sid = $sid");
    $query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_servers` WHERE sid = $sid");
    $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_servers_groups` WHERE server_id = $sid");
    $GLOBALS['db']->Execute(
        "UPDATE `" . DB_PREFIX . "_admins_servers_groups` SET server_id = -1 WHERE server_id = $sid"
    );

    $query = $GLOBALS['db']->GetRow("SELECT count(sid) AS cnt FROM `" . DB_PREFIX . "_servers`");
    $objResponse->addScript("$('srvcount').setHTML('" . $query['cnt'] . "');");


    if($query1) {
        $objResponse->addScript(
            "ShowBox('Server Deleted', 'The selected server has been deleted from the database', 'green', 'index.php?p=admin&c=servers', true);"
        );
        Log::add("m", "Server Deleted", "Server ($servinfo[ip]:$servinfo[port]) has been deleted.");
    }
    else {
        $objResponse->addScript(
            "ShowBox('Error', 'There was a problem deleting the server from the database. Check the logs for more info', 'red', 'index.php?p=admin&c=servers', true);"
        );
    }
    return $objResponse;
}

function RemoveMod($mid)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_MODS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to remove a mod, but doesnt have access.");
        return $objResponse;
    }
    $mid = (int)$mid;
    $objResponse->addScript("SlideUp('mid_$mid');");

    $modicon = $GLOBALS['db']->GetRow("SELECT icon, name FROM `" . DB_PREFIX . "_mods` WHERE mid = '" . $mid . "';");
    @unlink(SB_ICONS."/".$modicon['icon']);

    $query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_mods` WHERE mid = '" . $mid . "'");

    if($query1) {
        $objResponse->addScript(
            "ShowBox('MOD Deleted', 'The selected MOD has been deleted from the database', 'green', 'index.php?p=admin&c=mods', true);"
        );
        Log::add("m", "MOD Deleted", "MOD ($modicon[name]) has been deleted.");
    }
    else {
        $objResponse->addScript(
            "ShowBox('Error', 'There was a problem deleting the MOD from the database. Check the logs for more info', 'red', 'index.php?p=admin&c=mods', true);"
        );
    }
    return $objResponse;
}

/**
 * @param int $aid
 * @return xajaxResponse
 */
function RemoveAdmin($aid)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_ADMINS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to remove an admin, but doesnt have access.");
        return $objResponse;
    }
    $aid = (int)$aid;
    $gid = $GLOBALS['db']->GetRow(
        "SELECT gid, authid, extraflags, user FROM `" . DB_PREFIX . "_admins` WHERE aid = $aid"
    );
    if((intval($gid[2]) & ADMIN_OWNER) != 0) {
        $objResponse->addAlert("Error: You cannot delete the owner.");
        return $objResponse;
    }

    $delquery = $GLOBALS['db']->Execute(sprintf("DELETE FROM `%s_admins` WHERE aid = %d LIMIT 1", DB_PREFIX, $aid));
    if($delquery) {
        if (Config::getBool('config.enableadminrehashing')) {
            // rehash the admins for the servers where this admin was on
            $serveraccessq = $GLOBALS['db']->GetAll(
                "SELECT s.sid FROM `" . DB_PREFIX . "_servers` s
    LEFT JOIN `" . DB_PREFIX . "_admins_servers_groups` asg ON asg.admin_id = '" . (int)$aid . "'
    LEFT JOIN `" . DB_PREFIX . "_servers_groups` sg ON sg.group_id = asg.srv_group_id
    WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
    OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
    AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1"
            );
            $allservers = array();
            foreach ($serveraccessq as $access) {
                if (!in_array($access['sid'], $allservers)) {
                    $allservers[] = $access['sid'];
                }
            }
            $rehashing = true;
        }

        $GLOBALS['db']->Execute(sprintf("DELETE FROM `%s_admins_servers_groups` WHERE admin_id = %d", DB_PREFIX, $aid));
    }

    $query = $GLOBALS['db']->GetRow("SELECT count(aid) AS cnt FROM `" . DB_PREFIX . "_admins`");
    $objResponse->addScript("SlideUp('aid_$aid');");
    $objResponse->addScript("$('admincount').setHTML('" . $query['cnt'] . "');");
    if($delquery) {
        if (isset($rehashing)) {
            $objResponse->addScript(
                "ShowRehashBox('" . implode(",", $allservers) . "', 'Admin Deleted', 'The selected admin has been deleted from the database', 'green', 'index.php?p=admin&c=admins', true);"
            );
        } else {
            $objResponse->addScript(
                "ShowBox('Admin Deleted', 'The selected admin has been deleted from the database', 'green', 'index.php?p=admin&c=admins', true);"
            );
        }
        Log::add("m", "Admin Deleted", "Admin ($gid[user]) has been deleted.");
    }
    else {
        $objResponse->addScript(
            "ShowBox('Error', 'There was an error removing the admin from the database, please check the logs', 'red', 'index.php?p=admin&c=admins', true);"
        );
    }
    return $objResponse;
}

/**
 * @param string $ip
 * @param int $port
 * @param string $rcon Password
 * @param string $rcon2 Password-verify
 * @param $mod
 * @param bool $enabled
 * @param $group
 * @param string $group_name Ignored
 * @return xajaxResponse
 */
function AddServer($ip, $port, $rcon, $rcon2, $mod, $enabled, $group, $group_name)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_SERVER)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to add a server, but doesnt have access.");
        return $objResponse;
    }

    $error = 0;
    // ip
    if((empty($ip))) {
        $error++;
        $objResponse->addAssign("address.msg", "innerHTML", "You must type the server address.");
        $objResponse->addScript("$('address.msg').setStyle('display', 'block');");
    }
    else {
        $objResponse->addAssign("address.msg", "innerHTML", "");
        if (!filter_var($ip, FILTER_VALIDATE_IP) && !is_string($ip)) {
            $error++;
            $objResponse->addAssign("address.msg", "innerHTML", "You must type a valid IP.");
            $objResponse->addScript("$('address.msg').setStyle('display', 'block');");
        } else {
            $objResponse->addAssign("address.msg", "innerHTML", "");
        }
    }
    // Port
    if((empty($port))) {
        $error++;
        $objResponse->addAssign("port.msg", "innerHTML", "You must type the server port.");
        $objResponse->addScript("$('port.msg').setStyle('display', 'block');");
    }
    else {
        $objResponse->addAssign("port.msg", "innerHTML", "");
        if (!is_numeric($port)) {
            $error++;
            $objResponse->addAssign("port.msg", "innerHTML", "You must type a valid port <b>number</b>.");
            $objResponse->addScript("$('port.msg').setStyle('display', 'block');");
        } else {
            $objResponse->addScript("$('port.msg').setStyle('display', 'none');");
            $objResponse->addAssign("port.msg", "innerHTML", "");
        }
    }
    // rcon
    if(!empty($rcon) && $rcon != $rcon2) {
        $error++;
        $objResponse->addAssign("rcon2.msg", "innerHTML", "The passwords don't match.");
        $objResponse->addScript("$('rcon2.msg').setStyle('display', 'block');");
    }
    else {
        $objResponse->addAssign("rcon2.msg", "innerHTML", "");
    }

    // Please Select
    if($mod == -2) {
        $error++;
        $objResponse->addAssign("mod.msg", "innerHTML", "You must select the mod your server runs.");
        $objResponse->addScript("$('mod.msg').setStyle('display', 'block');");
    }
    else {
        $objResponse->addAssign("mod.msg", "innerHTML", "");
    }

    if($group == -2) {
        $error++;
        $objResponse->addAssign("group.msg", "innerHTML", "You must select an option.");
        $objResponse->addScript("$('group.msg').setStyle('display', 'block');");
    }
    else {
        $objResponse->addAssign("group.msg", "innerHTML", "");
    }

    if($error) {
        return $objResponse;
    }

    // Check for dublicates afterwards
    $chk = $GLOBALS['db']->GetRow(
        'SELECT sid FROM `'.DB_PREFIX.'_servers` WHERE ip = ? AND port = ?;',
        array($ip, (int)$port)
    );
    if($chk) {
        $objResponse->addScript("ShowBox('Error', 'There already is a server with that IP:Port combination.', 'red');");
        return $objResponse;
    }

    // ##############################################################
    // ##                     Start adding to DB                   ##
    // ##############################################################
    //they wanna make a new group
    $gid = -1;
    $sid = nextSid();

    $enable = ($enabled=="true"?1:0);

    // Add the server
    $addserver = $GLOBALS['db']->Prepare(
        "INSERT INTO ".DB_PREFIX."_servers (`sid`, `ip`, `port`, `rcon`, `modid`, `enabled`)
      VALUES (?,?,?,?,?,?)"
    );
    $GLOBALS['db']->Execute($addserver, array($sid, $ip, (int)$port, $rcon, $mod, $enable));

    // Add server to each group specified
    $groups = explode(",", $group);
    $addtogrp = $GLOBALS['db']->Prepare(
        "INSERT INTO ".DB_PREFIX."_servers_groups (`server_id`, `group_id`) VALUES (?,?)"
    );
    foreach($groups AS $g) {
        if ($g) {
            $GLOBALS['db']->Execute($addtogrp, array($sid, $g));
        }
    }

    $objResponse->addScript(
        "ShowBox('Server Added', 'Your server has been successfully created.', 'green', 'index.php?p=admin&c=servers');"
    );
    $objResponse->addScript("TabToReload();");
    Log::add("m", "Server Added", "Server ($ip:$port) has been added.");
    return $objResponse;
}

/**
 * @param int $gid
 * @return xajaxResponse
 */
function UpdateGroupPermissions($gid)
{
    $objResponse = new xajaxResponse();
    global $userbank;
    $gid = (int)$gid;
    if($gid == 1) {
        $permissions = @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php");
        $permissions = str_replace("{title}", "Web Admin Permissions", $permissions);
    }
    elseif($gid == 2) {
        $permissions = @file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php");
        $permissions = str_replace("{title}", "Server Admin Permissions", $permissions);
    }
    elseif($gid == 3) {
        $permissions = "";
    }

    $objResponse->addAssign("perms", "innerHTML", $permissions);
    if(!$userbank->HasAccess(ADMIN_OWNER)) {
        $objResponse->addScript(
            'if($("wrootcheckbox")) {
    $("wrootcheckbox").setStyle("display", "none");
    }
    if($("srootcheckbox")) {
    $("srootcheckbox").setStyle("display", "none");
    }'
        );
    }
    $objResponse->addScript("$('type.msg').setHTML('');");
    $objResponse->addScript("$('type.msg').setStyle('display', 'none');");
    return $objResponse;
}

/**
 * @param int $type
 * @param string $value
 * @return xajaxResponse
 */
function UpdateAdminPermissions($type, $value)
{
    $objResponse = new xajaxResponse();
    global $userbank;
    $type = (int)$type;
    if($type == 1) {
        $id = "web";
        if ($value == "c") {
            $permissions = @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php");
            $permissions = str_replace("{title}", "Web Admin Permissions", $permissions);
        } elseif ($value == "n") {
            $permissions = @file_get_contents(TEMPLATES_PATH . "/group.name.php")
                . @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php");
            $permissions = str_replace("{name}", "webname", $permissions);
            $permissions = str_replace("{title}", "New Group Permissions", $permissions);
        } else {
            $permissions = "";
        }
    }
    if($type == 2) {
        $id = "server";
        if ($value == "c") {
            $permissions = file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php");
            $permissions = str_replace("{title}", "Server Admin Permissions", $permissions);
        } elseif ($value == "n") {
            $permissions = @file_get_contents(TEMPLATES_PATH . "/group.name.php")
                . @file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php");
            $permissions = str_replace("{name}", "servername", $permissions);
            $permissions = str_replace("{title}", "New Group Permissions", $permissions);
        } else {
            $permissions = "";
        }
    }

    $objResponse->addAssign($id."perm", "innerHTML", $permissions);
    if(!$userbank->HasAccess(ADMIN_OWNER)) {
        $objResponse->addScript(
            'if($("wrootcheckbox")) {
    $("wrootcheckbox").setStyle("display", "none");
    }
    if($("srootcheckbox")) {
    $("srootcheckbox").setStyle("display", "none");
    }'
        );
    }
    $objResponse->addAssign($id.".msg", "innerHTML", "");
    return $objResponse;

}

function AddServerGroupName()
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_GROUPS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to edit group name, but doesnt have access.");
        return $objResponse;
    }
    $inject = <<<EOT
<td valign='top'>
    <div class='rowdesc'>
        <img align='absbottom' src='images/help.png' class='tip'
        title='Server Group Name::Please type the name of the new group you wish to create.'/>
    </div>
</td>
<td>
    <div align="left">
        <input type="text"
        style="border: 1px solid #000000; width: 105px; font-size: 14px; background-color: rgb(215, 215, 215);width: 200px;"
        id="sgroup" name="sgroup" />
    </div>
    <div id="group_name.msg" style="color:#CC0000;width:195px;display:none;"></div>
</td>
EOT;
    $objResponse->addAssign("nsgroup", "innerHTML", $inject);
    $objResponse->addAssign("group.msg", "innerHTML", "");
    return $objResponse;

}

/**
 * @param int $mask
 * @param $srv_mask
 * @param string $a_name
 * @param string $a_steam
 * @param string $a_email
 * @param string $a_password
 * @param string $a_password2
 * @param $a_sg
 * @param $a_wg
 * @param $a_serverpass
 * @param $a_webname
 * @param $a_servername
 * @param $server
 * @param $singlesrv
 * @return xajaxResponse
 */
function AddAdmin(
    $mask,
    $srv_mask,
    $a_name,
    $a_steam,
    $a_email,
    $a_password,
    $a_password2,
    $a_sg,
    $a_wg,
    $a_serverpass,
    $a_webname,
    $a_servername,
    $server,
    $singlesrv)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_ADMINS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to add an admin, but doesnt have access.");
        return $objResponse;
    }
    $a_steam = SteamID::toSteam2($a_steam);
    $a_servername = ($a_servername == "0" ? null : $a_servername);
    $mask = (int)$mask;

    $error=0;

    //No name
    if (empty($a_name)) {
        $error++;
        $objResponse->addAssign("name.msg", "innerHTML", "You must type a name for the admin.");
        $objResponse->addScript("$('name.msg').setStyle('display', 'block');");
    } else {
        if (strstr($a_name, "'")) {
            $error++;
            $objResponse->addAssign("name.msg", "innerHTML", "An admin name can not contain a \" ' \".");
            $objResponse->addScript("$('name.msg').setStyle('display', 'block');");
        } else {
            if ($userbank->isNameTaken($a_name)) {
                $error++;
                $objResponse->addAssign("name.msg", "innerHTML", "An admin with this name already exists");
                $objResponse->addScript("$('name.msg').setStyle('display', 'block');");
            } else {
                $objResponse->addAssign("name.msg", "innerHTML", "");
                $objResponse->addScript("$('name.msg').setStyle('display', 'none');");
            }
        }
    }
    // If they didnt type a steamid
    if (empty($a_steam)) {
        $error++;
        $objResponse->addAssign("steam.msg", "innerHTML", "You must type a Steam ID or Community ID for the admin.");
        $objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
    } else {
        // Validate the steamid or fetch it from the community id
        if (!SteamID::isValidID($a_steam)) {
            $error++;
            $objResponse->addAssign("steam.msg", "innerHTML", "Please enter a valid Steam ID or Community ID.");
            $objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
        } else {
            if ($userbank->isSteamIDTaken($a_steam)) {
                $admins = $userbank->GetAllAdmins();
                foreach ($admins as $admin) {
                    if ($admin['authid'] == $a_steam) {
                        $name = $admin['user'];
                        break;
                    }
                }
                $error++;
                $objResponse->addAssign(
                    "steam.msg",
                    "innerHTML",
                    "Admin ".htmlspecialchars(addslashes($name))." already uses this Steam ID."
                );
                $objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
            } else {
                $objResponse->addAssign("steam.msg", "innerHTML", "");
                $objResponse->addScript("$('steam.msg').setStyle('display', 'none');");
            }
        }
    }

    // No email
    if (empty($a_email)) {
        // An E-Mail address is only required for users with web permissions.
        if ($mask != 0) {
            $error++;
            $objResponse->addAssign("email.msg", "innerHTML", "You must type an e-mail address.");
            $objResponse->addScript("$('email.msg').setStyle('display', 'block');");
        }
    } else {
        // Is an other admin already registred with that email address?
        if ($userbank->isEmailTaken($a_email)) {
            $admins = $userbank->GetAllAdmins();
            foreach ($admins as $admin) {
                if ($admin['email'] == $a_email) {
                    $name = $admin['user'];
                    break;
                }
            }
            $error++;
            $objResponse->addAssign("email.msg", "innerHTML", "This email address is already being used by " . htmlspecialchars(addslashes($name)) . ".");
            $objResponse->addScript("$('email.msg').setStyle('display', 'block');");
        } else {
            $objResponse->addAssign("email.msg", "innerHTML", "");
            $objResponse->addScript("$('email.msg').setStyle('display', 'none');");
        }
    }

    // no pass
    if (empty($a_password)) {
        $error++;
        $objResponse->addAssign("password.msg", "innerHTML", "You must type a password.");
        $objResponse->addScript("$('password.msg').setStyle('display', 'block');");
    } elseif (strlen($a_password) < MIN_PASS_LENGTH) {
        // Password too short?
        $error++;
        $objResponse->addAssign("password.msg", "innerHTML", "Your password must be at-least " . MIN_PASS_LENGTH . " characters long.");
        $objResponse->addScript("$('password.msg').setStyle('display', 'block');");
    } else {
        $objResponse->addAssign("password.msg", "innerHTML", "");
        $objResponse->addScript("$('password.msg').setStyle('display', 'none');");

        // No confirmation typed
        if (empty($a_password2)) {
            $error++;
            $objResponse->addAssign("password2.msg", "innerHTML", "You must confirm the password");
            $objResponse->addScript("$('password2.msg').setStyle('display', 'block');");
        } elseif ($a_password != $a_password2) {
            // Passwords match?
            $error++;
            $objResponse->addAssign("password2.msg", "innerHTML", "Your passwords don't match");
            $objResponse->addScript("$('password2.msg').setStyle('display', 'block');");
        } else {
            $objResponse->addAssign("password2.msg", "innerHTML", "");
            $objResponse->addScript("$('password2.msg').setStyle('display', 'none');");
        }
    }

    // Choose to use a server password
    if($a_serverpass != "-1") {
        // No password given?
        if (empty($a_serverpass)) {
            $error++;
            $objResponse->addAssign(
                "a_serverpass.msg",
                "innerHTML",
                "You must type a server password or uncheck the box."
            );
            $objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'block');");
        } // Password too short?
        else if (strlen($a_serverpass) < MIN_PASS_LENGTH) {
            $error++;
            $objResponse->addAssign(
                "a_serverpass.msg",
                "innerHTML",
                "Your password must be at-least " . MIN_PASS_LENGTH . " characters long."
            );
            $objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'block');");
        } else {
            $objResponse->addAssign("a_serverpass.msg", "innerHTML", "");
            $objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'none');");
        }
    }
    else {
        $objResponse->addAssign("a_serverpass.msg", "innerHTML", "");
        $objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'none');");
        // Don't set "-1" as password ;)
        $a_serverpass = "";
    }

    // didn't choose a server group
    if($a_sg == "-2") {
        $error++;
        $objResponse->addAssign("server.msg", "innerHTML", "You must choose a group.");
        $objResponse->addScript("$('server.msg').setStyle('display', 'block');");
    }
    else
    {
        $objResponse->addAssign("server.msg", "innerHTML", "");
        $objResponse->addScript("$('server.msg').setStyle('display', 'none');");
    }

    // chose to create a new server group
    if($a_sg == 'n') {
        // didn't type a name
        if (empty($a_servername)) {
            $error++;
            $objResponse->addAssign("servername_err", "innerHTML", "You need to type a name for the new group.");
            $objResponse->addScript("$('servername_err').setStyle('display', 'block');");
        } // Group names can't contain ,
        else if (strstr($a_servername, ',')) {
            $error++;
            $objResponse->addAssign("servername_err", "innerHTML", "Group name cannot contain a ','");
            $objResponse->addScript("$('servername_err').setStyle('display', 'block');");
        } else {
            $objResponse->addAssign("servername_err", "innerHTML", "");
            $objResponse->addScript("$('servername_err').setStyle('display', 'none');");
        }
    }

    // didn't choose a web group
    if($a_wg == "-2") {
        $error++;
        $objResponse->addAssign("web.msg", "innerHTML", "You must choose a group.");
        $objResponse->addScript("$('web.msg').setStyle('display', 'block');");
    }
    else
    {
        $objResponse->addAssign("web.msg", "innerHTML", "");
        $objResponse->addScript("$('web.msg').setStyle('display', 'none');");
    }

    // Choose to create a new webgroup
    if($a_wg == 'n') {
        // But didn't type a name
        if (empty($a_webname)) {
            $error++;
            $objResponse->addAssign("webname_err", "innerHTML", "You need to type a name for the new group.");
            $objResponse->addScript("$('webname_err').setStyle('display', 'block');");
        } // Group names can't contain ,
        else if (strstr($a_webname, ',')) {
            $error++;
            $objResponse->addAssign("webname_err", "innerHTML", "Group name cannot contain a ','");
            $objResponse->addScript("$('webname_err').setStyle('display', 'block');");
        } else {
            $objResponse->addAssign("webname_err", "innerHTML", "");
            $objResponse->addScript("$('webname_err').setStyle('display', 'none');");
        }
    }

    // Ohnoes! something went wrong, stop and show errs
    if($error) {
        $objResponse->AddScript(
            "ShowBox('Error', 'There are some errors in your input. Please correct them.', 'red', '', true);"
        );
        return $objResponse;
    }

    // ##############################################################
    // ##                     Start adding to DB                   ##
    // ##############################################################

    $gid = 0;
    $groupID = 0;
    $inGroup = false;
    $wgid = NextAid();
    $immunity = 0;

    // Extract immunity from server mask string
    if (strstr($srv_mask, "#")) {
        $immunity = "0";
        $immunity = substr($srv_mask, strpos($srv_mask, "#")+1);
        $srv_mask = substr($srv_mask, 0, strlen($srv_mask) - strlen($immunity)-1);
    }

    // Avoid negative immunity
    $immunity = ($immunity>0) ? $immunity : 0;

    // Handle Webpermissions
    // Chose to create a new webgroup
    if ($a_wg == 'n') {
        $add_webgroup = $GLOBALS['db']->Execute(
            "INSERT INTO ".DB_PREFIX."_groups(type, name, flags)
        VALUES (?,?,?)", array(1, $a_webname, $mask)
        );
        $web_group = (int)$GLOBALS['db']->Insert_ID();

        // We added those permissons to the group, so don't add them as custom permissions again
        $mask = 0;
    } elseif ($a_wg != 'c' && $a_wg > 0) {
        // Chose an existing group
        $web_group = (int)$a_wg;
    } else {
        // Custom permissions -> no group
        $web_group = -1;
    }

    // Handle Serverpermissions
    // Chose to create a new server admin group
    if($a_sg == 'n') {
        $add_servergroup = $GLOBALS['db']->Execute(
            "INSERT INTO " . DB_PREFIX . "_srvgroups(immunity, flags, name, groups_immune)
    VALUES (?,?,?,?)", array($immunity, $srv_mask, $a_servername, " ")
        );

        $server_admin_group = $a_servername;
        $server_admin_group_int = (int)$GLOBALS['db']->Insert_ID();

        // We added those permissons to the group, so don't add them as custom permissions again
        $srv_mask = "";
    }
    // Chose an existing group
    else if($a_sg != 'c' && $a_sg > 0) {
        $server_admin_group = $GLOBALS['db']->GetOne(
            "SELECT `name` FROM " . DB_PREFIX . "_srvgroups WHERE id = '" . (int)$a_sg . "'"
        );
        $server_admin_group_int = (int)$a_sg;
    }
    // Custom permissions -> no group
    else {
        $server_admin_group = "";
        $server_admin_group_int = -1;
    }

    // Add the admin
    $aid = $userbank->AddAdmin(
        $a_name, $a_steam, $a_password, $a_email, $web_group, $mask, $server_admin_group, $srv_mask, $immunity, $a_serverpass
    );

    if($aid > -1) {
        // Grant permissions to the selected server groups
        $srv_groups = explode(",", $server);
        $addtosrvgrp = $GLOBALS['db']->Prepare(
            "INSERT INTO " . DB_PREFIX . "_admins_servers_groups(admin_id,group_id,srv_group_id,server_id) VALUES (?,?,?,?)"
        );
        foreach ($srv_groups AS $srv_group) {
            if (!empty($srv_group)) {
                $GLOBALS['db']->Execute(
                    $addtosrvgrp,
                    array($aid, $server_admin_group_int, substr($srv_group, 1), '-1')
                );
            }
        }

        // Grant permissions to individual servers
        $srv_arr = explode(",", $singlesrv);
        $addtosrv = $GLOBALS['db']->Prepare(
            "INSERT INTO " . DB_PREFIX . "_admins_servers_groups(admin_id,group_id,srv_group_id,server_id) VALUES (?,?,?,?)"
        );
        foreach ($srv_arr AS $server) {
            if (!empty($server)) {
                $GLOBALS['db']->Execute($addtosrv, array($aid, $server_admin_group_int, '-1', substr($server, 1)));
            }
        }
        if (Config::getBool('config.enableadminrehashing')) {
            // rehash the admins on the servers
            $serveraccessq = $GLOBALS['db']->GetAll(
                "SELECT s.sid FROM `" . DB_PREFIX . "_servers` s
    LEFT JOIN `" . DB_PREFIX . "_admins_servers_groups` asg ON asg.admin_id = '" . (int)$aid . "'
    LEFT JOIN `" . DB_PREFIX . "_servers_groups` sg ON sg.group_id = asg.srv_group_id
    WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
    OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
    AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1"
            );
            $allservers = array();
            foreach ($serveraccessq as $access) {
                if (!in_array($access['sid'], $allservers)) {
                    $allservers[] = $access['sid'];
                }
            }
            $objResponse->addScript(
                "ShowRehashBox('" . implode(",", $allservers) . "','Admin Added', 'The admin has been added successfully', 'green', 'index.php?p=admin&c=admins');TabToReload();"
            );
        } else {
            $objResponse->addScript(
                "ShowBox('Admin Added', 'The admin has been added successfully', 'green', 'index.php?p=admin&c=admins');TabToReload();"
            );
        }

        Log::add("m", "Admin added", "Admin ($a_name) has been added.");
        return $objResponse;
    }
    else
    {
        $objResponse->addScript(
            "ShowBox('User NOT Added', 'The admin failed to be added to the database. Check the logs for any SQL errors.', 'red', 'index.php?p=admin&c=admins');"
        );
    }
}

function ServerHostPlayers($sid, $type="servers", $obId="", $tplsid="", $open="", $inHome=false, $trunchostname=48)
{
    global $userbank;

    $objResponse = new xajaxResponse();

    $GLOBALS['PDO']->query('SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid');
    $GLOBALS['PDO']->bind(':sid', $sid, PDO::PARAM_INT);
    $server = $GLOBALS['PDO']->single();

    if (empty($server['ip']) || empty($server['port'])) {
        return $objResponse;
    }

    $query = new SourceQuery();
    try {
        $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
        $info = $query->GetInfo();
		$info['HostName'] = preg_replace('/[\x00-\x1f]/','', htmlspecialchars($info['HostName']));
        $players = $query->GetPlayers();
    } catch (Exception $e) {
        if ($userbank->HasAccess(ADMIN_OWNER)) {
            $objResponse->addAssign(
                "host_$sid",
                "innerHTML",
                "<b>Error connecting</b> (<i>".$server['ip'].":".$server['port']."</i>) <small><a href=\"https://sbpp.github.io/faq/\" title=\"Which ports does the SourceBans webpanel require to be open?\">Help</a></small>"
            );
        } else {
            $objResponse->addAssign(
                "host_$sid",
                "innerHTML",
                "<b>Error connecting</b> (<i>".$server['ip'].":".$server['port']."</i>)"
            );
            $objResponse->addAssign("players_$sid", "innerHTML", "N/A");
            $objResponse->addAssign("os_$sid", "innerHTML", "N/A");
            $objResponse->addAssign("vac_$sid", "innerHTML", "N/A");
            $objResponse->addAssign("map_$sid", "innerHTML", "N/A");
        }
        if (!$inHome) {
            $objResponse->addScript("$('sinfo_$sid').setStyle('display', 'none');");
            $objResponse->addScript("$('noplayer_$sid').setStyle('display', 'block');");
            $objResponse->addScript("$('serverwindow_$sid').setStyle('height', '64px');");
            $objResponse->addScript("if($('sid_$sid'))$('sid_$sid').setStyle('color', '#adadad');");
        }
        if ($type == "id") {
            $objResponse->addAssign(
                "$obId",
                "innerHTML",
                "<b>Error connecting</b> (<i>".$server['ip'].":".$server['port']. "</i>)"
            );
        }
        return $objResponse;
    } finally {
        $query->Disconnect();
    }

    if ($type == "servers") {
        if (!empty($info['HostName'])) {
            $objResponse->addAssign("host_$sid", "innerHTML", trunc($info['HostName'], $trunchostname));
            $objResponse->addAssign("players_$sid", "innerHTML", $info['Players'] . "/" . $info['MaxPlayers']);
            switch ($info['Os']) {
            case 'w':
                $os = 'fab fa-windows';
                break;
            case 'l':
                $os = 'fab fa-linux';
                break;
            default:
                $os = 'fas fa-server';
            }
            $objResponse->addAssign("os_$sid", "innerHTML", "<i class='$os fa-2x'></i>");
            if ($info['Secure']) {
                $objResponse->addAssign("vac_$sid", "innerHTML", "<i class='fas fa-shield-alt fa-2x'></i>");
            }
            $objResponse->addAssign("map_$sid", "innerHTML", basename($info['Map']));
            if (!$inHome) {
                $objResponse->addScript(
                    "$('mapimg_$sid').setProperty('src', '".GetMapImage($info['Map'])."').setProperty('alt', '".$info['Map']."').setProperty('title', '".basename($info['Map'])."');"
                );
                if ($info['Players'] == 0 || empty($info['Players'])) {
                    $objResponse->addScript("$('sinfo_$sid').setStyle('display', 'none');");
                    $objResponse->addScript("$('noplayer_$sid').setStyle('display', 'block');");
                    $objResponse->addScript("$('serverwindow_$sid').setStyle('height', '64px');");
                } else {
                    $objResponse->addScript("$('sinfo_$sid').setStyle('display', 'block');");
                    $objResponse->addScript("$('noplayer_$sid').setStyle('display', 'none');");
                    if (!defined('IN_HOME')) {

                        // remove childnodes
                        $objResponse->addScript(
                            'var toempty = document.getElementById("playerlist_'.$sid.'");
                            var empty = toempty.cloneNode(false);
                            toempty.parentNode.replaceChild(empty,toempty);'
                        );
                        //draw table headlines
                        $objResponse->addScript(
                            'var e = document.getElementById("playerlist_'.$sid.'");
                            var tr = e.insertRow("-1");
                            // Name Top TD
                            var td = tr.insertCell("-1");
                            td.setAttribute("width","45%");
                            td.setAttribute("height","16");
                            td.className = "listtable_top";
                            var b = document.createElement("b");
                            var txt = document.createTextNode("Name");
                            b.appendChild(txt);
                            td.appendChild(b);
                            // Score Top TD
                            var td = tr.insertCell("-1");
                            td.setAttribute("width","10%");
                            td.setAttribute("height","16");
                            td.className = "listtable_top";
                            var b = document.createElement("b");
                            var txt = document.createTextNode("Score");
                            b.appendChild(txt);
                            td.appendChild(b);
                            // Time Top TD
                            var td = tr.insertCell("-1");
                            td.setAttribute("height","16");
                            td.className = "listtable_top";
                            var b = document.createElement("b");
                            var txt = document.createTextNode("Time");
                            b.appendChild(txt);
                            td.appendChild(b);'
                        );
                        // add players
                        $playercount = 0;
                        foreach ($players as $player) {
                            $player["Id"] = $playercount;
                            $objResponse->addScript(
                                'var e = document.getElementById("playerlist_'.$sid.'");
                                var tr = e.insertRow("-1");
                                tr.className="tbl_out";
                                tr.onmouseout = function(){this.className="tbl_out"};
                                tr.onmouseover = function(){this.className="tbl_hover"};
                                tr.id = "player_s'.$sid.'p'.$player["Id"].'";
                                // Name TD
                                var td = tr.insertCell("-1");
                                td.className = "listtable_1";
                                var txt = document.createTextNode("'.str_replace('"', '\"', $player["Name"]).'");
                                td.appendChild(txt);
                                // Score TD
                                var td = tr.insertCell("-1");
                                td.className = "listtable_1";
                                var txt = document.createTextNode("'.$player["Frags"].'");
                                td.appendChild(txt);
                                // Time TD
                                var td = tr.insertCell("-1");
                                td.className = "listtable_1";
                                var txt = document.createTextNode("'.$player["TimeF"].'");
                                td.appendChild(txt);
                            '
                            );
                            if ($userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
                                $objResponse->addScript(
                                    'AddContextMenu("#player_s'.$sid.'p'.$player["Id"].'", "contextmenu", true, "Player Commands", [
                                    {name: "Kick", callback: function(){KickPlayerConfirm('.$sid.', "'.str_replace('"', '\"', $player["Name"]).'", 0);}},
                                    {name: "Block Comms", callback: function(){window.location = "index.php?p=admin&c=comms&action=pasteBan&sid='.$sid.'&pName='.str_replace('"', '\"', $player["Name"]).'"}},
                                    {name: "Ban", callback: function(){window.location = "index.php?p=admin&c=bans&action=pasteBan&sid='.$sid.'&pName='.str_replace('"', '\"', $player["Name"]).'"}},
                                    {separator: true},
                                    '.(ini_get('safe_mode')==0 ? '{name: "View Profile", callback: function(){ViewCommunityProfile('.$sid.', "'.str_replace('"', '\"', $player["Name"]).'")}},':'').'
                                    {name: "Send Message", callback: function(){OpenMessageBox('.$sid.', "'.str_replace('"', '\"', $player["Name"]).'", 1)}}
                                ]);'
                                );
                            }
                            $playercount++;
                        }
                    }
                    if ($playercount > 15) {
                        $height = 329 + 16 * ($playercount-15) + 4 * ($playercount-15) . "px";
                    } else {
                        $height = 329 . "px";
                    }
                    $objResponse->addScript("$('serverwindow_$sid').setStyle('height', '".$height."');");
                }
            }
        } else {
            if ($userbank->HasAccess(ADMIN_OWNER)) {
                $objResponse->addAssign(
                    "host_$sid",
                    "innerHTML",
                    "<b>Error connecting</b> (<i>" . $res[1] . ":" . $res[2]. "</i>) <small><a href=\"https://sbpp.github.io/faq/\" title=\"Which ports does the SourceBans webpanel require to be open?\">Help</a></small>"
                );
            } else {
                $objResponse->addAssign(
                    "host_$sid",
                    "innerHTML",
                    "<b>Error connecting</b> (<i>" . $res[1] . ":" . $res[2]. "</i>)"
                );
                $objResponse->addAssign("players_$sid", "innerHTML", "N/A");
                $objResponse->addAssign("os_$sid", "innerHTML", "N/A");
                $objResponse->addAssign("vac_$sid", "innerHTML", "N/A");
                $objResponse->addAssign("map_$sid", "innerHTML", "N/A");
            }
            if (!$inHome) {
                $objResponse->addScript("$('sinfo_$sid').setStyle('display', 'none');");
                $objResponse->addScript("$('noplayer_$sid').setStyle('display', 'block');");
                $objResponse->addScript("$('serverwindow_$sid').setStyle('height', '64px');");
                $objResponse->addScript("if($('sid_$sid'))$('sid_$sid').setStyle('color', '#adadad');");
            }
        }
    }
    if ($tplsid != "" && $open != "" && $tplsid==$open) {
        $objResponse->addScript("InitAccordion('tr.opener', 'div.opener', 'mainwrapper', '".$open."');");
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        $objResponse->addScript("$('dialog-placement').setStyle('display', 'none');");
    } elseif ($type=="id") {
        if (!empty($info['HostName'])) {
            $objResponse->addAssign("$obId", "innerHTML", trunc($info['HostName'], $trunchostname));
        } else {
            $objResponse->addAssign(
                "$obId",
            "innerHTML",
            "<b>Error connecting</b> (<i>".$server['ip'].":".$server['port']. "</i>)"
            );
        }
    } else {
        if (!empty($info['HostName'])) {
            $objResponse->addAssign("ban_server_$type", "innerHTML", trunc($info['HostName'], $trunchostname));
        }else{
            $objResponse->addAssign(
                "ban_server_$type",
                "innerHTML",
                "<b>Error connecting</b> (<i>".$server['ip'].":".$server['port']."</i>)"
            );
        }
    }
    return $objResponse;
}

/**
 * @param int $sid Server ID
 * @param $obId
 * @param $obProp
 * @param $trunchostname
 * @return xajaxResponse
 */
function ServerHostProperty($sid, $obId, $obProp, $trunchostname)
{
    global $userbank;

    $objResponse = new xajaxResponse();

    $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid, PDO::PARAM_INT);
    $server = $GLOBALS['PDO']->single();

    if (empty($server['ip']) || empty($server['port'])) {
        return $objResponse;
    }

    $query = new SourceQuery();
    try {
        $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
        $info = $query->GetInfo();
    } catch (Exception $e) {
        $objResponse->addAssign("$obId", "$obProp", "Error connecting (".$server['ip'].":".$server['port'].")");
        return $objResponse;
    } finally {
        $query->Disconnect();
    }

    if(!empty($info['HostName'])) {
        $objResponse->addAssign("$obId", "$obProp", trunc($info['HostName'], $trunchostname));
    } else {
        $objResponse->addAssign("$obId", "$obProp", "Error connecting (".$server['ip'].":".$server['port'].")");
    }
    return $objResponse;
}

/**
 * @param int $sid Server id
 * @param string $type
 * @param string $obId
 * @return xajaxResponse
 */
function ServerHostPlayers_list($sid, $type="servers", $obId="")
{
    global $userbank;

    $objResponse = new xajaxResponse();

    $ids = explode(";", $sid, -1);
    if (count($ids) < 1) {
        return $objResponse;
    }

    $ret = "";
    foreach ($ids as $sid) {
        $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
        $GLOBALS['PDO']->bind(':sid', $sid, PDO::PARAM_INT);
        $server = $GLOBALS['PDO']->single();

        if (empty($server['ip']) || empty($server['port'])) {
            return $objResponse;
        }

        $query = new SourceQuery();
        try {
            $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
            $info = $query->GetInfo();
        } catch (Exception $e) {
            $ret .= "<b>Error connecting</b> (<i>".$server['ip'].":".$server['port']."</i>)<br />";
            continue;
        } finally {
            $query->Disconnect();
        }

        if (!empty($info['HostName'])) {
            $ret .= trunc($info['HostName'], 48) . "<br />";
        } else {
            $ret .= "<b>Error connecting</b> (<i>".$server['ip'].":".$server['port']."</i>)<br />";
        }
    }

    if ($type=="id") {
        $objResponse->addAssign("$obId", "innerHTML", $ret);
    } else {
        $objResponse->addAssign("ban_server_$type", "innerHTML", $ret);
    }

    return $objResponse;
}

/**
 * @param int $sid Server ID
 * @return xajaxResponse
 */
function ServerPlayers($sid)
{
    global $userbank;

    $objResponse = new xajaxResponse();

    $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid, PDO::PARAM_INT);
    $server = $GLOBALS['PDO']->single();

    if (empty($server['ip']) || empty($server['port'])) {
        return $objResponse;
    }

    $query = new SourceQuery();
    try {
        $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
        $players = $query->GetPlayers();
    } catch (Exception $e) {
        return $objResponse;
    } finally {
        $query->Disconnect();
    }

    if (empty($players)) {
        return $objResponse;
    }

    $html = "";
    foreach ($players as $player) {
        $html .= '
            <tr>
                <td class="listtable_1">'.htmlentities($player['Name']).'</td>
                <td class="listtable_1">'.(int)$player['Frags'].'</td>
                <td class="listtable_1">'.$player['Time'].'</td>
            </tr>';
    }

    $objResponse->addAssign("player_detail_$sid", "innerHTML", $html);
    $objResponse->addScript("setTimeout('xajax_ServerPlayers($sid)', 5000);");
    $objResponse->addScript("$('opener_$sid').setProperty('onclick', '');");
    return $objResponse;
}

/**
 * @param int $sid
 * @param string $name
 * @return xajaxResponse
 */
function KickPlayer(int $sid, $name)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;

    $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");

    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to kick $name, but doesn't have access.");
        return $objResponse;
    }

    $ret = rcon('status', $sid);

    if (!$ret) {
        $objResponse->addScript(
            "ShowBox('Error', 'Can\'t kick ".addslashes(htmlspecialchars($name)).". Can\'t connect to server!', 'red', '', true);"
        );
        return $objResponse;
    }

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($player['name'], $name)) {
            //Todo: Rewrite query to ignore STEAM_[01] prefix
            $GLOBALS['PDO']->query(
                "SELECT a.immunity AS aimmune, g.immunity AS gimmune FROM `:prefix_admins` AS a
                LEFT JOIN `:prefix_srvgroups` AS g ON g.name = a.srv_group WHERE authid = :authid"
            );

            $GLOBALS['PDO']->bind(':authid', SteamID::toSteam2($player['steamid']));
            $admin = $GLOBALS['PDO']->single();

            $immune = 0;
            if ($admin && $admin['gimmune'] > $admin['aimmune']) {
                $immune = $admin['gimmune'];
            } else if ($admin) {
                $immune = $admin['aimmune'];
            }

            if($immune <= $userbank->GetProperty('srv_immunity')) {
                rcon("sm_kick #$player[id] You have been kicked from this server", $sid);
                Log::add("m", "Player kicked", "$username kicked player $player[name] ($player[steamid])");
                $objResponse->addScript(
                    "ShowBox('Success', 'Player ".addslashes($name)." has been kicked from the server!', 'green', '', true);"
                );
                return $objResponse;
            } else {
                $objResponse->addScript(
                    "ShowBox('Error', 'Can\'t kick ".addslashes($name).". Player is immune!', 'red', '', true);"
                );
            }
        }
    }

    $objResponse->addScript(
        "ShowBox('Error', 'Can\'t kick ".addslashes($name).". Player not on the server anymore!', 'red', '', true);"
    );
    return $objResponse;
}

function PasteBan(int $sid, $name, int $type = 0)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;

    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried paste a ban, but doesn't have access.");
        return $objResponse;
    }

    $ret = rcon('status', $sid);

    if (!$ret) {
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        $objResponse->addScript("ShowBox('Error', 'Can\'t connect to server!', 'red', '', true);");
        return $objResponse;
    }

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($player['name'], $name)) {
            $steam = SteamID::toSteam2($player['steamid']);
            $objResponse->addScript(
                "$('nickname').value = '" . addslashes(html_entity_decode($name, ENT_QUOTES)) . "'"
            );

            $objResponse->addScript("$('type').options[0].selected = true");
            $objResponse->addScript("$('steam').value = '$steam'");
            $objResponse->addScript("$('ip').value = '$player[ip]'");

            $objResponse->addScript("swapTab(0);");
            $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
            $objResponse->addScript("$('dialog-placement').setStyle('display', 'none');");
            return $objResponse;
        }
    }

    $objResponse->addScript(
        "ShowBox('Error', 'Can\'t get player info for ".addslashes(htmlspecialchars($name)).". Player is not on the server anymore!', 'red', '', true);"
    );
    $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
    return $objResponse;
}

/**
 * @param string $nickname
 * @param int $type
 * @param string $steam
 * @param string $ip
 * @param int $length
 * @param $dfile
 * @param string $dname
 * @param string $reason
 * @param $fromsub
 * @return xajaxResponse
 */
function AddBan($nickname, $type, $steam, $ip, $length, $dfile, $dname, $reason, $fromsub)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to add a ban, but doesnt have access.");
        return $objResponse;
    }

    $steam = SteamID::toSteam2(trim($steam));
    $nickname = htmlspecialchars_decode($nickname, ENT_QUOTES);
    $ip = preg_replace('#[^\d\.]#', '', $ip);//strip ip of all but numbers and dots
    $dname = htmlspecialchars_decode($dname, ENT_QUOTES);
    $reason = htmlspecialchars_decode($reason, ENT_QUOTES);

    $error = 0;
    // If they didnt type a steamid
    if (empty($steam) && $type == 0) {
        $error++;
        $objResponse->addAssign("steam.msg", "innerHTML", "You must type a Steam ID or Community ID");
        $objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
    } elseif ($type == 0 && !SteamID::isValidID($steam)) {
        $error++;
        $objResponse->addAssign("steam.msg", "innerHTML", "Please enter a valid Steam ID or Community ID");
        $objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
    } elseif (empty($ip) && $type == 1) {
        $error++;
        $objResponse->addAssign("ip.msg", "innerHTML", "You must type an IP");
        $objResponse->addScript("$('ip.msg').setStyle('display', 'block');");
    } elseif ($type == 1 && !filter_var($ip, FILTER_VALIDATE_IP)) {
        $error++;
        $objResponse->addAssign("ip.msg", "innerHTML", "You must type a valid IP");
        $objResponse->addScript("$('ip.msg').setStyle('display', 'block');");
    } elseif ($length < 0) {
        $error++;
        $objResponse->addAssign("length.msg", "innerHTML", "Length must be positive or 0");
        $objResponse->addScript("$('length.msg').setStyle('display', 'block');");
    } else {
        $objResponse->addAssign("steam.msg", "innerHTML", "");
        $objResponse->addScript("$('steam.msg').setStyle('display', 'none');");
        $objResponse->addAssign("ip.msg", "innerHTML", "");
        $objResponse->addScript("$('ip.msg').setStyle('display', 'none');");
    }

    if ($error > 0) {
        return $objResponse;
    }

    if (!$length) {
        $len = 0;
    } else {
        $len = $length*60;
    }

    // prune any old bans
    PruneBans();
    if ((int)$type==0) {
        // Check if the new steamid is already banned
        $chk = $GLOBALS['db']->GetRow(
            "SELECT count(bid) AS count
            FROM ".DB_PREFIX."_bans
            WHERE authid = ?
            AND (length = 0 OR ends > UNIX_TIMESTAMP())
            AND RemovedBy IS NULL
            AND type = '0'",
            array($steam)
        );

        if (intval($chk[0]) > 0) {
            $objResponse->addScript("ShowBox('Error', 'SteamID: $steam is already banned.', 'red', '');");
            return $objResponse;
        }

        // Check if player is immune
        $admchk = $userbank->GetAllAdmins();
        foreach ($admchk as $admin) {
            if ($admin['authid'] == $steam && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity']) {
                $objResponse->addScript(
                    "ShowBox('Error', 'SteamID: Admin ".$admin['user']." ($steam) is immune.', 'red', '');"
                );
                return $objResponse;
            }
        }
    }
    if ((int)$type==1) {
        $chk = $GLOBALS['db']->GetRow(
            "SELECT count(bid) AS count FROM ".DB_PREFIX."_bans WHERE ip = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '1'",
            array($ip)
        );

        if (intval($chk[0]) > 0) {
            $objResponse->addScript("ShowBox('Error', 'IP: $ip is already banned.', 'red', '');");
            return $objResponse;
        }
    }

    $pre = $GLOBALS['db']->Prepare(
        "INSERT INTO " . DB_PREFIX . "_bans(created,type,ip,authid,name,ends,length,reason,aid,adminIp ) VALUES
    (UNIX_TIMESTAMP(),?,?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)"
    );
    $GLOBALS['db']->Execute(
        $pre, array($type,
        $ip,
        $steam,
        $nickname,
        $length * 60,
        $len,
        $reason,
        $userbank->GetAid(),
        $_SERVER['REMOTE_ADDR'])
    );
    $subid = $GLOBALS['db']->Insert_ID();

    if ($dname && $dfile && preg_match('/^[a-z0-9]*$/i', $dfile)) {
        //Thanks jsifuentes: http://jacobsifuentes.com/sourcebans-1-4-lfi-exploit/
        //Official Fix: https://code.google.com/p/sourcebans/source/detail?r=165

        $GLOBALS['db']->Execute(
            "INSERT INTO ".DB_PREFIX."_demos(demid,demtype,filename,origname)
         VALUES(?,'B', ?, ?)", array((int)$subid, $dfile, $dname)
        );
    }
    if ($fromsub) {
        $submail = $GLOBALS['db']->Execute(
            "SELECT name, email FROM ".DB_PREFIX."_submissions WHERE subid = '" . (int)$fromsub . "'"
        );
        // Send an email when ban is accepted
        $requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], ".php") + 4);
        $headers = 'From: submission@' . $_SERVER['HTTP_HOST'] . "\n" .
            'X-Mailer: PHP/' . phpversion();

        $message = "Hello,\n";
        $message .= "Your ban submission was accepted by our admins.\nThank you for your support!\nClick the link below to view the current ban list.\n\nhttp://" . $_SERVER['HTTP_HOST'] . $requri . "?p=banlist";

        mail($submail->fields['email'], "[SourceBans] Ban Added", $message, $headers);
        $GLOBALS['db']->Execute(
            "UPDATE `" . DB_PREFIX . "_submissions` SET archiv = '2', archivedby = '".$userbank->GetAid()."' WHERE subid = '" . (int)$fromsub . "'"
        );
    }

    $GLOBALS['db']->Execute(
        "UPDATE `".DB_PREFIX."_submissions` SET archiv = '3', archivedby = '".$userbank->GetAid()."' WHERE SteamId = ?;",
        array($steam)
    );

    $kickit = Config::getBool('config.enablekickit');
    if ($kickit) {
        $objResponse->addScript("ShowKickBox('".((int)$type==0?$steam:$ip)."', '".(int)$type."');");
    } else {
        $objResponse->addScript(
            "ShowBox('Ban Added', 'The ban has been successfully added', 'green', 'index.php?p=admin&c=bans');"
        );
    }

    $objResponse->addScript("TabToReload();");
    $type = ($type == 0) ? $steam : $ip;
    Log::add("m", "Ban Added", "Ban against ($type) has been added. Reason: $reason; Length: $length");
    return $objResponse;
}

/**
 * @param int $subid
 * @return xajaxResponse
 */
function SetupBan(int $subid)
{
    $objResponse = new xajaxResponse();

    $GLOBALS['PDO']->query("SELECT * FROM `:prefix_submissions` WHERE subid = :subid");
    $GLOBALS['PDO']->bind(':subid', $subid);
    $ban = $GLOBALS['PDO']->single();

    $GLOBALS['PDO']->query("SELECT * FROM `:prefix_demos` WHERE demid = :subid AND demtype = 'S'");
    $GLOBALS['PDO']->bind(':subid', $subid);
    $demo = $GLOBALS['PDO']->single();
    // clear any old stuff
    $objResponse->addScript("$('nickname').value = ''");
    $objResponse->addScript("$('fromsub').value = ''");
    $objResponse->addScript("$('steam').value = ''");
    $objResponse->addScript("$('ip').value = ''");
    $objResponse->addScript("$('txtReason').value = ''");
    $objResponse->addAssign("demo.msg", "innerHTML",  "");
    // add new stuff
    $objResponse->addScript("$('nickname').value = '$ban[name]'");
    $objResponse->addScript("$('steam').value = '$ban[SteamId]'");
    $objResponse->addScript("$('ip').value = '$ban[sip]'");

    $type = (trim($ban['SteamId']) == "") ? 1 : 0;
    $objResponse->addScriptCall("selectLengthTypeReason", "0", $type, addslashes($ban['reason']));

    $objResponse->addScript("$('fromsub').value = '$subid'");
    if($demo) {
        $objResponse->addAssign("demo.msg", "innerHTML",  $demo['origname']);
        $objResponse->addScript("demo('$demo[filename]', '$demo[origname]');");
    }
    $objResponse->addScript("swapTab(0);");
    return $objResponse;
}

/**
 * @param int $bid
 * @return xajaxResponse
 */
function PrepareReban($bid)
{
    $objResponse = new xajaxResponse();
    $bid = (int)$bid;

    $ban = $GLOBALS['db']->GetRow(
        "SELECT type, ip, authid, name, length, reason FROM ".DB_PREFIX."_bans WHERE bid = '".$bid."';"
    );
    $demo = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_demos WHERE demid = '".$bid."' AND demtype = \"B\";");
    // clear any old stuff
    $objResponse->addScript("$('nickname').value = ''");
    $objResponse->addScript("$('ip').value = ''");
    $objResponse->addScript("$('fromsub').value = ''");
    $objResponse->addScript("$('steam').value = ''");
    $objResponse->addScript("$('txtReason').value = ''");
    $objResponse->addAssign("demo.msg", "innerHTML",  "");
    $objResponse->addAssign("txtReason", "innerHTML",  "");

    // add new stuff
    $objResponse->addScript("$('nickname').value = '" . $ban['name'] . "'");
    $objResponse->addScript("$('steam').value = '" . $ban['authid']. "'");
    $objResponse->addScript("$('ip').value = '" . $ban['ip']. "'");
    $objResponse->addScriptCall("selectLengthTypeReason", $ban['length'], $ban['type'], addslashes($ban['reason']));

    if($demo) {
        $objResponse->addAssign("demo.msg", "innerHTML", $demo['origname']);
        $objResponse->addScript("demo('" . $demo['filename'] . "', '" . $demo['origname'] . "');");
    }
    $objResponse->addScript("swapTab(0);");
    return $objResponse;
}

/**
 * @param int $sid
 * @return xajaxResponse
 */
function SetupEditServer($sid)
{
    global $userbank;
    $objResponse = new xajaxResponse();
    $sid = (int)$sid;

    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_SERVERS))
    {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to edit a server, but doesnt have access.");
        return $objResponse;
    }

    $server = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_servers WHERE sid = $sid");

    // clear any old stuff
    $objResponse->addScript("$('address').value = ''");
    $objResponse->addScript("$('port').value = ''");
    $objResponse->addScript("$('rcon').value = ''");
    $objResponse->addScript("$('rcon2').value = ''");
    $objResponse->addScript("$('mod').value = '0'");
    $objResponse->addScript("$('serverg').value = '0'");


    // add new stuff
    $objResponse->addScript("$('address').value = '" . $server['ip']. "'");
    $objResponse->addScript("$('port').value =  '" . $server['port']. "'");
    $objResponse->addScript("$('rcon').value =  '" . $server['rcon']. "'");
    $objResponse->addScript("$('rcon2').value =  '" . $server['rcon']. "'");
    $objResponse->addScript("$('mod').value =  " . $server['modid']);
    $objResponse->addScript("$('serverg').value =  " . $server['gid']);

    $objResponse->addScript("$('insert_type').value =  " . $server['sid']);
    $objResponse->addScript("SwapPane(1);");
    return $objResponse;
}

/**
 * @param int $aid
 * @param string $pass
 * @return xajaxResponse
 */
function CheckPassword($aid, $pass)
{
    $objResponse = new xajaxResponse();
    global $userbank;
    $GLOBALS['PDO']->query("SELECT password FROM `:prefix_admins` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $hash = $GLOBALS['PDO']->single();
    if (!password_verify($pass, $hash['password'])) {
        $objResponse->addScript("$('current.msg').setStyle('display', 'block');");
        $objResponse->addScript("$('current.msg').setHTML('Incorrect password.');");
        $objResponse->addScript("set_error(1);");
    } else {
        $objResponse->addScript("$('current.msg').setStyle('display', 'none');");
        $objResponse->addScript("set_error(0);");
    }
    return $objResponse;
}

/**
 * @param int $aid
 * @param string $newPass
 * @param string $oldPass
 * @return xajaxResponse
 */
function ChangePassword($aid, $newPass, $oldPass)
{
    global $userbank;
    $objResponse = new xajaxResponse();

    if ($aid != $userbank->GetAid() && !$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add(
            "w",
            "Hacking Attempt",
            "$_SERVER[REMOTE_ADDR] tried to change a password that doesn't have permissions."
        );
        return $objResponse;
    }

    if(!$userbank->isCurrentPasswordValid($aid, $oldPass)) {
        $objResponse->addAlert("Current password doesn't match.");
        return $objResponse;
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET password = :password WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':password', password_hash($newPass, PASSWORD_BCRYPT));
    $GLOBALS['PDO']->bind(':aid', $aid);
    $GLOBALS['PDO']->execute();

    $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $admname = $GLOBALS['PDO']->single();
    $objResponse->addAlert("Password changed successfully");
    $objResponse->addRedirect("index.php?p=login", 0);
    Log::add("m", "Password Changed", "Password changed for admin ($admname[user])");
    Auth::logout();
    return $objResponse;
}

/**
 * @param string $name
 * @param string $folder
 * @param string $icon
 * @param int $steam_universe
 * @param bool $enabled
 * @return xajaxResponse
 */
function AddMod($name, $folder, $icon, $steam_universe, $enabled)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_MODS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to add a mod, but doesnt have access.");
        return $objResponse;
    }
    $name = htmlspecialchars(strip_tags($name));//don't want to addslashes because execute will automatically do it
    $icon = htmlspecialchars(strip_tags($icon));
    $folder = htmlspecialchars(strip_tags($folder));
    $steam_universe = (int)$steam_universe;
    $enabled = (int)(bool)$enabled;

    // Already there?
    $check = $GLOBALS['db']->GetRow(
        "SELECT * FROM `" . DB_PREFIX . "_mods` WHERE modfolder = ? OR name = ?;", array($folder, $name)
    );
    if(!empty($check)) {
        $objResponse->addScript(
            "ShowBox('Error adding mod', 'A mod using that folder or name already exists.', 'red');"
        );
        return $objResponse;
    }

    $pre = $GLOBALS['db']->Prepare(
        "INSERT INTO ".DB_PREFIX."_mods(name,icon,modfolder,steam_universe,enabled) VALUES (?,?,?,?,?)"
    );
    $GLOBALS['db']->Execute($pre, array($name, $icon, $folder, $steam_universe, $enabled));

    $objResponse->addScript(
        "ShowBox('Mod Added', 'The game mod has been successfully added', 'green', 'index.php?p=admin&c=mods');"
    );
    $objResponse->addScript("TabToReload();");
    Log::add("m", "Mod Added", "Mod ($name) has been added.");
    return $objResponse;
}

/**
 * @param int $aid
 * @param int $web_flags
 * @param string $srv_flags
 * @return void|xajaxResponse
 */
function EditAdminPerms($aid, $web_flags, $srv_flags)
{
    if(empty($aid)) {
        return;
    }
    $aid = (int)$aid;
    $web_flags = (int)$web_flags;

    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to edit admin permissions, but doesnt have access.");
        return $objResponse;
    }

    if(!$userbank->HasAccess(ADMIN_OWNER) && (int)$web_flags & ADMIN_OWNER ) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to gain OWNER admin permissions, but doesnt have access.");
        return $objResponse;
    }

    // Users require a password and email to have web permissions
    $password = $GLOBALS['userbank']->GetProperty('password', $aid);
    $email = $GLOBALS['userbank']->GetProperty('email', $aid);
    if($web_flags > 0 && (empty($password) || empty($email))) {
        $objResponse->addScript(
            "ShowBox('Error', 'Admins have to have a password and email set in order to get web permissions.<br /><a href=\"index.php?p=admin&c=admins&o=editdetails&id=" . $aid . "\" title=\"Edit Admin Details\">Set the details</a> first and try again.', 'red', '');"
        );
        return $objResponse;
    }

    // Update web stuff
    $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `extraflags` = $web_flags WHERE `aid` = $aid");


    if(strstr($srv_flags, "#")) {
        $immunity = "0";
        $immunity = substr($srv_flags, strpos($srv_flags, "#") + 1);
        $srv_flags = substr($srv_flags, 0, strlen($srv_flags) - strlen($immunity) - 1);
    }
    $immunity = ($immunity>0) ? $immunity : 0;
    // Update server stuff
    $GLOBALS['db']->Execute(
        "UPDATE `".DB_PREFIX."_admins` SET `srv_flags` = ?, `immunity` = ? WHERE `aid` = $aid",
        array($srv_flags, $immunity)
    );

    if(Config::getBool('config.enableadminrehashing')) {
        // rehash the admins on the servers
        $serveraccessq = $GLOBALS['db']->GetAll(
            "SELECT s.sid FROM `" . DB_PREFIX . "_servers` s
    LEFT JOIN `" . DB_PREFIX . "_admins_servers_groups` asg ON asg.admin_id = '" . (int)$aid . "'
    LEFT JOIN `" . DB_PREFIX . "_servers_groups` sg ON sg.group_id = asg.srv_group_id
    WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
    OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
    AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1"
        );
        $allservers = array();
        foreach ($serveraccessq as $access) {
            if (!in_array($access['sid'], $allservers)) {
                $allservers[] = $access['sid'];
            }
        }
        $objResponse->addScript(
            "ShowRehashBox('" . implode(",", $allservers) . "', 'Permissions updated', 'The user`s permissions have been updated successfully', 'green', 'index.php?p=admin&c=admins');TabToReload();"
        );
    } else {
        $objResponse->addScript(
            "ShowBox('Permissions updated', 'The user`s permissions have been updated successfully', 'green', 'index.php?p=admin&c=admins');TabToReload();"
        );
    }
    $admname = $GLOBALS['db']->GetRow("SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = ?", array((int)$aid));
    Log::add("m", "Permissions Changed", "Permissions have been changed for ($admname[user])");
    return $objResponse;
}

function EditGroup($gid, $web_flags, $srv_flags, $type, $name, $overrides, $newOverride)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_GROUPS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to edit group details, but doesnt have access.");
        return $objResponse;
    }

    if(empty($name)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w",
            "Hacking Attempt",
            "$username tried to set group's name to nothing. This isn't possible with the normal form.");
        return $objResponse;
    }

    $gid = (int)$gid;
    $web_flags = (int)$web_flags;
    if ($type == "web" || $type == "server") {
        // Update web stuff
        $GLOBALS['db']->Execute(
            "UPDATE `" . DB_PREFIX . "_groups` SET `flags` = ?, `name` = ? WHERE `gid` = $gid",
            array($web_flags, $name)
        );
    }

    if($type == "srv") {
        $gname = $GLOBALS['db']->GetRow("SELECT name FROM " . DB_PREFIX . "_srvgroups WHERE id = $gid");

        if (strstr($srv_flags, "#")) {
            $immunity = 0;
            $immunity = substr($srv_flags, strpos($srv_flags, "#") + 1);
            $srv_flags = substr($srv_flags, 0, strlen($srv_flags) - strlen($immunity) - 1);
        }
        $immunity = ($immunity > 0) ? $immunity : 0;

        // Update server stuff
        $GLOBALS['db']->Execute(
            "UPDATE `" . DB_PREFIX . "_srvgroups` SET `flags` = ?, `name` = ?, `immunity` = ? WHERE `id` = $gid",
            array($srv_flags, $name, $immunity)
        );

        $oldname = $GLOBALS['db']->GetAll(
            "SELECT aid FROM " . DB_PREFIX . "_admins WHERE srv_group = ?",
            array($gname['name'])
        );
        foreach ($oldname as $o) {
            $GLOBALS['db']->Execute(
                "UPDATE `" . DB_PREFIX . "_admins` SET `srv_group` = ? WHERE `aid` = '" . (int)$o['aid'] . "'",
                array($name)
            );
        }

        $overrides = json_decode(html_entity_decode($overrides, ENT_QUOTES), true);
        $newOverride = json_decode(html_entity_decode($newOverride, ENT_QUOTES), true);

        // Update group overrides
        if (!empty($overrides)) {
            foreach ($overrides as $override) {
                // Skip invalid stuff?!
                if ($override['type'] != "command" && $override['type'] != "group") {
                    continue;
                }

                $id = (int)$override['id'];
                // Wants to delete this override?
                if (empty($override['name'])) {
                    $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE id = ?;",
                        array($id));
                    continue;
                }

                // Check for duplicates
                $chk = $GLOBALS['db']->GetAll(
                    "SELECT * FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE name = ? AND type = ? AND group_id = ? AND id != ?",
                    array($override['name'], $override['type'], $gid, $id));
                if (!empty($chk)) {
                    $objResponse->addScript(
                        "ShowBox('Error', 'There already is an override with name \\\"" . htmlspecialchars(addslashes($override['name'])) . "\\\" from the selected type..', 'red', '', true);"
                    );
                    return $objResponse;
                }

                // Edit the override
                $GLOBALS['db']->Execute(
                    "UPDATE `" . DB_PREFIX . "_srvgroups_overrides` SET name = ?, type = ?, access = ? WHERE id = ?;",
                    array($override['name'], $override['type'], $override['access'], $id)
                );
            }
        }

        // Add a new override
        if (!empty($newOverride)) {
            if (
                ($newOverride['type'] == "command" || $newOverride['type'] == "group")
                && !empty($newOverride['name'])
            ) {
                // Check for duplicates
                $chk = $GLOBALS['db']->GetAll(
                    "SELECT * FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE name = ? AND type = ? AND group_id = ?",
                    array($newOverride['name'], $newOverride['type'], $gid)
                );
                if (!empty($chk)) {
                    $objResponse->addScript(
                        "ShowBox('Error', 'There already is an override with name \\\"" . htmlspecialchars(addslashes($newOverride['name'])) . "\\\" from the selected type..', 'red', '', true);"
                    );
                    return $objResponse;
                }

                // Insert the new override
                $GLOBALS['db']->Execute(
                    "INSERT INTO `" . DB_PREFIX . "_srvgroups_overrides` (group_id, type, name, access) VALUES (?, ?, ?, ?);",
                    array($gid, $newOverride['type'], $newOverride['name'], $newOverride['access'])
                );
            }
        }

        if (Config::getBool('config.enableadminrehashing')) {
            // rehash the settings out of the database on all servers
            $serveraccessq = $GLOBALS['db']->GetAll("SELECT sid FROM " . DB_PREFIX . "_servers WHERE enabled = 1;");
            $allservers = array();
            foreach ($serveraccessq as $access) {
                if (!in_array($access['sid'], $allservers)) {
                    $allservers[] = $access['sid'];
                }
            }
            $objResponse->addScript(
                "ShowRehashBox('" . implode(",", $allservers) . "', 'Group updated', 'The group has been updated successfully', 'green', 'index.php?p=admin&c=groups');TabToReload();"
            );
        } else {
            $objResponse->addScript(
                "ShowBox('Group updated', 'The group has been updated successfully', 'green', 'index.php?p=admin&c=groups');TabToReload();"
            );
        }
        Log::add("m", "Group Updated", "Group ($name) has been updated.");
        return $objResponse;
    }

    $objResponse->addScript(
        "ShowBox('Group updated', 'The group has been updated successfully', 'green', 'index.php?p=admin&c=groups');TabToReload();"
    );
    Log::add("m", "Group Updated", "Group ($name) has been updated.");
    return $objResponse;
}

/**
 * @param int $sid
 * @param string $command
 * @param bool $output
 * @return xajaxResponse
 */
function SendRcon(int $sid, $command, $output)
{
    global $userbank, $username;
    $objResponse = new xajaxResponse();
    if(!$userbank->HasAccess(SM_RCON . SM_ROOT)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to send an rcon command, but doesnt have access.");
        return $objResponse;
    }

    if (empty($command)) {
        $objResponse->addScript("$('cmd').value=''; $('cmd').disabled='';$('rcon_btn').disabled=''");
        return $objResponse;
    } else if ($command == "clr") {
        $objResponse->addAssign("rcon_con", "innerHTML",  "");
        $objResponse->addScript("scroll.toBottom(); $('cmd').value=''; $('cmd').disabled='';$('rcon_btn').disabled=''");
        return $objResponse;
    } else if (stripos($command, "rcon_password") !== false) {
        $objResponse->addAppend("rcon_con",
            "innerHTML",
            "> Error: You have to use this console. Don't try to cheat the rcon password!<br />");
        $objResponse->addScript("scroll.toBottom(); $('cmd').value=''; $('cmd').disabled='';$('rcon_btn').disabled=''");
        return $objResponse;
    }

    $command = html_entity_decode($command, ENT_QUOTES);

    $ret = rcon($command, $sid);

    if (!$ret) {
        $objResponse->addAppend("rcon_con", "innerHTML",  "> Error: Can't connect to server!<br />");
        $objResponse->addScript("scroll.toBottom(); $('cmd').value=''; $('cmd').disabled='';$('rcon_btn').disabled=''");
        return $objResponse;
    }

    $ret = str_replace("\n", "<br />", $ret);

    if ($output) {
        if (empty($ret)) {
            $objResponse->addAppend("rcon_con", "innerHTML",  "-> $command<br />");
            $objResponse->addAppend("rcon_con", "innerHTML",  "Command Executed.<br />");
        } else {
            $objResponse->addAppend("rcon_con", "innerHTML",  "-> $command<br />");
            $objResponse->addAppend("rcon_con", "innerHTML",  "$ret<br />");
        }
    }

    $objResponse->addScript("scroll.toBottom(); $('cmd').value=''; $('cmd').disabled=''; $('rcon_btn').disabled=''");
    Log::add("m", "RCON Sent", "RCON Command was sent to server ($rcon[ip]:$rcon[port])");
    return $objResponse;
}


function SendMail($subject, $message, $type, $id)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;

    $id = (int)$id;

    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS|ADMIN_BAN_SUBMISSIONS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to send an email, but doesnt have access.");
        return $objResponse;
    }

    // Don't mind wrong types
    if($type != 's' && $type != 'p') {
        return $objResponse;
    }

    // Submission
    $email = "";
    if($type == 's') {
        $email = $GLOBALS['db']->GetOne(
            'SELECT email FROM `' . DB_PREFIX . '_submissions` WHERE subid = ?',
            array($id));
    }
    // Protest
    else if($type == 'p') {
        $email = $GLOBALS['db']->GetOne('SELECT email FROM `' . DB_PREFIX . '_protests` WHERE pid = ?', array($id));
    }

    if(empty($email)) {
        $objResponse->addScript(
            "ShowBox('Error', 'There is no email to send to supplied.', 'red', 'index.php?p=admin&c=bans');"
        );
        return $objResponse;
    }

    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\n" . 'X-Mailer: PHP/' . phpversion();
    $m = @mail($email, '[SourceBans] ' . $subject, $message, $headers);


    if($m) {
        $objResponse->addScript(
            "ShowBox('Email Sent', 'The email has been sent to the user.', 'green', 'index.php?p=admin&c=bans');"
        );
        Log::add("m",
            "Email Sent",
            "$username send an email to $email. Subject: '[SourceBans++] $subject'; Message: $message"
        );
    }
    else {
        $objResponse->addScript("ShowBox('Error', 'Failed to send the email to the user.', 'red', '');");
    }

    return $objResponse;
}

function CheckVersion()
{
    $objResponse = new xajaxResponse();
    $version = json_decode(file_get_contents("https://sbpp.github.io/version.json"), true);

    if(version_compare($version['version'], SB_VERSION) > 0) {
        $msg = "<span style='color:#aa0000;'><strong>A New Release is Available.</strong></span>";
    } else {
        $msg = "<span style='color:#00aa00;'><strong>You have the Latest Release.</strong></span>";
    }

    if(strlen($version['version']) > 8 || $version['version'] == "") {
        $version['version'] = "<span style='color:#aa0000;'>Error</span>";
        $msg = "<span style='color:#aa0000;'><strong>Error Retrieving Latest Release.</strong></span>";
    }

    $objResponse->addAssign("relver", "innerHTML",  $version['version']);

    if (SB_DEV) {
        if (intval($version['git']) > SB_GITREV) {
            $svnmsg = "<span style='color:#aa0000;'><strong>A New Dev Version is Available.</strong></span>";
        } else {
            $svnmsg = "<span style='color:#00aa00;'><strong>You have the Latest Dev Version.</strong></span>";
        }

        if (strlen($version['git']) > 8 || $version['git'] == "") {
            $version['git'] = "<span style='color:#aa0000;'>Error</span>";
            $svnmsg = "<span style='color:#aa0000;'><strong>Error retrieving latest Dev Version.</strong></span>";
        }
        $msg .= "<br>".$svnmsg;
        $objResponse->addAssign("svnrev", "innerHTML",  $version['git']);
    }

    $objResponse->addAssign("versionmsg", "innerHTML", $msg);
    return $objResponse;
}

function SelTheme($theme)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_WEB_SETTINGS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to execute SelTheme() function, but doesnt have access.");
        return $objResponse;
    }

    $theme = rawurldecode($theme);
    $theme = str_replace(array('../', '..\\', chr(0)), '', $theme);
    $theme = basename($theme);

    if($theme[0] == '.' || !in_array($theme, scandir(SB_THEMES)) || !is_dir(SB_THEMES . $theme) || !file_exists(SB_THEMES . $theme . "/theme.conf.php")) {
        $objResponse->addAlert('Invalid theme selected.');
        return $objResponse;
    }

    include SB_THEMES . $theme . "/theme.conf.php";

    if(!defined('theme_screenshot')) {
        $objResponse->addAlert('Bad theme selected.');
        return $objResponse;
    }

    $objResponse->addAssign("current-theme-screenshot",
        "innerHTML",
        '<img width="250px" height="170px" src="themes/' . $theme . '/' . strip_tags(theme_screenshot) . '">'
    );
    $objResponse->addAssign("theme.name", "innerHTML",  theme_name);
    $objResponse->addAssign("theme.auth", "innerHTML",  theme_author);
    $objResponse->addAssign("theme.vers", "innerHTML",  theme_version);
    $objResponse->addAssign("theme.link", "innerHTML",  '<a href="'.theme_link.'" target="_new">'.theme_link.'</a>');
    $objResponse->addAssign("theme.apply",
        "innerHTML",
        "<input type='button' onclick=\"javascript:xajax_ApplyTheme('" . $theme . "')\" name='btnapply' class='btn ok' onmouseover='ButtonOver(\"btnapply\")' onmouseout='ButtonOver(\"btnapply\")' id='btnapply' value='Apply Theme' />"
    );

    return $objResponse;
}

/**
 * @param $theme
 * @return xajaxResponse
 */
function ApplyTheme($theme)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_WEB_SETTINGS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to change the theme to $theme, but doesnt have access.");
        return $objResponse;
    }

    $theme = rawurldecode($theme);
    $theme = str_replace(array('../', '..\\', chr(0)), '', $theme);
    $theme = basename($theme);

    if($theme[0] == '.' || !in_array($theme, scandir(SB_THEMES)) || !is_dir(SB_THEMES . $theme) || !file_exists(SB_THEMES . $theme . "/theme.conf.php")) {
        $objResponse->addAlert('Invalid theme selected.');
        return $objResponse;
    }

    include SB_THEMES . $theme . "/theme.conf.php";

    if(!defined('theme_screenshot')) {
        $objResponse->addAlert('Bad theme selected.');
        return $objResponse;
    }

    $GLOBALS['db']->Execute(
        "UPDATE `" . DB_PREFIX . "_settings` SET `value` = ? WHERE `setting` = 'config.theme'",
        array($theme)
    );
    $objResponse->addScript('window.location.reload( false );');
    return $objResponse;
}

/**
 * @param int $bid
 * @param string $ctype
 * @param string $ctext
 * @param int $page
 * @return xajaxResponse
 */
function AddComment($bid, $ctype, $ctext, $page)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->is_admin()) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to add a comment, but doesnt have access.");
        return $objResponse;
    }

    $bid = (int)$bid;
    $page = (int)$page;

    $pagelink = "";
    if($page != -1) {
        $pagelink = "&page=" . $page;
    }

    if($ctype=="B") {
        $redir = "?p=banlist" . $pagelink;
    } elseif($ctype=="C") {
        $redir = "?p=commslist" . $pagelink;
    } elseif($ctype=="S") {
        $redir = "?p=admin&c=bans#^2";
    } elseif($ctype=="P") {
        $redir = "?p=admin&c=bans#^1";
    } else {
        $objResponse->addScript("ShowBox('Error', 'Bad comment type.', 'red');");
        return $objResponse;
    }

    $ctext = trim($ctext);

    $pre = $GLOBALS['db']->Prepare(
        "INSERT INTO " . DB_PREFIX . "_comments(bid,type,aid,commenttxt,added) VALUES (?,?,?,?,UNIX_TIMESTAMP())"
    );
    $GLOBALS['db']->Execute(
        $pre, array($bid,
        $ctype,
        $userbank->GetAid(),
        $ctext)
    );

    $objResponse->addScript(
        "ShowBox('Comment Added', 'The comment has been successfully published', 'green', 'index.php$redir');"
    );
    $objResponse->addScript("TabToReload();");
    Log::add("m", "Comment Added", "$username added a comment for ban #$bid");
    return $objResponse;
}

function EditComment($cid, $ctype, $ctext, $page)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->is_admin()) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to edit a comment, but doesnt have access.");
        return $objResponse;
    }

    $cid = (int)$cid;
    $page = (int)$page;

    $pagelink = "";
    if($page != -1) {
        $pagelink = "&page=" . $page;
    }

    if($ctype=="B") {
        $redir = "?p=banlist" . $pagelink;
    } elseif($ctype=="C") {
        $redir = "?p=commslist" . $pagelink;
    } elseif($ctype=="S") {
        $redir = "?p=admin&c=bans#^2";
    } elseif($ctype=="P") {
        $redir = "?p=admin&c=bans#^1";
    } else {
        $objResponse->addScript("ShowBox('Error', 'Bad comment type.', 'red');");
        return $objResponse;
    }

    $ctext = trim($ctext);

    $pre = $GLOBALS['db']->Prepare(
        "UPDATE " . DB_PREFIX . "_comments SET `commenttxt` = ?, `editaid` = ?, `edittime`= UNIX_TIMESTAMP() WHERE cid = ?"
    );
    $GLOBALS['db']->Execute(
        $pre, array($ctext,
        $userbank->GetAid(),
        $cid)
    );

    $objResponse->addScript(
        "ShowBox('Comment Edited', 'The comment #".$cid." has been successfully edited', 'green', 'index.php$redir');"
    );
    $objResponse->addScript("TabToReload();");
    Log::add("m", "Comment Edited", "$username edited comment #$cid");
    return $objResponse;
}

function RemoveComment($cid, $ctype, $page)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if (!$userbank->HasAccess(ADMIN_OWNER)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to remove a comment, but doesnt have access.");
        return $objResponse;
    }

    $cid = (int)$cid;
    $page = (int)$page;

    $pagelink = "";
    if($page != -1) {
        $pagelink = "&page=" . $page;
    }

    $res = $GLOBALS['db']->Execute(
        "DELETE FROM `" . DB_PREFIX . "_comments` WHERE `cid` = ?",
        array($cid)
    );
    if($ctype=="B") {
        $redir = "?p=banlist" . $pagelink;
    } elseif($ctype=="C") {
        $redir = "?p=commslist" . $pagelink;
    } else {
        $redir = "?p=admin&c=bans";
    }
    if($res) {
        $objResponse->addScript(
            "ShowBox('Comment Deleted', 'The selected comment has been deleted from the database', 'green', 'index.php$redir', true);"
        );
        Log::add("m", "Comment Deleted", "$username deleted comment #$cid");
    }
    else {
        $objResponse->addScript(
            "ShowBox('Error', 'There was a problem deleting the comment from the database. Check the logs for more info', 'red', 'index.php$redir', true);"
        );
    }
    return $objResponse;
}

function ClearCache()
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_WEB_SETTINGS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to clear the cache, but doesnt have access.");
        return $objResponse;
    }

    $cachedir = dir(SB_CACHE);
    while (($entry = $cachedir->read()) !== false) {
        if (is_file($cachedir->path . $entry)) {
            unlink($cachedir->path . $entry);
        }
    }
    $cachedir->close();

    $objResponse->addScript(
        "$('clearcache.msg').innerHTML = '<span style=\"color: green; font-size: xx-small; \">Cache cleared.</span>';"
    );

    return $objResponse;
}

function RefreshServer($sid)
{
    $objResponse = new xajaxResponse();
    $sid = (int)$sid;
    session_start();
    $data = $GLOBALS['db']->GetRow("SELECT ip, port FROM `".DB_PREFIX."_servers` WHERE sid = ?;", array($sid));

    $objResponse->addScript("xajax_ServerHostPlayers('".$sid."');");
    return $objResponse;
}

function RehashAdmins($server)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;

    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS|ADMIN_EDIT_GROUPS|ADMIN_ADD_ADMINS)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to rehash admins, but doesnt have access.");
        return $objResponse;
    }

    $servers = explode(",", $server);
    if (count($servers) < 1) {
        $objResponse->addAppend("rehashDiv", "innerHTML", "No servers to check.");
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        return $objResponse;
    }

    for ($i = 0; $i < count($servers); $i++) {
        $ret = rcon("sm_rehash", $servers[$i]);

        if (isset($ret) && $ret === '') {
            $objResponse->addAppend(
                "rehashDiv",
                "innerHTML",
                "Server #$servers[$i] (" . ($i + 1) . "/" . count($servers) . "): <font color='green'>successful</font>.<br />"
            );
        } else {
            $objResponse->addAppend(
                "rehashDiv",
                "innerHTML",
                "Server #$servers[$i] (" . ($i + 1) . "/" . count($servers) . "): <font color='red'>Can't connect to server.</font>.<br />"
            );
        }
    }

    return $objResponse;
}

/**
 * @param string $groupuri
 * @param string $isgrpurl
 * @param string $queue
 * @param string $reason
 * @param string $last
 * @return xajaxResponse
 */
function GroupBan($groupuri, $isgrpurl="no", $queue="no", $reason="", $last="")
{
    $objResponse = new xajaxResponse();
    if(!Config::getBool('config.enablegroupbanning')) {
        return $objResponse;
    }
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to initiate a groupban ($groupuri), but doesnt have access.");
        return $objResponse;
    }
    if($isgrpurl=="yes") {
        $grpname = $groupuri;
    } else {
        $url = parse_url($groupuri, PHP_URL_PATH);
        $url = explode("/", $url);
        $grpname = $url[2];
    }
    if(empty($grpname)) {
        $objResponse->addAssign("groupurl.msg", "innerHTML", "Error parsing the group url.");
        $objResponse->addScript("$('groupurl.msg').setStyle('display', 'block');");
        return $objResponse;
    }
    else {
        $objResponse->addScript("$('groupurl.msg').setStyle('display', 'none');");
    }

    if ($queue=="yes") {
        $objResponse->addScript(
            "ShowBox('Please Wait...', 'Banning all members of the selected groups... <br>Please Wait...<br>Notice: This can last 15mins or longer, depending on the amount of members of the groups!', 'info', '', true);"
        );
    } else {
        $objResponse->addScript(
            "ShowBox('Please Wait...', 'Banning all members of " . $grpname . "...<br>Please Wait...<br>Notice: This can last 15mins or longer, depending on the amount of members of the group!', 'info', '', true);"
        );
    }
    $objResponse->addScript("$('dialog-control').setStyle('display', 'none');");
    $objResponse->addScriptCall("xajax_BanMemberOfGroup",
        $grpname,
        $queue,
        htmlspecialchars(addslashes($reason)),
        $last);
    return $objResponse;

}

/**
 * @param string $grpurl
 * @param $queue
 * @param string $reason
 * @param $last
 * @return xajaxResponse
 */
function BanMemberOfGroup($grpurl, $queue, $reason, $last)
{
    set_time_limit(0);
    $objResponse = new xajaxResponse();
    if (!Config::getBool('config.enablegroupbanning') || !defined('STEAMAPIKEY') || STEAMAPIKEY == '') {
        return $objResponse;
    }
    global $userbank, $username;
    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to ban group ($grpurl), but doesnt have access.");
        return $objResponse;
    }

    $GLOBALS['PDO']->query(
        "SELECT CAST(MID(authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(authid, 11, 10) * 2 AS UNSIGNED) AS community_id FROM `:prefix_bans` WHERE RemoveType IS NULL;"
    );
    $bans = $GLOBALS['PDO']->resultset();
    $already = array();
    foreach($bans as $ban) {
        $already[] = $ban["community_id"];
    }

    $steamids = [];

    function getGroupMembers($url, &$members)
    {
        $xml = simplexml_load_file($url);

        $members = array_merge($members, (array) $xml->members->steamID64);

        if ($xml->nextPageLink) {
            getGroupMembers($xml->nextPageLink, $members);
        }
    }

    getGroupMembers('https://steamcommunity.com/groups/' . $grpurl . '/memberslistxml?xml=1', $steamids);

    $steamids = array_chunk($steamids, 100);
    $data = array();

    foreach ($steamids as $package) {
        $package = rawurlencode(json_encode($package));
        $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2?key=".STEAMAPIKEY."&steamids=".$package;
        $raw = json_decode(file_get_contents($url), true);
        $data = array_merge($data, $raw['response']['players']);
    }

    $amount = array(
        "total" => count($data),
        "banned" => 0,
        "before" => 0,
        "failed" => 0
    );

    foreach ($data as $player) {
        if (in_array($player['steamid'], $already)) {
            $amount['before']++;
            continue;
        }

        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp)
            VALUES (UNIX_TIMESTAMP(), :type, :ip, :authid, :name, UNIX_TIMESTAMP(), :length, :reason, :aid, :adminIp)"
        );

        $GLOBALS['PDO']->bind(':type', 0);
        $GLOBALS['PDO']->bind(':ip', '');
        $GLOBALS['PDO']->bind(':authid', SteamID::toSteam2($player['steamid']));
        $GLOBALS['PDO']->bind(':name', $player['personaname']);
        $GLOBALS['PDO']->bind(':length', 0);
        $GLOBALS['PDO']->bind(':reason', "Steam Community Group Ban (".$grpurl."): ".$reason);
        $GLOBALS['PDO']->bind(':aid', $userbank->GetAid());
        $GLOBALS['PDO']->bind(':adminIp', $_SERVER['REMOTE_ADDR']);
        if ($GLOBALS['PDO']->execute()) {
            $amount['banned']++;
            continue;
        }
        $amount['failed']++;
    }

    if ($queue=="yes") {
        $objResponse->addAppend("steamGroupStatus",
            "innerHTML",
            "<p>Banned " . ($amount['total'] - $amount['before'] - $amount['failed']) . "/" . $amount['total'] . " players of group '" . $grpurl . "'. | " . $amount['before'] . " were banned already. | " . $amount['failed'] . " failed.</p>");
        if ($grpurl==$last) {
            $objResponse->addScript(
                "ShowBox('Groups banned successfully', 'The selected Groups were banned successfully. For detailed info check below.', 'green', '', true);"
            );
            $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        }
    } else {
        $objResponse->addScript(
            "ShowBox('Group banned successfully', 'Banned ".($amount['total'] - $amount['before'] - $amount['failed'])."/".$amount['total']." players of group \'".$grpurl."\'.<br>".$amount['before']." were banned already.<br>".$amount['failed']." failed.', 'green', '', true);"
        );
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
    }

    Log::add("m",
        "Group Banned",
        "Banned " . ($amount['total'] - $amount['before'] - $amount['failed']) . "/$amount[total] players of group ($grpurl). $amount[before] were banned already. $amount[failed] failed."
    );
    return $objResponse;
}

/**
 * @param $friendid
 * @return xajaxResponse
 */
function GetGroups($friendid)
{
    set_time_limit(0);
    $objResponse = new xajaxResponse();
    if(!Config::getBool('config.enablegroupbanning') || !is_numeric($friendid)) {
        return $objResponse;
    }
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to list groups of '$friendid', but doesnt have access.");
        return $objResponse;
    }
    // check if we're getting redirected, if so there is $result["Location"] (the player uses custom id)
    // else just use the friendid. !We can't get the xml with the friendid url if the player has a custom one!
    $result = get_headers("http://steamcommunity.com/profiles/".$friendid."/", 1);
    $raw = file_get_contents(
        (!empty($result["Location"])?$result["Location"]:"http://steamcommunity.com/profiles/".$friendid."/")."?xml=1"
    );
    preg_match("/<privacyState>([^\]]*)<\/privacyState>/", $raw, $status);
    if(($status && $status[1] != "public") || strstr($raw, "<groups>")) {
        $raw = str_replace("&", "", $raw);
        $raw = utf8_encode($raw);
        $xml = simplexml_load_string($raw); // parse xml
        $result = $xml->xpath('/profile/groups/group'); // go to the group nodes
        $i = 0;
        foreach ($result as $k => $node) {
            // Steam only provides the details of the first 3 groups of a players profile.
            // We need to fetch the individual groups seperately to get the correct information.
            if (empty($node->groupName)) {
                $memberlistxml = file_get_contents(
                    "http://steamcommunity.com/gid/" . $node->groupID64 . "/memberslistxml/?xml=1"
                );
                $memberlistxml = str_replace("&", "", $memberlistxml);
                $memberlistxml = utf8_encode($memberlistxml);
                $groupxml = simplexml_load_string($memberlistxml); // parse xml
                $node = $groupxml->xpath('/memberList/groupDetails');
                $node = $node[0];
            }

            // Checkbox & Groupname table cols
            $objResponse->addScript(
                'var e = document.getElementById("steamGroupsTable");
                var tr = e.insertRow("-1");
                var td = tr.insertCell("-1");
                td.className = "listtable_1";
                td.style.padding = "0px";
                td.style.width = "3px";
                var input = document.createElement("input");
                input.setAttribute("type","checkbox");
                input.setAttribute("id","chkb_' . $i . '");
                input.setAttribute("value","' . $node->groupURL . '");
                td.appendChild(input);
                var td = tr.insertCell("-1");
                td.className = "listtable_1";
                var a = document.createElement("a");
                a.href = "http://steamcommunity.com/groups/'.$node->groupURL.'";
                a.setAttribute("target","_blank");
                var txt = document.createTextNode("'.utf8_decode($node->groupName).'");
                a.appendChild(txt);
                td.appendChild(a);
                var txt = document.createTextNode(" (");
                td.appendChild(txt);
                var span = document.createElement("span");
                span.setAttribute("id","membcnt_' . $i . '");
                span.setAttribute("value","' . $node->memberCount . '");
                var txt3 = document.createTextNode("' . $node->memberCount . '");
                span.appendChild(txt3);
                td.appendChild(span);
                var txt2 = document.createTextNode(" Members)");
                td.appendChild(txt2);
    '
            );
            $i++;
        }
    } else {
        $objResponse->addScript(
            "ShowBox('Error', 'There was an error retrieving the group data. <br>Maybe the player isn\'t member of any group or his profile is private?<br><a href=\"http://steamcommunity.com/profiles/" . $friendid . "/\" title=\"Community profile\" target=\"_blank\">Community profile</a>', 'red', 'index.php?p=banlist', true);"
        );
        $objResponse->addScript("$('steamGroupsText').innerHTML = '<i>No groups...</i>';");
        return $objResponse;
    }
    $objResponse->addScript("$('steamGroupsText').setStyle('display', 'none');");
    $objResponse->addScript("$('steamGroups').setStyle('display', 'block');");
    return $objResponse;
}

function BanFriends($friendid, $name)
{
    set_time_limit(0);
    global $userbank, $username;
    $objResponse = new xajaxResponse();
    $name = filter_var($name, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    if (!Config::getBool('config.enablefriendsbanning') || !is_numeric($friendid)) {
        return $objResponse;
    }

    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to ban friends of '$friendid', but doesnt have access.");
        return $objResponse;
    }

    $steam = SteamID::toSteam64($friendid);
    $friends = @json_decode(file_get_contents(
        "http://api.steampowered.com/ISteamUser/GetFriendList/v0001/?key=" . STEAMAPIKEY . "&steamid=" . $steam . "&relationship=friend"),
        true
    );
    $friends = $friends['friendslist']['friends'];

    if (is_null($friends)) {
        $objResponse->addScript(
            "ShowBox('Error private profile', 'There was an error retrieving the friend list.', 'red', 'index.php?p=banlist', true);"
        );
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        return $objResponse;
    }

    $total = count($friends);
    $before = 0;
    $error = 0;

    foreach ($friends as $friend) {
        $steam = SteamID::toSteam2($friend['steamid']);
        $fname = GetCommunityName($friend['steamid']);

        $GLOBALS['PDO']->query("SELECT 1 FROM `:prefix_bans` WHERE authid = :authid");
        $GLOBALS['PDO']->bind(':authid', $steam);
        $banned = $GLOBALS['PDO']->single();

        if ((bool)$banned[1]) {
            $before++;
            continue;
        }

        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_bans` (`created`, `type`, `ip`, `authid`, `name`, `ends`, `length`, `reason`, `aid`, `adminIp`)
            VALUES(UNIX_TIMESTAMP(), 0, '', :authid, :name, (UNIX_TIMESTAMP() + 0), 0, :reason, :aid, :admip)"
        );
        $GLOBALS['PDO']->bindMultiple(
            [
            ':authid' => $steam,
            ':name' => filter_var($fname, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
            ':reason' => "Steam Community Friend Ban (".$name.")",
            ':aid' => $userbank->GetAid(),
            ':admip' => $_SERVER['REMOTE_ADDR']
            ]
        );
        if (!$GLOBALS['PDO']->execute()) {
            $error++;
        }
    }

    if ($total === 0) {
        $objResponse->addScript(
            "ShowBox('Error retrieving friends', 'There was an error retrieving the friend list. Check if the profile isn\'t private or if he hasn\'t got any friends!', 'red', 'index.php?p=banlist', true);"
        );
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        return $objResponse;
    }

    $objResponse->addScript(
        "ShowBox('Friends banned successfully', 'Banned ".($total-$before-$error)."/".$total." friends of \'".$name."\'.<br>".$before." were banned already.<br>".$error." failed.', 'green', 'index.php?p=banlist', true);"
    );
    $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
    Log::add("m",
        "Friends Banned",
        "Banned " . ($total - $before - $error) . "/$total friends of $name. $before were banned already. $error failed.");
    return $objResponse;
}

function ViewCommunityProfile(int $sid, $name)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;

    if(!$userbank->is_admin()) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to view profile of '$name', but doesnt have access.");
        return $objResponse;
    }

    $ret = rcon('status', $sid);

    if (!$ret) {
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        $objResponse->addScript("ShowBox('Error', 'Can\' connect to server!', 'red', '', true);");
        return $objResponse;
    }

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($player['name'], $name)) {
            $objResponse->addScript(
                "$('dialog-control').setStyle('display', 'block');$('dialog-content-text').innerHTML = 'Generating Community Profile link for " . addslashes(htmlspecialchars($name)) . ", please wait...<br /><font color=\"green\">Done.</font><br /><br /><b>Watch the profile <a href=\"https://www.steamcommunity.com/profiles/" . SteamID::toSteam64($player['steamid']) . "/\" title=\"" . addslashes(htmlspecialchars($name)) . "\'s Profile\" target=\"_blank\">here</a>.</b>';"
            );
            $objResponse->addScript(
                "window.open('https://www.steamcommunity.com/profiles/" . SteamID::toSteam64($player['steamid']) . "/');"
            );
            return $objResponse;
        }
    }

    $objResponse->addScript(
        "ShowBox('Error', 'Can\'t get playerinfo for ".addslashes(htmlspecialchars($name)).". Player not on the server anymore!', 'red', '', true);"
    );
    return $objResponse;
}

/**
 * @param int $sid
 * @param string $name
 * @param string $message
 * @return xajaxResponse
 */
function SendMessage(int $sid, $name, $message)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->is_admin()) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w",
            "Hacking Attempt",
            "$username tried to send ingame message to '$name', but doesnt have access. Message: $message");
        return $objResponse;
    }

    $ret = rcon('status', $sid);

    if (!$ret) {
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        $objResponse->addScript("ShowBox('Error', 'Can\' connect to server!', 'red', '', true);");
        return $objResponse;
    }

    $message = html_entity_decode($message, ENT_QUOTES);
    $message = str_replace('"', "'", $message);

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($name, $player['name'])) {
            rcon("sm_psay #$player[id] \"$message\"", $sid);
        }
    }

    Log::add("m", "Message sent to player", "The following message was sent to $name on server (#$sid): $message");
    $objResponse->addScript(
        "ShowBox('Message Sent', 'The message has been sent to player \'".addslashes(htmlspecialchars($name))."\' successfully!', 'green', '', true);$('dialog-control').setStyle('display', 'block');"
    );
    return $objResponse;
}

/**
 * @param string $nickname
 * @param int $type
 * @param string $steam
 * @param int $length
 * @param string $reason
 * @return xajaxResponse
 */
function AddBlock($nickname, $type, $steam, $length, $reason)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried to add a block, but doesnt have access.");
        return $objResponse;
    }

    $steam = SteamID::toSteam2(trim($steam));
    $nickname = htmlspecialchars_decode($nickname, ENT_QUOTES);
    $reason = htmlspecialchars_decode($reason, ENT_QUOTES);

    $error = 0;
    // If they didnt type a steamid
    if (empty($steam)) {
        $error++;
        $objResponse->addAssign("steam.msg", "innerHTML", "You must type a Steam ID or Community ID");
        $objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
    } elseif (!SteamID::isValidID($steam)) {
        $error++;
        $objResponse->addAssign("steam.msg", "innerHTML", "Please enter a valid Steam ID or Community ID");
        $objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
    } elseif ($length < 0) {
        $error++;
        $objResponse->addAssign("length.msg", "innerHTML", "Length must be positive or 0");
        $objResponse->addScript("$('length.msg').setStyle('display', 'block');");
    } else {
        $objResponse->addAssign("steam.msg", "innerHTML", "");
        $objResponse->addScript("$('steam.msg').setStyle('display', 'none');");
    }

    if ($error > 0) {
        return $objResponse;
    }

    if (!$length) {
        $len = 0;
    } else {
        $len = $length*60;
    }

    // prune any old bans
    PruneComms();

    $typeW = "";
    switch ((int)$type) {
    case 1:
        $typeW = "type = 1";
        break;
    case 2:
        $typeW = "type = 2";
        break;
    case 3:
        $typeW = "(type = 1 OR type = 2)";
        break;
    default:
        $typeW = "";
        break;
    }

    // Check if the new steamid is already banned
    $chk = $GLOBALS['db']->GetRow(
        "SELECT count(bid) AS count FROM ".DB_PREFIX."_comms WHERE authid = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND ".$typeW,
        array($steam)
    );

    if (intval($chk[0]) > 0) {
        $objResponse->addScript("ShowBox('Error', 'SteamID: $steam is already blocked.', 'red', '');");
        return $objResponse;
    }

    // Check if player is immune
    $admchk = $userbank->GetAllAdmins();
    foreach ($admchk as $admin) {
        if ($admin['authid'] == $steam && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity']) {
            $objResponse->addScript(
                "ShowBox('Error', 'SteamID: Admin ".$admin['user']." ($steam) is immune.', 'red', '');"
            );
            return $objResponse;
        }
    }

    if ((int)$type == 1 || (int)$type == 3) {
        $pre = $GLOBALS['db']->Prepare(
            "INSERT INTO " . DB_PREFIX . "_comms(created,type,authid,name,ends,length,reason,aid,adminIp ) VALUES
      (UNIX_TIMESTAMP(),1,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)"
        );
        $GLOBALS['db']->Execute(
            $pre, array($steam,
            $nickname,
            $length * 60,
            $len,
            $reason,
            $userbank->GetAid(),
            $_SERVER['REMOTE_ADDR'])
        );
    }
    if ((int)$type == 2 || (int)$type ==3) {
        $pre = $GLOBALS['db']->Prepare(
            "INSERT INTO " . DB_PREFIX . "_comms(created,type,authid,name,ends,length,reason,aid,adminIp ) VALUES
      (UNIX_TIMESTAMP(),2,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)"
        );
        $GLOBALS['db']->Execute(
            $pre, array($steam,
            $nickname,
            $length * 60,
            $len,
            $reason,
            $userbank->GetAid(),
            $_SERVER['REMOTE_ADDR'])
        );
    }

    $objResponse->addScript("ShowBlockBox('".$steam."', '".(int)$type."', '".(int)$len."');");
    $objResponse->addScript("TabToReload();");
    Log::add("m", "Block Added", "Block against ($steam) has been added. Reason: $reason; Length: $length");
    return $objResponse;
}

/**
 * @param int $bid
 * @return xajaxResponse
 */
function PrepareReblock($bid)
{
    $objResponse = new xajaxResponse();

    $ban = $GLOBALS['db']->GetRow(
        "SELECT name, authid, type, length, reason FROM ".DB_PREFIX."_comms WHERE bid = '".$bid."';"
    );

    // clear any old stuff
    $objResponse->addScript("$('nickname').value = ''");
    $objResponse->addScript("$('steam').value = ''");
    $objResponse->addScript("$('txtReason').value = ''");
    $objResponse->addAssign("txtReason", "innerHTML",  "");

    // add new stuff
    $objResponse->addScript("$('nickname').value = '" . $ban['name'] . "'");
    $objResponse->addScript("$('steam').value = '" . $ban['authid']. "'");
    $objResponse->addScriptCall("selectLengthTypeReason", $ban['length'], $ban['type']-1, addslashes($ban['reason']));

    $objResponse->addScript("swapTab(0);");
    return $objResponse;
}

/**
 * @return xajaxResponse
 */
function generatePassword()
{
    $objResponse = new xajaxResponse();
    $password = Crypto::genPassword();
    $objResponse->addScript("$('password').value = '$password'");
    $objResponse->addScript("$('password2').value = '$password'");
    return $objResponse;
}

/**
 * @param int $bid
 * @return xajaxResponse
 */
function PrepareBlockFromBan($bid)
{
    $objResponse = new xajaxResponse();

    // clear any old stuff
    $objResponse->addScript("$('nickname').value = ''");
    $objResponse->addScript("$('steam').value = ''");
    $objResponse->addScript("$('txtReason').value = ''");
    $objResponse->addAssign("txtReason", "innerHTML",  "");

    $ban = $GLOBALS['db']->GetRow("SELECT name, authid FROM ".DB_PREFIX."_bans WHERE bid = '".$bid."';");

    // add new stuff
    $objResponse->addScript("$('nickname').value = '" . $ban['name'] . "'");
    $objResponse->addScript("$('steam').value = '" . $ban['authid']. "'");

    $objResponse->addScript("swapTab(0);");
    return $objResponse;
}

/**
 * @param int $sid
 * @param string $name
 * @return xajaxResponse
 */
function PasteBlock(int $sid, $name)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;

    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        Log::add("w", "Hacking Attempt", "$username tried paste a block, but doesn't have access.");
        return $objResponse;
    }

    $ret = rcon('status', $sid);

    if (!$ret) {
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        $objResponse->addScript("ShowBox('Error', 'Can\'t connect to server!', 'red', '', true);");
        return $objResponse;
    }

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($player['name'], $name)) {
            $objResponse->addScript(
                "$('nickname').value = '" . addslashes(html_entity_decode($name, ENT_QUOTES)) . "'"
            );
            $objResponse->addScript("$('steam').value = '" . $player['steamid'] . "'");

            $objResponse->addScript("swapTab(0);");
            $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
            $objResponse->addScript("$('dialog-placement').setStyle('display', 'none');");
            return $objResponse;
        }
    }

    $objResponse->addScript(
        "ShowBox('Error', 'Can\'t get player info for ".addslashes(htmlspecialchars($name)).". Player is not on the server (".$data['ip'].":".$data['port'].") anymore!', 'red', '', true);"
    );
    $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
    return $objResponse;
}
