<?php
$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_servers` ADD `enabled` TINYINT( 4 ) NOT NULL DEFAULT '1'");
if (!$ret)
    return false;

return true;