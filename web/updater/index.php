<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

define('IS_UPDATE', true);
include "../init.php";

global $theme;

require_once('Updater.php');
$updater = new Updater($GLOBALS['PDO']);

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\UpdaterView(
    updates: array_values(array_map('strval', $updater->getMessageStack())),
));

//clear compiled themes
$cachedir = dir(SB_CACHE);
while (($entry = $cachedir->read()) !== false) {
    if (is_file($cachedir->path . $entry)) {
        unlink($cachedir->path . $entry);
    }
}
$cachedir->close();
