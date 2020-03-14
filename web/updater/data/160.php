<?php
$this->dbs->query("ALTER TABLE `:prefix_protests` ADD IF NOT EXISTS `pip` varchar(64) NOT NULL");
return $this->dbs->execute();
