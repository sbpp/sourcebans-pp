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
global $userbank, $theme;

if (!isset($_GET['id'])) {
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No admin id specified. Please only follow links
</div>';
    PageDie();
}
$_GET['id'] = (int) $_GET['id'];

if (!$userbank->GetProperty("user", $_GET['id'])) {
    $log = new CSystemLog("e", "Getting admin data failed", "Can't find data for admin with id '" . $_GET['id'] . "'");
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	Error getting current data.
</div>';
    PageDie();
}

// Skip all checks if root
if (!$userbank->HasAccess(ADMIN_OWNER)) {
    if (!$userbank->HasAccess(ADMIN_EDIT_ADMINS) || ($userbank->HasAccess(ADMIN_OWNER, $_GET['id']) && $_GET['id'] != $userbank->GetAid())) {
        $log = new CSystemLog("w", "Hacking Attempt", $userbank->GetProperty("user") . " tried to edit " . $userbank->GetProperty('user', $_GET['id']) . "'s details, but doesnt have access.");
        echo '<div id="msg-red" >
		<i><img src="./images/warning.png" alt="Warning" /></i>
		<b>Error</b>
		<br />
		You are not allowed to edit other profiles.
	</div>';
        PageDie();
    }
}
$errorScript = "";

// Form submitted?
if (isset($_POST['adminname'])) {
    $a_name           = RemoveCode($_POST['adminname']);
    $a_steam          = trim(RemoveCode($_POST['steam']));
    $a_email          = trim(RemoveCode($_POST['email']));
    $a_serverpass     = $_POST['a_useserverpass'] == "on";
    $pw_changed       = false;
    $serverpw_changed = false;

    // Form validation
    $error = 0;

    // Check name
    if (empty($a_name)) {
        $error++;
        $errorScript .= "$('adminname.msg').innerHTML = 'You must type a name for the admin.';";
        $errorScript .= "$('adminname.msg').setStyle('display', 'block');";
    } else {
        if (strstr($a_name, "'")) {
            $error++;
            $errorScript .= "$('adminname.msg').innerHTML = 'An admin name can not contain a \" \' \".';";
            $errorScript .= "$('adminname.msg').setStyle('display', 'block');";
        } else {
            if ($a_name != $userbank->GetProperty('user', $_GET['id']) && is_taken("admins", "user", $a_name)) {
                $error++;
                $errorScript .= "$('adminname.msg').innerHTML = 'An admin with this name already exists.';";
                $errorScript .= "$('adminname.msg').setStyle('display', 'block');";
            }
        }
    }

    // If they didnt type a steamid
    if ((empty($a_steam) || strlen($a_steam) < 10)) {
        $error++;
        $errorScript .= "$('steam.msg').innerHTML = 'You must type a Steam ID or Community ID for the admin.';";
        $errorScript .= "$('steam.msg').setStyle('display', 'block');";
    } else {
        // Validate the steamid or fetch it from the community id
        if ((!is_numeric($a_steam) && !validate_steam($a_steam)) || (is_numeric($a_steam) && (strlen($a_steam) < 15 || !validate_steam($a_steam = FriendIDToSteamID($a_steam))))) {
            $error++;
            $errorScript .= "$('steam.msg').innerHTML = 'Please enter a valid Steam ID or Community ID.';";
            $errorScript .= "$('steam.msg').setStyle('display', 'block');";
        } else {
            // Is an other admin already registred with that steam id?
            if ($a_steam != $userbank->GetProperty('authid', $_GET['id']) && is_taken("admins", "authid", $a_steam)) {
                $admins = $userbank->GetAllAdmins();
                foreach ($admins as $admin) {
                    if ($admin['authid'] == $a_steam) {
                        $name = $admin['user'];
                        break;
                    }
                }
                $error++;
                $errorScript .= "$('steam.msg').innerHTML = 'Admin " . htmlspecialchars(addslashes($name)) . " already uses this Steam ID.';";
                $errorScript .= "$('steam.msg').setStyle('display', 'block');";
            }
        }
    }

    // No email
    if (empty($a_email)) {
        // Only required, if admin has web permissions.
        if ($GLOBALS['userbank']->GetProperty('extraflags', $_GET['id']) != 0 || $GLOBALS['userbank']->GetProperty('gid', $_GET['id']) > 0) {
            $error++;
            $errorScript .= "$('email.msg').innerHTML = 'You must type an e-mail address.';";
            $errorScript .= "$('email.msg').setStyle('display', 'block');";
        }
    } else {
        // Is an other admin already registred with that email address?
        if ($a_email != $userbank->GetProperty('email', $_GET['id']) && is_taken("admins", "email", $a_email)) {
            $admins = $userbank->GetAllAdmins();
            foreach ($admins as $admin) {
                if ($admin['email'] == $a_email) {
                    $name = $admin['user'];
                    break;
                }
            }
            $error++;
            $errorScript .= "$('email.msg').innerHTML = 'This email address is already being used by " . htmlspecialchars(addslashes($name)) . ".';";
            $errorScript .= "$('email.msg').setStyle('display', 'block');";
        }
        /*else if(!validate_email($a_email))
        $error++;
        $errorScript .= "$('email.msg').innerHTML = 'Please enter a valid email address.';";
        $errorScript .= "$('email.msg').setStyle('display', 'block');";
        }*/
    }

    // Only validate passwords, if admin has access to edit it at all
    if ($userbank->HasAccess(ADMIN_OWNER) || $_GET['id'] == $userbank->GetAid()) {
        // Don't change the password, if not set
        if (!empty($_POST['password'])) {
            $pw_changed = true;
            // DID type a password, so he wants to change it.
            // Password too short?
            if (strlen($_POST['password']) < MIN_PASS_LENGTH) {
                $error++;
                $errorScript .= "$('password.msg').innerHTML = 'Your password must be at-least " . MIN_PASS_LENGTH . " characters long.';";
                $errorScript .= "$('password.msg').setStyle('display', 'block');";
            } else {
                // No confirmation typed
                if (empty($_POST['password2'])) {
                    $error++;
                    $errorScript .= "$('password2.msg').innerHTML = 'You must confirm the password.';";
                    $errorScript .= "$('password2.msg').setStyle('display', 'block');";
                } elseif ($_POST['password'] != $_POST['password2']) {
                    // Passwords match?
                    $error++;
                    $errorScript .= "$('password2.msg').innerHTML = 'Your passwords don't match.';";
                    $errorScript .= "$('password2.msg').setStyle('display', 'block');";
                }
            }
        }

        // Check for the serverpassword
        if ($_POST['a_useserverpass'] == "on") {
            if (!empty($_POST['a_serverpass'])) {
                $serverpw_changed = true;
            }

            // No password given and no set before?
            $srvpw = $userbank->GetProperty('srv_password', $_GET['id']);
            if (empty($_POST['a_serverpass']) && empty($srvpw)) {
                $error++;
                $errorScript .= "$('a_serverpass.msg').innerHTML = 'You must type a server password or uncheck the box.';";
                $errorScript .= "$('a_serverpass.msg').setStyle('display', 'block');";
            } elseif (strlen($_POST['a_serverpass']) < MIN_PASS_LENGTH) {
                // Password too short?
                $error++;
                $errorScript .= "$('a_serverpass.msg').innerHTML = 'Your password must be at-least " . MIN_PASS_LENGTH . " characters long.';";
                $errorScript .= "$('a_serverpass.msg').setStyle('display', 'block');";
            }
        }
    }

    // Only proceed, if there are no errors in the form
    if ($error == 0) {
        // set the basic fields
        $edit = $GLOBALS['db']->Execute(
            "UPDATE " . DB_PREFIX . "_admins SET
            `user` = ?, `authid` = ?, `email` = ?
            WHERE `aid` = ?",
            array(
                $a_name,
                $a_steam,
                $a_email,
                $_GET['id']
            )
        );

        // Password changed?
        if ($pw_changed) {
            $edit = $GLOBALS['db']->Execute(
                "UPDATE " . DB_PREFIX . "_admins SET
                `password` = ?
                WHERE `aid` = ?",
                array(
                    $userbank->encrypt_password($_POST['password']),
                    $_GET['id']
                )
            );
        }

        // Server Admin Password changed?
        if ($serverpw_changed) {
            $edit = $GLOBALS['db']->Execute(
                "UPDATE " . DB_PREFIX . "_admins SET
                `srv_password` = ?
                WHERE `aid` = ?",
                array(
                    $_POST['a_serverpass'],
                    $_GET['id']
                )
            );
        } elseif ($_POST['a_useserverpass'] != "on") {
            // Remove the server password
            $edit = $GLOBALS['db']->Execute(
                "UPDATE " . DB_PREFIX . "_admins SET
                `srv_password` = NULL
                WHERE `aid` = ?",
                array(
                    $_GET['id']
                )
            );
        }

        // to prevent rehash window to error with "no access", cause pw doesn't match
        $ownpwchanged = false;
        if ($_GET['id'] == $userbank->GetAid() && !empty($_POST['password']) && $userbank->encrypt_password($_POST['password']) != $userbank->GetProperty("password")) {
            $ownpwchanged = true;
        }

        if (isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1) {
            // rehash the admins on the servers
            $serveraccessq = $GLOBALS['db']->GetAll("SELECT s.sid FROM `" . DB_PREFIX . "_servers` s
                LEFT JOIN `" . DB_PREFIX . "_admins_servers_groups` asg ON asg.admin_id = '" . (int) $_GET['id'] . "'
                LEFT JOIN `" . DB_PREFIX . "_servers_groups` sg ON sg.group_id = asg.srv_group_id
                WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
                OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
                AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1");
            $allservers    = array();
            foreach ($serveraccessq as $access) {
                if (!in_array($access['sid'], $allservers)) {
                    $allservers[] = $access['sid'];
                }
            }
            $rehashing = true;
        }

        $admname = $GLOBALS['db']->GetRow("SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = ?", array(
            (int) $_GET['id']
        ));
        $log     = new CSystemLog("m", "Admin Details Updated", "Admin (" . $admname['user'] . ") details has been changed");
        if ($ownpwchanged) {
            echo '<script>ShowBox("Admin details updated", "The admin details has been updated successfully", "green", "index.php?p=login");TabToReload();</script>';
        } elseif (isset($rehashing)) {
            echo '<script>ShowRehashBox("' . implode(",", $allservers) . '", "Admin details updated", "The admin details has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
        } else {
            echo '<script>ShowBox("Admin details updated", "The admin details has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
        }
    }
} else {
    // get current values
    $a_name = $userbank->GetProperty("user", $_GET['id']);
    $a_steam = trim($userbank->GetProperty("authid", $_GET['id']));
    $a_email = $userbank->GetProperty("email", $_GET['id']);
    $a_serverpass = $userbank->GetProperty("srv_password", $_GET['id']);
    $a_serverpass = !empty($a_serverpass);
}

$theme->assign('change_pass', ($userbank->HasAccess(ADMIN_OWNER) || $_GET['id'] == $userbank->GetAid()));
$theme->assign('user', $a_name);
$theme->assign('authid', $a_steam);
$theme->assign('email', $a_email);
$theme->assign('a_spass', $a_serverpass);

$theme->display('page_admin_edit_admins_details.tpl');
?>
<script type="text/javascript">window.addEvent('domready', function(){
<?=$errorScript?>
});
</script>
