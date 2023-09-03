<?php

$this->dbs->query("ALTER TABLE `:prefix_comms` MODIFY COLUMN `adminIp` VARCHAR(128) NOT NULL default ''");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_bans` MODIFY COLUMN `adminIp` VARCHAR(128) NOT NULL default ''");
$this->dbs->execute();

return true;
