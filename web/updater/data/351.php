<?php
$this->db->query("ALTER TABLE `:prefix_comments` ADD FULLTEXT `commenttxt` (`commenttxt`)");
return $this->db->execute();
