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
$counts = $GLOBALS['db']->GetRow("SELECT
								 (SELECT COUNT(bid) FROM `" . DB_PREFIX . "_banlog`) AS blocks,
								 (SELECT COUNT(bid) FROM `" . DB_PREFIX . "_bans`) AS bans,
								 (SELECT COUNT(aid) FROM `" . DB_PREFIX . "_admins` WHERE aid > 0) AS admins,
								 (SELECT COUNT(subid) FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0') AS subs,
								 (SELECT COUNT(subid) FROM `" . DB_PREFIX . "_submissions` WHERE archiv > 0) AS archiv_subs,
								 (SELECT COUNT(pid) FROM `" . DB_PREFIX . "_protests` WHERE archiv = '0') AS protests,
								 (SELECT COUNT(pid) FROM `" . DB_PREFIX . "_protests` WHERE archiv > 0) AS archiv_protests,
								 (SELECT COUNT(sid) FROM `" . DB_PREFIX . "_servers`) AS servers");

$demsi = getDirectorySize(SB_DEMOS);

$theme->assign('access_admins', $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_ADMINS | ADMIN_ADD_ADMINS | ADMIN_EDIT_ADMINS | ADMIN_DELETE_ADMINS));
$theme->assign('access_servers', $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_SERVERS | ADMIN_ADD_SERVER | ADMIN_EDIT_SERVERS | ADMIN_DELETE_SERVERS));
$theme->assign('access_bans', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN | ADMIN_EDIT_OWN_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_ALL_BANS | ADMIN_BAN_PROTESTS | ADMIN_BAN_SUBMISSIONS));
$theme->assign('access_groups', $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_GROUPS | ADMIN_ADD_GROUP | ADMIN_EDIT_GROUPS | ADMIN_DELETE_GROUPS));
$theme->assign('access_settings', $userbank->HasAccess(ADMIN_OWNER | ADMIN_WEB_SETTINGS));
$theme->assign('access_mods', $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_MODS | ADMIN_ADD_MODS | ADMIN_EDIT_MODS | ADMIN_DELETE_MODS));

$theme->assign('dev', SB_DEV);

$theme->assign('demosize', sizeFormat($demsi['size']));
$theme->assign('total_admins', $counts['admins']);
$theme->assign('total_bans', $counts['bans']);
$theme->assign('total_blocks', $counts['blocks']);
$theme->assign('total_servers', $counts['servers']);
$theme->assign('total_protests', $counts['protests']);
$theme->assign('archived_protests', $counts['archiv_protests']);
$theme->assign('total_submissions', $counts['subs']);
$theme->assign('archived_submissions', $counts['archiv_subs']);

$theme->display('page_admin.tpl');
