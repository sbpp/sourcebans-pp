<?php
    $temp = $GLOBALS['db']->GetAll("SELECT * FROM `".DB_PREFIX."_settings` WHERE setting = 'banlist.hideplayerips'");
	if(count($temp) == 0)
	{
		$ret2 = $GLOBALS['db']->Execute("INSERT INTO `".DB_PREFIX."_settings` (`setting`, `value`) VALUES ('banlist.hideplayerips', '0')");
		if(!$ret2)
			return false;
	}
    return true;
?>