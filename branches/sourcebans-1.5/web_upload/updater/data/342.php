<?php
	$temp = $GLOBALS['db']->GetAll("SELECT * FROM `".DB_PREFIX."_mods` WHERE `modfolder` = 'left4dead2';");
	if(count($temp) == 0)
	{
		$ret = $GLOBALS['db']->Execute('INSERT INTO `'.DB_PREFIX.'_mods` (`name`, `icon`, `modfolder`) VALUES ("Left 4 Dead 2", "l4d2.png", "left4dead2");');
		if(!$ret)
			return false;
	}
	return true;
?>