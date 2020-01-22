<?php
$this->dbs->query("ALTER TABLE `:prefix_submissions` ADD IF NOT EXISTS `archivedby` INT(11) NULL AFTER `archiv`");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_protests` ADD IF NOT EXISTS `archivedby` INT(11) NULL AFTER `archiv`");
$this->dbs->execute();

$this->dbs->query("UPDATE `:prefix_submissions` SET `archivedby` = 0 WHERE `archiv` > 0");
$this->dbs->execute();

$this->dbs->query("UPDATE `:prefix_protests` SET `archivedby` = 0 WHERE `archiv` > 0");
$this->dbs->execute();

$this->dbs->query("SELECT message, aid FROM `:prefix_log` WHERE title = 'Submission Archived' OR title = 'Protest Deleted'");
$logs = $this->dbs->resultset();

foreach ($logs as $log) {
    if (preg_match("/(Submission|Protest) \(([0-9]+)\) has been moved to the/", $log['message'], $matches)) {
        $this->dbs->query("UPDATE `:prefix_".strtolower($matches[1])."s` SET `archivedby` = :aid WHERE :sub = :match AND `archiv` > 0");
        $this->dbs->bind(':aid', $log['aid']);
        $this->dbs->bind(':sub', strtolower($matches[1]) == "submission" ? "subid" : "pid");
        $this->dbs->bind(':match', $matches[2]);
        $this->dbs->execute();
    }
}

return true;
