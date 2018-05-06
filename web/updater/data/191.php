<?php
$this->dbs->query("UPDATE `:prefix_mods` SET `icon` = 'l4d.png' WHERE `modfolder` = 'left4dead'");
return $this->dbs->execute();
