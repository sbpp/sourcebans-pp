<?php
$this->dbs->query(
    "CREATE TABLE IF NOT EXISTS `:prefix_overrides` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `type` enum('command', 'group') NOT NULL,
        `name` varchar(32) NOT NULL,
        `flags` varchar(30) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `type` (`type`, `name`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8"
);
$this->dbs->execute();

$this->dbs->query(
    "CREATE TABLE IF NOT EXISTS `:prefix_srvgroups_overrides` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `group_id` smallint(5) unsigned NOT NULL,
        `type` enum('command', 'group') NOT NULL,
        `name` varchar(32) NOT NULL,
        `access` enum('allow', 'deny') NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `group_id` (`group_id`, `type`, `name`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8"
);

$this->dbs->execute();

return true;
