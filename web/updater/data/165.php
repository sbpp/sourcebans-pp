<?php
$this->db->query("SELECT * FROM `:prefix_settings` WHERE setting = 'bans.customreasons'");
$data = $this->db->single();

if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('bans.customreasons', '')");
    $this->db->execute();
}

return true;
