<?php
set_time_limit(0);

$playPrev = 0;

$version = "0.5.9";

require("config/config.php");
require("classes/logicreactor.php");
require("classes/mysql.php");
require("classes/checkApp.php");

$check = new checkApp();
if (!$check->check("mplayer")) {
	die("mplayer is required for ZefieCast to work. Please visit http://www.mplayerhq.hu/");
}

if ($useshoutcast) {
	if ($usephpshout) {
		require("classes/scBroadcast_phpShout.php");	
	} else {
		require("classes/scBroadcast.php");
	}
}

if ($useicecast) {
	require("classes/icecastBroadcast.php");
}

if ($useogg) {
	if (!$check->check("oggenc")) {
		die("oggenc is required for OGG Vorbis encoding. Please install vorbis-tools");
	}
}
if ($uselame) {
	if (!$check->check("lame")) {
		die("lame is required for mp3 encoding. Please visit http://lame.sf.net/");
	}
}

if ($useicecast && $useshoutcast) {
	die("Multiple output not yet supported, please choose Icecast OR Shoutcast\n");
}

if ($uselame && $useogg) {
	die("Multiple output not yet supported, please choose mp3 OR ogg\n");
}

if ($useshoutcast && $useogg) {
	die("That configuration will never work. OGG and Shoutcast are not compatible.\n");
}

if (!$useshoutcast && !$useicecast) {
	die("Not a very productive radio station if you aren't broadcasting to any server...\n");
}

if (!$uselame && !$useogg) {
	die("Please choose a codec.");
}

echo "ZefieCast v".$version." (C) Zefie Hosting\n";
echo "Please visit http://zefiecast.sf.net/ for updates\n\n";

echo "Attemping to broadcast...\n";
echo "Output Media: ".$bitrate."kbps ".$srate."hz\n";
echo "Station Name: ".$stationname."\n";
echo "Station Genre: ".$stationgenre."\n\n";

if ($useshoutcast) {
	$scb = new scBroadcast();
	$scb->Connect($scserv,$scport);

	$scbconn = $scb->Auth($scpass,$bitrate,$stationpub,$stationname,$stationgenre,$stationurl,$stationaim,$stationicq,$stationirc);

	if (!$scbconn) {
		die($scb->getError()."\n\n");
	}
}

if ($useicecast) {
	$scb = new icecastBroadcast();
	$scb->Connect($icserv,$icport);

	$scbconn = $scb->Auth($icuser,$icpass,$icmount,$bitrate,$stationpub,$stationname,$stationgenre,$stationurl,$stationdesc);

	if (!$scbconn) {
		die($scb->getError()."\n\n");
	}
}

$mysql = new MySQL();
$mysql->connect($sqldb,$sqluser,$sqlpass,$sqlhost);

$logic = new LogicReactor();

while ($scbconn) {
	if ($usephpshout) {
		if ($scb->error) {
			break;
		}
	}
	$mysql->getConfig();
	if ($playPrev) {
		$logic->queueLastSong();	
		$playPrev = 0;
	}
	$song = $logic->getSong();
	if ($song['album']) {
		$currentsong = $song['artist']." - ".$song['title']." (".$song['album'].")";
	} else {
		$currentsong = $song['artist']." - ".$song['title'];
	}
	if ($song["requested"]) {
		$currentsong .= " ~requested~";
	}
	$file = $song["filename"];
	if ($live365update) {
		file_get_contents("http://tools.live365.com/cgi-bin/add_song.cgi?version=2&pass=".urlencode($live365pass)."&handle=".urlencode($live365user)."&title=".urlencode($song['title'])."&artist=".urlencode($song['artist'])."&album=".urlencode($song['album'])."&seconds=".$song['length']."&fileName=".urlencode($song['filename']));
	}
	$scb->Update($currentsong);
	echo strftime("[%H:%M]",time())." ".$currentsong."\n";
	$scb->broadcast($file);
}

@fclose ($sbconn);
die("Error: Server Disconnected\n");

?>

