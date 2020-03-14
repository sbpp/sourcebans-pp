<?php
$this->dbs->query("SELECT * FROM `:prefix_settings` WHERE setting = 'bans.customreasons'");
$data = $this->dbs->single();

if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('bans.customreasons', '')");
    $this->dbs->execute();
}

return true;
