<?php
$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.enableadminrehashing'");
$data = $this->db->single();

if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.enableadminrehashing', '1')");
    $this->db->execute();
}

$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'protest.emailonlyinvolved'");
$data = $this->db->single();

if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('protest.emailonlyinvolved', '0')");
    $this->db->execute();
}

return true;
