<?php
$alter = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_mods` ADD `steam_universe` TINYINT NOT NULL DEFAULT '0' AFTER `modfolder`, ADD INDEX ( `steam_universe` );");
if (!$alter)
    return false;
$temp = $GLOBALS['db']->Execute('UPDATE `' . DB_PREFIX . '_mods` SET steam_universe = 1 WHERE modfolder = "left4dead" OR modfolder = "left4dead2" OR modfolder = "csgo";');
if (!$temp)
    return false;
$alter = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_mods` ADD UNIQUE (`modfolder`), ADD UNIQUE(`name`);");
if (!$alter)
    return false;
return true;