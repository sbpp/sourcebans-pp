<?php
$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.enablekickit'");
$data = $this->dbs->single();
if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.enablekickit', '1')");
    $this->dbs->execute();
}

$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.dateformat'");
$data = $this->dbs->single();
if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.dateformat', '')");
    $this->dbs->execute();
}

return true;
