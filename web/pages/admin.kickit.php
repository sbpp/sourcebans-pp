<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

include_once '../init.php';

global $userbank, $theme;

// Apache hands this iframe page a CWD of /pages/ (the script's
// directory), but Smarty's default compile-dir path is the relative
// `./templates_c/` — which would resolve under /pages/ here and fail
// because only the web-root /templates_c/ is writable. Re-chdir to
// the web root so Smarty (and any other relative path) lines up with
// what index.php-routed pages see. ROOT is defined by ../init.php.
chdir(ROOT);

if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddBan))) {
    echo "No Access";
    die();
}

$servers = $GLOBALS['PDO']->query("SELECT ip, port, rcon FROM `:prefix_servers` WHERE enabled = 1 ORDER BY modid, sid")->resultset();
$serverlinks = [];
$num         = 0;
foreach ($servers as $server) {
    $serverlinks[] = ['num' => $num, 'ip' => (string)$server['ip'], 'port' => (string)$server['port']];
    $num++;
}

// #1439 — allowlist the `mode` URL param so the iframe can tell the
// post-ban-kick flow (admin.bans.php "Ban Added" success dialog,
// default) apart from the kick-only flow (right-click context menu on
// the public servers page, `&mode=kick`). The handler branches on
// this string to (a) skip the `:prefix_bans` UPDATE that's only
// meaningful when a ban row exists, and (b) emit the matching rcon
// kick message ("kicked" vs "banned"). Anything other than 'kick'
// falls through to 'ban' (backward-compat — pre-#1439 callers don't
// supply the param).
$rawMode = (string) ($_GET['mode'] ?? 'ban');
$mode    = $rawMode === 'kick' ? 'kick' : 'ban';

$theme->setLeftDelimiter('-{');
$theme->setRightDelimiter('}-');
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\KickitView(
    csrf_token: CSRF::token(),
    total: count($serverlinks),
    check: (string) ($_GET['check'] ?? ''),
    type: (int) ($_GET['type'] ?? 0),
    mode: $mode,
    servers: $serverlinks,
));
$theme->setLeftDelimiter('{');
$theme->setRightDelimiter('}');
