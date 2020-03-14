<?php
$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'template.title'");
$data = $this->dbs->single();

if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('template.title', 'SourceBans++')");
    $this->dbs->execute();
}

$this->dbs->query("ALTER TABLE `:prefix_submissions` ADD IF NOT EXISTS `server` tinyint(3)");
return $this->dbs->execute();
