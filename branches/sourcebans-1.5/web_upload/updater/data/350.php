<?php
$ret = $GLOBALS['db']->GetAll('SELECT *
                               FROM   ' . DB_PREFIX . '_settings
                               WHERE  setting = "banlist.hideplayerips"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
                                  VALUES      ("banlist.hideplayerips", "0")');
  if(!$ret)
    return false;
}

return true;