<?php
$ret = $GLOBALS['db']->GetAll('SELECT *
                               FROM   ' . DB_PREFIX . '_settings
                               WHERE  setting = "config.summertime"');
if(empty($ret))
{
  $ret = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
                                  VALUES      ("config.summertime", "0")');
  if(!$ret)
    return false;
}

$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_bans
                                ADD         ureason text');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_protests
                                ADD         archiv tinyint(1) default "0"');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_submissions
                                ADD         subname varchar(128) default NULL');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_submissions
                                ADD         sip varchar(64) default NULL');
if(!$ret)
  return false;

$ret = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_submissions
                                ADD         archiv tinyint(1) default "0"');
if(!$ret)
  return false;

$timezone = $GLOBALS['db']->GetOne('SELECT value
                                    FROM   ' . DB_PREFIX . '_settings
                                    WHERE  setting = "config.timezone"');
switch($timezone)
{
  case 'Pacific/Apia':
    $offset = -11;
    break;
  case 'Pacific/Honolulu':
    $offset = -10';
    break;
  case 'America/Anchorage':
    $offset = -9';
    break;
  case 'America/Los_Angeles':
    $offset = -8';
    break;
  case 'America/Denver':
    $offset = -7';
    break;
  case 'America/Chicago':
    $offset = -6';
    break;
  case 'America/New_York':
    $offset = -5';
    break;
  case 'America/Halifax':
    $offset = -4';
    break;
  case 'America/Sao_Paulo':
    $offset = -3';
    break;
  case 'Atlantic/Azores':
    $offset = -1';
    break;
  case 'Europe/London':
    $offset = 0';
    break;
  case 'Europe/Paris':
    $offset = 1';
    break;
  case 'Europe/Helsinki':
    $offset = 2';
    break;
  case 'Europe/Moscow':
    $offset = 3';
    break;
  case 'Asia/Dubai':
    $offset = 4';
    break;
  case 'Asia/Karachi':
    $offset = 5';
    break;
  case 'Asia/Krasnoyarsk':
    $offset = 7';
    break;
  case 'Asia/Tokyo':
    $offset = 9';
    break;
  case 'Australia/Melbourne':
    $offset = 10';
    break;
  case 'Pacific/Auckland':
    $offset = 12;
    break;
  default:
    $offset = 0;
}

$ret = $GLOBALS['db']->Execute('UPDATE ' . DB_PREFIX . '_settings
                                SET    value   = ?
                                WHERE  setting = "config.timezone"',
                               array($offset));
if(!$ret)
  return false;

return true;