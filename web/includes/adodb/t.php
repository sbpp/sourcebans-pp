<?php
include 'adodb.inc.php';
//include 'adodb-exceptions.inc.php';

$driver = 'mysqli';
$host = 'localhost';
$user = 'root';
$password = 'C0yote71';
$database = 'bugtracker';

$sql = "SELECT * FROM mantis_config_table";

$db = NewADOConnection($driver);
$db->connect($host, $user, $password, $database);
$db->setFetchMode(ADODB_FETCH_ASSOC);

$q = $db->execute($sql, []);
//print_r( $q->getAssoc() );
