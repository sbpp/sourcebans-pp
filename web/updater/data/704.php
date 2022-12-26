<?php

$sql = <<<SQL
ALTER TABLE `:prefix_settings`
    ADD COLUMN `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY;
SQL;

$this->dbs->query($sql);
$this->dbs->execute();

return true;
