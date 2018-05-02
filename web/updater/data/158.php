<?php
$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'banlist.hideadminname'");
$data = $this->db->single();

if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix-settings` (`setting`, `value`) VALUES ('banlist.hideadminname', '0')");
    $this->db->execute();
}

return true;
