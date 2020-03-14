<?php
$this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.enablepubliccomments'");
$data = $this->dbs->single();

if (!$data['value']) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.enablepubliccomments', '0')");
    $this->dbs->execute();
}

return true;
