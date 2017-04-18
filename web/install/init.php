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

// ---------------------------------------------------
//  Directories
// ---------------------------------------------------
define('ROOT', dirname(__FILE__) . "/");
define('SCRIPT_PATH', ROOT . 'scripts');
define('TEMPLATES_PATH', ROOT . 'template');
define('INCLUDES_PATH', ROOT . 'includes');
define('IN_SB', true);
define('IN_INSTALL', true);

// ---------------------------------------------------
//  Fix some $_SERVER vars
// ---------------------------------------------------
// Fix for IIS, which doesn't set REQUEST_URI
if (!isset($_SERVER['REQUEST_URI']) || trim($_SERVER['REQUEST_URI']) == '') {
    $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];

    if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
        $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
    }
}
// Fix for Dreamhost and other PHP as CGI hosts
if (strstr($_SERVER['SCRIPT_NAME'], 'php.cgi')) {
    unset($_SERVER['PATH_INFO']);
}
if (trim($_SERVER['PHP_SELF']) == '') {
    $_SERVER['PHP_SELF'] = preg_replace("/(\?.*)?$/", '', $_SERVER["REQUEST_URI"]);
}

// ---------------------------------------------------
//  Initial setup
// ---------------------------------------------------
if (!defined('SB_VERSION')) {
    define('SB_VERSION', '1.6.0-Installer');
}
define('LOGIN_COOKIE_LIFETIME', (60*60*24*7)*2);
define('COOKIE_PATH', '/');
define('COOKIE_DOMAIN', '');
define('COOKIE_SECURE', false);
define('SB_SALT', 'SourceBans');
// ---------------------------------------------------
//  Setup PHP
// ---------------------------------------------------
ini_set('date.timezone', 'GMT');

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('GMT');
} elseif (!ini_get('safe_mode')) {
    putenv('TZ=GMT');
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Create a blank config file
if (!file_exists("../config.php") && is_writable('../')) {
    $handle = fopen("../config.php", "w");
    fclose($handle);
}

// ---------------------------------------------------
//  Some defs
// ---------------------------------------------------
define('EMAIL_FORMAT', "/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/");
define('URL_FORMAT', "/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}((:[0-9]{1,5})?\/.*)?$/i");
define('STEAM_FORMAT', "/^STEAM_[0-9]:[0-9]:[0-9]+$/");
define('STATUS_PARSE', '/#[ ]*([0-9]+) "(.+)" (STEAM_0:[0-1]:[0-9]+)[ ]{1,2}([0-9]+[:[0-9]+) ([0-9]+)[ ]([0-9]+) ([a-zA-Z]+) ([0-9.:]+)/');
