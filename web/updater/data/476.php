<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_mods` WHERE `modfolder` = 'synergy';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute('INSERT INTO `' . DB_PREFIX . '_mods` (`name`, `icon`, `modfolder`) VALUES ("Synergy", "synergy.png", "synergy");');
    if (!$ret)
        return false;
}
return true;