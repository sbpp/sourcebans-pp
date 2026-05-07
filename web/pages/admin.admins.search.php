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

// Server list, plus a server-built `<script>` blob preserved for any
// third-party theme that forked the pre-v2.0.0 default and consumes
// `{$server_script nofilter}` to populate each
// `<option id="ssSID">Loading…</option>`. The shipped template owns
// its own inline initializer (and carries an `{if false}…{/if}`
// parity reference to `$server_script` to keep SmartyTemplateRule's
// "unused property" check green) and ignores the blob built here.
//
// Inputs to the blob are server-controlled integers (`:prefix_servers.sid`)
// only — no user input flows into the emitted JS. Safe under the
// `{$server_script nofilter}` annotation.
$server_rows  = $GLOBALS['PDO']->query("SELECT sid, ip, port FROM `:prefix_servers` WHERE enabled = 1")->resultset();
$servers      = [];
$serverscript = '<script>(function(){'
    . 'if (typeof sb === "undefined" || !sb || !sb.api || typeof Actions === "undefined") return;';
foreach ($server_rows as $row) {
    $sid          = (int) $row['sid'];
    $servers[]    = [
        'sid'  => $sid,
        'ip'   => (string) $row['ip'],
        'port' => (int) $row['port'],
    ];
    $serverscript .= 'sb.api.call(Actions.ServersHostPlayers,{sid:' . $sid . ',trunchostname:200}).then(function(r){'
        . 'var el=document.getElementById("ss' . $sid . '");'
        . 'if(!el)return;'
        . 'if(!r||!r.ok||!r.data){el.textContent="Offline";return;}'
        . 'var d=r.data;'
        . 'if(d.error==="connect"){el.textContent="Offline ("+d.ip+":"+d.port+")";return;}'
        . 'el.textContent=(d.hostname||"")+" ("+d.ip+":"+d.port+")";'
        . '});';
}
$serverscript .= '})();</script>';

$webgroup_list = $GLOBALS['PDO']->query("SELECT gid, name FROM `:prefix_groups` WHERE type = '1'")->resultset();
$webgroups     = [];
foreach ($webgroup_list as $row) {
    $webgroups[] = [
        'gid'  => $row['gid'],
        'name' => (string) $row['name'],
    ];
}

$srvadmgroup_list = $GLOBALS['PDO']->query("SELECT name FROM `:prefix_srvgroups` ORDER BY name ASC")->resultset();
$srvadmgroups     = [];
foreach ($srvadmgroup_list as $row) {
    $srvadmgroups[] = ['name' => (string) $row['name']];
}

$srvgroup_list = $GLOBALS['PDO']->query("SELECT gid, name FROM `:prefix_groups` WHERE type = '3'")->resultset();
$srvgroups     = [];
foreach ($srvgroup_list as $row) {
    $srvgroups[] = [
        'gid'  => $row['gid'],
        'name' => (string) $row['name'],
    ];
}

// Web-permission catalogue. Submitted as comma-joined `ADMIN_*` constant
// names; admin.admins.php resolves each via `constant()` to build the
// bitmask filter — same wire shape the legacy box emitted.
$webflag = [
    ['name' => 'Root Admin',                 'flag' => 'ADMIN_OWNER'],
    ['name' => 'View admins',                'flag' => 'ADMIN_LIST_ADMINS'],
    ['name' => 'Add admins',                 'flag' => 'ADMIN_ADD_ADMINS'],
    ['name' => 'Edit admins',                'flag' => 'ADMIN_EDIT_ADMINS'],
    ['name' => 'Delete admins',              'flag' => 'ADMIN_DELETE_ADMINS'],
    ['name' => 'View servers',               'flag' => 'ADMIN_LIST_SERVERS'],
    ['name' => 'Add servers',                'flag' => 'ADMIN_ADD_SERVER'],
    ['name' => 'Edit servers',               'flag' => 'ADMIN_EDIT_SERVERS'],
    ['name' => 'Delete servers',             'flag' => 'ADMIN_DELETE_SERVERS'],
    ['name' => 'Add bans',                   'flag' => 'ADMIN_ADD_BAN'],
    ['name' => 'Edit own bans',              'flag' => 'ADMIN_EDIT_OWN_BANS'],
    ['name' => 'Edit groups bans',           'flag' => 'ADMIN_EDIT_GROUP_BANS'],
    ['name' => 'Edit all bans',              'flag' => 'ADMIN_EDIT_ALL_BANS'],
    ['name' => 'Ban protests',               'flag' => 'ADMIN_BAN_PROTESTS'],
    ['name' => 'Ban submissions',            'flag' => 'ADMIN_BAN_SUBMISSIONS'],
    ['name' => 'Delete bans',                'flag' => 'ADMIN_DELETE_BAN'],
    ['name' => 'Unban own bans',             'flag' => 'ADMIN_UNBAN_OWN_BANS'],
    ['name' => 'Unban group bans',           'flag' => 'ADMIN_UNBAN_GROUP_BANS'],
    ['name' => 'Unban all bans',             'flag' => 'ADMIN_UNBAN'],
    ['name' => 'Import bans',                'flag' => 'ADMIN_BAN_IMPORT'],
    ['name' => 'Submission email notifying', 'flag' => 'ADMIN_NOTIFY_SUB'],
    ['name' => 'Protest email notifying',    'flag' => 'ADMIN_NOTIFY_PROTEST'],
    ['name' => 'List groups',                'flag' => 'ADMIN_LIST_GROUPS'],
    ['name' => 'Add groups',                 'flag' => 'ADMIN_ADD_GROUP'],
    ['name' => 'Edit groups',                'flag' => 'ADMIN_EDIT_GROUPS'],
    ['name' => 'Delete groups',              'flag' => 'ADMIN_DELETE_GROUPS'],
    ['name' => 'Web settings',               'flag' => 'ADMIN_WEB_SETTINGS'],
    ['name' => 'List mods',                  'flag' => 'ADMIN_LIST_MODS'],
    ['name' => 'Add mods',                   'flag' => 'ADMIN_ADD_MODS'],
    ['name' => 'Edit mods',                  'flag' => 'ADMIN_EDIT_MODS'],
    ['name' => 'Delete mods',                'flag' => 'ADMIN_DELETE_MODS'],
];

// SourceMod-permission catalogue. Same wire shape (comma-joined `SM_*`
// constant names).
$serverflag = [
    ['name' => 'Full Admin',     'flag' => 'SM_ROOT'],
    ['name' => 'Reserved slot',  'flag' => 'SM_RESERVED_SLOT'],
    ['name' => 'Generic admin',  'flag' => 'SM_GENERIC'],
    ['name' => 'Kick',           'flag' => 'SM_KICK'],
    ['name' => 'Ban',            'flag' => 'SM_BAN'],
    ['name' => 'Un-ban',         'flag' => 'SM_UNBAN'],
    ['name' => 'Slay',           'flag' => 'SM_SLAY'],
    ['name' => 'Map change',     'flag' => 'SM_MAP'],
    ['name' => 'Change cvars',   'flag' => 'SM_CVAR'],
    ['name' => 'Run configs',    'flag' => 'SM_CONFIG'],
    ['name' => 'Admin chat',     'flag' => 'SM_CHAT'],
    ['name' => 'Start votes',    'flag' => 'SM_VOTE'],
    ['name' => 'Password server','flag' => 'SM_PASSWORD'],
    ['name' => 'RCON',           'flag' => 'SM_RCON'],
    ['name' => 'Enable Cheats',  'flag' => 'SM_CHEATS'],
    ['name' => 'Custom flag 1',  'flag' => 'SM_CUSTOM1'],
    ['name' => 'Custom flag 2',  'flag' => 'SM_CUSTOM2'],
    ['name' => 'Custom flag 3',  'flag' => 'SM_CUSTOM3'],
    ['name' => 'Custom flag 4',  'flag' => 'SM_CUSTOM4'],
    ['name' => 'Custom flag 5',  'flag' => 'SM_CUSTOM5'],
    ['name' => 'Custom flag 6',  'flag' => 'SM_CUSTOM6'],
];

/*
 * #1207 ADM-4: pre-fill every input from the URL so the form reflects
 * the request that produced the visible result list. admin.admins.php
 * runs the legacy `advType=…&advSearch=…` shim before this file is
 * loaded, so reading `$_GET` here picks up modern submits *and*
 * translated legacy URLs in the same shape.
 *
 * Multi-select filters accept either the legacy comma-joined string
 * (`?admwebflag=ADMIN_OWNER,ADMIN_LIST_ADMINS`, the pre-fix wire
 * shape) or the new repeated-key array (`?admwebflag[]=ADMIN_OWNER&…`).
 * Both are normalised to a list of validated constant names; the
 * template uses `in_array` to mark the matching option rows.
 */
$rawWebFlag = $_GET['admwebflag'] ?? null;
if (is_string($rawWebFlag)) {
    $rawWebFlag = explode(',', $rawWebFlag);
}
$activeWebFlags = [];
if (is_array($rawWebFlag)) {
    foreach ($rawWebFlag as $f) {
        if (is_string($f) && preg_match('/^ADMIN_[A-Z_]+$/', $f)) {
            $activeWebFlags[] = $f;
        }
    }
}

$rawSrvFlag = $_GET['admsrvflag'] ?? null;
if (is_string($rawSrvFlag)) {
    $rawSrvFlag = explode(',', $rawSrvFlag);
}
$activeSrvFlags = [];
if (is_array($rawSrvFlag)) {
    foreach ($rawSrvFlag as $f) {
        if (is_string($f) && preg_match('/^SM_[A-Z_]+$/', $f)) {
            $activeSrvFlags[] = $f;
        }
    }
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminAdminsSearchView(
    can_editadmin:             $userbank->HasAccess(ADMIN_EDIT_ADMINS | ADMIN_OWNER),
    server_list:               $servers,
    server_script:             $serverscript,
    webgroup_list:             $webgroups,
    srvadmgroup_list:          $srvadmgroups,
    srvgroup_list:             $srvgroups,
    admwebflag_list:           $webflag,
    admsrvflag_list:           $serverflag,
    // Match-mode defaults differ per filter (#1231):
    //   - steam_match defaults to '0' (exact) — typical SteamID
    //     queries are "find this one admin by their full ID".
    //   - name_match / admemail_match default to '1' (partial) so
    //     pre-#1231 URLs (`?name=alice`) keep their substring
    //     behaviour. Adding the toggle widens the UI without
    //     regressing the default.
    active_filter_name:           is_string($_GET['name']           ?? null) ? (string) $_GET['name']           : '',
    active_filter_name_match:     is_scalar($_GET['name_match']     ?? null) ? (string) $_GET['name_match']     : '1',
    active_filter_steamid:        is_string($_GET['steamid']        ?? null) ? (string) $_GET['steamid']        : '',
    active_filter_steam_match:    is_scalar($_GET['steam_match']    ?? null) ? (string) $_GET['steam_match']    : '0',
    active_filter_admemail:       is_string($_GET['admemail']       ?? null) ? (string) $_GET['admemail']       : '',
    active_filter_admemail_match: is_scalar($_GET['admemail_match'] ?? null) ? (string) $_GET['admemail_match'] : '1',
    active_filter_webgroup:       is_scalar($_GET['webgroup']       ?? null) ? (string) $_GET['webgroup']       : '',
    active_filter_srvadmgroup:    is_string($_GET['srvadmgroup']    ?? null) ? (string) $_GET['srvadmgroup']    : '',
    active_filter_srvgroup:       is_scalar($_GET['srvgroup']       ?? null) ? (string) $_GET['srvgroup']       : '',
    active_filter_server:         is_scalar($_GET['server']         ?? null) ? (string) $_GET['server']         : '',
    active_filter_admwebflag:     $activeWebFlags,
    active_filter_admsrvflag:     $activeSrvFlags,
));
