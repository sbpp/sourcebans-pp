<?php

$sql = <<<SQL
INSERT INTO `:prefix_settings` (`setting`, `value`) VALUES                                       
('smtp.host', ''),
('smtp.pass', ''),
('smtp.port', ''),
('smtp.user', ''),
('smtp.verify_peer', '')
SQL;

$this->dbs->query($sql);
$this->dbs->execute();

return true;