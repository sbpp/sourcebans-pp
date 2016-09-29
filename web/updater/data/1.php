<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_settings` WHERE setting = 'config.exportpublic'");
$ret  = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_mods` ADD `enabled` TINYINT NOT NULL DEFAULT '1'");
return (!empty($ret));