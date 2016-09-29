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
// Steam Login by @duhowpi 2015

session_start();
include_once 'init.php';
include_once 'config.php';
require_once 'includes/openid.php';

define('SB_HOST', SB_WP_URL);
define('SB_URL', SB_WP_URL);

function steamOauth()
{
    $openid = new LightOpenID(SB_HOST);
    if (!$openid->mode) {
        $openid->identity = 'http://steamcommunity.com/openid';
        header("Location: " . $openid->authUrl());
        exit();
    } elseif ($openid->mode == 'cancel') {
        // User canceled auth.
        return false;
    } else {
        if ($openid->validate()) {
            $id  = $openid->identity;
            $ptn = "/^http:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
            preg_match($ptn, $id, $matches);
            
            if (!empty($matches[1])) {
                return $matches[1];
            }
            return null;
        } else {
            // Not valid
            return false;
        }
    }
}

function convert64to32($steam_cid)
{
    $id    = array(
        'STEAM_0'
    );
    $id[1] = substr($steam_cid, -1, 1) % 2 == 0 ? 0 : 1;
    $id[2] = bcsub($steam_cid, '76561197960265728');
    if (bccomp($id[2], '0') != 1) {
        return false;
    }
    $id[2] = bcsub($id[2], $id[1]);
    list($id[2], ) = explode('.', bcdiv($id[2], 2), 2);
    return implode(':', $id);
}

if (isset($_COOKIE['aid'])) {
    header("Location: " . SB_URL);
}

$data = steamOauth();

if ($data !== false) {
    $data = convert64to32($data);
    
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if (defined('DB_PREFIX')) {
        $prfx = DB_PREFIX . "_";
    } else {
        $prfx = "";
    }
    
    $resultado = $mysqli->query("SELECT aid,password FROM " . $prfx . "admins WHERE authid = '" . $data . "'; ");
    if ($resultado->num_rows == 1) {
        list($aid, $password) = $resultado->fetch_row();
        global $userbank;
        if (empty($password) || $password == $userbank->encrypt_password('')) {
            header("Location: " . SB_URL . "/index.php?p=login&m=empty_pwd");
            die;
        } else {
            setcookie("aid", $aid, time() + LOGIN_COOKIE_LIFETIME);
            setcookie("password", $password, time() + LOGIN_COOKIE_LIFETIME);
        }
    }
    $mysqli->close();
} else {
    header("Location: " . SB_URL . "/index.php?p=login");
}
header("Location: " . SB_URL);