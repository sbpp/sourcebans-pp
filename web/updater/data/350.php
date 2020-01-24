<?php
$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'banlist.hideplayerips'");
$data = $this->dbs->single();

if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('banlist.hideplayerips', '0')");
    $this->dbs->execute();
}

return true;
