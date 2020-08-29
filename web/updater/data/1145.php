<?php

$this->dbs->query("ALTER TABLE `:prefix_bans` ADD INDEX `type_authid` (`type`, `authid`)");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_bans` ADD INDEX `type_ip` (`type`, `ip`)");
$this->dbs->execute();

return true;
