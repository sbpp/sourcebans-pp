<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

global $userbank, $theme;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

new AdminTabs([
    ['name' => 'Add a ban', 'permission' => ADMIN_OWNER|ADMIN_ADD_BAN],
    ['name' => 'Ban protests', 'permission' => ADMIN_OWNER|ADMIN_BAN_PROTESTS],
    ['name' => 'Ban submissions', 'permission' => ADMIN_OWNER|ADMIN_BAN_SUBMISSIONS],
    ['name' => 'Import bans', 'permission' => ADMIN_OWNER|ADMIN_BAN_IMPORT],
    ['name' => 'Group ban', 'permission' => ADMIN_OWNER|ADMIN_ADD_BAN, 'config' => Config::getBool('config.enablegroupbanning')]
], $userbank, $theme);

if (isset($_GET['mode']) && $_GET['mode'] == "delete") {
    echo "<script>ShowBox('Ban Deleted', 'The ban has been deleted from SourceBans', 'green', '', true);</script>";
} elseif (isset($_GET['mode']) && $_GET['mode']=="unban") {
    echo "<script>ShowBox('Player Unbanned', 'The Player has been unbanned from SourceBans', 'green', '', true);</script>";
}

if (isset($GLOBALS['IN_ADMIN'])) {
    define('CUR_AID', $userbank->GetAid());
}

if (isset($_POST['action']) && $_POST['action'] == "importBans") {
    $bannedcfg = file($_FILES["importFile"]["tmp_name"]);
    $bancnt    = 0;

    foreach ($bannedcfg as $ban) {
        $line = explode(" ", trim($ban));

        if ($line[0] === 'addip') {
            if (filter_var($line[2], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $GLOBALS['PDO']->query("SELECT ip FROM `:prefix_bans` WHERE ip = :ip AND RemoveType IS NULL");
                $GLOBALS['PDO']->bind(':ip', $line[2]);
                $check = $GLOBALS['PDO']->single();

                if (!$check) {
                    $bancnt++;

                    $GLOBALS['PDO']->query(
                        "INSERT INTO `:prefix_bans` (`created`, `authid`, `ip`, `name`, `ends`, `length`, `reason`, `aid`, `adminIp`, `type`)
                        VALUES (UNIX_TIMESTAMP(), '', :ip, 'Imported Ban', (UNIX_TIMESTAMP() + 0), 0, 'banned_ip.cfg import', :aid, :admip, 1)"
                    );
                    $GLOBALS['PDO']->bindMultiple([
                        ':ip' => $line[2],
                        ':aid' => $userbank->GetAid(),
                        ':admip' => $_SERVER['REMOTE_ADDR']
                    ]);
                    $GLOBALS['PDO']->execute();
                }
            }
        } elseif ($line[0] === 'banid') {
            $steam = \SteamID\SteamID::toSteam2($line[2]);

            $GLOBALS['PDO']->query("SELECT authid FROM `:prefix_bans` WHERE authid = :authid AND RemoveType IS NULL");
            $GLOBALS['PDO']->bind(':authid', $steam);
            $check = $GLOBALS['PDO']->single();

            if (!$check) {
                if (!isset($_POST['friendsname']) || $_POST['friendsname'] !== 'on' || ($name = GetCommunityName($steam)) === '') {
                    $name = "Imported Ban";
                }
                $bancnt++;
                $GLOBALS['PDO']->query(
                    "INSERT INTO `:prefix_bans` (`created`, `authid`, `ip`, `name`, `ends`, `length`, `reason`, `aid`, `adminIp`, `type`)
                    VALUES (UNIX_TIMESTAMP(), :authid, '', :name, (UNIX_TIMESTAMP() + 0), 0, 'banned_user.cfg import', :aid, :ip, 0)"
                );
                $GLOBALS['PDO']->bindMultiple([
                    ':authid' => $steam,
                    ':name' => $name,
                    ':aid' => $userbank->GetAid(),
                    ':ip' => $_SERVER['REMOTE_ADDR']
                ]);
                $GLOBALS['PDO']->execute();
            }
        }
    }
    if ($bancnt > 0) {
        Log::add("m", "Bans imported", "$bancnt Ban(s) imported");
    }

    echo "<script>ShowBox('Bans Import', '$bancnt ban" . ($bancnt != 1 ? "s have" : " has") . " been imported and posted.', 'green', '');</script>";
}

if (isset($_GET["rebanid"])) {
    echo '<script type="text/javascript">LoadPrepareReban("' . (int) $_GET["rebanid"] . '");</script>';
}
if ((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
    $pNameJs = json_encode((string) $_GET['pName'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<script type=\"text/javascript\">ShowBox('Loading..','<b>Loading...</b><br><i>Please Wait!</i>', 'blue', '', true);sb.hide('dialog-control');LoadPasteBan(" . (int) $_GET['sid'] . ", " . $pNameJs . ");</script>";
}

echo '<div id="admin-page-content">';
// Add Ban
echo '<div class="tabcontent" id="Add a ban">';
$customReason = Config::getBool('bans.customreasons')
    ? unserialize((string) Config::get('bans.customreasons'))
    : false;
/** @var false|list<string> $customReason */
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansAddView(
    permission_addban: $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN),
    customreason: $customReason,
));
echo '</div>';

// Protests
echo '<div class="tabcontent" id="Ban protests">';
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
$protests       = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_protests` WHERE archiv = '0' ORDER BY pid DESC LIMIT " . intval(($page - 1) * $ItemsPerPage) . "," . intval($ItemsPerPage))->resultset();
$protests_count = $GLOBALS['PDO']->query("SELECT count(pid) AS count FROM `:prefix_protests` WHERE archiv = '0' ORDER BY pid DESC")->single();
$page_count     = $protests_count['count'];
$PageStart      = intval(($page - 1) * $ItemsPerPage);
$PageEnd        = intval($PageStart + $ItemsPerPage);
if ($PageEnd > $page_count) {
    $PageEnd = $page_count;
}
if ($page > 1) {
    $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=bans&ppage=" . ($page - 1) . "#^1");
} else {
    $prev = "";
}
if ($PageEnd < $page_count) {
    $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=bans&ppage=" . ($page + 1) . "#^1");
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

$delete       = [];
$protest_list = [];
foreach ($protests as $prot) {
    $prot['reason'] = wordwrap(htmlspecialchars($prot['reason']), 55, "<br />\n", true);
    $GLOBALS['PDO']->query("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid AS ba_sid, email, ad.user, CONCAT(se.ip,':',se.port) AS server_addr, se.sid AS se_sid
							    				FROM `:prefix_bans` AS ba
							    				LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
							    				LEFT JOIN `:prefix_servers` AS se ON se.sid = ba.sid
							    				WHERE bid = :bid");
    $GLOBALS['PDO']->bind(':bid', (int) $prot['bid']);
    $protestb = $GLOBALS['PDO']->single();
    if (!$protestb) {
        $delete[] = $prot['bid'];
        continue;
    }

    $prot['name']   = $protestb['name'];
    $prot['authid'] = $protestb['authid'];
    $prot['ip']     = $protestb['ip'];

    $prot['date'] = Config::time($protestb['created']);
    if ($protestb['ends'] == 'never') {
        $prot['ends'] = 'never';
    } else {
        $prot['ends'] = Config::time($protestb['ends']);
    }
    $prot['ban_reason'] = htmlspecialchars($protestb['reason']);

    $prot['admin'] = $protestb['user'];
    if (!$protestb['server_addr']) {
        $prot['server'] = "Web Ban";
    } else {
        $prot['server'] = $protestb['server_addr'];
    }
    $prot['datesubmitted'] = Config::time($prot['datesubmitted']);
    //COMMENT STUFF
    //-----------------------------------
    $view_comments         = true;
    $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
												(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
												(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
												FROM `:prefix_comments` AS C
												WHERE type = 'P' AND bid = :bid ORDER BY added desc");
    $GLOBALS['PDO']->bind(':bid', (int) $prot['pid']);
    $commentres            = $GLOBALS['PDO']->resultset();

    if (count($commentres) > 0) {
        $comment = [];
        $morecom = 0;
        foreach ($commentres as $crow) {
            $cdata            = [];
            $cdata['morecom'] = ($morecom == 1 ? true : false);
            if ($crow['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                $cdata['editcomlink'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i>', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P&cid=' . $crow['cid'], 'Edit Comment');
                if ($userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $crow['cid'] . ",'P',-1);\"><i class='fas fa-trash fa-lg'></i></a>";
                }
            } else {
                $cdata['editcomlink'] = "";
                $cdata['delcomlink']  = "";
            }

            $cdata['comname']    = $crow['comname'];
            $cdata['added']      = Config::time($crow['added']);
            $commentText         = html_entity_decode($crow['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $commentText         = encodePreservingBr($commentText);
            $commentText         = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $commentText);
            $cdata['commenttxt'] = $commentText;

            if (!empty($crow['edittime'])) {
                $cdata['edittime'] = Config::time($crow['edittime']);
                $cdata['editname'] = $crow['editname'];
            } else {
                $cdata['edittime'] = "";
                $cdata['editname'] = "";
            }

            $morecom = 1;
            array_push($comment, $cdata);
        }
    } else {
        $comment = "None";
    }

    $prot['commentdata']    = $comment;
    $prot['protaddcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P');
    //-----------------------------------------

    array_push($protest_list, $prot);
}
if (count($delete) > 0) { //time for protest cleanup
    $cnt = count($delete);
    $placeholders = implode(',', array_fill(0, $cnt, '?'));
    $GLOBALS['PDO']->query("UPDATE `:prefix_protests` SET archiv = '2' WHERE bid IN($placeholders) LIMIT $cnt")->execute($delete);
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansProtestsView(
    permission_protests: $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_PROTESTS),
    permission_editban: $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS),
    protest_nav: $page_nav,
    protest_list: $protest_list,
    protest_count: (int) $page_count - (isset($cnt) ? $cnt : 0),
));
echo '</div>';

// archived protests
echo '<div id="p1" style="display:none;">';

$ItemsPerPage = SB_BANS_PER_PAGE;
$page         = 1;
if (isset($_GET['papage']) && $_GET['papage'] > 0) {
    $page = intval($_GET['papage']);
}
$protestsarchiv       = $GLOBALS['PDO']->query("SELECT p.*, (SELECT user FROM `:prefix_admins` WHERE aid = p.archivedby) AS archivedby FROM `:prefix_protests` p WHERE archiv > '0' ORDER BY pid DESC LIMIT " . intval(($page - 1) * $ItemsPerPage) . "," . intval($ItemsPerPage))->resultset();
$protestsarchiv_count = $GLOBALS['PDO']->query("SELECT count(pid) AS count FROM `:prefix_protests` WHERE archiv > '0' ORDER BY pid DESC")->single();
$page_count           = $protestsarchiv_count['count'];
$PageStart            = intval(($page - 1) * $ItemsPerPage);
$PageEnd              = intval($PageStart + $ItemsPerPage);
if ($PageEnd > $page_count) {
    $PageEnd = $page_count;
}
if ($page > 1) {
    $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=bans&papage=" . ($page - 1) . "#^1~p1");
} else {
    $prev = "";
}
if ($PageEnd < $page_count) {
    $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=bans&papage=" . ($page + 1) . "#^1~p1");
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

$delete              = [];
$protest_list_archiv = [];
foreach ($protestsarchiv as $prot) {
    $prot['reason'] = wordwrap(htmlspecialchars($prot['reason']), 55, "<br />\n", true);

    if ($prot['archiv'] != "2") {
        $GLOBALS['PDO']->query("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid AS ba_sid, email, ad.user, CONCAT(se.ip,':',se.port) AS server_addr, se.sid AS se_sid
								    				FROM `:prefix_bans` AS ba
								    				LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
								    				LEFT JOIN `:prefix_servers` AS se ON se.sid = ba.sid
								    				WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', (int) $prot['bid']);
        $protestb = $GLOBALS['PDO']->single();
        if (!$protestb) {
            $GLOBALS['PDO']->query("UPDATE `:prefix_protests` SET archiv = '2' WHERE pid = :pid");
            $GLOBALS['PDO']->bind(':pid', (int) $prot['pid']);
            $GLOBALS['PDO']->execute();
            $prot['archiv']  = "2";
            $prot['archive'] = "ban has been deleted.";
        } else {
            $prot['name']   = $protestb['name'];
            $prot['authid'] = $protestb['authid'];
            $prot['ip']     = $protestb['ip'];

            $prot['date'] = Config::time($protestb['created']);
            if ($protestb['ends'] == 'never') {
                $prot['ends'] = 'never';
            } else {
                $prot['ends'] = Config::time($protestb['ends']);
            }
            $prot['ban_reason'] = htmlspecialchars($protestb['reason']);
            $prot['admin']      = $protestb['user'];
            if (!$protestb['server_addr']) {
                $prot['server'] = "Web Ban";
            } else {
                $prot['server'] = $protestb['server_addr'];
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
    $prot['datesubmitted'] = Config::time($prot['datesubmitted']);
    //COMMENT STUFF
    //-----------------------------------
    $view_comments         = true;
    $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
												(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
												(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
												FROM `:prefix_comments` AS C
												WHERE type = 'P' AND bid = :bid ORDER BY added desc");
    $GLOBALS['PDO']->bind(':bid', (int) $prot['pid']);
    $commentres            = $GLOBALS['PDO']->resultset();

    if (count($commentres) > 0) {
        $comment = [];
        $morecom = 0;
        foreach ($commentres as $crow) {
            $cdata            = [];
            $cdata['morecom'] = ($morecom == 1 ? true : false);
            if ($crow['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                $cdata['editcomlink'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i>', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P&cid=' . $crow['cid'], 'Edit Comment');
                if ($userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $crow['cid'] . ",'P',-1);\"><i class='fas fa-trash fa-lg'></i></a>";
                }
            } else {
                $cdata['editcomlink'] = "";
                $cdata['delcomlink']  = "";
            }

            $cdata['comname']    = $crow['comname'];
            $cdata['added']      = Config::time($crow['added']);
            $commentText         = html_entity_decode($crow['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $commentText         = encodePreservingBr($commentText);
            $commentText         = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $commentText);
            $cdata['commenttxt'] = $commentText;

            if (!empty($crow['edittime'])) {
                $cdata['edittime'] = Config::time($crow['edittime']);
                $cdata['editname'] = $crow['editname'];
            } else {
                $cdata['edittime'] = "";
                $cdata['editname'] = "";
            }

            $morecom = 1;
            array_push($comment, $cdata);
        }
    } else {
        $comment = "None";
    }

    $prot['commentdata']    = $comment;
    $prot['protaddcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P');
    //-----------------------------------------

    array_push($protest_list_archiv, $prot);
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansProtestsArchivView(
    permission_protests: $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_PROTESTS),
    permission_editban: $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS),
    aprotest_nav: $page_nav,
    protest_list_archiv: $protest_list_archiv,
    protest_count_archiv: (int) $page_count,
));
echo '</div>';
echo '</div>';



//Submissions page
echo '<div class="tabcontent" id="Ban submissions">';
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
$submissions       = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_submissions` WHERE archiv = '0' ORDER BY subid DESC LIMIT " . intval(($page - 1) * $ItemsPerPage) . "," . intval($ItemsPerPage))->resultset();
$submissions_count = $GLOBALS['PDO']->query("SELECT count(subid) AS count FROM `:prefix_submissions` WHERE archiv = '0' ORDER BY subid DESC")->single();
$page_count        = $submissions_count['count'];
$PageStart         = intval(($page - 1) * $ItemsPerPage);
$PageEnd           = intval($PageStart + $ItemsPerPage);
if ($PageEnd > $page_count) {
    $PageEnd = $page_count;
}
if ($page > 1) {
    $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=bans&spage=" . ($page - 1) . "#^2");
} else {
    $prev = "";
}
if ($PageEnd < $page_count) {
    $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=bans&spage=" . ($page + 1) . "#^2");
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

$submission_list = [];
foreach ($submissions as $sub) {
    $sub['name']   = wordwrap(htmlspecialchars($sub['name']), 55, "<br />", true);
    $sub['reason'] = wordwrap(htmlspecialchars($sub['reason']), 55, "<br />", true);

    $GLOBALS['PDO']->query("SELECT filename FROM `:prefix_demos` WHERE demtype = 'S' AND demid = :subid");
    $GLOBALS['PDO']->bind(':subid', (int) $sub['subid']);
    $dem = $GLOBALS['PDO']->single();

    if ($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename'])) {
        $sub['demo'] = '<a href="getdemo.php?id=' . urlencode($sub['subid']) . '&type=S"><i class=\'fas fa-video fa-lg\'></i> Get Demo</a>';
    } else {
        $sub['demo'] = "<a href=\"#\"><i class='fas fa-video-slash fa-lg'></i> No Demo</a>";
    }

    $sub['submitted'] = Config::time($sub['submitted']);

    $GLOBALS['PDO']->query("SELECT m.name FROM `:prefix_submissions` AS s LEFT JOIN `:prefix_mods` AS m ON m.mid = s.ModID WHERE s.subid = :subid");
    $GLOBALS['PDO']->bind(':subid', (int) $sub['subid']);
    $mod        = $GLOBALS['PDO']->single();
    $sub['mod'] = $mod['name'];

    if (empty($sub['server'])) {
        $sub['hostname'] = '<i><font color="#677882">Other server...</font></i>';
    } else {
        $sub['hostname'] = "";
    }
    //COMMENT STUFF
    //-----------------------------------
    $view_comments = true;
    $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
														(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
														(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
														FROM `:prefix_comments` AS C
														WHERE type = 'S' AND bid = :bid ORDER BY added desc");
    $GLOBALS['PDO']->bind(':bid', (int) $sub['subid']);
    $commentres    = $GLOBALS['PDO']->resultset();

    if (count($commentres) > 0) {
        $comment = [];
        $morecom = 0;
        foreach ($commentres as $crow) {
            $cdata            = [];
            $cdata['morecom'] = ($morecom == 1 ? true : false);
            if ($crow['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                $cdata['editcomlink'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i>', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S&cid=' . $crow['cid'], 'Edit Comment');
                if ($userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $crow['cid'] . ",'S',-1);\"><i class='fas fa-trash fa-lg'></i></a>";
                }
            } else {
                $cdata['editcomlink'] = "";
                $cdata['delcomlink']  = "";
            }

            $cdata['comname']    = $crow['comname'];
            $cdata['added']      = Config::time($crow['added']);
            $commentText         = html_entity_decode($crow['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $commentText         = encodePreservingBr($commentText);
            // Parse links and wrap them in a <a href=""></a> tag to be easily clickable
            $commentText         = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $commentText);
            $cdata['commenttxt'] = $commentText;

            if (!empty($crow['edittime'])) {
                $cdata['edittime'] = Config::time($crow['edittime']);
                $cdata['editname'] = $crow['editname'];
            } else {
                $cdata['edittime'] = "";
                $cdata['editname'] = "";
            }

            $morecom = 1;
            array_push($comment, $cdata);
        }
    } else {
        $comment = "None";
    }

    $sub['commentdata']   = $comment;
    $sub['subaddcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S');
    //----------------------------------------

    array_push($submission_list, $sub);
}
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansSubmissionsView(
    permissions_submissions: $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS),
    permissions_editsub: $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS),
    submission_count: (int) $page_count,
    submission_nav: $page_nav,
    submission_list: $submission_list,
));
echo '</div>';

// submission archiv
echo '<div id="s1" style="display:none;">';
$ItemsPerPage = SB_BANS_PER_PAGE;
$page         = 1;
if (isset($_GET['sapage']) && $_GET['sapage'] > 0) {
    $page = intval($_GET['sapage']);
}
$submissionsarchiv       = $GLOBALS['PDO']->query("SELECT s.*, (SELECT user FROM `:prefix_admins` WHERE aid = s.archivedby) AS archivedby FROM `:prefix_submissions` s WHERE archiv > '0' ORDER BY subid DESC LIMIT " . intval(($page - 1) * $ItemsPerPage) . "," . intval($ItemsPerPage))->resultset();
$submissionsarchiv_count = $GLOBALS['PDO']->query("SELECT count(subid) AS count FROM `:prefix_submissions` WHERE archiv > '0' ORDER BY subid DESC")->single();
$page_count              = $submissionsarchiv_count['count'];
$PageStart               = intval(($page - 1) * $ItemsPerPage);
$PageEnd                 = intval($PageStart + $ItemsPerPage);
if ($PageEnd > $page_count) {
    $PageEnd = $page_count;
}
if ($page > 1) {
    $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=bans&sapage=" . ($page - 1) . "#^2~s1");
} else {
    $prev = "";
}
if ($PageEnd < $page_count) {
    $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=bans&sapage=" . ($page + 1) . "#^2~s1");
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

$submission_list_archiv = [];
foreach ($submissionsarchiv as $sub) {
    $sub['name']   = wordwrap(htmlspecialchars($sub['name']), 55, "<br />", true);
    $sub['reason'] = wordwrap(htmlspecialchars($sub['reason']), 55, "<br />", true);

    $GLOBALS['PDO']->query("SELECT filename FROM `:prefix_demos` WHERE demtype = 'S' AND demid = :subid");
    $GLOBALS['PDO']->bind(':subid', (int) $sub['subid']);
    $dem = $GLOBALS['PDO']->single();

    if ($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename'])) {
        $sub['demo'] = '<a href="getdemo.php?id=' . urlencode($sub['subid']) . '&type=S"><i class=\'fas fa-video fa-lg\'></i> Get Demo</a>';
    } else {
        $sub['demo'] = "<a href=\"#\"><i class='fas fa-video-slash fa-lg'></i> No Demo</a>";
    }

    $sub['submitted'] = Config::time($sub['submitted']);

    $GLOBALS['PDO']->query("SELECT m.name FROM `:prefix_submissions` AS s LEFT JOIN `:prefix_mods` AS m ON m.mid = s.ModID WHERE s.subid = :subid");
    $GLOBALS['PDO']->bind(':subid', (int) $sub['subid']);
    $mod        = $GLOBALS['PDO']->single();
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
    $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
														(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
														(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
														FROM `:prefix_comments` AS C
														WHERE type = 'S' AND bid = :bid ORDER BY added desc");
    $GLOBALS['PDO']->bind(':bid', (int) $sub['subid']);
    $commentres    = $GLOBALS['PDO']->resultset();

    if (count($commentres) > 0) {
        $comment = [];
        $morecom = 0;
        foreach ($commentres as $crow) {
            $cdata            = [];
            $cdata['morecom'] = ($morecom == 1 ? true : false);
            if ($crow['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                $cdata['editcomlink'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i>', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S&cid=' . $crow['cid'], 'Edit Comment');
                if ($userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $crow['cid'] . ",'S',-1);\"><i class='fas fa-trash fa-lg'></i></a>";
                }
            } else {
                $cdata['editcomlink'] = "";
                $cdata['delcomlink']  = "";
            }

            $cdata['comname']    = $crow['comname'];
            $cdata['added']      = Config::time($crow['added']);
            $commentText         = html_entity_decode($crow['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $commentText         = encodePreservingBr($commentText);
            // Parse links and wrap them in a <a href=""></a> tag to be easily clickable
            $commentText         = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $commentText);
            $cdata['commenttxt'] = $commentText;

            if (!empty($crow['edittime'])) {
                $cdata['edittime'] = Config::time($crow['edittime']);
                $cdata['editname'] = $crow['editname'];
            } else {
                $cdata['edittime'] = "";
                $cdata['editname'] = "";
            }

            $morecom = 1;
            array_push($comment, $cdata);
        }
    } else {
        $comment = "None";
    }

    $sub['commentdata']   = $comment;
    $sub['subaddcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S');
    //----------------------------------------

    array_push($submission_list_archiv, $sub);
}
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansSubmissionsArchivView(
    permissions_submissions: $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS),
    permissions_editsub: $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS),
    submission_count_archiv: (int) $page_count,
    asubmission_nav: $page_nav,
    submission_list_archiv: $submission_list_archiv,
));
echo '</div>';
echo '</div>';

echo '<div class="tabcontent" id="Import bans">';
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansImportView(
    permission_import: $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_IMPORT),
    extreq: ini_get('safe_mode') != 1,
));
echo '</div>';

echo '<div class="tabcontent" id="Group ban">';
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansGroupsView(
    permission_addban: $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN),
    groupbanning_enabled: Config::getBool('config.enablegroupbanning'),
    list_steam_groups: isset($_GET['fid']) ? (string) $_GET['fid'] : false,
    // `player_name` is rendered next to the steam-groups list when an
    // admin reaches Group Ban via "Ban groups of player X" from the
    // banlist (?fid=…). The legacy template emitted `{$player_name}`
    // unguarded but no caller ever assigned it; we now pass an empty
    // string so the SmartyTemplateRule contract holds and the new
    // sbpp2026 template can render the placeholder when wired up.
    player_name: '',
));
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

    // 0 - SteamID / 1 - IP
    let type = $('type').selectedOptions[0].value;

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

    if (!/(?:STEAM_[01]:[01]:\d+)|(?:\[U:1:\d+\])|(?:\d{17})/.test($('steam').value) && type == 0) {
        $('steam.msg').setHTML('You must enter a valid STEAM ID or Community ID');
        $('steam.msg').setStyle('display', 'block');
        err++;
    } else {
        $('steam.msg').setHTML('');
        $('steam.msg').setStyle('display', 'none');
    }

    if ($('ip').value.length < 7 && type == 1) {
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

    sb.api.call(Actions.BansAdd, {
        nickname: $('nickname').value,
        type:     Number($('type').value),
        steam:    $('steam').value,
        ip:       $('ip').value,
        length:   Number($('banlength').value),
        dfile:    did,
        dname:    dname,
        reason:   reason,
        fromsub:  Number($('fromsub').value || 0),
    }).then(function (r) {
        if (r && r.ok && r.data && r.data.kickit) {
            ShowKickBox(r.data.kickit.check, r.data.kickit.type);
            if (r.data.reload) TabToReload();
            return;
        }
        applyApiResponse(r);
    });
}
function ProcessGroupBan()
{
    if (!$('groupurl').value) {
        $('groupurl.msg').setHTML('You must enter the group link of the group you are banning');
        $('groupurl.msg').setStyle('display', 'block');
    } else {
        $('groupurl.msg').setHTML('');
        $('groupurl.msg').setStyle('display', 'none');
        LoadGroupBan($('groupurl').value, "no", "no", $('groupreason').value, "");
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
            LoadGroupBan($('chkb_' + i).value, "yes", "yes", $('groupreason').value, last);
        }
    }
}
</script>
</div>
