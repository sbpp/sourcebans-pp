<?php
$data = [
    'Insurgency: Source' => 'ins.png',
    'Dystopia' => 'dys.png',
    'Pirates Vikings and Knights II' => 'pvkii.png',
    'Perfect Dark: Source' => 'pdark.png',
    'The Ship' => 'ship.png',
    'Fortress Forever' => 'hl2-fortressforever.png',
    'Team Fortress 2' => 'tf2.png',
    'Zombie Panic' => 'zps.png'
];

$this->db->query("UPDATE `:prefix_mods` SET icon = :icon WHERE name = :name");

foreach ($data as $name => $icon) {
    $this->db->bind(':icon', $icon);
    $this->db->bind(':name', $name);
    $this->db->execute();
}

return true;
