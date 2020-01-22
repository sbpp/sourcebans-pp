<?php
$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.enablesteamlogin'");
$data = $this->dbs->single();

if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.enablesteamlogin', '1')");
    $this->dbs->execute();
}

return true;
