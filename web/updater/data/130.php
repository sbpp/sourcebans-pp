<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_settings` WHERE setting = 'config.enablekickit'");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.enablekickit', '1')");
    if (!$ret)
        return false;
}

$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_settings` WHERE setting = 'config.dateformat'");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.dateformat', '')");
    if (!$ret)
        return false;
}

return true;