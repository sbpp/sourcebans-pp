<?php
$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.enablekickit'");
$data = $this->db->single();
if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.enablekickit', '1')");
    $this->db->execute();
}

$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.dateformat'");
$data = $this->db->single();
if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.dateformat', '')");
    $this->db->execute();
}

return true;
