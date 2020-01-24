<?php
$this->dbs->query("ALTER TABLE `:prefix_comments` ADD IF NOT EXISTS `commenttxt` longtext NOT NULL");
return $this->dbs->execute();
