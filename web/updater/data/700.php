<?php

$auth = [
    'auth.maxlife' => 1440,
    'auth.maxlife.remember' => 10080,
    'auth.maxlife.steam' => 10080
];

foreach ($auth as $setting => $value) {
    $this->dbs->query("INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES (:setting, :value)");
    $this->dbs->bind(':setting', $setting);
    $this->dbs->bind(':value', $value);
    $this->dbs->execute();
}

$this->dbs->query(
    "CREATE TABLE IF NOT EXISTS `:prefix_login_tokens` (
        `jti` varchar(16) NOT NULL,
        `secret` varchar(64) NOT NULL,
        `lastAccessed` int(11) NOT NULL,
        PRIMARY KEY (`jti`),
        UNIQUE KEY `secret` (`secret`)
    ) ENGINE=InnoDB DEFAULT CHARSET=:charset;"
);

$this->dbs->bind(':charset', DB_CHARSET);

return $this->dbs->execute();
