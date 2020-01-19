<?php
global $theme;

if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

$theme->assign('git', (SB_DEV) ? ' | Git: '.SB_GITREV : '');
$theme->assign('version', SB_VERSION);
$theme->assign('query', !empty($GLOBALS['server_qry']) ? $GLOBALS['server_qry'] : '');
$theme->display('core/footer.tpl');
