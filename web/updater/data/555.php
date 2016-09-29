<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_settings` WHERE `setting` = 'config.enablesteamlogin';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute('INSERT INTO `' . DB_PREFIX . '_settings` (`setting`, `value`) VALUES ("config.enablesteamlogin", "0");');
    if (!$ret)
        return false;
}
return true;