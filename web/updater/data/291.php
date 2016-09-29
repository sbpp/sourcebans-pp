<?php
$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_submissions` ADD `archivedby` INT( 11 ) NULL AFTER `archiv`;");
if (!$ret)
    return false;

$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_protests` ADD `archivedby` INT( 11 ) NULL AFTER `archiv`;");
if (!$ret)
    return false;

$logs = $GLOBALS['db']->GetAll("SELECT message, aid FROM `" . DB_PREFIX . "_log` WHERE title = 'Submission Archived' OR title = 'Protest Deleted'");
$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_submissions` SET `archivedby` = 0 WHERE `archiv` > 0");
$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_protests` SET `archivedby` = 0 WHERE `archiv` > 0");
foreach ($logs as $log) {
    if (preg_match("/(Submission|Protest) \(([0-9]+)\) has been moved to the/", $log['message'], $matches))
        $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_" . strtolower($matches[1]) . "s` set `archivedby` = '" . $log['aid'] . "' WHERE `" . (strtolower($matches[1]) == "submission" ? "subid" : "pid") . "` = '" . $matches[2] . "' AND `archiv` > 0;");
}

return true;