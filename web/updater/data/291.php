<?php
$this->db->query("ALTER TABLE `:prefix_submissions` ADD `archivedby` INT(11) NULL AFTER `archiv`");
$this->db->execute();

$this->db->query("ALTER TABLE `:prefix_protests` ADD `archivedby` INT(11) NULL AFTER `archiv`");
$this->db->execute();

$this->db->query("UPDATE `:prefix_submissions` SET `archivedby` = 0 WHERE `archiv` > 0");
$this->db->execute();

$this->db->query("UPDATE `:prefix_protests` SET `archivedby` = 0 WHERE `archiv` > 0");
$this->db->execute();

$this->db->query("SELECT message, aid FROM `:prefix_log` WHERE title = 'Submission Archived' OR title = 'Protest Deleted'");
$logs = $this->db->resultset();

foreach ($logs as $log) {
    if (preg_match("/(Submission|Protest) \(([0-9]+)\) has been moved to the/", $log['message'], $matches)) {
        $this->db->query("UPDATE `:prefix_".strtolower($matches[1])."s` SET `archivedby` = :aid WHERE :sub = :match AND `archiv` > 0");
        $this->db->bind(':aid', $log['aid']);
        $this->db->bind(':sub', strtolower($matches[1]) == "submission" ? "subid" : "pid");
        $this->db->bind(':match', $matches[2]);
        $this->db->execute();
    }
}

return true;
