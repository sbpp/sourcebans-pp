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

$admin_list = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_admins` ORDER BY user ASC")->resultset();

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
$server_list  = [];
$serverscript = '<script>(function(){'
    . 'if (typeof sb === "undefined" || !sb || !sb.api || typeof Actions === "undefined") return;';
foreach ($server_rows as $row) {
    $sid           = (int) $row['sid'];
    $server_list[] = [
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

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansSearchView(
    admin_list:    $admin_list,
    server_list:   $server_list,
    server_script: $serverscript,
    hideplayerips: (Config::getBool('banlist.hideplayerips') && !$userbank->is_admin()),
    hideadminname: (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()),
    is_admin:      $userbank->is_admin(),
));
