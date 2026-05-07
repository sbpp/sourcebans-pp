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

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
global $userbank, $theme;

/*
 * #1207 ADM-3 introduced the page-level ToC for admin-admins; #1239
 * dropped the trailing-edge `AdminTabs([...], $userbank, $theme)` call
 * that still rendered the broken openTab() button strip above the ToC.
 * Navigation on this page now flows entirely through the sticky ToC
 * sidebar (anchor jumps to `#search`, `#admins`, `#add-admin`,
 * `#overrides`, `#add-override`) — see `web/themes/default/page_toc.tpl`
 * and the `.page-toc-*` rules in `theme.css`.
 */

$AdminsPerPage = SB_BANS_PER_PAGE;
$page = 1;
if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page = intval($_GET['page']);
}

/*
 * #1207 ADM-4: collapse the eight per-row Search buttons into one
 * combined filter form and AND the populated filters server-side.
 *
 * The new wire format reads each filter from its own query parameter
 * (`name`, `steamid`, `steam_match`, `admemail`, `webgroup`,
 * `srvadmgroup`, `srvgroup`, `admwebflag[]`, `admsrvflag[]`, `server`)
 * so a single GET submit carries the full filter snapshot. URL-shareable
 * searches are preserved by the legacy-shim block below: any incoming
 * `?advType=…&advSearch=…` is translated into the new shape so old
 * bookmarks and cross-page links keep working.
 *
 * Server-side filters are AND-combined: a request with two non-empty
 * filters narrows to admins matching both, which matches the typical
 * search-UI mental model the audit called out (an admin in group X
 * AND with permission Y). The pre-fix shape only honoured one filter
 * at a time and silently dropped the rest.
 */
$where        = "";
$whereParams  = [];
$joinAdminsServersGroups = false;
$joinServersGroups       = false;
/** @var array<string, string|list<string>> $activeFilters */
$activeFilters = [];

// Legacy `advType=…&advSearch=…` URLs still flow through any external
// links / bookmarks. Translate them into the modern shape so the rest
// of this handler operates on a single, consistent input source.
if (isset($_GET['advType']) && isset($_GET['advSearch']) && $_GET['advSearch'] !== '') {
    /** @var string $legacyType */
    $legacyType  = (string) $_GET['advType'];
    /** @var string $legacyValue */
    $legacyValue = (string) $_GET['advSearch'];
    switch ($legacyType) {
        case 'name':
        case 'admemail':
        case 'webgroup':
        case 'srvadmgroup':
        case 'srvgroup':
        case 'server':
        case 'steamid':
            if (!isset($_GET[$legacyType]) || $_GET[$legacyType] === '') {
                $_GET[$legacyType] = $legacyValue;
            }
            break;
        case 'steam':
            // The legacy form distinguished exact (`steamid`) from
            // partial (`steam`) matches as two distinct advTypes. The
            // modern form folds both onto `steamid` + `steam_match`.
            if (!isset($_GET['steamid']) || $_GET['steamid'] === '') {
                $_GET['steamid']     = $legacyValue;
                $_GET['steam_match'] = '1';
            }
            break;
        case 'admwebflag':
        case 'admsrvflag':
            if (!isset($_GET[$legacyType]) || $_GET[$legacyType] === '' || (is_array($_GET[$legacyType]) && empty($_GET[$legacyType]))) {
                $_GET[$legacyType] = explode(',', $legacyValue);
            }
            break;
    }
}

// 1) Login name (exact or partial against ADM.user).
//    `name_match` was added in #1231; default is partial ('1') so
//    pre-#1231 URLs (`?name=alice` with no name_match) keep their
//    substring semantics. `0` flips to exact.
if (!empty($_GET['name']) && is_string($_GET['name'])) {
    $partialName = !isset($_GET['name_match']) || (string) $_GET['name_match'] !== '0';
    if ($partialName) {
        $where        .= " AND ADM.user LIKE ?";
        $whereParams[] = '%' . $_GET['name'] . '%';
    } else {
        $where        .= " AND ADM.user = ?";
        $whereParams[] = $_GET['name'];
    }
    $activeFilters['name']       = (string) $_GET['name'];
    $activeFilters['name_match'] = $partialName ? '1' : '0';
}

// 2) Steam ID (exact or partial against ADM.authid).
if (!empty($_GET['steamid']) && is_string($_GET['steamid'])) {
    $partial = isset($_GET['steam_match']) && (string) $_GET['steam_match'] === '1';
    if ($partial) {
        $where        .= " AND ADM.authid LIKE ?";
        $whereParams[] = '%' . $_GET['steamid'] . '%';
    } else {
        $where        .= " AND ADM.authid = ?";
        $whereParams[] = $_GET['steamid'];
    }
    $activeFilters['steamid']     = (string) $_GET['steamid'];
    $activeFilters['steam_match'] = $partial ? '1' : '0';
}

// 3) E-mail (exact or partial; `admemail_match` was added in #1231,
//    same default-partial shape as `name_match`). Gated on the same
//    flag the search box gates the input field on so URL forgery
//    can't bypass the visibility gate.
if (!empty($_GET['admemail']) && is_string($_GET['admemail']) && $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS)) {
    $partialEmail = !isset($_GET['admemail_match']) || (string) $_GET['admemail_match'] !== '0';
    if ($partialEmail) {
        $where        .= " AND ADM.email LIKE ?";
        $whereParams[] = '%' . $_GET['admemail'] . '%';
    } else {
        $where        .= " AND ADM.email = ?";
        $whereParams[] = $_GET['admemail'];
    }
    $activeFilters['admemail']       = (string) $_GET['admemail'];
    $activeFilters['admemail_match'] = $partialEmail ? '1' : '0';
}

// 4) Web group (`:prefix_groups.gid` -> `:prefix_admins.gid`).
if (!empty($_GET['webgroup']) && is_scalar($_GET['webgroup'])) {
    $where                       .= " AND ADM.gid = ?";
    $whereParams[]                = (int) $_GET['webgroup'];
    $activeFilters['webgroup']    = (string) $_GET['webgroup'];
}

// 5) SourceMod admin group (matched by name against ADM.srv_group).
if (!empty($_GET['srvadmgroup']) && is_string($_GET['srvadmgroup'])) {
    $where                          .= " AND ADM.srv_group = ?";
    $whereParams[]                   = $_GET['srvadmgroup'];
    $activeFilters['srvadmgroup']    = $_GET['srvadmgroup'];
}

// 6) Server group (`:prefix_groups.gid` -> `:prefix_admins_servers_groups.srv_group_id`).
if (!empty($_GET['srvgroup']) && is_scalar($_GET['srvgroup'])) {
    $joinAdminsServersGroups       = true;
    $where                        .= " AND ASG.srv_group_id = ?";
    $whereParams[]                 = (int) $_GET['srvgroup'];
    $activeFilters['srvgroup']     = (string) $_GET['srvgroup'];
}

// 7) Web permission flags (multi). Submitted as either `admwebflag[]=X&admwebflag[]=Y`
// or the legacy comma-joined string. Resolve each name to its bit and
// OR-combine into a single bitmask, then narrow ADM.aid to admins with
// access — same per-admin permission probe the legacy code used.
$rawWebFlags = $_GET['admwebflag'] ?? null;
if (is_string($rawWebFlags)) {
    $rawWebFlags = explode(',', $rawWebFlags);
}
if (is_array($rawWebFlags)) {
    /** @var list<string> $webFlagNames */
    $webFlagNames = [];
    foreach ($rawWebFlags as $candidate) {
        if (is_string($candidate) && preg_match('/^ADMIN_[A-Z_]+$/', $candidate) && defined($candidate)) {
            $webFlagNames[] = $candidate;
        }
    }
    if (!empty($webFlagNames)) {
        $flagBits = array_map(fn(string $name): int => (int) constant($name), $webFlagNames);
        $flagstring = implode('|', $flagBits);
        $alladmins = $GLOBALS['PDO']->query("SELECT aid FROM `:prefix_admins` WHERE aid > 0")->resultset();
        $accessAids = [];
        foreach ($alladmins as $row) {
            if ($userbank->HasAccess($flagstring, $row['aid'])) {
                $accessAids[] = (int) $row['aid'];
            }
        }
        if (empty($accessAids)) {
            $where .= " AND 0";
        } else {
            $placeholders  = implode(',', array_fill(0, count($accessAids), '?'));
            $where        .= " AND ADM.aid IN($placeholders)";
            $whereParams   = array_merge($whereParams, $accessAids);
        }
        $activeFilters['admwebflag'] = $webFlagNames;
    }
}

// 8) Server permission flags (multi).
$rawSrvFlags = $_GET['admsrvflag'] ?? null;
if (is_string($rawSrvFlags)) {
    $rawSrvFlags = explode(',', $rawSrvFlags);
}
if (is_array($rawSrvFlags)) {
    /** @var list<string> $srvFlagNames */
    $srvFlagNames = [];
    foreach ($rawSrvFlags as $candidate) {
        if (is_string($candidate) && preg_match('/^SM_[A-Z_]+$/', $candidate) && defined($candidate)) {
            $srvFlagNames[] = $candidate;
        }
    }
    if (!empty($srvFlagNames)) {
        $flagBits = array_map(fn(string $name): int => (int) constant($name), $srvFlagNames);
        $alladmins = $GLOBALS['PDO']->query("SELECT aid, authid FROM `:prefix_admins` WHERE aid > 0")->resultset();
        $accessAids = [];
        foreach ($alladmins as $row) {
            $matched = false;
            foreach ($flagBits as $fla) {
                if ($userbank->HasAccess($fla, $row['authid'])) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched && $userbank->HasAccess(SM_ROOT, $row['authid'])) {
                $matched = true;
            }
            if ($matched) {
                $accessAids[] = (int) $row['aid'];
            }
        }
        if (empty($accessAids)) {
            $where .= " AND 0";
        } else {
            $placeholders  = implode(',', array_fill(0, count($accessAids), '?'));
            $where        .= " AND ADM.aid IN($placeholders)";
            $whereParams   = array_merge($whereParams, $accessAids);
        }
        $activeFilters['admsrvflag'] = $srvFlagNames;
    }
}

// 9) Server (`:prefix_servers.sid`). Either via direct admin->server
// access (`ASG.server_id`) or via the server's group attachment
// (`SGS.server_id`). Reuses the ASG join above when "server group" is
// also active so the WHERE still ANDs cleanly.
if (!empty($_GET['server']) && is_scalar($_GET['server'])) {
    $joinAdminsServersGroups   = true;
    $joinServersGroups         = true;
    $where                    .= " AND (ASG.server_id = ? OR SGS.server_id = ?)";
    $whereParams[]             = (int) $_GET['server'];
    $whereParams[]             = (int) $_GET['server'];
    $activeFilters['server']   = (string) $_GET['server'];
}

$join = "";
if ($joinAdminsServersGroups) {
    $join .= " LEFT JOIN `:prefix_admins_servers_groups` AS ASG ON ASG.admin_id = ADM.aid";
}
if ($joinServersGroups) {
    $join .= " LEFT JOIN `:prefix_servers_groups` AS SGS ON SGS.group_id = ASG.srv_group_id";
}

// Pagination needs the active-filter snapshot baked into every "next"
// page link so subsequent navigation preserves the search.
// `http_build_query` handles array values (`admwebflag[]=…&admwebflag[]=…`)
// natively, so multi-select filters round-trip without manual joining.
$advSearchString = empty($activeFilters) ? '' : '&' . http_build_query($activeFilters);
$admins = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_admins` AS ADM".$join." WHERE ADM.aid > 0".$where." ORDER BY user LIMIT " . intval(($page-1) * $AdminsPerPage) . "," . intval($AdminsPerPage))->resultset($whereParams);
// The server filter joins through `:prefix_admins_servers_groups` and
// `:prefix_servers_groups`, which can produce duplicate ADM.aid rows
// when an admin reaches the same server via multiple paths. Dedupe
// here to keep the rendered list one-row-per-admin.
if (isset($activeFilters['server'])) {
    $aadm = [];
    $num = 0;
    foreach ($admins as $aadmin) {
        if (!in_array($aadmin['aid'], $aadm)) {
            $aadm[] = $aadmin['aid'];
        } else {
            unset($admins[$num]);
        }
        $num++;
    }
}

$query = $GLOBALS['PDO']->query("SELECT COUNT(ADM.aid) AS cnt FROM `:prefix_admins` AS ADM".$join." WHERE ADM.aid > 0".$where)->single($whereParams);
$admin_count = $query['cnt'];

if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page = intval($_GET['page']);
}

$AdminsStart = intval(($page - 1) * $AdminsPerPage);
$AdminsEnd   = intval($AdminsStart + $AdminsPerPage);
if ($AdminsEnd > $admin_count) {
    $AdminsEnd = $admin_count;
}

// List Page
$admin_list = [];
foreach ($admins as $admin) {
    $admin['immunity']     = $userbank->GetProperty("srv_immunity", $admin['aid']);
    $admin['web_group']    = $userbank->GetProperty("group_name", $admin['aid']);
    $admin['server_group'] = $userbank->GetProperty("srv_groups", $admin['aid']);
    if (empty($admin['web_group']) || $admin['web_group'] == " ") {
        $admin['web_group'] = "No Group/Individual Permissions";
    }
    if (empty($admin['server_group']) || $admin['server_group'] == " ") {
        $admin['server_group'] = "No Group/Individual Permissions";
    }
    $GLOBALS['PDO']->query("SELECT count(authid) AS num FROM `:prefix_bans` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $admin['aid']);
    $num               = $GLOBALS['PDO']->single();
    $admin['bancount'] = $num['num'];

    $GLOBALS['PDO']->query("SELECT count(B.bid) AS num FROM `:prefix_bans` AS B WHERE aid = :aid AND NOT EXISTS (SELECT D.demid FROM `:prefix_demos` AS D WHERE D.demid = B.bid)");
    $GLOBALS['PDO']->bind(':aid', $admin['aid']);
    $nodem                = $GLOBALS['PDO']->single();
    $admin['aid']         = $admin['aid'];
    $admin['nodemocount'] = $nodem['num'];

    $admin['name']               = stripslashes($admin['user']);
    $admin['server_flag_string'] = SmFlagsToSb($userbank->GetProperty("srv_flags", $admin['aid']));
    $admin['web_flag_string']    = BitToString($userbank->GetProperty("extraflags", $admin['aid']));

    $lastvisit = $userbank->GetProperty("lastvisit", $admin['aid']);
    if (!$lastvisit) {
        $admin['lastvisit'] = "Never";
    } else {
        $admin['lastvisit'] = Config::time($userbank->GetProperty("lastvisit", $admin['aid']));
    }
    array_push($admin_list, $admin);
}

if ($page > 1) {
    $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=admins&page=" . ($page - 1) . $advSearchString);
} else {
    $prev = "";
}
if ($AdminsEnd < $admin_count) {
    $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=admins&page=" . ($page + 1) . $advSearchString);
} else {
    $next = "";
}

//=================[ Start Layout ]==================================
$admin_nav = 'displaying&nbsp;' . $AdminsStart . '&nbsp;-&nbsp;' . $AdminsEnd . '&nbsp;of&nbsp;' . $admin_count . '&nbsp;results';

if (strlen($prev) > 0) {
    $admin_nav .= ' | <b>' . $prev . '</b>';
}
if (strlen($next) > 0) {
    $admin_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($admin_count / $AdminsPerPage);
if ($pages > 1) {
    // The dropdown's `onchange` navigates to the keyed page directly.
    // The legacy code reached for `changePage(this, 'A', advSearch,
    // advType)` from `sourcebans.js`, but the bulk file was removed at
    // #1123 D1 — clicking the picker therefore raised `ReferenceError`
    // on origin/main. Inline a vanilla snippet that builds the URL
    // from the same `$advSearchString` the prev/next links use, so the
    // multi-filter snapshot round-trips through the picker too.
    //
    // `htmlspecialchars` on the base URL is what stops the ADM-4
    // multi-filter `&admwebflag[]=…` from breaking out of the
    // attribute string.
    $baseUrl    = 'index.php?p=admin&c=admins' . $advSearchString . '&page=';
    $baseUrlAttr = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
    $admin_nav .= '&nbsp;<select onchange="window.location.href=\'' . $baseUrlAttr . '\'+encodeURIComponent(this.value);"'
        . ' aria-label="Jump to page">';
    for ($i = 1; $i <= $pages; $i++) {
        if (isset($_GET['page']) && $i === (int) $_GET['page']) {
            $admin_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
            continue;
        }
        $admin_nav .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $admin_nav .= '</select>';
}

// Permission-filtered ToC entries for the sticky page-level ToC. We
// build this in the FIRST template's view (the list view) because the
// ToC partial is included from page_admin_admins_list.tpl. Sections
// gated on a permission only appear when the dispatcher would paint
// them — a ToC entry pointing at a section that wouldn't render is a
// dead link by definition (see the same rule in AGENTS.md "Page-level
// table of contents").
$canListAdmins   = $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_ADMINS);
$canAddAdmins    = $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_ADMINS);
$canEditAdmins   = $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS);
$canDeleteAdmins = $userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_ADMINS);

/*
 * #1266 — each ToC entry carries a Lucide `icon` so the rendered
 * link gets the same iconed-pill visual weight as the Pattern A
 * sidebar (`core/admin_sidebar.tpl`). Icons follow the Pattern A
 * vocabulary (see admin.servers.php / admin.groups.php / etc):
 * `users` for the admin list, `user-plus` / `plus` for create,
 * `shield` for permission overrides.
 */
/** @var list<array{slug: string, label: string, icon: string}> $tocEntries */
$tocEntries = [];
if ($canListAdmins) {
    $tocEntries[] = ['slug' => 'search', 'label' => 'Search',      'icon' => 'search'];
    $tocEntries[] = ['slug' => 'admins', 'label' => 'Admins list', 'icon' => 'users'];
}
if ($canAddAdmins) {
    $tocEntries[] = ['slug' => 'add-admin',    'label' => 'Add admin',    'icon' => 'user-plus'];
    $tocEntries[] = ['slug' => 'overrides',    'label' => 'Overrides',    'icon' => 'shield'];
    $tocEntries[] = ['slug' => 'add-override', 'label' => 'Add override', 'icon' => 'plus'];
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminAdminsListView(
    // We pass the can_* gates explicitly rather than splatting
    // ...Perms::for($userbank): the helper's @return array<string,bool>
    // doesn't expose a constant key shape, so PHPStan can't prove the
    // splat fills these named params and reports argument.missing.
    // Listing them by hand keeps the page handler PHPStan-clean while
    // the View itself still follows the can_* convention from
    // Sbpp\View\View's class-level docblock.
    can_list_admins: $canListAdmins,
    can_add_admins: $canAddAdmins,
    can_edit_admins: $canEditAdmins,
    can_delete_admins: $canDeleteAdmins,
    admin_count: (int) $admin_count,
    admin_nav: (string) $admin_nav,
    admins: $admin_list,
    toc_id: 'admin-admins',
    toc_label: 'Admins page sections',
    toc_entries: $tocEntries,
));

// Add Page
$group_list              = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_groups` WHERE type = '3'")->resultset();
$servers                 = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_servers`")->resultset();
$server_admin_group_list = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_srvgroups`")->resultset();
$server_group_list       = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_groups` WHERE type != 3")->resultset();
$server_list             = [];
$serverscript            = "<script type=\"text/javascript\">";
foreach ($servers as $server) {
    $serverscript .= "LoadServerHost('" . $server['sid'] . "', 'id', 'sa" . $server['sid'] . "');";
    $info['sid']  = $server['sid'];
    $info['ip']   = $server['ip'];
    $info['port'] = $server['port'];
    array_push($server_list, $info);
}
$serverscript .= "</script>";

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminAdminsAddView(
    // See AdminAdminsListView above for why we don't splat Perms::for().
    can_add_admins: $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_ADMINS),
    group_list: $group_list,
    server_list: $server_list,
    server_admin_group_list: $server_admin_group_list,
    server_group_list: $server_group_list,
    server_script: $serverscript,
));

// Overrides — extracted into its own handler in #1123 B17 so the
// "single-template, single-View" pattern lines up with one .tpl per
// theme and one Sbpp\View\AdminOverridesView. The require keeps the
// existing AdminTabs ?p=admin&c=admins URL working unchanged.
require(TEMPLATES_PATH . "/admin.overrides.php");
