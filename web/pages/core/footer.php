<?php
global $theme;

if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

// Suffix the footer with the short SHA when we have one. Both empty
// branches (an empty string from a missing-git fallback, or the literal
// `0` from the unresolved sentinel / the test bootstrap) are PHP-falsy,
// so this gates on the presence of an actual SHA without needing a
// separate "is this a dev build?" boolean.
$theme->assign('git', SB_GITREV ? ' | Git: '.SB_GITREV : '');
$theme->assign('version', SB_VERSION);
$theme->assign('query', !empty($GLOBALS['server_qry']) ? $GLOBALS['server_qry'] : '');
$theme->display('core/footer.tpl');
