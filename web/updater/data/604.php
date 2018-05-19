<?php

$this->dbs->query("SELECT authid FROM `:prefix_admins`");
$data = $this->dbs->resultset();

foreach ($data as $steamid) {
    $steamid = $steamid['authid'];
    if (strpos($steamid, 'STEAM_1') !== false) {
        $new = str_replace('STEAM_1', 'STEAM_0', $steamid);

        $this->dbs->query("UPDATE `:prefix_admins` SET authid = :new WHERE authid = :old");
        $this->dbs->bind(':new', $new);
        $this->dbs->bind(':old', $steamid);
        $this->dbs->execute();
    }
}

return true;
