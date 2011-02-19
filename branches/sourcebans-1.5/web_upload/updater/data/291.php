<?php
$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_submissions
                                ADD         archivedby int(11) NULL AFTER archiv');
if(!$ret)
    return false;

$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_protests
                                ADD         archivedby int(11) NULL AFTER archiv');
if(!$ret)
    return false;

$GLOBALS['db']->Execute('UPDATE ' . DB_PREFIX . '_submissions
                         SET    archivedby = 0
                         WHERE  archiv     > 0');
$GLOBALS['db']->Execute('UPDATE ' . DB_PREFIX . '_protests
                         SET    archivedby = 0
                         WHERE  archiv     > 0');

$logs = $GLOBALS['db']->GetAll('SELECT message, aid
                                FROM   ' . DB_PREFIX . '_log
                                WHERE  title = "Submission Archived"
                                   OR  title = "Protest Deleted"');
foreach($logs as $log)
{
  if(!preg_match('/(Submission|Protest) \(([0-9]+)\) has been moved to the/', $log['message'], $matches))
    continue;\
  
  $GLOBALS['db']->Execute('UPDATE ' . DB_PREFIX . '_' . strtolower($matches[1]) . 's
                           SET    archivedby = ?
                           WHERE  archiv     > 0
                             AND  ' . (strtolower($matches[1]) == 'protest' ? 'pid' : 'subid') . ' = ?',
                          array($log['aid'], $matches[2]));
}

return true;