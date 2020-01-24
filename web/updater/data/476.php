<?php
$this->dbs->query("SELECT mid FROM `:prefix_mods` WHERE modfolder = 'synergy'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Synergy', 'synergy.png', 'synergy')");
    $this->dbs->execute();
}

return true;
