<?php
$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.enablefriendsbanning'");
$data = $this->db->single();

if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.enablefriendsbanning', '0')");
    $this->db->execute();
}

$this->db->query("SELECT mid FROM `:prefix_mods` WHERE modfolder = 'garrysmod'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Garrys\'s Mod', 'gmod.png', 'garrysmod')");
    $this->db->execute();
}

return true;
