<?php

require("../config/config.php");
require("../classes/mysql.php");

$sqlfile = fopen("sqlstructure.sql","r");

if (!$sqlfile) {
	die("sqlstructure.sql not found");
}

$sqldata = "";

while (!feof($sqlfile)) {
	$sqldata .= fread($sqlfile,128);
}

fclose($sqlfile);

$mysql = new MySQL();
$mysql->connect($sqldb,$sqluser,$sqlpass,$sqlhost);
$mysql->query($sqldata);

echo "Table structure successfuly created.\n";

?>

