<?php
$this->dbs->query("SELECT mid FROM `:prefix_mods` WHERE modfolder = 'left4dead'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Left 4 Dead', 'l4d.png', 'left4dead')");
    $this->dbs->execute();
}

return true;
