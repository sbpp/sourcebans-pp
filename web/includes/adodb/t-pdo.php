<?php
include 'adodb.inc.php';
include 'adodb-exceptions.inc.php';
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

$u = 'root';
$p = 'C0yote71';

$driver = 'pdo\mysql';
$driver = "mysql://$u:$p@localhost/bugtracker";
$driver = "pdo\\mysql://$u:$p@localhost/bugtracker";
//$driver = "pdo_mysql://$u:$p@localhost/bugtracker";
//$driver = "pdo_mysql://$u:$p@localhost?dbname=bugtracker";

$db = ADONewConnection($driver) or die("New connection failed");
//$db->connect('dbname=bugtracker', $u, $p) or die("Connect failed");
echo "DB=$db->database, TYPE=$db->databaseType, PROVIDER=$db->dataProvider\n";
$db->debug = true;
print_r( $db->getarray('select id, summary from mantis_bug_table limit 1')[0] );


