<?php
$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.enableadminrehashing'");
$data = $this->dbs->single();

if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.enableadminrehashing', '1')");
    $this->dbs->execute();
}

$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'protest.emailonlyinvolved'");
$data = $this->dbs->single();

if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('protest.emailonlyinvolved', '0')");
    $this->dbs->execute();
}

return true;
