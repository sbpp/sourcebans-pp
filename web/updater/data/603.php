<?php
$database = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX);
$database->query("INSERT INTO `:prefix_settings` (value, setting) VALUES ('1', 'config.enablecomms')");
$database->execute();
return true;
