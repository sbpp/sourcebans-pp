<?php
$this->dbs->query("ALTER TABLE `:prefix_comments` ADD FULLTEXT `commenttxt` (`commenttxt`)");
return $this->dbs->execute();
