<?php
$database = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX);

$database->query("UPDATE `:prefix_mods` SET icon = 'ins.png' WHERE name = 'Insurgency: Source'");
$database->execute();
$database->query("UPDATE `:prefix_mods` SET icon = 'dys.png' WHERE name = 'Dystopia'");
$database->execute();
$database->query("UPDATE `:prefix_mods` SET icon = 'pvkii.png' WHERE name = 'Pirates Vikings and Knights II'");
$database->execute();
$database->query("UPDATE `:prefix_mods` SET icon = 'pdark.png' WHERE name = 'Perfect Dark: Source'");
$database->execute();
$database->query("UPDATE `:prefix_mods` SET icon = 'ship.png' WHERE name = 'The Ship'");
$database->execute();
$database->query("UPDATE `:prefix_mods` SET icon = 'hl2-fortressforever.png' WHERE name = 'Fortress Forever'");
$database->execute();
$database->query("UPDATE `:prefix_mods` SET icon = 'tf2.png' WHERE name = 'Team Fortress 2'");
$database->execute();
$database->query("UPDATE `:prefix_mods` SET icon = 'zps.png' WHERE name = 'Zombie Panic'");
$database->execute();
return true;
