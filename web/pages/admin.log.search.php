<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

global $theme;

$admin_list = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_admins` ORDER BY user ASC")->resultset();

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminLogSearchView(
    admin_list: $admin_list,
));
