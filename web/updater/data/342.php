<?php
$this->db->query("SELECT mid FROM `:prefix_mods` WHERE `modfolder` = 'left4dead2'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Left 4 Dead 2', 'l4d2.png', 'left4dead2')");
    $this->db->execute();
}

return true;
