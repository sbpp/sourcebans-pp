<?php
$this->db->query("ALTER TABLE `:prefix_mods` ADD `enabled` TINYINT NOT NULL DEFAULT '1'");
return $this->db->execute();
