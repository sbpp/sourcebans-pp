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
