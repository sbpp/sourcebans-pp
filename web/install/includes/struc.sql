SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE IF NOT EXISTS `{prefix}_admins` (
  `aid` int(6) NOT NULL auto_increment,
  `user` varchar(64) NOT NULL,
  `authid` varchar(64) NOT NULL default '',
  `password` varchar(128) NOT NULL,
  `gid` int(6) NOT NULL,
  `email` varchar(128) NOT NULL,
  `validate` varchar(128) NULL default NULL,
  `extraflags` int(10) NOT NULL,
  `immunity` int(10) NOT NULL default '0',
  `srv_group` varchar(128) default NULL,
  `srv_flags` varchar(64) default NULL,
  `srv_password` varchar(128) default NULL,
  `lastvisit` int(11) NULL,
  PRIMARY KEY  (`aid`),
  UNIQUE KEY `user` (`user`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_admins_servers_groups` (
  `admin_id` int(10) NOT NULL,
  `group_id` int(10) NOT NULL,
  `srv_group_id` int(10) NOT NULL,
  `server_id` int(10) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_banlog` (
  `sid` int(6) NOT NULL,
  `time` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `bid` int(6) NOT NULL,
  PRIMARY KEY  (`sid`,`time`,`bid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_bans` (
  `bid` int(6) NOT NULL auto_increment,
  `ip` varchar(32) default NULL,
  `authid` varchar(64) character set utf8 NOT NULL default '',
  `name` varchar(128) character set utf8 NOT NULL default 'unnamed',
  `created` int(11) NOT NULL default '0',
  `ends` int(11) NOT NULL default '0',
  `length` int(10) NOT NULL default '0',
  `reason` text character set utf8 NOT NULL,
  `aid` int(6) NOT NULL default '0',
  `adminIp` varchar(32) NOT NULL default '',
  `sid` int(6) NOT NULL default '0',
  `country` varchar(4) default NULL,
  `RemovedBy` int(8) NULL,
  `RemoveType` VARCHAR(3) NULL,
  `RemovedOn` int(10) NULL,
  `type` TINYINT NOT NULL DEFAULT '0',
  `ureason` text,
  PRIMARY KEY  (`bid`),
  KEY `sid` (`sid`),
  FULLTEXT KEY `reason` (`reason`),
  FULLTEXT KEY `authid_2` (`authid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_comments` (
  `cid` int(6) NOT NULL auto_increment,
  `bid` int(6) NOT NULL,
  `type` varchar(1) NOT NULL,
  `aid` int(6) NOT NULL,
  `commenttxt` longtext NOT NULL,
  `added` int(11) NOT NULL,
  `editaid` int(6) default NULL,
  `edittime` int(11) default NULL,
  FULLTEXT `commenttxt` (`commenttxt`),
  KEY `cid` (`cid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_demos` (
  `demid` int(6) NOT NULL,
  `demtype` varchar(1) NOT NULL,
  `filename` varchar(128) character set utf8 NOT NULL,
  `origname` varchar(128) NOT NULL,
  PRIMARY KEY  (`demid`,`demtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_groups` (
  `gid` int(6) NOT NULL auto_increment,
  `type` smallint(6) NOT NULL default '0',
  `name` varchar(128) character set utf8 NOT NULL default 'unnamed',
  `flags` int(10) NOT NULL,
  PRIMARY KEY  (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_log` (
  `lid` int(11) NOT NULL auto_increment,
  `type` enum('m','w','e') NOT NULL,
  `title` varchar(512) NOT NULL,
  `message` text NOT NULL,
  `function` text NOT NULL,
  `query` text NOT NULL,
  `aid` int(11) NOT NULL,
  `host` text NOT NULL,
  `created` int(11) NOT NULL,
  PRIMARY KEY  (`lid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_mods` (
  `mid` int(11) NOT NULL auto_increment,
  `name` varchar(128) NOT NULL,
  `icon` varchar(128) NOT NULL,
  `modfolder` varchar(64) NOT NULL,
  `steam_universe` TINYINT NOT NULL DEFAULT '0',
  `enabled` TINYINT NOT NULL DEFAULT '1',
  PRIMARY KEY  (`mid`),
  UNIQUE (`modfolder`),
  UNIQUE (`name`),
  INDEX (`steam_universe`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('command','group') NOT NULL,
  `name` varchar(32) NOT NULL,
  `flags` varchar(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_protests` (
  `pid` int(6) NOT NULL auto_increment,
  `bid` int(6) NOT NULL,
  `datesubmitted` int(11) NOT NULL,
  `reason` text NOT NULL,
  `email` varchar(128) NOT NULL,
  `archiv` tinyint(1) default '0',
  `archivedby` INT(11) NULL,
  `pip` varchar(64) NOT NULL,
  PRIMARY KEY  (`pid`),
  KEY `bid` (`bid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_servers` (
  `sid` int(6) NOT NULL auto_increment,
  `ip` varchar(64) NOT NULL,
  `port` int(5) NOT NULL,
  `rcon` varchar(64) NOT NULL,
  `modid` int(10) NOT NULL,
  `enabled` TINYINT NOT NULL DEFAULT '1',
  PRIMARY KEY  (`sid`),
  UNIQUE KEY `ip` (`ip`,`port`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_servers_groups` (
  `server_id` int(10) NOT NULL,
  `group_id` int(10) NOT NULL,
  PRIMARY KEY  (`server_id`,`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_settings` (
  `setting` varchar(128) NOT NULL,
  `value` text NOT NULL,
  UNIQUE KEY `setting` (`setting`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_srvgroups` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `flags` varchar(30) NOT NULL,
  `immunity` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `groups_immune` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_srvgroups_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` smallint(5) unsigned NOT NULL,
  `type` enum('command','group') NOT NULL,
  `name` varchar(32) NOT NULL,
  `access` enum('allow','deny') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_id` (`group_id`,`type`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{prefix}_submissions` (
  `subid` int(6) NOT NULL auto_increment,
  `submitted` int(11) NOT NULL,
  `ModID` int(6) NOT NULL,
  `SteamId` varchar(64) NOT NULL default 'unnamed',
  `name` varchar(128) NOT NULL,
  `email` varchar(128) NOT NULL,
  `reason` text NOT NULL,
  `ip` varchar(64) NOT NULL,
  `subname` varchar(128) default NULL,
  `sip` varchar(64) default NULL,
  `archiv` tinyint(1) default '0',
  `archivedby` INT(11) NULL,
  `server` tinyint(3) default NULL,
  PRIMARY KEY  (`subid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{prefix}_comms` (
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
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;