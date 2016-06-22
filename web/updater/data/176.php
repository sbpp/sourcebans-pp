<?php
	$temp = $GLOBALS['db']->GetAll("SELECT * FROM `".DB_PREFIX."_settings` WHERE setting = 'config.enablefriendsbanning';");
	if(count($temp) == 0)
	{
		$ret = $GLOBALS['db']->Execute("INSERT INTO `".DB_PREFIX."_settings` (`setting`, `value`) VALUES ('config.enablefriendsbanning', '0');");
		if(!$ret)
			return false;
	}
	
	$temp = $GLOBALS['db']->GetAll("SELECT * FROM `".DB_PREFIX."_mods` WHERE `modfolder` = 'garrysmod';");
	if(count($temp) == 0)
	{
		$ret = $GLOBALS['db']->Execute('INSERT INTO `'.DB_PREFIX.'_mods` (`name`, `icon`, `modfolder`) VALUES ("Garry\'s Mod", "gmod.png", "garrysmod");');
		if(!$ret)
			return false;
	}
	return true;
?>