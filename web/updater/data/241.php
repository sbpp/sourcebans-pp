<?php
$this->dbs->query("ALTER TABLE `:prefix_servers` ADD `enabled` TINYINT(4) NOT NULL DEFAULT '1'");
return $this->dbs->execute();
