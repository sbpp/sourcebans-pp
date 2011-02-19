<?php
$ret = $GLOBALS['db']->Execute('DELETE FROM ' . DB_PREFIX . '_settings
                                WHERE       setting = "config.uri"');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('DELETE FROM ' . DB_PREFIX . '_settings
                                WHERE       setting = "config.publicexport"');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->GetAll('SELECT *
                               FROM   ' . DB_PREFIX . '_settings
                               WHERE  setting = "dash.lognopopup"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
                                  VALUES      ("dash.lognopopup", "0")');
  if(!$ret)
    return false;
}

$ret = $GLOBALS['db']->GetAll('SELECT *
                               FROM   ' . DB_PREFIX . '_settings
                               WHERE  setting = "config.exportpublic"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
                                  VALUES      ("config.exportpublic", "0")');
  if(!$ret)
    return false;
}

$ret = $GLOBALS['db']->Execute('UPDATE ' . DB_PREFIX . '_admins
                                SET    lastvisit = "0000-00-00 00:00:00"');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_admins
                                CHANGE      lastvisit lastvisit INT( 11 ) NULL DEFAULT NULL');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_bans
                                ADD         type TINYINT NOT NULL DEFAULT "0"');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . '_comments (
                                  cid int(6) NOT NULL auto_increment,
                                  bid int(6) NOT NULL,
                                  type varchar(1) NOT NULL,
                                  aid int(6) NOT NULL,
                                  commenttxt longtext NOT NULL,
                                  added datetime NOT NULL,
                                  editaid int(6) default NULL,
                                  edittime datetime default NULL,
                                  KEY cid (cid)
                                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_mods
                                ADD         enabled TINYINT NOT NULL DEFAULT "1"');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('UPDATE ' . DB_PREFIX . '_bans AS ba
                                SET    RemoveType = NULL,
                                       RemovedOn  = NULL
                                WHERE  RemoveType = "U"
                                  AND  RemovedOn IS NOT NULL
                                  AND  (SELECT COUNT(*)
                                        FROM   ' . DB_PREFIX . '_bans AS ba2
                                        WHERE  ba.RemoveType = ba2.RemoveType
                                          AND  ba.RemovedOn  = ba2.RemovedOn) > 1');
if(!$ret)
  return false;

return true;