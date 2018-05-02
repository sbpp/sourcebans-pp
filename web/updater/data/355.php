<?php
$this->dbs->query("ALTER TABLE `:prefix_mods` ADD `steam_universe` TINYINT NOT NULL DEFAULT '0' AFTER `modfolder`, ADD INDEX('steam_universe')");
$this->dbs->execute();

$this->dbs->query("UPDATE `:prefix_mods` SET steam_universe = 1 WHERE modfolder = 'left4dead' OR modfolder = 'left4dead2' OR modfolder = 'csgo'");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_mods` ADD UNIQUE (`modfolder`), ADD UNIQUE (`name`)");
$this->dbs->execute();

return true;
