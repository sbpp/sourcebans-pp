<?php
$this->db->query("SELECT mid FROM `:prefix_mods` WHERE modfolder = 'synergy'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Synergy', 'synergy.png', 'synergy')");
    $this->db->execute();
}

return true;
