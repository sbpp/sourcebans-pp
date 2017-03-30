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

global $userbank, $theme;

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
if ($userbank->GetAid() == -1) {
    echo "You shoudnt be here. looks like we messed up ><";
    die();
}

$groupsTabMenu = new CTabsMenu();
$groupsTabMenu->addMenuItem("View Permissions", 0);
$groupsTabMenu->addMenuItem("Change Password", 1);
$groupsTabMenu->addMenuItem("Server Password", 2);
$groupsTabMenu->addMenuItem("Change Email", 3);
$groupsTabMenu->outputMenu();

$res      = $GLOBALS['db']->Execute("SELECT `srv_password`, `email` FROM `" . DB_PREFIX . "_admins` WHERE `aid` = '" . $userbank->GetAid() . "'");
$srvpwset = (!empty($res->fields['srv_password']) ? true : false);

$theme->assign('srvpwset', $srvpwset);
$theme->assign('email', $res->fields['email']);
$theme->assign('user_aid', $userbank->GetAid());
$theme->assign('web_permissions', BitToString($userbank->GetProperty("extraflags")));
$theme->assign('server_permissions', SmFlagsToSb($userbank->GetProperty("srv_flags")));
$theme->assign('min_pass_len', MIN_PASS_LENGTH);

$theme->left_delimiter  = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_youraccount.tpl');
$theme->left_delimiter  = "{";
$theme->right_delimiter = "}";
