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

$this->dbs->query("UPDATE `:prefix_mods` SET icon = :icon WHERE name = :name");

foreach ($data as $name => $icon) {
    $this->dbs->bind(':icon', $icon);
    $this->dbs->bind(':name', $name);
    $this->dbs->execute();
}

return true;
