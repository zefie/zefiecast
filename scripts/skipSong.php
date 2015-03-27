<?php

require("../config/config.php");
require("../classes/mysql.php");

$mysql = new MySQL();
$mysql->connect($sqldb,$sqluser,$sqlpass,$sqlhost);
$mysql->query("UPDATE config SET skip = '1';");
echo "Song skipped\n";
?>
