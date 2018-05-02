<?php
$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'template.title'");
$data = $this->db->single();

if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('template.title', 'SourceBans++')");
    $this->db->execute();
}

$this->db->query("ALTER TABLE `:prefix_submissions` ADD `server` tinyint(3)");
return $this->db->execute();
