<?php
$ret = $GLOBALS['db']->GetAll('SELECT *
                               FROM   ' . DB_PREFIX . '_settings
                               WHERE  setting = "config.enablefriendsbanning"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
                                  VALUES      ("config.enablefriendsbanning", "0")');
  if(!$ret)
    return false;
}

$ret = $GLOBALS['db']->GetAll('SELECT *
                               FROM   ' . DB_PREFIX . '_mods
                               WHERE  modfolder = "garrysmod"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_mods (name, icon, modfolder)
                                  VALUES      ("Garry\'s Mod", "gmod.png", "garrysmod")');
  if(!$ret)
    return false;
}

return true;