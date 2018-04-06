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
// Steam Login by @duhowpi 2015

include_once 'init.php';
include_once 'config.php';
require_once 'includes/openid.php';

define('SB_HOST', SB_WP_URL);
define('SB_URL', SB_WP_URL);

$dbs = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX);

function steamOauth()
{
    $openid = new LightOpenID(SB_HOST);
    if (!$openid->mode) {
        $openid->identity = 'https://steamcommunity.com/openid';
        header("Location: " . $openid->authUrl());
        exit();
    }
    if ($openid->validate()) {
        $ids = $openid->identity;
        $ptn = "/^https:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
        preg_match($ptn, $ids, $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }
    }
    return false;
}

function convert64to32(Database $dbs, $communityID)
{
    $query = "SELECT CONCAT(\"STEAM_0:\", (CAST(':communityID' AS UNSIGNED) - CAST('76561197960265728' AS UNSIGNED)) % 2, \":\", CAST(((CAST(':communityID' AS UNSIGNED) - CAST('76561197960265728' AS UNSIGNED)) - ((CAST(':communityID' AS UNSIGNED) - CAST('76561197960265728' AS UNSIGNED)) % 2)) / 2 AS UNSIGNED)) AS steam_id";
    $query = str_replace(':communityID', $communityID, $query);
    $dbs->query($query);
    $steamid = $dbs->single();
    return $steamid['steam_id'];
}

if (isset($_COOKIE['aid'])) {
    header("Location: " . SB_URL);
}

$data = steamOauth();

if ($data !== false) {
    $data = convert64to32($dbs, $data);

    $dbs->query('SELECT aid, password FROM `:prefix_admins` WHERE authid = :authid');
    $dbs->bind(':authid', $data);
    $result = $dbs->single();
    if (count($result) == 2) {
        global $userbank;
        if (empty($result['password']) || $result['password'] == $userbank->encrypt_password('') || $result['password'] == $userbank->hash('')) {
            header("Location: " . SB_URL . "/index.php?p=login&m=empty_pwd");
            die;
        } else {
            session_destroy();
            \SessionManager::sessionStart('SourceBans', (60*60*24*7));
            $_SESSION['aid'] = $result['aid'];
        }
    }
} else {
    header("Location: " . SB_URL . "/index.php?p=login");
}
header("Location: " . SB_URL);
