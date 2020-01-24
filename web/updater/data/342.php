<?php
$this->dbs->query("SELECT mid FROM `:prefix_mods` WHERE `modfolder` = 'left4dead2'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Left 4 Dead 2', 'l4d2.png', 'left4dead2')");
    $this->dbs->execute();
}

return true;
