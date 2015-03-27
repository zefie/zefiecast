<?php
// MySQL Settings
$sqlhost = "localhost"; // Host, remote DB not tested yet.
$sqluser = "zcast"; // MySQL Username
$sqldb = $sqluser; // MySQL Database, doesn't HAVE to be the same as user.
$sqlpass = "lamepassword"; // MySQL Password

$live365update = 0; // Enable live365 metadata reporting
$live365user = "zefie";
$live365pass = "";

// General Station Info
$stationname = "Zefiecast Test Stream"; // Your Station's Name
$stationgenre = "Top 40"; // Your Station's Genre
$stationurl = ""; // Your Station's Website
$stationpub = 1; // 1 = List on Directory, 0 = Don't.

// Shoutcast DNAS Settings
$useshoutcast = 0; // Using Shoutcast DNAS?
$usephpshout = 0; // If you have libshout and phpShout, and want to use it

$scserv = ""; // Shoutcast Server IP
$scport = 8000; // Shoutcast Server Port
$scpass = ""; // Broadcaster Password, doesn't HAVE to be admin password.

$stationirc = ""; // Your Station IRC
$stationicq = ""; // Who uses ICQ anymore?
$stationaim = ""; // Your Station AIM Name


// Icecast Settings
$useicecast = 1; // Using Icecast?
$icecast1 = 0; // If you are using pre-Icecast2 software, set this to 1.

$icserv = "server.mycoolhost.com"; // Icecast Server IP
$icport = 8000; // Icecast Server Port
$icuser = "lameusername"; // Broadcaster Username
$icpass = "lamepassword"; // Broadcaster Password
$icmount = "/live.ogg"; // Mount point
$stationdesc = "ZefieCast Testbed"; // Your station description;

// Server + Encoder Settings
$bitrate = 192; // Station bitrate
$vbr = 1; // Requires phpShout, ingored otherwise. Will broadcast between 32kbps and $bitrate
$vbrquality = 1; // VBR quality, like -V0, set to 1 higher (1=0, 2=1, ect)
		 // Note you still need to set $bitrate to average output
	 	 // for directory listings

// MP3 (LAME) Settings
$uselame = 1; // Using LAME Encoder?
$srate = 44100; // Frequency of the MP3 stream

// OGG Vorbis (oggenc) Settings
$useogg = 0; // Using OGG Encoder?
$srate = 44100; // Frequency of the OGG stream
$stereo = 1; // 0 = mono

$seconds = 5; // Powerbuffer data to send at start, in seconds.
?>
