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
global $theme;

$first = true;
$i     = 0;
$tabs  = array();
foreach ($var as $v) {
    if (empty($v['title'])) {
        $i++;
        continue;
    }
    if ($first) {
        $GLOBALS['enable'] = $v['id'];
    }
    if (isset($v['external']) && $v['external'] == true) {
        $lnk   = $v['url'];
        $click = "";
    } else {
        $lnk   = "#^" . $v['id'];
        $click = "SwapPane(" . $v['id'] . ");";
    }
    if ($i == 0) {
        $class = "active";
    } else {
        $class = "";
    }
    $itm        = array();
    $itm['tab'] = "<li id='tab-" . $v['id'] . "' class='" . $class . "'><a href='$lnk' id='admin_tab_" . $v['id'] . "' onclick=\"$click\"> " . $v['title'] . "</a></li>";
    array_push($tabs, $itm);
    $i++;
    $first = false;
}

if ($_GET['p'] == "account") {
    $theme->assign('pane_image', '<img src="themes/' . SB_THEME . '/images/admin/your_account.png"> </div>');
} else {
    $theme->assign('pane_image', '<img src="themes/' . SB_THEME . '/images/admin/' . $_GET['c'] . '.png"> </div>');
}
$theme->assign('tabs', $tabs);

$theme->display('item_admin_tabs.tpl');
