<?php
$ret = $GLOBALS['db']->Execute('ALTER TABLE  ' . DB_PREFIX . '_comments
                                ADD FULLTEXT commenttxt (commenttxt)');
if(!$ret)
  return false;

return true;