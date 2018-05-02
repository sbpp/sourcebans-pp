<?php
$this->db->query("ALTER TABLE `:prefix_mods` ADD `steam_universe` TINYINT NOT NULL DEFAULT '0' AFTER `modfolder`, ADD INDEX('steam_universe')");
$this->db->execute();

$this->db->query("UPDATE `:prefix_mods` SET steam_universe = 1 WHERE modfolder = 'left4dead' OR modfolder = 'left4dead2' OR modfolder = 'csgo'");
$this->db->execute();

$this->db->query("ALTER TABLE `:prefix_mods` ADD UNIQUE (`modfolder`), ADD UNIQUE (`name`)");
$this->db->execute();

return true;
