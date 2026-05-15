<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

include_once '../init.php';

global $userbank, $theme;

// See admin.kickit.php for why this chdir() is needed.
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
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\BlockitView(
    csrf_token: CSRF::token(),
    total: count($serverlinks),
    check: (string) ($_GET['check'] ?? ''),
    type: (int) ($_GET['type'] ?? 0),
    length: (int) ($_GET['length'] ?? 0),
    servers: $serverlinks,
));
$theme->setLeftDelimiter('{');
$theme->setRightDelimiter('}');
