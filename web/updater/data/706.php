<?php

$sql = <<<SQL
INSERT INTO `:prefix_settings`
    (`setting`, `value`, `id`) VALUES ('config.enablegametracker', '1', NULL);
SQL;

$this->dbs->query($sql);
$this->dbs->execute();

return true;
