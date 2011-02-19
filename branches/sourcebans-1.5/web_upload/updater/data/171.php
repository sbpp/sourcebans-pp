<?php
$ret = $GLOBALS['db']->GetAll('SELECT *
                               FROM   ' . DB_PREFIX . '_mods
                               WHERE  modfolder = "zps"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_mods (name, icon, modfolder)
                                  VALUES      ("Zombie Panic", "zps.gif", "zps")');
  if(!$ret)
    return false;
}

return true;