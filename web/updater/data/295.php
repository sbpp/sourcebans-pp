<?php
$oldcomments = $GLOBALS['db']->GetAll('SELECT cid, added, edittime FROM `' . DB_PREFIX . '_comments`');

$res = $GLOBALS['db']->Execute('ALTER TABLE `' . DB_PREFIX . '_comments` DROP `added`, DROP `edittime`');
if (!$res)
    return false;

$res = $GLOBALS['db']->Execute('ALTER TABLE `' . DB_PREFIX . '_comments` ADD `added` INT( 11 ) NOT NULL DEFAULT \'0\' AFTER `commenttxt`, ADD `edittime` INT( 11 ) NOT NULL DEFAULT \'0\' AFTER `editaid`');
if (!$res)
    return false;

$res = $GLOBALS['db']->Execute('ALTER TABLE `' . DB_PREFIX . '_comments` CHANGE `added` `added` INT( 11 ) NOT NULL');
if (!$res)
    return false;

$res = $GLOBALS['db']->Execute('ALTER TABLE `' . DB_PREFIX . '_comments` CHANGE `edittime` `edittime` INT( 11 ) NULL DEFAULT NULL ');
if (!$res)
    return false;

foreach ($oldcomments AS $oldcomment)
    if (empty($oldcomment['edittime']))
        $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_comments` SET added = '" . strtotime($oldcomment['added']) . "' WHERE cid = '" . $oldcomment['cid'] . "'");
    else
        $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_comments` SET added = '" . strtotime($oldcomment['added']) . "', edittime = '" . strtotime($oldcomment['edittime']) . "' WHERE cid = '" . $oldcomment['cid'] . "'");

return true;