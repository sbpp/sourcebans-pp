<?php
$this->dbs->query("SELECT mid FROM `:prefix_mods` WHERE `modfolder` = 'csgo'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Counter-Strike: Global Offensive', 'csgo.png', 'csgo')");
    $this->dbs->execute();
}

return true;
