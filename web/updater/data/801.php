<?php

// Issue #1472: panels upgrading from v1.8.x (or re-running the updater
// after a partial pass) may already carry the lockout columns — the bare
// ADD COLUMN shape fatals with SQLSTATE[42S21] Duplicate column name.
// ADD IF NOT EXISTS matches the idempotent contract used by sibling
// migrations (112.php, 150.php, …) and converges to the same schema.
$this->dbs->query("ALTER TABLE `:prefix_admins` ADD IF NOT EXISTS `attempts` INT DEFAULT 0");
$this->dbs->execute();

$this->dbs->query("ALTER TABLE `:prefix_admins` ADD IF NOT EXISTS `lockout_until` DATETIME DEFAULT NULL");
$this->dbs->execute();

return true;