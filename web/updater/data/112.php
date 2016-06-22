<?php
	$ret = $GLOBALS['db']->Execute("DELETE FROM `".DB_PREFIX."_settings` WHERE setting = 'config.uri'");
	if(!$ret)
		return false;
		
	$ret1 = $GLOBALS['db']->Execute("DELETE FROM `".DB_PREFIX."_settings` WHERE setting = 'config.publicexport'");
	if(!$ret1)
		return false;
		
	$temp = $GLOBALS['db']->GetAll("SELECT * FROM `".DB_PREFIX."_settings` WHERE setting = 'dash.lognopopup'");
	if(count($temp) == 0)
	{
		$ret2 = $GLOBALS['db']->Execute("INSERT INTO `".DB_PREFIX."_settings` (`setting`, `value`) VALUES ('dash.lognopopup', '0')");
		if(!$ret2)
			return false;
	}
	
	$temp = $GLOBALS['db']->GetAll("SELECT * FROM `".DB_PREFIX."_settings` WHERE setting = 'config.exportpublic'");
	if(count($temp) == 0)
	{
		$ret3 = $GLOBALS['db']->Execute("INSERT INTO `".DB_PREFIX."_settings` (`setting`, `value`) VALUES ('config.exportpublic', '0')");
		if(!$ret3)
			return false;
	}
	
	$admins = $GLOBALS['db']->GetAll("SELECT * FROM   ".DB_PREFIX."_admins");
	foreach ($admins as $adm)
	{
		$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET lastvisit = '0000-00-00 00:00:00' WHERE aid = " . $adm['aid']);
	}
	
	$ret4 = $GLOBALS['db']->Execute("ALTER TABLE `".DB_PREFIX."_admins` CHANGE `lastvisit` `lastvisit` INT( 11 ) NULL DEFAULT NULL");
	if(!$ret4)
			return false;
	
	$ret5 = $GLOBALS['db']->Execute("ALTER TABLE `".DB_PREFIX."_bans` ADD `type` TINYINT NOT NULL DEFAULT '0'");
	if(!$ret5)
			return false;

	$ret6 = $GLOBALS['db']->Execute("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."_comments` (
									  `cid` int(6) NOT NULL auto_increment,
									  `bid` int(6) NOT NULL,
									  `type` varchar(1) NOT NULL,
									  `aid` int(6) NOT NULL,
									  `commenttxt` longtext NOT NULL,
									  `added` datetime NOT NULL,
									  `editaid` int(6) default NULL,
									  `edittime` datetime default NULL,
									  KEY `cid` (`cid`)
									) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");
	if(!$ret6)
			return false;
	
	$ret7 = $GLOBALS['db']->Execute("ALTER TABLE ".DB_PREFIX."_mods ADD enabled TINYINT NOT NULL DEFAULT '1'");
	if(!$ret7)
			return false;
		

	$bans = $GLOBALS['db']->GetAll("SELECT bid
                     FROM   ".DB_PREFIX."_bans AS ba
                     WHERE  RemoveType = 'U'
                       AND  RemovedON IS NOT NULL
                       AND  (SELECT COUNT(*)
                             FROM   ".DB_PREFIX."_bans AS ba2
                             WHERE  ba.RemoveType = ba2.RemoveType
                               AND  ba.RemovedOn  = ba2.RemovedOn) > 1");

	foreach ($bans as $ban)
	{
		$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_bans
									SET    RemovedOn = NULL, RemoveType = NULL
									WHERE  bid = " . $ban['bid']);
	}

	return true;
?>

