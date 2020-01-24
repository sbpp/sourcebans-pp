<?php
$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.enablefriendsbanning'");
$data = $this->dbs->single();

if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.enablefriendsbanning', '0')");
    $this->dbs->execute();
}

$this->dbs->query("SELECT mid FROM `:prefix_mods` WHERE modfolder = 'garrysmod'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Garrys\'s Mod', 'gmod.png', 'garrysmod')");
    $this->dbs->execute();
}

return true;
