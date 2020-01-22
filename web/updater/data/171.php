<?php
$this->dbs->query("SELECT mid FROM `:prefix_mods` WHERE `modfolder` = 'zps'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Zombie Panic', 'zps.gif', 'zps')");
    $this->dbs->execute();
}

return true;
