<?php
$ret = $GLOBALS['db']->GetAll('SELECT *
                               FROM   ' . DB_PREFIX . '_settings
                               WHERE  setting = "config.enablegroupbanning"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
                                  VALUES      ("config.enablegroupbanning", "0")');
  if(!$ret)
    return false;
}

return true;