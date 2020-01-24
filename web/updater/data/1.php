<?php
$this->dbs->query("ALTER TABLE `:prefix_mods` ADD IF NOT EXISTS `enabled` TINYINT NOT NULL DEFAULT '1'");
return $this->dbs->execute();