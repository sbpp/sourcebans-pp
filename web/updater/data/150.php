<?php
$temp = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_settings` WHERE setting = 'config.summertime';");
if (count($temp) == 0) {
    $ret = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.summertime', '0');");
    if (!$ret)
        return false;
}

$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_bans` ADD `ureason` text;");
if (!$ret)
    return false;

$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_protests` ADD `archiv` tinyint(1) default '0';");
if (!$ret)
    return false;

$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_submissions` ADD `subname` varchar(128) default NULL;");
if (!$ret)
    return false;

$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_submissions` ADD `sip` varchar(64) default NULL;");
if (!$ret)
    return false;

$ret = $GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_submissions` ADD `archiv` tinyint(1) default '0';");
if (!$ret)
    return false;

$timezone = $GLOBALS['db']->GetRow("SELECT value FROM `" . DB_PREFIX . "_settings` WHERE setting = 'config.timezone'");
if ($timezone['value'] == 'Pacific/Apia')
    $ver = '-11';
else if ($timezone['value'] == 'Pacific/Honolulu')
    $ver = '-10';
else if ($timezone['value'] == 'America/Anchorage')
    $ver = '-9';
else if ($timezone['value'] == 'America/Los_Angeles')
    $ver = '-8';
else if ($timezone['value'] == 'America/Denver')
    $ver = '-7';
else if ($timezone['value'] == 'America/Chicago')
    $ver = '-6';
else if ($timezone['value'] == 'America/New_York')
    $ver = '-5';
else if ($timezone['value'] == 'America/Halifax')
    $ver = '-4';
else if ($timezone['value'] == 'America/Sao_Paulo')
    $ver = '-3';
else if ($timezone['value'] == 'Atlantic/Azores')
    $ver = '-1';
else if ($timezone['value'] == 'Europe/London')
    $ver = '0';
else if ($timezone['value'] == 'Europe/Paris')
    $ver = '1';
else if ($timezone['value'] == 'Europe/Helsinki')
    $ver = '2';
else if ($timezone['value'] == 'Europe/Moscow')
    $ver = '3';
else if ($timezone['value'] == 'Asia/Dubai')
    $ver = '4';
else if ($timezone['value'] == 'Asia/Karachi')
    $ver = '5';
else if ($timezone['value'] == 'Asia/Krasnoyarsk')
    $ver = '7';
else if ($timezone['value'] == 'Asia/Tokyo')
    $ver = '9';
else if ($timezone['value'] == 'Australia/Melbourne')
    $ver = '10';
else if ($timezone['value'] == 'Pacific/Auckland')
    $ver = '12';
else
    $ver = '0';
$ret = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_settings` SET value = '" . $ver . "' WHERE setting = 'config.timezone';");
if (!$ret)
    return false;

return true;