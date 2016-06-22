<?php
    $alter = $GLOBALS['db']->Execute('ALTER TABLE `'.DB_PREFIX.'_admins` CHANGE `validate` `validate` VARCHAR( 128 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;');
    if(!$alter)
        return false;
    $temp = $GLOBALS['db']->Execute('UPDATE `'.DB_PREFIX.'_admins` SET validate = NULL;');
    if(!$temp)
        return false;
	return true;
?>