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
if (isset($GLOBALS['IN_ADMIN'])) {
    define('CUR_AID', $userbank->GetAid());
}

if (isset($_POST['action']) && $_POST['action'] == "importBans") {
    $bannedcfg = file($_FILES["importFile"]["tmp_name"]);
    $bancnt    = 0;

    foreach ($bannedcfg as $ban) {
        $line = explode(" ", trim($ban));

        if ($line[1] == "0") {
            if (validate_ip($line[2])) {// if its an banned_ip.cfg
                $check = $GLOBALS['db']->Execute("SELECT ip FROM `" . DB_PREFIX . "_bans` WHERE ip = ? AND RemoveType IS NULL", array(
                    $line[2]
                ));

                if ($check->RecordCount() == 0) {
                    $bancnt++;
                    $pre = $GLOBALS['db']->Prepare("INSERT INTO " . DB_PREFIX . "_bans(created,authid,ip,name,ends,length,reason,aid,adminIp,type) VALUES
										(UNIX_TIMESTAMP(),?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?,?)");
                    $GLOBALS['db']->Execute($pre, array(
                        "",
                        $line[2],
                        "Imported Ban",
                        0,
                        0,
                        "banned_ip.cfg import",
                        $_COOKIE['aid'],
                        $_SERVER['REMOTE_ADDR'],
                        1
                    ));
                }
            } else { // if its an banned_user.cfg
                if (!validate_steam($line[2])) {
                    if (($accountId = getAccountId($line[2])) !== -1) {
                        $steam = renderSteam2($accountId, 0);
                    } else {
                        continue;
                    }
                } else {
                    $steam = $line[2];
                }
                $check = $GLOBALS['db']->Execute("SELECT authid FROM `" . DB_PREFIX . "_bans` WHERE authid = ? AND RemoveType IS NULL", array(
                    $steam
                ));
                if ($check->RecordCount() == 0) {
                    if (!isset($_POST['friendsname']) || $_POST['friendsname'] != "on" || ($pname = GetCommunityName($steam)) == "") {
                        $pname = "Imported Ban";
                    }

                    $bancnt++;
                    $pre = $GLOBALS['db']->Prepare("INSERT INTO " . DB_PREFIX . "_bans(created,authid,ip,name,ends,length,reason,aid,adminIp,type) VALUES
										(UNIX_TIMESTAMP(),?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?,?)");
                    $GLOBALS['db']->Execute($pre, array(
                        $steam,
                        "",
                        $pname,
                        0,
                        0,
                        "banned_user.cfg import",
                        $_COOKIE['aid'],
                        $_SERVER['REMOTE_ADDR'],
                        0
                    ));
                }
            }
        }
    }
    if ($bancnt > 0) {
        $log = new CSystemLog("m", "Bans imported", "$bancnt Ban(s) imported");
    }

    echo "<script>ShowBox('Bans Import', '$bancnt ban" . ($bancnt != 1 ? "s have" : " has") . " been imported and posted.', 'green', '');</script>";
}

if (isset($_GET["rebanid"])) {
    echo '<script type="text/javascript">xajax_PrepareReban("' . (int) $_GET["rebanid"] . '");</script>';
}
if ((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
    echo "<script type=\"text/javascript\">ShowBox('Loading..','<b>Loading...</b><br><i>Please Wait!</i>', 'blue', '', true);document.getElementById('dialog-control').setStyle('display', 'none');xajax_PasteBan('" . (int) $_GET['sid'] . "', '" . addslashes($_GET['pName']) . "');</script>";
}

echo '<div id="admin-page-content">';
// Add Ban
echo '<div id="0" style="display:none;">';
$theme->assign('permission_addban', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN));
$theme->assign('customreason', ((isset($GLOBALS['config']['bans.customreasons']) && $GLOBALS['config']['bans.customreasons'] != "") ? unserialize($GLOBALS['config']['bans.customreasons']) : false));
$theme->display('page_admin_bans_add.tpl');
echo '</div>';

// Protests
echo '<div id="1" style="display:none;">';
echo '<div id="tabsWrapper" style="margin:0px;">
    <div id="tabs">
	<ul>
		<li id="utab-p0" class="active">
			<a href="index.php?p=admin&c=bans#^1~p0" id="admin_utab_p0" onclick="Swap2ndPane(0,\'p\');" class="tip" title="Show Protests :: Show current protests." target="_self">Current</a>
		</li>
		<li id="utab-p1" class="nonactive">
			<a href="index.php?p=admin&c=bans#^1~p1" id="admin_utab_p1" onclick="Swap2ndPane(1,\'p\');" class="tip" title="Show Archive :: Show the protest archive." target="_self">Archive</a>
		</li>
	</ul>
	</div>
	</div>';
// current protests
echo '<div id="p0">';
$ItemsPerPage = SB_BANS_PER_PAGE;
$page         = 1;
if (isset($_GET['ppage']) && $_GET['ppage'] > 0) {
    $page = intval($_GET['ppage']);
}
$protests       = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_protests` WHERE archiv = '0' ORDER BY pid DESC LIMIT " . intval(($page - 1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
$protests_count = $GLOBALS['db']->GetRow("SELECT count(pid) AS count FROM `" . DB_PREFIX . "_protests` WHERE archiv = '0' ORDER BY pid DESC");
$page_count     = $protests_count['count'];
$PageStart      = intval(($page - 1) * $ItemsPerPage);
$PageEnd        = intval($PageStart + $ItemsPerPage);
if ($PageEnd > $page_count) {
    $PageEnd = $page_count;
}
if ($page > 1) {
    $prev = CreateLinkR('<img border="0" alt="prev" src="images/left.png" style="vertical-align:middle;" /> prev', "index.php?p=admin&c=bans&ppage=" . ($page - 1) . "#^1");
} else {
    $prev = "";
}
if ($PageEnd < $page_count) {
    $next = CreateLinkR('next <img border="0" alt="prev" src="images/right.png" style="vertical-align:middle;" />', "index.php?p=admin&c=bans&ppage=" . ($page + 1) . "#^1");
} else {
    $next = "";
}

$page_nav = 'displaying&nbsp;' . $PageStart . '&nbsp;-&nbsp;' . $PageEnd . '&nbsp;of&nbsp;' . $page_count . '&nbsp;results';

if (strlen($prev) > 0) {
    $page_nav .= ' | <b>' . $prev . '</b>';
}
if (strlen($next) > 0) {
    $page_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($page_count / $ItemsPerPage);
if ($pages > 1) {
    $page_nav .= '&nbsp;<select onchange="changePage(this,\'P\',\'\',\'\');">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i == $page) {
            $page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
            continue;
        }
        $page_nav .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $page_nav .= '</select>';
}

$delete       = array();
$protest_list = array();
foreach ($protests as $prot) {
    $prot['reason'] = wordwrap(htmlspecialchars($prot['reason']), 55, "<br />\n", true);
    $protestb       = $GLOBALS['db']->GetRow("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, email,ad.user, CONCAT(se.ip,':',se.port), se.sid
							    				FROM " . DB_PREFIX . "_bans AS ba
							    				LEFT JOIN " . DB_PREFIX . "_admins AS ad ON ba.aid = ad.aid
							    				LEFT JOIN " . DB_PREFIX . "_servers AS se ON se.sid = ba.sid
							    				WHERE bid = \"" . (int) $prot['bid'] . "\"");
    if (!$protestb) {
        $delete[] = $prot['bid'];
        continue;
    }

    $prot['name']   = $protestb[3];
    $prot['authid'] = $protestb[2];
    $prot['ip']     = $protestb['ip'];

    $prot['date'] = date($dateformat, $protestb['created']);
    if ($protestb['ends'] == 'never') {
        $prot['ends'] = 'never';
    } else {
        $prot['ends'] = date($dateformat, $protestb['ends']);
    }
    $prot['ban_reason'] = htmlspecialchars($protestb['reason']);

    $prot['admin'] = $protestb[11];
    if (!$protestb[12]) {
        $prot['server'] = "Web Ban";
    } else {
        $prot['server'] = $protestb[12];
    }
    $prot['datesubmitted'] = date($dateformat, $prot['datesubmitted']);
    //COMMENT STUFF
    //-----------------------------------
    $view_comments         = true;
    $commentres            = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
												(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.aid) AS comname,
												(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.editaid) AS editname
												FROM `" . DB_PREFIX . "_comments` AS C
												WHERE type = 'P' AND bid = '" . (int) $prot['pid'] . "' ORDER BY added desc");

    if ($commentres->RecordCount() > 0) {
        $comment = array();
        $morecom = 0;
        while (!$commentres->EOF) {
            $cdata            = array();
            $cdata['morecom'] = ($morecom == 1 ? true : false);
            if ($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                $cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.png\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P&cid=' . $commentres->fields['cid'], 'Edit Comment');
                if ($userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.png' border='0' alt='' style='vertical-align:middle' /> :: Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $commentres->fields['cid'] . ",'P',-1);\"><img src='images/delete.png' border='0' alt='' style='vertical-align:middle' /></a>";
                }
            } else {
                $cdata['editcomlink'] = "";
                $cdata['delcomlink']  = "";
            }

            $cdata['comname']    = $commentres->fields['comname'];
            $cdata['added']      = date($dateformat, $commentres->fields['added']);
            $cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
            $cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

            if (!empty($commentres->fields['edittime'])) {
                $cdata['edittime'] = date($dateformat, $commentres->fields['edittime']);
                $cdata['editname'] = $commentres->fields['editname'];
            } else {
                $cdata['edittime'] = "";
                $cdata['editname'] = "";
            }

            $morecom = 1;
            array_push($comment, $cdata);
            $commentres->MoveNext();
        }
    } else {
        $comment = "None";
    }

    $prot['commentdata']    = $comment;
    $prot['protaddcomment'] = CreateLinkR('<img src="images/details.png" border="0" alt="" style="vertical-align:middle" /> Add Comment', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P');
    //-----------------------------------------

    array_push($protest_list, $prot);
}
if (count($delete) > 0) { //time for protest cleanup
    $ids = rtrim(implode(',', $delete), ',');
    $cnt = count($delete);
    $GLOBALS['db']->Execute("UPDATE " . DB_PREFIX . "_protests SET archiv = '2' WHERE bid IN($ids) limit $cnt");
}

$theme->assign('permission_protests', $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_PROTESTS));
$theme->assign('permission_editban', $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS));
$theme->assign('protest_nav', $page_nav);
$theme->assign('protest_list', $protest_list);
$theme->assign('protest_count', $page_count - (isset($cnt) ? $cnt : 0));
$theme->display('page_admin_bans_protests.tpl');
echo '</div>';

$protestsarchiv = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_protests` WHERE archiv > '0' ORDER BY pid DESC");
// archived protests
echo '<div id="p1" style="display:none;">';

$ItemsPerPage = SB_BANS_PER_PAGE;
$page         = 1;
if (isset($_GET['papage']) && $_GET['papage'] > 0) {
    $page = intval($_GET['papage']);
}
$protestsarchiv       = $GLOBALS['db']->GetAll("SELECT p.*, (SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = p.archivedby) AS archivedby FROM `" . DB_PREFIX . "_protests` p WHERE archiv > '0' ORDER BY pid DESC LIMIT " . intval(($page - 1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
$protestsarchiv_count = $GLOBALS['db']->GetRow("SELECT count(pid) AS count FROM `" . DB_PREFIX . "_protests` WHERE archiv > '0' ORDER BY pid DESC");
$page_count           = $protestsarchiv_count['count'];
$PageStart            = intval(($page - 1) * $ItemsPerPage);
$PageEnd              = intval($PageStart + $ItemsPerPage);
if ($PageEnd > $page_count) {
    $PageEnd = $page_count;
}
if ($page > 1) {
    $prev = CreateLinkR('<img border="0" alt="prev" src="images/left.png" style="vertical-align:middle;" /> prev', "index.php?p=admin&c=bans&papage=" . ($page - 1) . "#^1~p1");
} else {
    $prev = "";
}
if ($PageEnd < $page_count) {
    $next = CreateLinkR('next <img border="0" alt="prev" src="images/right.png" style="vertical-align:middle;" />', "index.php?p=admin&c=bans&papage=" . ($page + 1) . "#^1~p1");
} else {
    $next = "";
}

$page_nav = 'displaying&nbsp;' . $PageStart . '&nbsp;-&nbsp;' . $PageEnd . '&nbsp;of&nbsp;' . $page_count . '&nbsp;results';

if (strlen($prev) > 0) {
    $page_nav .= ' | <b>' . $prev . '</b>';
}
if (strlen($next) > 0) {
    $page_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($page_count / $ItemsPerPage);
if ($pages > 1) {
    $page_nav .= '&nbsp;<select onchange="changePage(this,\'PA\',\'\',\'\');">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i == $page) {
            $page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
            continue;
        }
        $page_nav .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $page_nav .= '</select>';
}

$delete              = array();
$protest_list_archiv = array();
foreach ($protestsarchiv as $prot) {
    $prot['reason'] = wordwrap(htmlspecialchars($prot['reason']), 55, "<br />\n", true);

    if ($prot['archiv'] != "2") {
        $protestb = $GLOBALS['db']->GetRow("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, email,ad.user, CONCAT(se.ip,':',se.port), se.sid
								    				FROM " . DB_PREFIX . "_bans AS ba
								    				LEFT JOIN " . DB_PREFIX . "_admins AS ad ON ba.aid = ad.aid
								    				LEFT JOIN " . DB_PREFIX . "_servers AS se ON se.sid = ba.sid
								    				WHERE bid = \"" . (int) $prot['bid'] . "\"");
        if (!$protestb) {
            $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_protests` SET archiv = '2' WHERE pid = '" . (int) $prot['pid'] . "';");
            $prot['archiv']  = "2";
            $prot['archive'] = "ban has been deleted.";
        } else {
            $prot['name']   = $protestb[3];
            $prot['authid'] = $protestb[2];
            $prot['ip']     = $protestb['ip'];

            $prot['date'] = date($dateformat, $protestb['created']);
            if ($protestb['ends'] == 'never') {
                $prot['ends'] = 'never';
            } else {
                $prot['ends'] = date($dateformat, $protestb['ends']);
            }
            $prot['ban_reason'] = htmlspecialchars($protestb['reason']);
            $prot['admin']      = $protestb[11];
            if (!$protestb[12]) {
                $prot['server'] = "Web Ban";
            } else {
                $prot['server'] = $protestb[12];
            }
            if ($prot['archiv'] == "1") {
                $prot['archive'] = "protest has been archived.";
            } elseif ($prot['archiv'] == "3") {
                $prot['archive'] = "ban has expired.";
            } elseif ($prot['archiv'] == "4") {
                $prot['archive'] = "ban has been unbanned.";
            }
        }
    } else {
        $prot['archive'] = "ban has been deleted.";
    }
    $prot['datesubmitted'] = date($dateformat, $prot['datesubmitted']);
    //COMMENT STUFF
    //-----------------------------------
    $view_comments         = true;
    $commentres            = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
												(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.aid) AS comname,
												(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.editaid) AS editname
												FROM `" . DB_PREFIX . "_comments` AS C
												WHERE type = 'P' AND bid = '" . (int) $prot['pid'] . "' ORDER BY added desc");

    if ($commentres->RecordCount() > 0) {
        $comment = array();
        $morecom = 0;
        while (!$commentres->EOF) {
            $cdata            = array();
            $cdata['morecom'] = ($morecom == 1 ? true : false);
            if ($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                $cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.png\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P&cid=' . $commentres->fields['cid'], 'Edit Comment');
                if ($userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.png' border='0' alt='' style='vertical-align:middle' /> :: Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $commentres->fields['cid'] . ",'P',-1);\"><img src='images/delete.png' border='0' alt='' style='vertical-align:middle' /></a>";
                }
            } else {
                $cdata['editcomlink'] = "";
                $cdata['delcomlink']  = "";
            }

            $cdata['comname']    = $commentres->fields['comname'];
            $cdata['added']      = date($dateformat, $commentres->fields['added']);
            $cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
            $cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

            if (!empty($commentres->fields['edittime'])) {
                $cdata['edittime'] = date($dateformat, $commentres->fields['edittime']);
                $cdata['editname'] = $commentres->fields['editname'];
            } else {
                $cdata['edittime'] = "";
                $cdata['editname'] = "";
            }

            $morecom = 1;
            array_push($comment, $cdata);
            $commentres->MoveNext();
        }
    } else {
        $comment = "None";
    }

    $prot['commentdata']    = $comment;
    $prot['protaddcomment'] = CreateLinkR('<img src="images/details.png" border="0" alt="" style="vertical-align:middle" /> Add Comment', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P');
    //-----------------------------------------

    array_push($protest_list_archiv, $prot);
}

$theme->assign('permission_protests', $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_PROTESTS));
$theme->assign('permission_editban', $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS));
$theme->assign('aprotest_nav', $page_nav);
$theme->assign('protest_list_archiv', $protest_list_archiv);
$theme->assign('protest_count_archiv', $page_count);
$theme->display('page_admin_bans_protests_archiv.tpl');
echo '</div>';
echo '</div>';



//Submissions page
echo '<div id="2" style="display:none;">';
echo '<div id="tabsWrapper" style="margin:0px;">
    <div id="tabs">
	<ul>
		<li id="utab-s0" class="active">
			<a href="index.php?p=admin&c=bans#^2~s0" id="admin_utab_s0" onclick="Swap2ndPane(0,\'s\');" class="tip" title="Show Submissions :: Show current submissions." target="_self">Current</a>
		</li>
		<li id="utab-s1" class="nonactive">
			<a href="index.php?p=admin&c=bans#^2~s1" id="admin_utab_s1" onclick="Swap2ndPane(1,\'s\');" class="tip" title="Show Archive :: Show the submission archive." target="_self">Archive</a>
		</li>
	</ul>
	</div>
	</div>';
echo '<div id="s0">'; // current submissions
$ItemsPerPage = SB_BANS_PER_PAGE;
$page         = 1;
if (isset($_GET['spage']) && $_GET['spage'] > 0) {
    $page = intval($_GET['spage']);
}
$submissions       = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0' ORDER BY subid DESC LIMIT " . intval(($page - 1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
$submissions_count = $GLOBALS['db']->GetRow("SELECT count(subid) AS count FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0' ORDER BY subid DESC");
$page_count        = $submissions_count['count'];
$PageStart         = intval(($page - 1) * $ItemsPerPage);
$PageEnd           = intval($PageStart + $ItemsPerPage);
if ($PageEnd > $page_count) {
    $PageEnd = $page_count;
}
if ($page > 1) {
    $prev = CreateLinkR('<img border="0" alt="prev" src="images/left.png" style="vertical-align:middle;" /> prev', "index.php?p=admin&c=bans&spage=" . ($page - 1) . "#^2");
} else {
    $prev = "";
}
if ($PageEnd < $page_count) {
    $next = CreateLinkR('next <img border="0" alt="prev" src="images/right.png" style="vertical-align:middle;" />', "index.php?p=admin&c=bans&spage=" . ($page + 1) . "#^2");
} else {
    $next = "";
}

$page_nav = 'displaying&nbsp;' . $PageStart . '&nbsp;-&nbsp;' . $PageEnd . '&nbsp;of&nbsp;' . $page_count . '&nbsp;results';

if (strlen($prev) > 0) {
    $page_nav .= ' | <b>' . $prev . '</b>';
}
if (strlen($next) > 0) {
    $page_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($page_count / $ItemsPerPage);
if ($pages > 1) {
    $page_nav .= '&nbsp;<select onchange="changePage(this,\'S\',\'\',\'\');">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i == $page) {
            $page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
            continue;
        }
        $page_nav .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $page_nav .= '</select>';
}

$theme->assign('permissions_submissions', $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS));
$theme->assign('permissions_editsub', $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS));
$theme->assign('submission_count', $page_count);
$submission_list = array();
foreach ($submissions as $sub) {
    $sub['name']   = wordwrap(htmlspecialchars($sub['name']), 55, "<br />", true);
    $sub['reason'] = wordwrap(htmlspecialchars($sub['reason']), 55, "<br />", true);

    $dem = $GLOBALS['db']->GetRow("SELECT filename FROM " . DB_PREFIX . "_demos
												WHERE demtype = \"S\" AND demid = " . (int) $sub['subid']);

    if ($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename'])) {
        $sub['demo'] = "<a href=\"getdemo.php?id=" . $sub['subid'] . "&type=S\"><img src=\"images/demo.png\" border=\"0\" style=\"vertical-align:middle\" /> Get Demo</a>";
    } else {
        $sub['demo'] = "<a href=\"#\"><img src=\"images/demo.png\" border=\"0\" style=\"vertical-align:middle\" /> No Demo</a>";
    }

    $sub['submitted'] = date($dateformat, $sub['submitted']);

    $mod        = $GLOBALS['db']->GetRow("SELECT m.name FROM `" . DB_PREFIX . "_submissions` AS s
												LEFT JOIN `" . DB_PREFIX . "_mods` AS m ON m.mid = s.ModID
												WHERE s.subid = " . (int) $sub['subid']);
    $sub['mod'] = $mod['name'];

    if (empty($sub['server'])) {
        $sub['hostname'] = '<i><font color="#677882">Other server...</font></i>';
    } else {
        $sub['hostname'] = "";
    }
    //COMMENT STUFF
    //-----------------------------------
    $view_comments = true;
    $commentres    = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
														(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.aid) AS comname,
														(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.editaid) AS editname
														FROM `" . DB_PREFIX . "_comments` AS C
														WHERE type = 'S' AND bid = '" . (int) $sub['subid'] . "' ORDER BY added desc");

    if ($commentres->RecordCount() > 0) {
        $comment = array();
        $morecom = 0;
        while (!$commentres->EOF) {
            $cdata            = array();
            $cdata['morecom'] = ($morecom == 1 ? true : false);
            if ($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                $cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.png\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S&cid=' . $commentres->fields['cid'], 'Edit Comment');
                if ($userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.png' border='0' alt='' style='vertical-align:middle' /> :: Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $commentres->fields['cid'] . ",'S',-1);\"><img src='images/delete.png' border='0' alt='' style='vertical-align:middle' /></a>";
                }
            } else {
                $cdata['editcomlink'] = "";
                $cdata['delcomlink']  = "";
            }

            $cdata['comname']    = $commentres->fields['comname'];
            $cdata['added']      = date($dateformat, $commentres->fields['added']);
            $cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
            $cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

            if (!empty($commentres->fields['edittime'])) {
                $cdata['edittime'] = date($dateformat, $commentres->fields['edittime']);
                $cdata['editname'] = $commentres->fields['editname'];
            } else {
                $cdata['edittime'] = "";
                $cdata['editname'] = "";
            }

            $morecom = 1;
            array_push($comment, $cdata);
            $commentres->MoveNext();
        }
    } else {
        $comment = "None";
    }

    $sub['commentdata']   = $comment;
    $sub['subaddcomment'] = CreateLinkR('<img src="images/details.png" border="0" alt="" style="vertical-align:middle" /> Add Comment', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S');
    //----------------------------------------

    array_push($submission_list, $sub);
}
$theme->assign('submission_nav', $page_nav);
$theme->assign('submission_list', $submission_list);
$theme->display('page_admin_bans_submissions.tpl');
echo '</div>';

// submission archiv
echo '<div id="s1" style="display:none;">';
$ItemsPerPage = SB_BANS_PER_PAGE;
$page         = 1;
if (isset($_GET['sapage']) && $_GET['sapage'] > 0) {
    $page = intval($_GET['sapage']);
}
$submissionsarchiv       = $GLOBALS['db']->GetAll("SELECT s.*, (SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = s.archivedby) AS archivedby FROM `" . DB_PREFIX . "_submissions` s WHERE archiv > '0' ORDER BY subid DESC LIMIT " . intval(($page - 1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
$submissionsarchiv_count = $GLOBALS['db']->GetRow("SELECT count(subid) AS count FROM `" . DB_PREFIX . "_submissions` WHERE archiv > '0' ORDER BY subid DESC");
$page_count              = $submissionsarchiv_count['count'];
$PageStart               = intval(($page - 1) * $ItemsPerPage);
$PageEnd                 = intval($PageStart + $ItemsPerPage);
if ($PageEnd > $page_count) {
    $PageEnd = $page_count;
}
if ($page > 1) {
    $prev = CreateLinkR('<img border="0" alt="prev" src="images/left.png" style="vertical-align:middle;" /> prev', "index.php?p=admin&c=bans&sapage=" . ($page - 1) . "#^2~s1");
} else {
    $prev = "";
}
if ($PageEnd < $page_count) {
    $next = CreateLinkR('next <img border="0" alt="prev" src="images/right.png" style="vertical-align:middle;" />', "index.php?p=admin&c=bans&sapage=" . ($page + 1) . "#^2~s1");
} else {
    $next = "";
}

$page_nav = 'displaying&nbsp;' . $PageStart . '&nbsp;-&nbsp;' . $PageEnd . '&nbsp;of&nbsp;' . $page_count . '&nbsp;results';

if (strlen($prev) > 0) {
    $page_nav .= ' | <b>' . $prev . '</b>';
}
if (strlen($next) > 0) {
    $page_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($page_count / $ItemsPerPage);
if ($pages > 1) {
    $page_nav .= '&nbsp;<select onchange="changePage(this,\'SA\',\'\',\'\');">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i == $page) {
            $page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
            continue;
        }
        $page_nav .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $page_nav .= '</select>';
}

$theme->assign('permissions_submissions', $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS));
$theme->assign('permissions_editsub', $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS));
$theme->assign('submission_count_archiv', $page_count);
$submission_list_archiv = array();
foreach ($submissionsarchiv as $sub) {
    $sub['name']   = wordwrap(htmlspecialchars($sub['name']), 55, "<br />", true);
    $sub['reason'] = wordwrap(htmlspecialchars($sub['reason']), 55, "<br />", true);

    $dem = $GLOBALS['db']->GetRow("SELECT filename FROM " . DB_PREFIX . "_demos
												WHERE demtype = \"S\" AND demid = " . (int) $sub['subid']);

    if ($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename'])) {
        $sub['demo'] = "<a href=\"getdemo.php?id=" . $sub['subid'] . "&type=S\"><img src=\"images/demo.png\" border=\"0\" style=\"vertical-align:middle\" /> Get Demo</a>";
    } else {
        $sub['demo'] = "<a href=\"#\"><img src=\"images/demo.png\" border=\"0\" style=\"vertical-align:middle\" /> No Demo</a>";
    }

    $sub['submitted'] = date($dateformat, $sub['submitted']);

    $mod        = $GLOBALS['db']->GetRow("SELECT m.name FROM `" . DB_PREFIX . "_submissions` AS s
												LEFT JOIN `" . DB_PREFIX . "_mods` AS m ON m.mid = s.ModID
												WHERE s.subid = " . (int) $sub['subid']);
    $sub['mod'] = $mod['name'];
    if (empty($sub['server'])) {
        $sub['hostname'] = '<i><font color="#677882">Other server...</font></i>';
    } else {
        $sub['hostname'] = "";
    }
    if ($sub['archiv'] == "3") {
        $sub['archive'] = "player has been banned.";
    } elseif ($sub['archiv'] == "2") {
        $sub['archive'] = "submission has been accepted.";
    } elseif ($sub['archiv'] == "1") {
        $sub['archive'] = "submission has been archived.";
    }
    //COMMENT STUFF
    //-----------------------------------
    $view_comments = true;
    $commentres    = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
														(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.aid) AS comname,
														(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.editaid) AS editname
														FROM `" . DB_PREFIX . "_comments` AS C
														WHERE type = 'S' AND bid = '" . (int) $sub['subid'] . "' ORDER BY added desc");

    if ($commentres->RecordCount() > 0) {
        $comment = array();
        $morecom = 0;
        while (!$commentres->EOF) {
            $cdata            = array();
            $cdata['morecom'] = ($morecom == 1 ? true : false);
            if ($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                $cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.png\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S&cid=' . $commentres->fields['cid'], 'Edit Comment');
                if ($userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.png' border='0' alt='' style='vertical-align:middle' /> :: Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $commentres->fields['cid'] . ",'S',-1);\"><img src='images/delete.png' border='0' alt='' style='vertical-align:middle' /></a>";
                }
            } else {
                $cdata['editcomlink'] = "";
                $cdata['delcomlink']  = "";
            }

            $cdata['comname']    = $commentres->fields['comname'];
            $cdata['added']      = date($dateformat, $commentres->fields['added']);
            $cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
            $cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

            if (!empty($commentres->fields['edittime'])) {
                $cdata['edittime'] = date($dateformat, $commentres->fields['edittime']);
                $cdata['editname'] = $commentres->fields['editname'];
            } else {
                $cdata['edittime'] = "";
                $cdata['editname'] = "";
            }

            $morecom = 1;
            array_push($comment, $cdata);
            $commentres->MoveNext();
        }
    } else {
        $comment = "None";
    }

    $sub['commentdata']   = $comment;
    $sub['subaddcomment'] = CreateLinkR('<img src="images/details.png" border="0" alt="" style="vertical-align:middle" /> Add Comment', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S');
    //----------------------------------------

    array_push($submission_list_archiv, $sub);
}
$theme->assign('asubmission_nav', $page_nav);
$theme->assign('submission_list_archiv', $submission_list_archiv);
$theme->display('page_admin_bans_submissions_archiv.tpl');
echo '</div>';
echo '</div>';

echo '<div id="3" style="display:none;">';
$theme->assign('permission_import', $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_IMPORT));
if (ini_get('safe_mode') == 1) {
    $requirements = false;
} else {
    $requirements = true;
}
$theme->assign('extreq', $requirements);
$theme->display('page_admin_bans_import.tpl');
echo '</div>';

echo '<div id="4" style="display:none;">';
$theme->assign('permission_addban', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN));
$theme->assign('groupbanning_enabled', $GLOBALS['config']['config.enablegroupbanning'] == 1 ? true : false);
if (isset($_GET['fid'])) {
    $theme->assign('list_steam_groups', $_GET['fid']);
} else {
    $theme->assign('list_steam_groups', false);
}
$theme->display('page_admin_bans_groups.tpl');
echo '</div>';
?>






<script type="text/javascript">
var did = 0;
var dname = "";
function demo(id, name)
{
    $('demo.msg').setHTML("Uploaded: <b>" + name);
    did = id;
    dname = name;
}

function changeReason(szListValue)
{
    $('dreason').style.display = (szListValue == "other" ? "block" : "none");
}


function ProcessBan()
{
    var err = 0;
    var reason = $('listReason')[$('listReason').selectedIndex].value;

    if (reason == "other") {
        reason = $('txtReason').value;
    }

    if (!$('nickname').value) {
        $('nick.msg').setHTML('You must enter the nickname of the person you are banning');
        $('nick.msg').setStyle('display', 'block');
        err++;
    } else {
        $('nick.msg').setHTML('');
        $('nick.msg').setStyle('display', 'none');
    }

    if ($('steam').value.length < 10 && !$('ip').value) {
        $('steam.msg').setHTML('You must enter a valid STEAM ID or Community ID');
        $('steam.msg').setStyle('display', 'block');
        err++;
    } else {
        $('steam.msg').setHTML('');
        $('steam.msg').setStyle('display', 'none');
    }

    if ($('ip').value.length < 7 && !$('steam').value) {
        $('ip.msg').setHTML('You must enter a valid IP address');
        $('ip.msg').setStyle('display', 'block');
        err++;
    } else {
        $('ip.msg').setHTML('');
        $('ip.msg').setStyle('display', 'none');
    }

    if (!reason) {
        $('reason.msg').setHTML('You must select or enter a reason for this ban.');
        $('reason.msg').setStyle('display', 'block');
        err++;
    } else {
        $('reason.msg').setHTML('');
        $('reason.msg').setStyle('display', 'none');
    }

    if (err) {
        return 0;
    }

    xajax_AddBan($('nickname').value,
                 $('type').value,
                 $('steam').value,
                 $('ip').value,
                 $('banlength').value,
                 did,
                 dname,
                 reason,
                 $('fromsub').value);
}
function ProcessGroupBan()
{
    if (!$('groupurl').value) {
        $('groupurl.msg').setHTML('You must enter the group link of the group you are banning');
        $('groupurl.msg').setStyle('display', 'block');
    } else {
        $('groupurl.msg').setHTML('');
        $('groupurl.msg').setStyle('display', 'none');
        xajax_GroupBan($('groupurl').value, "no", "no", $('groupreason').value, "");
    }
}
function CheckGroupBan()
{
    var last = 0;
    for (var i=0;$('chkb_' + i);i++) {
        if($('chkb_' + i).checked == true) {
            last = $('chkb_' + i).value;
        }
    }
    for (var i=0;$('chkb_' + i);i++) {
        if($('chkb_' + i).checked == true) {
            xajax_GroupBan($('chkb_' + i).value, "yes", "yes", $('groupreason').value, last);
        }
    }
}
</script>
</div>
