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

include_once '../init.php';

if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)) {
    echo "No Access";
    die();
}
require_once(INCLUDES_PATH . '/xajax.inc.php');
$xajax = new xajax();
//$xajax->debugOn();
$xajax->setRequestURI("./admin.blockit.php");
$xajax->registerFunction("BlockPlayer");
$xajax->registerFunction("LoadServers2");
$xajax->processRequests();
$username = $userbank->GetProperty("user");

function LoadServers2($check, $type, $length)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        $log = new CSystemLog("w", "Hacking Attempt", $username . " tried to use blockit, but doesn't have access.");
        return $objResponse;
    }
    $id      = 0;
    $servers = $GLOBALS['db']->Execute("SELECT sid, rcon FROM " . DB_PREFIX . "_servers WHERE enabled = 1 ORDER BY modid, sid;");
    while (!$servers->EOF) {
        //search for player
        if (!empty($servers->fields["rcon"])) {
            $text = '<font size="1">Searching...</font>';
            $objResponse->addScript("xajax_BlockPlayer('" . $check . "', '" . $servers->fields["sid"] . "', '" . $id . "', '" . $type . "', '" . $length . "');");
        } else { //no rcon = servercount + 1 ;)
            $text = '<font size="1">No rcon password.</font>';
            $objResponse->addScript('set_counter(1);');
        }
        $objResponse->addAssign("srv_" . $id, "innerHTML", $text);
        $id++;
        $servers->MoveNext();
    }
    return $objResponse;
}

function BlockPlayer($check, $sid, $num, $type, $length)
{
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    $sid    = (int) $sid;
    $length = (int) $length;

    if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        $log = new CSystemLog("w", "Hacking Attempt", $username . " tried to process a playerblock, but doesnt have access.");
        return $objResponse;
    }

    //get the server data
    $sdata = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM " . DB_PREFIX . "_servers WHERE sid = '" . $sid . "';");

    //test if server is online
    if ($test = @fsockopen($sdata['ip'], $sdata['port'], $errno, $errstr, 2)) {
        @fclose($test);
        require_once(INCLUDES_PATH . "/CServerRcon.php");

        $r = new CServerRcon($sdata['ip'], $sdata['port'], $sdata['rcon']);

        if (!$r->Auth()) {
            $GLOBALS['db']->Execute("UPDATE " . DB_PREFIX . "_servers SET rcon = '' WHERE sid = '" . $sid . "' LIMIT 1;");
            $objResponse->addAssign("srv_$num", "innerHTML", "<font color='red' size='1'>Wrong RCON Password, please change!</font>");
            $objResponse->addScript('set_counter(1);');
            return $objResponse;
        }
        $ret = $r->rconCommand("status");

        // show hostname instead of the ip, but leave the ip in the title
        require_once("../includes/system-functions.php");
        $hostsearch = preg_match_all('/hostname:[ ]*(.+)/', $ret, $hostname, PREG_PATTERN_ORDER);
        $hostname   = trunc(htmlspecialchars($hostname[1][0]), 25, false);
        if (!empty($hostname))
            $objResponse->addAssign("srvip_$num", "innerHTML", "<font size='1'><span title='" . $sdata['ip'] . ":" . $sdata['port'] . "'>" . $hostname . "</span></font>");

        $gothim = false;
        $search = preg_match_all(STATUS_PARSE, $ret, $matches, PREG_PATTERN_ORDER);

        //search for the steamid on the server
        foreach ($matches[3] as $match) {
            if (!preg_match(STEAM_FORMAT, $match)) {
                $match = explode(':', $match);
                $match = steam2to3(rtrim($match[2], ']'));
            }
            if (substr($match, 8) == substr($check, 8)) {
                // gotcha!!! kick him!
                $gothim = true;
                $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_comms` SET sid = '" . $sid . "' WHERE authid = '" . $check . "' AND RemovedBy IS NULL;");
                $requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], "pages/admin.blockit.php"));
                $kick   = $r->sendCommand("sc_fw_block " . $type . " " . $length . " " . $match);
                $objResponse->addAssign("srv_$num", "innerHTML", "<font color='green' size='1'><b><u>Player Found & blocked!</u></b></font>");
                $objResponse->addScript("set_counter('-1');");
                return $objResponse;
            }
        }

        if (!$gothim) {
            $objResponse->addAssign("srv_$num", "innerHTML", "<font size='1'>Player not found.</font>");
            $objResponse->addScript('set_counter(1);');
            return $objResponse;
        }
    } else {
        $objResponse->addAssign("srv_$num", "innerHTML", "<font color='red' size='1'><i>Can't connect to server.</i></font>");
        $objResponse->addScript('set_counter(1);');
        return $objResponse;
    }
}
$servers = $GLOBALS['db']->Execute("SELECT ip, port, rcon FROM " . DB_PREFIX . "_servers WHERE enabled = 1 ORDER BY modid, sid;");
$theme->assign('total', $servers->RecordCount());
$serverlinks = array();
$num         = 0;
while (!$servers->EOF) {
    $info         = array();
    $info['num']  = $num;
    $info['ip']   = $servers->fields["ip"];
    $info['port'] = $servers->fields["port"];
    array_push($serverlinks, $info);
    $num++;
    $servers->MoveNext();
}
$theme->assign('servers', $serverlinks);
$theme->assign('xajax_functions', $xajax->printJavascript("../scripts", "xajax.js"));
$theme->assign('check', $_GET["check"]); // steamid or ip address
$theme->assign('type', $_GET['type']);
$theme->assign('length', $_GET['length']);

$theme->left_delimiter  = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_blockit.tpl');
$theme->left_delimiter  = "{";
$theme->right_delimiter = "}";
