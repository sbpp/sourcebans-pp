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

/*
 * #1239 — Pattern B (sticky page-level ToC).
 *
 * Pre-#1239 the page emitted a broken `<button onclick="openTab(...)">`
 * strip via `new AdminTabs([...])`; the JS handler was deleted with
 * sourcebans.js at #1123 D1 and every pane (Add a ban / Ban protests /
 * Ban submissions / Import bans / Group ban) stacked together below
 * the strip, with no way to switch. Per the issue body, bans is the
 * one admin route where the panes are *context for each other* (admins
 * triage submissions ↔ protests ↔ live bans in one session), so we
 * keep all panes in the DOM and ride the page-level ToC pattern that
 * #1207 ADM-3 locked in for admin-admins. The `page_toc.tpl` partial
 * is parameterized; this is its second consumer.
 *
 * Each section gets `<section id="…" class="page-toc-section">` plus
 * an aria-labelled heading so screen readers can navigate by landmark
 * name. ToC entries mirror what the dispatcher would render anyway —
 * the rule is "a ToC link must point at a section that lands in the
 * DOM" (AGENTS.md "Page-level table of contents (dense admin pages)").
 */
$canAddBan       = $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN);
$canProtests     = $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_PROTESTS);
$canSubmissions  = $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS);
$canImport       = $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_IMPORT);
$groupBanEnabled = Config::getBool('config.enablegroupbanning');
$canGroupBan     = $canAddBan && $groupBanEnabled;

/*
 * #1266 — each ToC entry carries a Lucide `icon` so the rendered
 * link gets the same iconed-pill visual weight as the Pattern A
 * sidebar (`core/admin_sidebar.tpl`). Icons follow the Pattern A
 * vocabulary (see admin.servers.php / admin.groups.php / etc):
 * `plus` for create, `users` for multi-user, `flag` for reports,
 * `clipboard-list` for queues, `upload` for file imports.
 */
/** @var list<array{slug: string, label: string, icon: string}> $tocEntries */
$tocEntries = [];
if ($canAddBan)      { $tocEntries[] = ['slug' => 'add-ban',     'label' => 'Add a ban',       'icon' => 'plus']; }
if ($canProtests)    { $tocEntries[] = ['slug' => 'protests',    'label' => 'Ban protests',    'icon' => 'flag']; }
if ($canSubmissions) { $tocEntries[] = ['slug' => 'submissions', 'label' => 'Ban submissions', 'icon' => 'clipboard-list']; }
if ($canImport)      { $tocEntries[] = ['slug' => 'import',      'label' => 'Import bans',     'icon' => 'upload']; }
if ($canGroupBan)    { $tocEntries[] = ['slug' => 'group-ban',   'label' => 'Group ban',       'icon' => 'users']; }

if (isset($_GET['mode']) && $_GET['mode'] == "delete") {
    // sb.message (sb.js) replaces the v1.x ShowBox helper.
    echo "<script>sb.message.show('Ban Deleted', 'The ban has been deleted from SourceBans', 'green', '', true);</script>";
} elseif (isset($_GET['mode']) && $_GET['mode']=="unban") {
    echo "<script>sb.message.show('Player Unbanned', 'The Player has been unbanned from SourceBans', 'green', '', true);</script>";
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

    // sb.message (sb.js) replaces the v1.x ShowBox helper.
    echo "<script>sb.message.show('Bans Import', '$bancnt ban" . ($bancnt != 1 ? "s have" : " has") . " been imported and posted.', 'green', '');</script>";
}

// Self-contained reban / paste-ban prefill (replaces the v1.x
// LoadPrepareReban / LoadPasteBan / ShowBox / applyBanFields helpers).
// Built on sb.api.call + a small DOM-prefill helper that lives in this
// file's tail script (window.__sbppApplyBanFields). Both inline scripts
// below dispatch through Actions.* (api-contract.js) and use sb.ready
// so the helper is defined by the time the API response lands.
if (isset($_GET["rebanid"])) {
    echo '<script type="text/javascript">sb.ready(function(){sb.api.call(Actions.BansPrepareReban,{bid:' . (int) $_GET["rebanid"] . '}).then(function(r){if(r&&r.ok&&r.data&&typeof window.__sbppApplyBanFields==="function")window.__sbppApplyBanFields(r.data);});});</script>';
}
if ((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
    $pNameJs = json_encode((string) $_GET['pName'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<script type=\"text/javascript\">sb.ready(function(){sb.message.show('Loading..','<b>Loading...</b><br><i>Please Wait!</i>','blue','',true);sb.hide('dialog-control');sb.api.call(Actions.BansPaste,{sid:" . (int) $_GET['sid'] . ",name:" . $pNameJs . ",type:0}).then(function(r){if(r&&r.ok&&r.data){if(typeof window.__sbppApplyBanFields==='function')window.__sbppApplyBanFields(r.data);sb.show('dialog-control');sb.hide('dialog-placement');}else if(r&&r.ok===false&&r.error){sb.message.error('Error',r.error.message);sb.show('dialog-control');}});});</script>";
}

/*
 * Open the cross-section shell + sticky ToC partial. The shell wraps
 * EVERY rendered View on this page; closing tags live at the bottom
 * of this file (search for "page-toc-shell"). Keep the open/close
 * pair documented at each end so edits don't silently break the
 * layout.
 */
echo '<div class="page-toc-shell" data-testid="admin-bans-shell">';
$theme->assign('toc_id', 'admin-bans');
$theme->assign('toc_label', 'Bans page sections');
$theme->assign('toc_entries', $tocEntries);
$theme->display('page_toc.tpl');
echo '<div class="page-toc-content">';

// Add Ban
echo '<section id="add-ban" class="page-toc-section" data-testid="admin-bans-section-add-ban" aria-labelledby="add-ban-heading">';
echo '<h2 id="add-ban-heading" class="page-toc-section__heading">Add a ban</h2>';
$customReason = Config::getBool('bans.customreasons')
    ? unserialize((string) Config::get('bans.customreasons'))
    : false;
/** @var false|list<string> $customReason */
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansAddView(
    permission_addban: $canAddBan,
    customreason: $customReason,
));
echo '</section>';

// Protests
echo '<section id="protests" class="page-toc-section" data-testid="admin-bans-section-protests" aria-labelledby="protests-heading">';
echo '<h2 id="protests-heading" class="page-toc-section__heading">Ban protests</h2>';
// Current/Archive segmented control. v1.x emitted a <ul><li> styled by
// the (now-removed) #tabsWrapper / .nonactive CSS pair (#1124, #1187);
// the v2.0.0 sweep deleted those rules but kept the bullet markup, so
// it was rendering as a browser-default disc list. Re-skinned as a
// .chip-row matching banlist/comms/audit; `data-active="true"` is the
// #1123 testability hook. The legacy id="admin_utab_p*" + onclick=
// Swap2ndPane attribute is kept verbatim so any caller that re-supplies
// that helper (or sb.tabs.init() reading the URL anchor) keeps working.
echo '<div class="chip-row" role="tablist" aria-label="Protest archive filter" data-testid="protests-archive-tabs" style="margin-bottom:0.75rem">
		<a class="chip" id="admin_utab_p0" data-active="true" data-testid="filter-chip-protests-current" role="tab" aria-selected="true" aria-controls="p0" href="index.php?p=admin&c=bans#^1~p0" onclick="Swap2ndPane(0,\'p\');" title="Show current protests">Current</a>
		<a class="chip" id="admin_utab_p1" data-active="false" data-testid="filter-chip-protests-archive" role="tab" aria-selected="false" aria-controls="p1" href="index.php?p=admin&c=bans#^1~p1" onclick="Swap2ndPane(1,\'p\');" title="Show the protest archive">Archive</a>
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

if ($prev !== '') {
    $page_nav .= ' | <b>' . $prev . '</b>';
}
if ($next !== '') {
    $page_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($page_count / $ItemsPerPage);
if ($pages > 1) {
    $page_nav .= '&nbsp;<select onchange="if(this.value!==\'0\')window.location.href=\'index.php?p=admin&c=bans&ppage=\'+this.value+\'#^1\';">';
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

if ($prev !== '') {
    $page_nav .= ' | <b>' . $prev . '</b>';
}
if ($next !== '') {
    $page_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($page_count / $ItemsPerPage);
if ($pages > 1) {
    $page_nav .= '&nbsp;<select onchange="if(this.value!==\'0\')window.location.href=\'index.php?p=admin&c=bans&papage=\'+this.value+\'#^1~p1\';">';
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
echo '</section>';



//Submissions page
echo '<section id="submissions" class="page-toc-section" data-testid="admin-bans-section-submissions" aria-labelledby="submissions-heading">';
echo '<h2 id="submissions-heading" class="page-toc-section__heading">Ban submissions</h2>';
// Same Current/Archive segmented control as the protests block above —
// see the comment on the protests echo for the bullet-list backstory
// (#1124, #1187). Aria/testid hooks are scoped with `submissions-` so
// e2e specs can target each filter row independently.
echo '<div class="chip-row" role="tablist" aria-label="Submission archive filter" data-testid="submissions-archive-tabs" style="margin-bottom:0.75rem">
		<a class="chip" id="admin_utab_s0" data-active="true" data-testid="filter-chip-submissions-current" role="tab" aria-selected="true" aria-controls="s0" href="index.php?p=admin&c=bans#^2~s0" onclick="Swap2ndPane(0,\'s\');" title="Show current submissions">Current</a>
		<a class="chip" id="admin_utab_s1" data-active="false" data-testid="filter-chip-submissions-archive" role="tab" aria-selected="false" aria-controls="s1" href="index.php?p=admin&c=bans#^2~s1" onclick="Swap2ndPane(1,\'s\');" title="Show the submission archive">Archive</a>
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

if ($prev !== '') {
    $page_nav .= ' | <b>' . $prev . '</b>';
}
if ($next !== '') {
    $page_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($page_count / $ItemsPerPage);
if ($pages > 1) {
    $page_nav .= '&nbsp;<select onchange="if(this.value!==\'0\')window.location.href=\'index.php?p=admin&c=bans&spage=\'+this.value+\'#^2\';">';
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

if ($prev !== '') {
    $page_nav .= ' | <b>' . $prev . '</b>';
}
if ($next !== '') {
    $page_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($page_count / $ItemsPerPage);
if ($pages > 1) {
    $page_nav .= '&nbsp;<select onchange="if(this.value!==\'0\')window.location.href=\'index.php?p=admin&c=bans&sapage=\'+this.value+\'#^2~s1\';">';
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
echo '</section>';

if ($canImport) {
    echo '<section id="import" class="page-toc-section" data-testid="admin-bans-section-import" aria-labelledby="import-heading">';
    echo '<h2 id="import-heading" class="page-toc-section__heading">Import bans</h2>';
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansImportView(
        permission_import: true,
        extreq: ini_get('safe_mode') != 1,
    ));
    echo '</section>';
}

/*
 * "Group ban" is the one section we omit from the DOM entirely when
 * the feature toggle is off — pre-#1239 the AdminTabs strip already
 * gated the tab on `Config::getBool('config.enablegroupbanning')`, so
 * preserving the same gate keeps the ToC contract clean: every entry
 * points at a section that actually lands. The other admin-bans
 * sections render unconditionally with their own access-denied stubs
 * because they're permission-gated rather than feature-flag-gated, so
 * the ToC entry is omitted via the `$canX` checks above.
 */
if ($canGroupBan) {
    echo '<section id="group-ban" class="page-toc-section" data-testid="admin-bans-section-group-ban" aria-labelledby="group-ban-heading">';
    echo '<h2 id="group-ban-heading" class="page-toc-section__heading">Group ban</h2>';
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansGroupsView(
        permission_addban: $canAddBan,
        groupbanning_enabled: $groupBanEnabled,
        list_steam_groups: isset($_GET['fid']) ? (string) $_GET['fid'] : false,
        // `player_name` is rendered next to the steam-groups list when an
        // admin reaches Group Ban via "Ban groups of player X" from the
        // banlist (?fid=…). The pre-v2.0.0 template emitted `{$player_name}`
        // unguarded but no caller ever assigned it; we pass an empty
        // string so the SmartyTemplateRule contract holds and the
        // template can render the placeholder when wired up.
        player_name: '',
    ));
    echo '</section>';
}
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
        // sb.message (sb.js) replaces the v1.x ShowKickBox /
        // TabToReload / applyApiResponse helpers.
        if (r && r.ok && r.data && r.data.kickit) {
            sb.message.show(
                'Ban Added',
                'The ban has been successfully added<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.kickit.php?check='
                    + encodeURIComponent(r.data.kickit.check) + '&type=' + encodeURIComponent(r.data.kickit.type) + '"></iframe>',
                'green',
                'index.php?p=admin&c=bans',
                true
            );
            if (r.data.reload) setTimeout(function () { window.location.href = window.location.href.replace(/#\^.*$/, ''); }, 2000);
            return;
        }
        if (!r) return;
        if (r.redirect) return;
        if (r.ok === false) {
            if (r.error) sb.message.error('Error', r.error.message || 'Unknown error');
            return;
        }
        var data = r.data || {};
        if (data.message) {
            sb.message.show(data.message.title, data.message.body, data.message.kind, data.message.redir, data.message.noclose);
        }
        if (data.reload) {
            setTimeout(function () { window.location.href = window.location.href.replace(/#\^.*$/, ''); }, 2000);
        }
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

// Self-contained DOM-prefill helper (replaces the v1.x applyBanFields)
// so both LoadPrepareReban and LoadPasteBan keep prefilling the form.
window.__sbppApplyBanFields = function (d) {
    var byId = function (id) { return document.getElementById(id); };
    if (byId('nickname'))   byId('nickname').value   = d.nickname || '';
    if (byId('fromsub'))    byId('fromsub').value    = d.subid    || '';
    if (byId('steam'))      byId('steam').value      = d.steam    || '';
    if (byId('ip'))         byId('ip').value         = d.ip       || '';
    if (byId('txtReason'))  byId('txtReason').value  = '';
    if (byId('demo.msg'))   byId('demo.msg').innerHTML = '';
    if (typeof window.selectLengthTypeReason === 'function') {
        window.selectLengthTypeReason(d.length || 0, d.type || 0, d.reason || '');
    }
    if (d.demo) {
        if (byId('demo.msg')) byId('demo.msg').innerHTML = d.demo.origname || '';
        if (typeof window.demo === 'function') window.demo(d.demo.filename, d.demo.origname);
    }
    if (typeof window.swapTab === 'function') window.swapTab(0);
};
</script>
</div><!-- /.page-toc-content -->
</div><!-- /.page-toc-shell — opened above before the Add Ban section -->
