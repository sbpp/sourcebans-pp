<?php
$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_servers
                                ADD         enabled tinyint(4) NOT NULL DEFAULT "1"');
if(!$ret)
  return false;

return true;