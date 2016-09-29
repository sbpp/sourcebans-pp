<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_mods` WHERE `modfolder` = 'left4dead';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute('INSERT INTO `' . DB_PREFIX . '_mods` (`name`, `icon`, `modfolder`) VALUES ("Left 4 Dead", "l4d.png", "left4dead");');
    if (!$ret)
        return false;
}
return true;