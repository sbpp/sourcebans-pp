<?php
$this->db->query("SELECT cid, added, edittime FROM `:prefix_comments`");
$old = $this->db->resultset();

$this->db->query("ALTER TABLE `:prefix_comments` DROP `added`, DROP `edittime`");
$this->db->execute();

$this->db->query("ALTER TABLE `:prefix_comments` ADD `added` INT(11) NOT NULL DEFAULT 0 AFTER `commenttxt`, ADD `edittime` INT(11) NOT NULL DEFAULT 0 AFTER `editaid`");
$this->db->execute();

$this->db->query("ALTER TABLE `:prefix_comments` CHANGE `added` `added` INT(11) NOT NULL");
$this->db->execute();

$this->db->query("ALTER TABLE `:prefix_comments` CHANGE `edittime` `edittime` INT(11) NULL DEFAULT NULL");
$this->db->execute();

$this->db->query("UPDATE `:prefix_comments` SET added = :added, edittime = :edittime WHERE cid = :cid");
foreach ($old as $comment) {
    $this->db->bind(':added', $comment['added']);
    $this->db->bind(':edittime', $comment['edittime']);
    $this->db->bind(':cid', $comment['cid']);
    $this->db->execute();
}

return true;
