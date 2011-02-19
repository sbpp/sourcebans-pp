<?php
$ret = $GLOBALS['db']->GetAll('SELECT *
                               FROM   ' . DB_PREFIX . '_settings
                               WHERE  setting = "config.enablekickit"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
                                  VALUES      ("config.enablekickit", "1")');
  if(!$ret)
    return false;
}

$ret= $GLOBALS['db']->GetAll('SELECT *
                              FROM   ' . DB_PREFIX . '_settings
                              WHERE  setting = "config.dateformat"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
                                  VALUES      ("config.dateformat", "")');
  if(!$ret)
    return false;
}

return true;