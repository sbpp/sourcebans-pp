<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_settings` WHERE setting = 'template.title';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('template.title', 'SourceBans');");
    if (!$ret)
        return false;
}

$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_submissions` ADD `server` tinyint(3);");
if (!$ret)
    return false;

return true;