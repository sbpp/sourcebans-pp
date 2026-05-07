<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2026 by SourceBans++ Dev Team

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
 * #1275 — Pattern A (`?section=…` URL routing).
 *
 * Pre-#1275 admin-bans rode the page-level ToC (#1239 / #1207 ADM-3):
 * every section (Add a ban / Ban protests / Ban submissions / Import
 * bans / Group ban) stacked into one DOM and the sticky sidebar
 * emitted `#fragment` anchor jumps. The chrome looked identical to
 * the Pattern A admin sidebars (servers / mods / groups / settings)
 * after #1266's visual unification, but the routing semantics
 * diverged — clicks emitted `#fragment` URLs, scroll position was
 * lost on browser back, and link sharing broke. #1275 unifies on
 * Pattern A so back-button navigation, link sharing, and the
 * per-section testid contract all match the rest of the admin family.
 *
 * Section split — five sections, one render branch each:
 *   - `add-ban`     — Add a ban (the main form)
 *   - `protests`    — Ban protests queue (sub-views: current / archive
 *                     via `?view=archive`)
 *   - `submissions` — Ban submissions queue (same sub-view shape)
 *   - `import`      — Import bans (file upload)
 *   - `group-ban`   — Group ban (Steam community group banning;
 *                     gated on `config.enablegroupbanning`)
 *
 * Sub-view routing inside protests / submissions:
 *
 * Pre-#1275 each of these sections rendered BOTH the current and the
 * archive view simultaneously and a `.chip-row` toggled visibility via
 * `Swap2ndPane()` — a JS helper that lived in `web/scripts/sourcebans.js`
 * and was deleted at #1123 D1, leaving the chips dead. #1275 promotes
 * the sub-view to a real query param (`?section=protests&view=archive`)
 * so the chips become normal anchors, the back button works, and only
 * the active view's data is queried/rendered. The default for both
 * sections is the live queue (`view=current`).
 *
 * Cross-section "Ban from submission" flow:
 *
 * Pre-#1275 the submissions row's "Ban" button called
 * `Actions.BansSetupBan` and `__sbppApplyBanFields(r.data)` to fill
 * the Add Ban form on the same page, then `swapTab(0)` to scroll up.
 * After Pattern A split, the form lives on a different URL — the
 * button now navigates to `?section=add-ban&fromsub=<subid>` and the
 * add-ban section emits a self-contained prefill snippet (alongside
 * the existing `?rebanid` and `?action=pasteBan` shapes) that calls
 * `BansSetupBan` and `__sbppApplyBanFields` after the form mounts.
 *
 * Legacy fragment URLs (`#protests`, `#submissions`, …) are NOT
 * shimmed — browsers don't send fragments to the server, so the
 * page handler can't observe them. The cleanest fallback is to land
 * on the default section and accept that bookmarks lose their
 * anchor. There are no cross-app deep-links to these fragments other
 * than the dashboard cards (which point at `?p=admin&c=bans` with no
 * fragment).
 */

$canAddBan       = $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN);
$canProtests     = $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_PROTESTS);
$canSubmissions  = $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS);
$canImport       = $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_IMPORT);
$groupBanEnabled = Config::getBool('config.enablegroupbanning');
$canGroupBan     = $canAddBan && $groupBanEnabled;

/*
 * Top-of-page side effects: toasts for delete/unban redirects and the
 * file-upload `importBans` POST. These run regardless of section
 * because the redirects are out-of-band (banlist row -> "deleted" /
 * "unbanned" toast on bans landing page) and the POST has to hit its
 * dedicated section anyway. Order them BEFORE the AdminTabs sidebar
 * paints so any echo'd toast lands above the chrome.
 */
if (isset($_GET['mode']) && $_GET['mode'] == "delete") {
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
    echo "<script>sb.message.show('Bans Import', '$bancnt ban" . ($bancnt != 1 ? "s have" : " has") . " been imported and posted.', 'green', '');</script>";
}

/*
 * #1275 — `$sections` array drives the new vertical sidebar via
 * AdminTabs. Each entry carries `slug` + `name` + `permission` +
 * `url` + `icon` (Lucide). Icons follow the Pattern A vocabulary
 * already in `admin.servers.php` / `admin.groups.php` / etc:
 * `plus` for create, `flag` for reports, `clipboard-list` for
 * queues, `upload` for file imports, `users` for multi-user.
 */
/** @var list<array{slug: string, name: string, permission: int, url: string, icon: string}> $sections */
$sections = [
    [
        'slug'       => 'add-ban',
        'name'       => 'Add a ban',
        'permission' => ADMIN_OWNER | ADMIN_ADD_BAN,
        'url'        => 'index.php?p=admin&c=bans&section=add-ban',
        'icon'       => 'plus',
    ],
    [
        'slug'       => 'protests',
        'name'       => 'Ban protests',
        'permission' => ADMIN_OWNER | ADMIN_BAN_PROTESTS,
        'url'        => 'index.php?p=admin&c=bans&section=protests',
        'icon'       => 'flag',
    ],
    [
        'slug'       => 'submissions',
        'name'       => 'Ban submissions',
        'permission' => ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS,
        'url'        => 'index.php?p=admin&c=bans&section=submissions',
        'icon'       => 'clipboard-list',
    ],
    [
        'slug'       => 'import',
        'name'       => 'Import bans',
        'permission' => ADMIN_OWNER | ADMIN_BAN_IMPORT,
        'url'        => 'index.php?p=admin&c=bans&section=import',
        'icon'       => 'upload',
    ],
];
// Group ban is feature-flag-gated (Config::getBool('config.enablegroupbanning'))
// in addition to the permission gate. The other sections render an
// access-denied stub when the user lacks the perm; group-ban is omitted
// from the sidebar entirely when the feature is off, so the link
// doesn't appear at all on installs that have the feature disabled.
if ($groupBanEnabled) {
    $sections[] = [
        'slug'       => 'group-ban',
        'name'       => 'Group ban',
        'permission' => ADMIN_OWNER | ADMIN_ADD_BAN,
        'url'        => 'index.php?p=admin&c=bans&section=group-ban',
        'icon'       => 'users',
    ];
}

$validSlugs = ['add-ban', 'protests', 'submissions', 'import', 'group-ban'];
$section    = (string) ($_GET['section'] ?? '');

// Smarter default selection: if the URL carries section-specific
// query params, infer the section so deep links (e.g. the banlist's
// "Reban" → `?p=admin&c=bans&rebanid=…`, or the moderation queue's
// "Ban from submission" cross-section flow) still land on the right
// surface.
if (!in_array($section, $validSlugs, true)) {
    if (isset($_GET['rebanid']) || (isset($_GET['action']) && $_GET['action'] === 'pasteBan') || isset($_GET['fromsub'])) {
        $section = 'add-ban';
    } elseif (isset($_GET['fid'])) {
        $section = 'group-ban';
    } elseif ($canAddBan) {
        $section = 'add-ban';
    } elseif ($canProtests) {
        $section = 'protests';
    } elseif ($canSubmissions) {
        $section = 'submissions';
    } elseif ($canImport) {
        $section = 'import';
    } elseif ($canGroupBan) {
        $section = 'group-ban';
    } else {
        $section = 'add-ban';
    }
}

// AdminTabs opens the sidebar shell + emits the <aside> + opens the
// content column. Closing tags live AFTER each render branch below —
// document the pairing so future edits don't strand an open <div>.
new AdminTabs($sections, $userbank, $theme, $section, 'Bans sections');

// Helper to close the shell consistently after every section returns.
// PHP doesn't bind a local closure to `return` from the outer scope,
// so each branch echoes the closing pair itself before returning.

// ---------------------------------------------------------------- add-ban
if ($section === 'add-ban') {
    /*
     * Self-contained reban / paste-ban / ban-from-submission prefill.
     * All three shapes call `Actions.<Setup>` and pipe the response
     * into the shared `window.__sbppApplyBanFields` helper defined in
     * the section's tail script (same helper sourcebans.js used to
     * provide as `applyBanFields`). `sb.ready` defers the calls until
     * the API client is up.
     */
    if (isset($_GET["rebanid"])) {
        echo '<script type="text/javascript">sb.ready(function(){sb.api.call(Actions.BansPrepareReban,{bid:' . (int) $_GET["rebanid"] . '}).then(function(r){if(r&&r.ok&&r.data&&typeof window.__sbppApplyBanFields==="function")window.__sbppApplyBanFields(r.data);});});</script>';
    }
    if ((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
        $pNameJs = json_encode((string) $_GET['pName'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        echo "<script type=\"text/javascript\">sb.ready(function(){sb.message.show('Loading..','<b>Loading...</b><br><i>Please Wait!</i>','blue','',true);sb.hide('dialog-control');sb.api.call(Actions.BansPaste,{sid:" . (int) $_GET['sid'] . ",name:" . $pNameJs . ",type:0}).then(function(r){if(r&&r.ok&&r.data){if(typeof window.__sbppApplyBanFields==='function')window.__sbppApplyBanFields(r.data);sb.show('dialog-control');sb.hide('dialog-placement');}else if(r&&r.ok===false&&r.error){sb.message.error('Error',r.error.message);sb.show('dialog-control');}});});</script>";
    }
    /*
     * #1275 — "Ban from submission" cross-section prefill. Mirrors
     * the rebanid / pasteBan shape: `?section=add-ban&fromsub=<subid>`
     * dispatches `Actions.BansSetupBan`, fills the form, and the
     * admin can review/edit/submit. The submissions queue's "Ban"
     * button is now a normal anchor to this URL, so clicks are
     * back-button-friendly and the URL is bookmarkable.
     */
    if (isset($_GET['fromsub']) && is_numeric($_GET['fromsub'])) {
        echo '<script type="text/javascript">sb.ready(function(){sb.api.call(Actions.BansSetupBan,{subid:' . (int) $_GET['fromsub'] . '}).then(function(r){if(r&&r.ok&&r.data&&typeof window.__sbppApplyBanFields==="function")window.__sbppApplyBanFields(r.data);});});</script>';
    }

    $customReason = Config::getBool('bans.customreasons')
        ? unserialize((string) Config::get('bans.customreasons'))
        : false;
    /** @var false|list<string> $customReason */
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansAddView(
        permission_addban: $canAddBan,
        customreason: $customReason,
    ));

    // Tail script: defines `__sbppApplyBanFields` (used by the prefill
    // scripts above) and the legacy `ProcessBan` global (third-party
    // themes still bind `onclick="ProcessBan();"`). The default theme's
    // `page_admin_bans_add.tpl` carries its own self-contained
    // `data-action="addban-submit"` listener that doesn't need
    // `ProcessBan`, but keeping the global preserves theme-fork parity.
    echo <<<'JS'
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
            sb.message.show(
                'Ban Added',
                'The ban has been successfully added<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.kickit.php?check='
                    + encodeURIComponent(r.data.kickit.check) + '&type=' + encodeURIComponent(r.data.kickit.type) + '"></iframe>',
                'green',
                'index.php?p=admin&c=bans&section=add-ban',
                true
            );
            if (r.data.reload) setTimeout(function () { window.location.href = window.location.href.replace(/[#?].*$/, '') + '?p=admin&c=bans&section=add-ban'; }, 2000);
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
            setTimeout(function () { window.location.href = window.location.href.replace(/[#?].*$/, '') + '?p=admin&c=bans&section=add-ban'; }, 2000);
        }
    });
}

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
};
</script>
JS;
    echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell — opened by new AdminTabs(...) above -->';
    return;
}

// ---------------------------------------------------------------- protests
if ($section === 'protests') {
    if (!$canProtests) {
        echo '<div class="card"><div class="card__body"><p class="text-muted m-0">Access denied.</p></div></div>';
        echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
        return;
    }

    /*
     * Sub-view chip row — `?view=current` (default) or `?view=archive`.
     * Pre-#1275 these chips called `Swap2ndPane()` (deleted with
     * sourcebans.js at #1123 D1, leaving them dead) to toggle
     * server-rendered hidden divs. Now they're real anchors and the
     * server only renders the active view.
     */
    $protestView = (isset($_GET['view']) && $_GET['view'] === 'archive') ? 'archive' : 'current';
    $currentActive = $protestView === 'current' ? 'true' : 'false';
    $archiveActive = $protestView === 'archive' ? 'true' : 'false';
    echo '<div class="chip-row" role="tablist" aria-label="Protest archive filter" data-testid="protests-archive-tabs" style="margin-bottom:0.75rem">'
        . '<a class="chip" data-active="' . $currentActive . '" data-testid="filter-chip-protests-current" role="tab" aria-selected="' . $currentActive . '" href="index.php?p=admin&amp;c=bans&amp;section=protests" title="Show current protests">Current</a>'
        . '<a class="chip" data-active="' . $archiveActive . '" data-testid="filter-chip-protests-archive" role="tab" aria-selected="' . $archiveActive . '" href="index.php?p=admin&amp;c=bans&amp;section=protests&amp;view=archive" title="Show the protest archive">Archive</a>'
        . '</div>';

    if ($protestView === 'current') {
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
        // Pagination URLs carry `&section=protests` so prev/next stays
        // on the section. Pre-#1275 they used `#^1` fragment anchors
        // for the (now-defunct) Swap2ndPane scroll target.
        $prev = $page > 1
            ? CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=bans&section=protests&ppage=" . ($page - 1))
            : "";
        $next = $PageEnd < $page_count
            ? CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=bans&section=protests&ppage=" . ($page + 1))
            : "";

        $page_nav = 'displaying&nbsp;' . $PageStart . '&nbsp;-&nbsp;' . $PageEnd . '&nbsp;of&nbsp;' . $page_count . '&nbsp;results';
        if ($prev !== '') { $page_nav .= ' | <b>' . $prev . '</b>'; }
        if ($next !== '') { $page_nav .= ' | <b>' . $next . '</b>'; }

        $pages = ceil($page_count / $ItemsPerPage);
        if ($pages > 1) {
            $page_nav .= '&nbsp;<select onchange="if(this.value!==\'0\')window.location.href=\'index.php?p=admin&c=bans&section=protests&ppage=\'+this.value;" aria-label="Jump to page">';
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
            $prot['ends'] = $protestb['ends'] == 'never' ? 'never' : Config::time($protestb['ends']);
            $prot['ban_reason'] = htmlspecialchars($protestb['reason']);
            $prot['admin']      = $protestb['user'];
            $prot['server']     = $protestb['server_addr'] ? $protestb['server_addr'] : "Web Ban";
            $prot['datesubmitted'] = Config::time($prot['datesubmitted']);

            $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
                (SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
                (SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
                FROM `:prefix_comments` AS C
                WHERE type = 'P' AND bid = :bid ORDER BY added desc");
            $GLOBALS['PDO']->bind(':bid', (int) $prot['pid']);
            $commentres = $GLOBALS['PDO']->resultset();
            $prot['commentdata'] = bansBuildComments($commentres, $userbank, (int) $prot['pid'], 'P');
            $prot['protaddcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P');

            array_push($protest_list, $prot);
        }
        if (count($delete) > 0) {
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
    } else {
        // archived protests
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
        $prev = $page > 1
            ? CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=bans&section=protests&view=archive&papage=" . ($page - 1))
            : "";
        $next = $PageEnd < $page_count
            ? CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=bans&section=protests&view=archive&papage=" . ($page + 1))
            : "";

        $page_nav = 'displaying&nbsp;' . $PageStart . '&nbsp;-&nbsp;' . $PageEnd . '&nbsp;of&nbsp;' . $page_count . '&nbsp;results';
        if ($prev !== '') { $page_nav .= ' | <b>' . $prev . '</b>'; }
        if ($next !== '') { $page_nav .= ' | <b>' . $next . '</b>'; }

        $pages = ceil($page_count / $ItemsPerPage);
        if ($pages > 1) {
            $page_nav .= '&nbsp;<select onchange="if(this.value!==\'0\')window.location.href=\'index.php?p=admin&c=bans&section=protests&view=archive&papage=\'+this.value;" aria-label="Jump to page">';
            for ($i = 1; $i <= $pages; $i++) {
                if ($i == $page) {
                    $page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
                    continue;
                }
                $page_nav .= '<option value="' . $i . '">' . $i . '</option>';
            }
            $page_nav .= '</select>';
        }

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
                    $prot['date']   = Config::time($protestb['created']);
                    $prot['ends']   = $protestb['ends'] == 'never' ? 'never' : Config::time($protestb['ends']);
                    $prot['ban_reason'] = htmlspecialchars($protestb['reason']);
                    $prot['admin']      = $protestb['user'];
                    $prot['server']     = $protestb['server_addr'] ? $protestb['server_addr'] : "Web Ban";
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

            $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
                (SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
                (SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
                FROM `:prefix_comments` AS C
                WHERE type = 'P' AND bid = :bid ORDER BY added desc");
            $GLOBALS['PDO']->bind(':bid', (int) $prot['pid']);
            $commentres = $GLOBALS['PDO']->resultset();
            $prot['commentdata'] = bansBuildComments($commentres, $userbank, (int) $prot['pid'], 'P');
            $prot['protaddcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . (int) $prot['pid'] . '&ctype=P');

            array_push($protest_list_archiv, $prot);
        }

        \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansProtestsArchivView(
            permission_protests: $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_PROTESTS),
            permission_editban: $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS),
            aprotest_nav: $page_nav,
            protest_list_archiv: $protest_list_archiv,
            protest_count_archiv: (int) $page_count,
        ));
    }
    echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
    return;
}

// ---------------------------------------------------------------- submissions
if ($section === 'submissions') {
    if (!$canSubmissions) {
        echo '<div class="card"><div class="card__body"><p class="text-muted m-0">Access denied.</p></div></div>';
        echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
        return;
    }

    $submissionView = (isset($_GET['view']) && $_GET['view'] === 'archive') ? 'archive' : 'current';
    $currentActive = $submissionView === 'current' ? 'true' : 'false';
    $archiveActive = $submissionView === 'archive' ? 'true' : 'false';
    echo '<div class="chip-row" role="tablist" aria-label="Submission archive filter" data-testid="submissions-archive-tabs" style="margin-bottom:0.75rem">'
        . '<a class="chip" data-active="' . $currentActive . '" data-testid="filter-chip-submissions-current" role="tab" aria-selected="' . $currentActive . '" href="index.php?p=admin&amp;c=bans&amp;section=submissions" title="Show current submissions">Current</a>'
        . '<a class="chip" data-active="' . $archiveActive . '" data-testid="filter-chip-submissions-archive" role="tab" aria-selected="' . $archiveActive . '" href="index.php?p=admin&amp;c=bans&amp;section=submissions&amp;view=archive" title="Show the submission archive">Archive</a>'
        . '</div>';

    if ($submissionView === 'current') {
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
        $prev = $page > 1
            ? CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=bans&section=submissions&spage=" . ($page - 1))
            : "";
        $next = $PageEnd < $page_count
            ? CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=bans&section=submissions&spage=" . ($page + 1))
            : "";

        $page_nav = 'displaying&nbsp;' . $PageStart . '&nbsp;-&nbsp;' . $PageEnd . '&nbsp;of&nbsp;' . $page_count . '&nbsp;results';
        if ($prev !== '') { $page_nav .= ' | <b>' . $prev . '</b>'; }
        if ($next !== '') { $page_nav .= ' | <b>' . $next . '</b>'; }

        $pages = ceil($page_count / $ItemsPerPage);
        if ($pages > 1) {
            $page_nav .= '&nbsp;<select onchange="if(this.value!==\'0\')window.location.href=\'index.php?p=admin&c=bans&section=submissions&spage=\'+this.value;" aria-label="Jump to page">';
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

            $sub['demo'] = ($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename']))
                ? '<a href="getdemo.php?id=' . urlencode($sub['subid']) . '&type=S"><i class=\'fas fa-video fa-lg\'></i> Get Demo</a>'
                : "<a href=\"#\"><i class='fas fa-video-slash fa-lg'></i> No Demo</a>";

            $sub['submitted'] = Config::time($sub['submitted']);

            $GLOBALS['PDO']->query("SELECT m.name FROM `:prefix_submissions` AS s LEFT JOIN `:prefix_mods` AS m ON m.mid = s.ModID WHERE s.subid = :subid");
            $GLOBALS['PDO']->bind(':subid', (int) $sub['subid']);
            $mod = $GLOBALS['PDO']->single();
            $sub['mod'] = $mod['name'];
            $sub['hostname'] = empty($sub['server']) ? '<i><font color="#677882">Other server...</font></i>' : "";

            $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
                (SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
                (SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
                FROM `:prefix_comments` AS C
                WHERE type = 'S' AND bid = :bid ORDER BY added desc");
            $GLOBALS['PDO']->bind(':bid', (int) $sub['subid']);
            $commentres = $GLOBALS['PDO']->resultset();
            $sub['commentdata'] = bansBuildComments($commentres, $userbank, (int) $sub['subid'], 'S');
            $sub['subaddcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S');

            array_push($submission_list, $sub);
        }
        \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansSubmissionsView(
            permissions_submissions: $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS),
            permissions_editsub: $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS),
            submission_count: (int) $page_count,
            submission_nav: $page_nav,
            submission_list: $submission_list,
        ));
    } else {
        // submission archive
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
        $prev = $page > 1
            ? CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=bans&section=submissions&view=archive&sapage=" . ($page - 1))
            : "";
        $next = $PageEnd < $page_count
            ? CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=bans&section=submissions&view=archive&sapage=" . ($page + 1))
            : "";

        $page_nav = 'displaying&nbsp;' . $PageStart . '&nbsp;-&nbsp;' . $PageEnd . '&nbsp;of&nbsp;' . $page_count . '&nbsp;results';
        if ($prev !== '') { $page_nav .= ' | <b>' . $prev . '</b>'; }
        if ($next !== '') { $page_nav .= ' | <b>' . $next . '</b>'; }

        $pages = ceil($page_count / $ItemsPerPage);
        if ($pages > 1) {
            $page_nav .= '&nbsp;<select onchange="if(this.value!==\'0\')window.location.href=\'index.php?p=admin&c=bans&section=submissions&view=archive&sapage=\'+this.value;" aria-label="Jump to page">';
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

            $sub['demo'] = ($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename']))
                ? '<a href="getdemo.php?id=' . urlencode($sub['subid']) . '&type=S"><i class=\'fas fa-video fa-lg\'></i> Get Demo</a>'
                : "<a href=\"#\"><i class='fas fa-video-slash fa-lg'></i> No Demo</a>";

            $sub['submitted'] = Config::time($sub['submitted']);

            $GLOBALS['PDO']->query("SELECT m.name FROM `:prefix_submissions` AS s LEFT JOIN `:prefix_mods` AS m ON m.mid = s.ModID WHERE s.subid = :subid");
            $GLOBALS['PDO']->bind(':subid', (int) $sub['subid']);
            $mod = $GLOBALS['PDO']->single();
            $sub['mod'] = $mod['name'];
            $sub['hostname'] = empty($sub['server']) ? '<i><font color="#677882">Other server...</font></i>' : "";
            if ($sub['archiv'] == "3") {
                $sub['archive'] = "player has been banned.";
            } elseif ($sub['archiv'] == "2") {
                $sub['archive'] = "submission has been accepted.";
            } elseif ($sub['archiv'] == "1") {
                $sub['archive'] = "submission has been archived.";
            }

            $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
                (SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
                (SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
                FROM `:prefix_comments` AS C
                WHERE type = 'S' AND bid = :bid ORDER BY added desc");
            $GLOBALS['PDO']->bind(':bid', (int) $sub['subid']);
            $commentres = $GLOBALS['PDO']->resultset();
            $sub['commentdata'] = bansBuildComments($commentres, $userbank, (int) $sub['subid'], 'S');
            $sub['subaddcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . (int) $sub['subid'] . '&ctype=S');

            array_push($submission_list_archiv, $sub);
        }
        \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansSubmissionsArchivView(
            permissions_submissions: $userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS),
            permissions_editsub: $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_OWN_BANS),
            submission_count_archiv: (int) $page_count,
            asubmission_nav: $page_nav,
            submission_list_archiv: $submission_list_archiv,
        ));
    }
    echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
    return;
}

// ---------------------------------------------------------------- import
if ($section === 'import') {
    if (!$canImport) {
        echo '<div class="card"><div class="card__body"><p class="text-muted m-0">Access denied.</p></div></div>';
        echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
        return;
    }
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansImportView(
        permission_import: true,
        extreq: ini_get('safe_mode') != 1,
    ));
    echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
    return;
}

// ---------------------------------------------------------------- group-ban
// At this point `$section` is structurally guaranteed to be 'group-ban'
// (every other slug in `$validSlugs` has its own `if` branch above and
// each one returns; the default-section guard above narrows `$section`
// to a member of `$validSlugs`). PHPStan flags the redundant
// `if ($section === 'group-ban')` wrapper as `identical.alwaysTrue`,
// so we emit the section directly. If a new slug ever lands in
// `$validSlugs`, the test suite catches the mismatch (the responsive
// admin-tabs spec enumerates the slug list against the rendered
// sidebar).
if (!$canGroupBan) {
    echo '<div class="card"><div class="card__body"><p class="text-muted m-0">'
        . (!$canAddBan ? 'Access denied.' : 'Group banning is disabled in <strong>config.enablegroupbanning</strong>.')
        . '</p></div></div>';
    echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
    return;
}
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
// Tail script: defines the legacy `ProcessGroupBan()` and
// `CheckGroupBan()` globals that `page_admin_bans_groups.tpl`
// binds via `onclick=`. Other supporting globals (`LoadGroupBan`,
// `TickSelectAll`) used to live in `web/scripts/sourcebans.js`,
// deleted at #1123 D1; the buttons reference them too. The
// group-ban surface is therefore partially-broken on default
// (the 'ban URL' submit calls into LoadGroupBan which is undefined)
// — this is a pre-existing #1123 follow-up, tracked separately
// from #1275. We preserve the legacy globals here so the
// remaining flows (e.g. an external theme that ships LoadGroupBan)
// keep working.
echo <<<'JS'
<script type="text/javascript">
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
JS;
echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';

/*
 * Comment-thread builder used by both protests + submissions sections.
 * Consolidated here because the per-section foreach loops in the
 * original file were a verbatim duplicate (the only difference was
 * the `type` argument 'P' vs 'S'). Returns a list<array> ready to
 * splat into the View DTO.
 *
 * @param list<array<string,mixed>> $commentres
 * @param object $userbank Logged-in user (CUserManager-like).
 * @param int    $rowId    pid (for protests) or subid (for submissions).
 * @param string $type     'P' for protests, 'S' for submissions.
 * @return string|list<array<string,mixed>> "None" sentinel or comment rows.
 */
function bansBuildComments(array $commentres, $userbank, int $rowId, string $type)
{
    if (count($commentres) === 0) {
        return "None";
    }

    $comments = [];
    $morecom  = 0;
    foreach ($commentres as $crow) {
        $cdata            = [];
        $cdata['morecom'] = ($morecom == 1 ? true : false);
        if ($crow['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
            $cdata['editcomlink'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i>', 'index.php?p=banlist&comment=' . $rowId . '&ctype=' . $type . '&cid=' . $crow['cid'], 'Edit Comment');
            if ($userbank->HasAccess(ADMIN_OWNER)) {
                $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $crow['cid'] . ",'" . $type . "',-1);\"><i class='fas fa-trash fa-lg'></i></a>";
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
        $comments[] = $cdata;
    }
    return $comments;
}
