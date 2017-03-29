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

global $theme;
$srv_admins = $GLOBALS['db']->GetAll("SELECT authid, user
    FROM " . DB_PREFIX . "_admins_servers_groups AS asg
    LEFT JOIN " . DB_PREFIX . "_admins AS a ON a.aid = asg.admin_id
    WHERE (server_id = " . (int) $_GET['id'] . " OR srv_group_id = ANY
    (
            SELECT group_id
            FROM " . DB_PREFIX . "_servers_groups
            WHERE server_id = " . (int) $_GET['id'] . ")
    )
    GROUP BY aid, authid, srv_password, srv_group, srv_flags, user ");
$i = 0;
foreach ($srv_admins as $admin) {
    $admsteam[] = $admin['authid'];
}
if (sizeof($admsteam) > 0 && $serverdata = checkMultiplePlayers((int) $_GET['id'], $admsteam)) {
    $noproblem = true;
}
foreach ($srv_admins as $admin) {
    $admins[$i]['user']   = $admin['user'];
    $admins[$i]['authid'] = $admin['authid'];
    if (isset($noproblem) && isset($serverdata[$admin['authid']])) {
        $admins[$i]['ingame'] = true;
        $admins[$i]['iname']  = $serverdata[$admin['authid']]['name'];
        $admins[$i]['iip']    = $serverdata[$admin['authid']]['ip'];
        $admins[$i]['iping']  = $serverdata[$admin['authid']]['ping'];
        $admins[$i]['itime']  = $serverdata[$admin['authid']]['time'];
    } else {
        $admins[$i]['ingame'] = false;
    }
    $i++;
}
$theme->assign('admin_count', count($srv_admins));
$theme->assign('admin_list', $admins);
?>
<div id="admin-page-content">
    <div id="0" style="display:none;">
        <?php $theme->display('page_admin_servers_adminlist.tpl');?>
    </div>
</div>
