<?php
$this->db->query("SELECT `mid` FROM `:prefix_mods` WHERE `modfolder` = 'eye'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('E.Y.E: Divine Cybermancy', 'eye.png', 'eye')");
    $this->db->execute();
}

$this->db->query("SELECT `mid` FROM `:prefix_mods` WHERE `modfolder` = 'nucleardawn'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Nuclear Dawn', 'nucleardawn.png', 'nucleardawn')");
    $this->db->execute();
}

$this->db->query("SELECT `mid` FROM `:prefix_mods` WHERE `modfolder` = 'alienswarm'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Alien Swarm', 'alienswarm.png', 'alienswarm')");
    $this->db->execute();
}

$this->db->query("SELECT `mid` FROM `:prefix_mods` WHERE `modfolder` = 'cspromod'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('CSPromod', 'cspromod.png', 'cspromod')");
    $this->db->execute();
}

return true;
