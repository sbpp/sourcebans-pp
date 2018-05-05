<?php
$this->dbs->query("ALTER TABLE `:prefix_admins` CHANGE `validate` `validate` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL");
$this->dbs->execute();

$this->dbs->query("UPDATE `:prefix_admins` SET validate = NULL");
$this->dbs->execute();

return true;
