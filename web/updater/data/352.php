<?php
$this->dbs->query("SELECT `mid` FROM `:prefix_mods` WHERE `modfolder` = 'eye'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('E.Y.E: Divine Cybermancy', 'eye.png', 'eye')");
    $this->dbs->execute();
}

$this->dbs->query("SELECT `mid` FROM `:prefix_mods` WHERE `modfolder` = 'nucleardawn'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Nuclear Dawn', 'nucleardawn.png', 'nucleardawn')");
    $this->dbs->execute();
}

$this->dbs->query("SELECT `mid` FROM `:prefix_mods` WHERE `modfolder` = 'alienswarm'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Alien Swarm', 'alienswarm.png', 'alienswarm')");
    $this->dbs->execute();
}

$this->dbs->query("SELECT `mid` FROM `:prefix_mods` WHERE `modfolder` = 'cspromod'");
$data = $this->dbs->single();

if (!$data['mid']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('CSPromod', 'cspromod.png', 'cspromod')");
    $this->dbs->execute();
}

return true;
