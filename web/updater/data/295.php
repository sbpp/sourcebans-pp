<?php
$this->dbs->query("SELECT cid, added, edittime FROM `:prefix_comments`");
$old = $this->dbs->resultset();

$this->dbs->query("ALTER TABLE `:prefix_comments` DROP `added`, DROP `edittime`");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_comments` ADD IF NOT EXISTS `added` INT(11) NOT NULL DEFAULT 0 AFTER `commenttxt`, ADD `edittime` INT(11) NOT NULL DEFAULT 0 AFTER `editaid`");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_comments` CHANGE `added` `added` INT(11) NOT NULL");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_comments` CHANGE `edittime` `edittime` INT(11) NULL DEFAULT NULL");
$this->dbs->execute();

$this->dbs->query("UPDATE `:prefix_comments` SET added = :added, edittime = :edittime WHERE cid = :cid");
foreach ($old as $comment) {
    $this->dbs->bind(':added', $comment['added']);
    $this->dbs->bind(':edittime', $comment['edittime']);
    $this->dbs->bind(':cid', $comment['cid']);
    $this->dbs->execute();
}

return true;
