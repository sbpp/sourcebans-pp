<?php
$this->db->query("SELECT mid FROM `:prefix_mods` WHERE `modfolder` = 'zps'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Zombie Panic', 'zps.gif', 'zps')");
    $this->db->execute();
}

return true;
