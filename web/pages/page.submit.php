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

global $userbank, $ui, $theme;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
if ($GLOBALS['config']['config.enablesubmit'] != "1") {
    CreateRedBox("Error", "This page is disabled. You should not be here.");
    PageDie();
}
if (!isset($_POST['subban']) || $_POST['subban'] != 1) {
    $SteamID       = "";
    $BanIP         = "";
    $PlayerName    = "";
    $BanReason     = "";
    $SubmitterName = "";
    $Email         = "";
    $SID           = -1;
} else {
    $SteamID       = trim(htmlspecialchars($_POST['SteamID']));
    $BanIP         = trim(htmlspecialchars($_POST['BanIP']));
    $PlayerName    = htmlspecialchars($_POST['PlayerName']);
    $BanReason     = htmlspecialchars($_POST['BanReason']);
    $SubmitterName = htmlspecialchars($_POST['SubmitName']);
    $Email         = trim(htmlspecialchars($_POST['EmailAddr']));
    $SID           = (int) $_POST['server'];
    $validsubmit   = true;
    $errors        = "";
    if ((strlen($SteamID) != 0 && $SteamID != "STEAM_0:") && !validate_steam($SteamID)) {
        $errors .= '* Please type a valid STEAM ID.<br>';
        $validsubmit = false;
    }
    if (strlen($BanIP) != 0 && !validate_ip($BanIP)) {
        $errors .= '* Please type a valid IP-address.<br>';
        $validsubmit = false;
    }
    if (strlen($PlayerName) == 0) {
        $errors .= '* You must include a player name<br>';
        $validsubmit = false;
    }
    if (strlen($BanReason) == 0) {
        $errors .= '* You must include comments<br>';
        $validsubmit = false;
    }
    if (!check_email($Email)) {
        $errors .= '* You must include a valid email address<br>';
        $validsubmit = false;
    }
    if ($SID == -1) {
        $errors .= '* Please select a server.<br>';
        $validsubmit = false;
    }
    if (!empty($_FILES['demo_file']['name'])) {
        if (!CheckExt($_FILES['demo_file']['name'], "zip") && !CheckExt($_FILES['demo_file']['name'], "rar") && !CheckExt($_FILES['demo_file']['name'], "dem") && !CheckExt($_FILES['demo_file']['name'], "7z") && !CheckExt($_FILES['demo_file']['name'], "bz2") && !CheckExt($_FILES['demo_file']['name'], "gz")) {
            $errors .= '* A demo can only be a dem, zip, rar, 7z, bz2 or a gz filetype.<br>';
            $validsubmit = false;
        }
    }
    $checkres = $GLOBALS['db']->Execute("SELECT length FROM " . DB_PREFIX . "_bans WHERE authid = ? AND RemoveType IS NULL", array(
        $SteamID
    ));
    $numcheck = $checkres->RecordCount();
    if ($numcheck == 1 && $checkres->fields['length'] == 0) {
        $errors .= '* The player is already banned permanent.<br>';
        $validsubmit = false;
    }
    
    
    if (!$validsubmit)
        CreateRedBox("Error", $errors);
    
    if ($validsubmit) {
        $filename = md5($SteamID . time());
        //echo SB_DEMOS."/".$filename;
        $demo     = move_uploaded_file($_FILES['demo_file']['tmp_name'], SB_DEMOS . "/" . $filename);
        if ($demo || empty($_FILES['demo_file']['name'])) {
            if ($SID != 0) {
                require_once(INCLUDES_PATH . '/CServerInfo.php');
                $res   = $GLOBALS['db']->GetRow("SELECT ip, port FROM " . DB_PREFIX . "_servers WHERE sid = $SID");
                $sinfo = new CServerInfo($res[0], $res[1]);
                $info  = $sinfo->getInfo();
                if (!empty($info['hostname']))
                    $mailserver = "Server: " . $info['hostname'] . " (" . $res[0] . ":" . $res[1] . ")\n";
                else
                    $mailserver = "Server: Error Connecting (" . $res[0] . ":" . $res[1] . ")\n";
                $modid = $GLOBALS['db']->GetRow("SELECT m.mid FROM `" . DB_PREFIX . "_servers` as s LEFT JOIN `" . DB_PREFIX . "_mods` as m ON m.mid = s.modid WHERE s.sid = '" . $SID . "';");
            } else {
                $mailserver = "Server: Other server\n";
                $modid[0]   = 0;
            }
            if ($SteamID == "STEAM_0:")
                $SteamID = "";
            $pre = $GLOBALS['db']->Prepare("INSERT INTO " . DB_PREFIX . "_submissions(submitted,SteamId,name,email,ModID,reason,ip,subname,sip,archiv,server) VALUES (UNIX_TIMESTAMP(),?,?,?,?,?,?,?,?,0,?)");
            $GLOBALS['db']->Execute($pre, array(
                $SteamID,
                $PlayerName,
                $Email,
                $modid[0],
                $BanReason,
                $_SERVER['REMOTE_ADDR'],
                $SubmitterName,
                $BanIP,
                $SID
            ));
            $subid = (int) $GLOBALS['db']->Insert_ID();
            
            if (!empty($_FILES['demo_file']['name']))
                $GLOBALS['db']->Execute("INSERT INTO " . DB_PREFIX . "_demos(demid,demtype,filename,origname) VALUES (?, 'S', ?, ?)", array(
                    $subid,
                    $filename,
                    $_FILES['demo_file']['name']
                ));
            $SteamID       = "";
            $BanIP         = "";
            $PlayerName    = "";
            $BanReason     = "";
            $SubmitterName = "";
            $Email         = "";
            $SID           = -1;
            
            // Send an email when ban was posted
            $headers = 'From: ' . $GLOBALS['sb-email'] . "\n" . 'X-Mailer: PHP/' . phpversion();
            
            $admins = $userbank->GetAllAdmins();
            $requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], ".php") - 5);
            foreach ($admins AS $admin) {
                $message = "";
                $message .= "Hello " . $admin['user'] . ",\n\n";
                $message .= "A new ban submission has been posted on your SourceBans page:\n\n";
                $message .= "Player: " . $_POST['PlayerName'] . " (" . $_POST['SteamID'] . ")\nDemo: " . (empty($_FILES['demo_file']['name']) ? 'no' : 'yes (http://' . $_SERVER['HTTP_HOST'] . $requri . 'getdemo.php?type=S&id=' . $subid . ')') . "\n" . $mailserver . "Reason: " . $_POST['BanReason'] . "\n\n";
                $message .= "Click the link below to view the current ban submissions.\n\nhttp://" . $_SERVER['HTTP_HOST'] . $requri . "index.php?p=admin&c=bans#^2";
                if ($userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS, $admin['aid']) && $userbank->HasAccess(ADMIN_NOTIFY_SUB, $admin['aid']))
                    mail($admin['email'], "[SourceBans] Ban Submission Added", $message, $headers);
            }
            CreateGreenBox("Successful", "Your submission has been added into the database, and will be reviewed by one of our admins");
        } else {
            CreateRedBox("Error", "There was an error uploading your demo to the server. Please try again later.");
            $log = new CSystemLog("e", "Demo Upload Failed", "A demo failed to upload for a submission from (" . $Email . ")");
        }
    }
}

//$mod_list = $GLOBALS['db']->GetAssoc("SELECT mid,name FROM ".DB_PREFIX."_mods WHERE `mid` > 0 AND `enabled`= 1 ORDER BY mid ");
require_once INCLUDES_PATH . '/CServerInfo.php';
//serverlist
$server_list = $GLOBALS['db']->Execute("SELECT sid, ip, port FROM `" . DB_PREFIX . "_servers` WHERE enabled = 1 ORDER BY modid, sid");
$servers     = array();
while (!$server_list->EOF) {
    $info  = array();
    $sinfo = new CServerInfo($server_list->fields[1], $server_list->fields[2]);
    $info  = $sinfo->getInfo();
    if (empty($info['hostname'])) {
        $info['hostname'] = "Error Connecting (" . $server_list->fields[1] . ":" . $server_list->fields[2] . ")";
    }
    $info['sid'] = $server_list->fields[0];
    array_push($servers, $info);
    $server_list->MoveNext();
}

$theme->assign('STEAMID', $SteamID == "" ? "STEAM_0:" : $SteamID);
$theme->assign('ban_ip', $BanIP);
$theme->assign('ban_reason', $BanReason);
$theme->assign('player_name', $PlayerName);
$theme->assign('subplayer_name', $SubmitterName);
$theme->assign('player_email', $Email);
$theme->assign('server_list', $servers);
$theme->assign('server_selected', $SID);

$theme->display('page_submitban.tpl');
