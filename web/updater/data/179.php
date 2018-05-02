<?php
$this->db->query("SELECT mid FROM `:prefix_mods` WHERE modfolder = 'left4dead'");
$data = $this->db->single();

if (!$data['mid']) {
    $this->db->query("INSERT INTO `:prefix_mods` (`name`, `icon`, `modfolder`) VALUES ('Left 4 Dead', 'l4d.png', 'left4dead')");
    $this->db->execute();
}

return true;
