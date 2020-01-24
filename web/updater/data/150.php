<?php
$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.summertime'");
$data = $this->dbs->single();

if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.summertime', '0')");
    $this->dbs->execute();
}

$this->dbs->query("ALTER TABLE `:prefix_bans` ADD IF NOT EXISTS `ureason` text");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_protests` ADD IF NOT EXISTS `archiv` tinyint(1) DEFAULT '0'");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_submissions` ADD IF NOT EXISTS `subname` varchar(128) DEFAULT NULL");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_submissions` ADD IF NOT EXISTS `sip` varchar(64) DEFAULT NULL");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_submissions` ADD IF NOT EXISTS `archiv` tinyint(1) DEFAULT '0'");
$this->dbs->execute();

$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.timezone'");
$data = $this->dbs->single();

switch ($data['value']) {
    case 'Pacific/Apia':
        $ver = '-11';
        break;
    case 'Pacific/Honolulu':
        $ver = '-10';
        break;
    case 'America/Anchorage':
        $ver = '-9';
        break;
    case 'America/Los_Angeles':
        $ver = '-8';
        break;
    case 'America/Denver':
        $ver = '-7';
        break;
    case 'America/Chicago':
        $ver = '-6';
        break;
    case 'America/New_York':
        $ver = '-5';
        break;
    case 'America/Halifax':
        $ver = '-4';
        break;
    case 'America/Sao_Paulo':
        $ver = '-3';
        break;
    case 'Atlantic/Azores':
        $ver = '-1';
        break;
    case 'Europe/London':
        $ver = '0';
        break;
    case 'Europe/Paris':
        $ver = '1';
        break;
    case 'Europe/Helsinki':
        $ver = '2';
        break;
    case 'Europe/Moscow':
        $ver = '3';
        break;
    case 'Asia/Dubai':
        $ver = '4';
        break;
    case 'Asia/Karachi':
        $ver = '5';
        break;
    case 'Asia/Krasnoyarsk':
        $ver = '7';
        break;
    case 'Asia/Tokyo':
        $ver = '9';
        break;
    case 'Australia/Melbourne':
        $ver = '10';
        break;
    case 'Pacific/Auckland':
        $ver = '12';
        break;
    default:
        $ver = '0';
}

$this->dbs->query("UPDATE `:prefix_settings` SET value = :value WHERE setting = 'config.timezone'");
$this->dbs->bind(':value', $ver);
return $this->dbs->execute();
