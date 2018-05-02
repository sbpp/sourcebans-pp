<?php
$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'banlist.nocountryfetch'");
$data = $this->db->single();

if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('banlist.nocountryfetch', '0')");
    $this->db->execute();
}

return true;
