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

if (!$userbank->HasAccess(ADMIN_OWNER)) {
    echo "Access Denied!";
} else {
    
    $srv_cfg = '"Databases"
{
	"driver_default"		"mysql"
	
	"sourcebans"
	{
		"driver"			"mysql"
		"host"				"{server}"
		"database"			"{db}"
		"user"				"{user}"
		"pass"				"{pass}"
		//"timeout"			"0"
		"port"			"{port}"
	}
	
	"storage-local"
	{
		"driver"			"sqlite"
		"database"			"sourcemod-local"
	}
}
';
    $srv_cfg = str_replace("{server}", DB_HOST, $srv_cfg);
    $srv_cfg = str_replace("{user}", DB_USER, $srv_cfg);
    $srv_cfg = str_replace("{pass}", DB_PASS, $srv_cfg);
    $srv_cfg = str_replace("{db}", DB_NAME, $srv_cfg);
    $srv_cfg = str_replace("{prefix}", DB_PREFIX, $srv_cfg);
    $srv_cfg = str_replace("{port}", DB_PORT, $srv_cfg);
    
    if (strtolower(DB_HOST) == "localhost") {
        ShowBox("Local server warning", "You have said your MySQL server is running on the same box as the webserver, this is fine, but you may need to alter the following config to set the remote domain/ip of your MySQL server. Unless your gameserver is on the same box as your webserver.", "blue", "", true);
    }
    $theme->assign('conf', $srv_cfg);
?>
<div id="admin-page-content">
    <div id="0">
<?php
$theme->display('page_admin_servers_db.tpl');
?>
    </div>
</div>
<?php
}
