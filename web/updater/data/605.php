<?php

$this->dbs->query("UPDATE `:prefix_settings` SET value = 1 WHERE setting = 'banlist.hideadminname'");
$this->dbs->execute();

$this->dbs->query("UPDATE `:prefix_settings` SET value = 1 WHERE setting = 'banlist.hideplayerips'");
$this->dbs->execute();

$this->dbs->query("UPDATE `:prefix_settings` SET value = 1 WHERE setting = 'banlist.nocountryfetch'");
$this->dbs->execute();

return true;
