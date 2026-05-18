<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

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

$theme->setLeftDelimiter('-{');
$theme->setRightDelimiter('}-');
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\KickitView(
    csrf_token: CSRF::token(),
    total: count($serverlinks),
    check: (string) ($_GET['check'] ?? ''),
    type: (int) ($_GET['type'] ?? 0),
    servers: $serverlinks,
));
$theme->setLeftDelimiter('{');
$theme->setRightDelimiter('}');
