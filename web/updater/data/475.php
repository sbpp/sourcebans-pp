<?php
$create = $GLOBALS['db']->Execute("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "_comms` (
			`bid` int(6) NOT NULL AUTO_INCREMENT,
			`authid` varchar(64) NOT NULL,
			`name` varchar(128) NOT NULL DEFAULT 'unnamed',
			`created` int(11) NOT NULL DEFAULT '0',
			`ends` int(11) NOT NULL DEFAULT '0',
			`length` int(10) NOT NULL DEFAULT '0',
			`reason` text NOT NULL,
			`aid` int(6) NOT NULL DEFAULT '0',
			`adminIp` varchar(32) NOT NULL DEFAULT '',
			`sid` int(6) NOT NULL DEFAULT '0',
			`RemovedBy` int(8) DEFAULT NULL,
			`RemoveType` varchar(3) DEFAULT NULL,
			`RemovedOn` int(11) DEFAULT NULL,
			`type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1 - Mute, 2 - Gag',
			`ureason` text,
			PRIMARY KEY (`bid`),
			KEY `sid` (`sid`),
			KEY `type` (`type`),
			KEY `RemoveType` (`RemoveType`),
			KEY `authid` (`authid`),
			KEY `created` (`created`),
			KEY `aid` (`aid`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
if (!$create)
    return false;
return true;