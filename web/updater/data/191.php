<?php
$this->db->query("UPDATE `:prefix_mods` SET `icon` = 'l4d.png' WHERE `modfolder` = 'left4dead'");
return $this->db->execute();
