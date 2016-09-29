<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_settings` WHERE setting = 'config.enableadminrehashing';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.enableadminrehashing', '1');");
    if (!$ret)
        return false;
}
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_settings` WHERE setting = 'protest.emailonlyinvolved';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('protest.emailonlyinvolved', '0');");
    if (!$ret)
        return false;
}
return true;