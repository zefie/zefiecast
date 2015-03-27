<?php
require("../config/config.php");
require("../classes/mysql.php");

// Verify Files in ZefieCast Database actually exist

$mysql = new MySQL();
$mysql->connect($sqldb,$sqluser,$sqlpass,$sqlhost);
$mysql->query("SELECT filename FROM songlist");

echo "Scanning for deleted files...\n";

// scan database and populate an array with missing files

while ($data = $mysql->row()) {
	if (!file_exists($data[0])) {
		$files[] = $data[0];
		if (count($files) > 0) {
			echo "Found ".count($files)." to delete...\r";
		}
	}
}

echo "\n";

// remove entries in populated array from database

if (count($files) > 0) {
	foreach ($files as $file) {
		$mysql->query("DELETE FROM songlist WHERE filename = '".$mysql->escape($file)."';");
		echo "Removed ".$file."\n";
	}
}		

echo "Removed ".count($files)." files.\n";

?>
