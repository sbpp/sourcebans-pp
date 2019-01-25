<?php
if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

Template::render('core/footer', [
    'git' => (SB_DEV) ? ' | Git: '.SB_GITREV : '',
    'version' => SB_VERSION,
    'query' => $GLOBALS['server_qry']
]);
