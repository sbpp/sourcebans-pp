<?php
$this->db->query("ALTER TABLE `:prefix_admins` CHANGE `validate` `validate` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL");
$this->db->execute();

$this->db->query("UPDATE `:prefix_admins` SET validate = NULL");
$this->db->execute();

return true;
