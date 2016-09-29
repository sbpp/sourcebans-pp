<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_mods` WHERE `modfolder` = 'eye';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute('INSERT INTO `' . DB_PREFIX . '_mods` (`name`, `icon`, `modfolder`) VALUES ("E.Y.E: Divine Cybermancy", "eye.png", "eye");');
    if (!$ret)
        return false;
}

$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_mods` WHERE `modfolder` = 'nucleardawn';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute('INSERT INTO `' . DB_PREFIX . '_mods` (`name`, `icon`, `modfolder`) VALUES ("Nuclear Dawn", "nucleardawn.png", "nucleardawn");');
    if (!$ret)
        return false;
}

$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_mods` WHERE `modfolder` = 'alienswarm';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute('INSERT INTO `' . DB_PREFIX . '_mods` (`name`, `icon`, `modfolder`) VALUES ("Alien Swarm", "alienswarm.png", "alienswarm");');
    if (!$ret)
        return false;
}

$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_mods` WHERE `modfolder` = 'cspromod';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute('INSERT INTO `' . DB_PREFIX . '_mods` (`name`, `icon`, `modfolder`) VALUES ("CSPromod", "cspromod.png", "cspromod");');
    if (!$ret)
        return false;
}
return true;