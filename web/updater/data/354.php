<?php
	$temp = $GLOBALS['db']->GetAll("SELECT * FROM `".DB_PREFIX."_mods` WHERE `modfolder` = 'csgo';");
	if(count($temp) == 0)
	{
		$ret = $GLOBALS['db']->Execute('INSERT INTO `'.DB_PREFIX.'_mods` (`name`, `icon`, `modfolder`) VALUES ("Counter-Strike: Global Offensive", "csgo.png", "csgo");');
		if(!$ret)
			return false;
	}
	return true;
?>