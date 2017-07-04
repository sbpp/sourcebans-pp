<?php
/*************************************************************************
	This file is part of SourceBans++

	Copyright © 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

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
		Copyright © 2007-2014 SourceBans Team - Part of GameConnect
		Licensed under CC BY-NC-SA 3.0
		Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

/**
 * Checks the database for any identical
 * rows, username, email etc
 *
 * @param string $table the table to lookup
 * @param string $field The field to check
 * @param string $value The value to check against
 * @return true if the value already exists in that field is found, else false
 */
function is_taken($table, $field, $value)
{
    // This one is nasty and should be removed. Avoid throwing any user input into $table and $field here...
    $query = $GLOBALS['db']->GetRow("SELECT * FROM `" . DB_PREFIX . "_$table` WHERE `$field` = ?", array($value));
    return (count($query) > 0);
}


/**
 * Generates a random string to use as the salt
 *
 * @param integer $length the length of the salt
 * @return string of random chars in the length specified
 */
function generate_salt($length = 5)
{
    return (substr(str_shuffle('qwertyuiopasdfghjklmnbvcxz0987612345'), 0, $length));
}

/**
 * Logs out the admin by removing cookies and killing the session
 *
 * @param string $username The username of the admin
 * @param string $password The password of the admin
 * @param boolean $cookie Should we create a cookie
 * @return true.
 */
function logout()
{
    $_SESSION = array();
    session_destroy();
    return true;
}

/**
 * Changes the admins data
 *
 * @param integer $aid The admin id to change the details of
 * @param string $username The new username of the admin
 * @param string $name The new realname of the admin
 * @param string $email The email of the admin
 * @param string $authid the STEAM of the admin
 * @return true on success.
 */
function edit_admin($aid, $username, $name, $email, $authid)
{
    $query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `user` = ?,  `authid` = ?, `email` = ? WHERE `aid` = ?", array($username, $authid, $email, $aid));
    if ($query) {
        return true;
    }
    return false;
}

/**
 * Removes an admin from the system
 *
 * @param integer $aid The admin id of the admin to delete
 * @return true on success.
 */
function delete_admin($aid)
{
    $aid = (int)$aid;
    $query = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_admins` WHERE `aid` = '$aid'");
    if ($query) {
        return true;
    }
    return false;
}

/**
 * Creates an array to store the admin's data in
 *
 * @param integer $aid The admin id of the user get details for
 * @param string $pass The admins password for security
 * @return array.
 */
function userdata($aid, $pass)
{
    global $userbank;
    if (!$userbank->CheckLogin($userbank->encrypt_password($pass), $aid)) {
        //Fill array with guest data
        $_SESSION['user'] = array('aid' => '-1',
                                  'user' => 'Guest',
                                  'password' => '',
                                  'steam' => '',
                                  'email' => '',
                                  'gid' => '',
                                  'flags' => '0');
    } else {
        $query = $GLOBALS['db']->GetRow("SELECT * FROM `" . DB_PREFIX . "_admins` WHERE `aid` = '$aid'");
        $_SESSION['user'] = array('aid' => $aid,
                                  'user' => $query['user'],
                                  'password' => $query['password'],
                                  'steam' => $query['authid'],
                                  'email' => $query['email'],
                                  'gid' => $query['gid'],
                                  'flags' => get_user_flags($aid),
                                  'admin' => get_user_admin($query['authid']));
        $GLOBALS['aid'] = $aid;
        $GLOBALS['user'] = new CUser($aid);
        $GLOBALS['user']->FillData();
    }
}

/**
 * Returns the current flags associated with the user
 *
 * @param integer The admin id to check
 * @return integer.
 */
function get_user_flags($aid)
{
    if (empty($aid)) {
        return 0;
    }

    $admin = $query = $GLOBALS['db']->GetRow("SELECT `gid`, `extraflags` FROM `" . DB_PREFIX . "_admins` WHERE aid = '$aid'");
    if (intval($admin['gid']) == -1) {
        return intval($admin['extraflags']);
    }
    $query = $GLOBALS['db']->GetRow("SELECT `flags` FROM `" . DB_PREFIX . "_groups` WHERE gid = (SELECT gid FROM " . DB_PREFIX . "_admins WHERE aid = '$aid')");
    return (intval($query['flags']) | intval($admin['extraflags']));
}

/**
 * Returns the current server flags associated with the user
 *
 * @param string The admin to check
 * @return string.
 */
function get_user_admin($steam)
{
    if (empty($steam)) {
        return 0;
    }
    $admin = $GLOBALS['db']->GetRow("SELECT * FROM " . DB_PREFIX . "_admins WHERE authid = '" . $steam . "'");
    if (strlen($admin['srv_group']) > 1) {
        $query = $GLOBALS['db']->GetRow("SELECT flags FROM " . DB_PREFIX . "_srvgroups WHERE name = (SELECT srv_group FROM " . DB_PREFIX . "_admins WHERE authid = '" . $steam . "')");
        return $query['flags'] . $admin['srv_flags'];
    }
    return $admin['srv_flags'];
}

/**
 * Returns the current server flags associated with the user
 *
 * @param string The admin to check
 * @return string.
 */
function get_non_inherited_admin($steam)
{
    if (empty($steam)) {
        return 0;
    }
    $admin = $GLOBALS['db']->GetRow("SELECT * FROM `" . DB_PREFIX . "_admins` WHERE authid = '$steam'");
    return $admin['srv_flags'];
}

/**
 * Checks if user is logged in.
 *
 * @return boolean.
 */
function is_logged_in()
{
    if ($_SESSION['user']['user'] == "Guest" || $_SESSION['user']['user'] == "") {
        return false;
    }
    return true;
}

/**
 * Checks if user is an admin.
 *
 * @return boolean.
 */
function is_admin($aid)
{
    if (check_flags($aid, ALL_WEB)) {
        return true;
    }
    return false;
}

/**
 * Checks which admin type the admin is
 * using the given mask
 *
 * @return integer.
 */
function check_group($mask)
{
    if ($mask &
    (ADMIN_WEB_BANS|ADMIN_WEB_ADMINS|ADMIN_WEB_AGROUPS|
    ADMIN_SERVER_ADMINS|ADMIN_SERVER_AGROUPS|ADMIN_SERVER_SETTINGS|
    ADMIN_SERVER_ADD|ADMIN_SERVER_REMOVE|ADMIN_SERVER_GROUPS|ADMIN_WEB_SETTINGS|
    ADMIN_OWNER|ADMIN_MODS != 0 && $mask &
    SM_RESERVED_SLOT|SM_GENERIC|SM_KICK|SM_BAN|SM_UNBAN|SM_SLAY|
    SM_MAP|SM_CVAR|SM_CONFIG|SM_CHAT|SM_VOTE|SM_PASSWORD|SM_RCON|
    SM_CHEATS|SM_ROOT|SM_DEF_IMMUNITY|SM_GLOBAL_IMMUNITY == 0)) {
        return GROUP_WEB_A;
    } elseif ($mask == 0) {
        return GROUP_NONE_A;
    }
    return GROUP_SERVER_A;
}



/**
 * Removes all flags and replaces with new flag
 *
 * @param integet $aid the admin id to change the flags of
 * @param integer $flag the new flag to apply to the user
 * @return noreturn
 */
function set_flag($aid, $flag)
{
    $aid = (int)$aid;
    $query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_groups` SET `flags` = '$flag' WHERE gid = (SELECT gid FROM " . DB_PREFIX . "_admins WHERE aid = '$aid')");
    userdata($aid, $_SESSION['user']['password']);
}

/**
 * Adds a new flag to the current bitmask
 *
 * @param integet $aid the admin id to change the flags of
 * @param integer $flag the flag to apply to the user
 * @return noreturn
 */
function add_flag($aid, $flag)
{
    $aid = (int)$aid;
    $flagd = get_user_flags($aid);
    $flagd |= $flag;
    $query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_groups` SET `flags` = '$flagd' WHERE gid = (SELECT gid FROM " . DB_PREFIX . "_admins WHERE aid = '$aid')");
    userdata($aid, $_SESSION['user']['password']);
}

/**
 * Removes a flag from the bitmask
 *
 * @param integet $aid the admin id to change the flags of
 * @param integer $flag the flag to remove from the user
 * @return noreturn
 */
function remove_flag($aid, $flag)
{
    $aid = (int)$aid;
    $flagd = get_user_flags($aid);
    $flagd &= ~($flag);
    $query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_groups` SET `flags` = '$flagd' WHERE gid = (SELECT gid FROM " . DB_PREFIX . "_admins WHERE aid = '$aid')");
    userdata($aid, $_SESSION['user']['password']);
}

/**
 * Checks if the admin has ALL the specified flags
 *
 * @param integet $aid the admin id to check the flags of
 * @param integer $flag the flag to check
 * @return boolean
 */
function check_all_flags($aid, $flag)
{
    $mask = get_user_flags($aid);
    return ($mask & $flag) == $flag;
}

/**
 * Checks if the admin has ANY the specified flags
 *
 * @param integet $aid the admin id to check the flags of
 * @param integer $flag the flag to check
 * @return boolean
 */
function check_flags($aid, $flag)
{
    $mask = get_user_flags($aid);
    if (($mask & $flag) !=0) {
        return true;
    }
    return false;
}

/**
 * Checks if the mask contains ANY the specified flags
 *
 * @param integet $aid the admin id to check the flags of
 * @param integer $flag the flag to check
 * @return boolean
 */
function check_flag($mask, $flag)
{
    if (($mask & $flag) !=0) {
        return true;
    }
    return false;
}

function validate_steam($steam)
{
    return preg_match(STEAM_FORMAT, $steam) ? true : false;
}

function validate_email($email)
{
    return preg_match(EMAIL_FORMAT, $email) ? true : false;
}
function validate_ip($ips)
{
    return preg_match(IP_FORMAT, $ips) ? true : false;
}

/**
 * added for the steam login option mod
 * checks the value of the config setting
 * called by  steamopenid.php
 * called by pages/pages.login.php
 * @param int 1 or 0
 * @return int
 */
function get_steamenabled_conf($value)
{
    $settingvalue = "config.enablesteamlogin";
    $query = $GLOBALS['db']->GetRow("SELECT `value` FROM `" . DB_PREFIX . "_settings` WHERE `setting` = '$settingvalue'");
    $value = intval($query['value']);
    return $value;
}
