<?php
$this->db->query("DELETE FROM `:prefix_settings` WHERE setting = 'config.uri'");
$this->db->execute();

$this->db->query("DELETE FROM `:prefix_settings` WHERE setting = 'config.publicexport'");
$this->db->execute();

$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'dash.lognopopup'");
$data = $this->db->single();

if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('dash.lognopopup', '0')");
    $this->db->execute();
}

$this->db->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.exportpublic'");
$data = $this->db->single();

if (!$data['value']) {
    $this->db->query("INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.exportpublic', '0')");
    $this->db->execute();
}

$this->db->query("SELECT aid FROM `:prefix_admins`");
$admins = $this->db->resultset();

$this->db->query("UPDATE `:prefix_admins` SET lastvisit = '0000-00-00 00:00:00' WHERE aid = :aid");
foreach ($admins as $admin) {
    $this->db->bind(':aid', $admin['aid'], \PDO::PARAM_INT);
    $this->db->execute();
}

$this->db->query("ALTER TABLE `:prefix_admins` CHANGE `lastvisit` `lastvisit` INT(11) NULL DEFAULT NULL");
$this->db->execute();

$this->db->query("ALTER TABLE `:prefix_bans` ADD `type` TINYINT NOT NULL DEFAULT '0'");
$this->db->execute();

$this->db->query(
    "CREATE TABLE IF NOT EXISTS `:prefix_comments` (
        `cid` int(6) NOT NULL AUTO_INCREMENT,
        `bid` int(6) NOT NULL,
        `type` varchar(1) NOT NULL,
        `aid` int(6) NOT NULL,
        `commenttxt` longtext NOT NULL,
        `added` datetime NOT NULL,
        `editaid` int(6) DEFAULT NULL,
        `edittime` datetime DEFAULT NULL,
        KEY `cid` (`cid`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8"
);
$this->db->execute();

$this->db->query("ALTER TABLE `:prefix_mods` ADD enabled TINYINT NOT NULL DEFAULT '1'");
$this->db->execute();

$this->db->query(
    "SELECT bid FROM `:prefix_bans` AS ba
    WHERE RemoveType = 'U' AND RemovedOn IS NOT NULL AND
    (SELECT COUNT(*) FROM `:prefix_bans` AS ba2 WHERE ba.RemoveType = ba2.RemoveType AND ba.RemovedOn = ba2.RemovedOn) > 1"
);
$bans = $this->db->resultset();

$this->db->query("UPDATE `:prefix_bans` SET RemovedOn = NULL, RemoveType = NULL WHERE bid = :bid");
foreach ($bans as $ban) {
    $this->db->bind(':bid', $ban['bid'], \PDO::PARAM_INT);
    $this->db->execute();
}

return true;
