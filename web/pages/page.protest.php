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

global $userbank, $theme;
if ($GLOBALS['config']['config.enableprotest'] != "1") {
    CreateRedBox("Error", "This page is disabled. You should not be here.");
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

    if ($Type == 0 && !validate_steam($SteamID)) {
        $errors .= '* Please type a valid STEAM ID.<br>';
        $validsubmit = false;
    } elseif ($Type == 0) {
        $pre = $GLOBALS['db']->Prepare("SELECT bid FROM " . DB_PREFIX . "_bans WHERE authid=? AND RemovedBy IS NULL AND type=0;");
        $res = $GLOBALS['db']->Execute($pre, array(
            $SteamID
        ));
        if ($res->RecordCount() == 0) {
            $errors .= '* That Steam ID is not banned!<br>';
            $validsubmit = false;
        } else {
            $BanId = (int) $res->fields[0];
            $res   = $GLOBALS['db']->Execute("SELECT pid FROM " . DB_PREFIX . "_protests WHERE bid=$BanId");
            if ($res->RecordCount() > 0) {
                $errors .= '* A protest is already pending for this Steam ID.<br>';
                $validsubmit = false;
            }
        }
    }
    if ($Type == 1 && !validate_ip($IP)) {
        $errors .= '* Please type a valid IP.<br>';
        $validsubmit = false;
    } elseif ($Type == 1) {
        $pre = $GLOBALS['db']->Prepare("SELECT bid FROM " . DB_PREFIX . "_bans WHERE ip=? AND RemovedBy IS NULL AND type=1;");
        $res = $GLOBALS['db']->Execute($pre, array(
            $IP
        ));
        if ($res->RecordCount() == 0) {
            $errors .= '* That IP is not banned!<br>';
            $validsubmit = false;
        } else {
            $BanId = (int) $res->fields[0];
            $res   = $GLOBALS['db']->Execute("SELECT pid FROM " . DB_PREFIX . "_protests WHERE bid=$BanId");
            if ($res->RecordCount() > 0) {
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
    if (!check_email($Email)) {
        $errors .= '* You must include a valid email address<br>';
        $validsubmit = false;
    }

    if (!$validsubmit) {
        CreateRedBox("Error", $errors);
    }

    if ($validsubmit && $BanId != -1) {
        $UnbanReason = trim($UnbanReason);
        $pre         = $GLOBALS['db']->Prepare("INSERT INTO " . DB_PREFIX . "_protests(bid,datesubmitted,reason,email,archiv,pip) VALUES (?,UNIX_TIMESTAMP(),?,?,0,?)");
        $res         = $GLOBALS['db']->Execute($pre, array(
            $BanId,
            $UnbanReason,
            $Email,
            $_SERVER['REMOTE_ADDR']
        ));
        $protid      = $GLOBALS['db']->Insert_ID();
        $protadmin   = $GLOBALS['db']->GetRow("SELECT ad.user FROM " . DB_PREFIX . "_protests p, " . DB_PREFIX . "_admins ad, " . DB_PREFIX . "_bans b WHERE p.pid = '" . $protid . "' AND b.bid = p.bid AND ad.aid = b.aid");

        $Type        = 0;
        $SteamID     = "";
        $IP          = "";
        $PlayerName  = "";
        $UnbanReason = "";
        $Email       = "";

        // Send an email when protest was posted
        $headers = 'From: ' . $GLOBALS['sb-email'] . "\n" . 'X-Mailer: PHP/' . phpversion();

        $emailinfo = $GLOBALS['db']->Execute("SELECT aid, user, email FROM `" . DB_PREFIX . "_admins` WHERE aid = (SELECT aid FROM `" . DB_PREFIX . "_bans` WHERE bid = '" . (int) $BanId . "');");
        $requri    = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], ".php") + 4);
        if (isset($GLOBALS['config']['protest.emailonlyinvolved']) && $GLOBALS['config']['protest.emailonlyinvolved'] == 1 && !empty($emailinfo->fields['email'])) {
            $admins = array(
                array(
                    'aid' => $emailinfo->fields['aid'],
                    'user' => $emailinfo->fields['user'],
                    'email' => $emailinfo->fields['email']
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

        CreateGreenBox("Successful", "Your protest has been sent.");
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
