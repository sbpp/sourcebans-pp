<?php
$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_protests` ADD `pip` varchar(64) NOT NULL;");
if (!$ret)
    return false;

return true;