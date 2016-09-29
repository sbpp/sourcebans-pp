<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_settings` WHERE setting = 'bans.customreasons';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('bans.customreasons', '');");
    if (!$ret)
        return false;
}

return true;