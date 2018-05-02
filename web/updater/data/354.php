<?php
$this->db->query("SELECT mid FROM `:prefix_mods` WHERE `modfolder` = 'csgo'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Counter-Strike: Global Offensive', 'csgo.png', 'csgo')");
    $this->db->execute();
}

return true;
