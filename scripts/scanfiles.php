<?php
//metaflac --show-tag=title --show-tag=artist --show-tag=album
require("../config/config.php");
require("../classes/mysql.php");
require("../classes/checkApp.php");
require_once("getid3/getid3.php");


// Populate ZefieCast Database .. v2.5
// Still a mess!

$path = $argv[1];
$flags = $argv[2];

if (!$path) {
	die("Usage: php ".$argv[0]." path [-R]\n");
}

// Trailing slash makes a mess of database and allows duplicates. Destroy the trail slash.
$path = rtrim($path,"/");

$recursive = 0;


if (substr($path,0,1) != "/") {
	// Bad things happen without absolute paths.
	$pwd = `pwd`;
	die("Please use an ABSOLUTE path, such as ".$pwd);
}

$mysql = new MySQL();
$mysql->connect($sqldb,$sqluser,$sqlpass,$sqlhost);

if ($flags == "-R") {
	$recursive = 1;
}

$check = new checkApp();
if (!$check->check("mplayer")) {
        die("mplayer is required for ZefieCast to work. Please visit http://www.mplayerhq.hu/");
}

//doGetID3Scan(zScanDir($path,"wma",$recursive),"WMA"); // Tags not yet supported
doGetID3Scan(zScanDir($path,"mp3",$recursive),"MP3"); 
//doGetID3Scan(zScanDir($path,"ogg",$recursive),"OGG"); // Tags not yet supported
//doGetID3Scan(zScanDir($path,"flac",$recursive),"FLAC"); // Tags not yet supported
doGetID3Scan(zScanDir($path,"mp4",$recursive),"MP4");
doGetID3Scan(zScanDir($path,"m4a",$recursive),"M4A");


function zgetTag($data) {
	$check = "";
	foreach ($data as $d) {
		$check = html_entity_decode($d[0]);
		if ($check != "")
			break;
	}
	return $check;
}


function doGetID3Scan($filelist,$type) {
	global $mysql;
        echo count($filelist)." ".$type." files found...\n";
	if (count($filelist) > 0) {
		foreach ($filelist as $file) {
			if (file_exists($file)) {
				$info = new getID3();
				$data = $info->analyze($file);

				$artist = zgetTag(array(@$data['tags']['id3v2']['artist'],@$data['tags_html']['quicktime']['artist'],@$data['tags']['quicktime']['artist']));
				$album = zgetTag(array(@$data['tags']['id3v2']['album'],@$data['tags_html']['quicktime']['album'],@$data['tags']['quicktime']['album']));
				$title = zgetTag(array(@$data['tags']['id3v2']['title'],@$data['tags_html']['quicktime']['title'],@$data['tags']['quicktime']['title']));
				$trackno = html_entity_decode(@$data['tags']['id3v2']['track_number'][0]);
				if (($trackno + 0) <= 0) {
					$trackno = zgetTag(array(@$data['tags_html']['quicktime']['comment'],@$data['tags']['quicktime']['comment']));
					$trackno = intval(preg_replace("/Track /i","",$trackno));
				}
				$length = round($data['playtime_seconds']);



				if (!$trackno) {
					echo "Warning: $file does not contain track number data.\n";
				}

				$ext = preg_split("/\./",$file);
				$ext = $ext[(count($ext)-1)];
				if ($artist && $title) {
					$check = checkData($file,$ext,$artist,$title,$album,$length,$trackno);
					if (!$check) { 
						addData($file,$ext,$artist,$title,$album,$length,$trackno);
					}
				} else {
                                        echo "Skipping ".$type." File: ".$file." (No tags or incomplete)\n";
                                }
			}
		}
	}
}

function zScanDir($path,$type,$recursive = 0)
{
	$i = 0;
	if ($handle = @opendir($path)) {
		while (false !== ($file = readdir($handle))) {
		       if ($file != "." && $file != ".." && $file != "") {
		                $ext = strtolower(substr($file,(strlen($file) - strlen($type)),strlen($type)));
	        	        if ($ext == $type) {
					$files[] = $path."/".$file;
				}
		       }
		}
	} else {
		die($path." does not exist.\n");
	}

	if ($recursive) {
		// Scan directories recursively

		if ($handle = opendir($path)) {
			while (false !== ($file = readdir($handle))) {
				if (filetype($path."/".$file) == 'dir') {
					if ($file != '..' && $file != '.' && $file != '' && (substr($file,0,1) != '.')) {
						$folders[$i] = $path."/".$file;
						$i++;
					}
				}
			}
		}
		
		@closedir($handle);

		for ($u = 0; $u < $i; $u++) {
			if ($handle = opendir($folders[$u])) {
				while (false !== ($file = readdir($handle))) {
					if (filetype($folders[$u]."/".$file) == 'dir') {
						if ($file != '..' && $file != '.' && $file != '' && (substr($file,0,1) != '.')) {
							$folders[$i] = $folders[$u]."/".$file;
							$i++;
						}
					}
					if (filetype($folders[$u]."/".$file) == 'file') {
				                $ext = strtolower(substr($file,(strlen($file) - strlen($type)),strlen($type)));
			        	        if ($ext == $type) {
							$files[] = $folders[$u]."/".$file;
						}
					}
				}
			}
			@closedir($handle);
		}
	}

	return @$files;
}

function checkData($file,$filetype,$artist,$title,$album,$length,$trackno) {
	global $mysql;
	$mysql->query("SELECT * from songlist WHERE filename = '".$mysql->escape($file)."';");
	$artist = trim($artist);
	$title = trim($title);
	$album = trim($album);
        if ($mysql->count() > 0) {
		$data = $mysql->row();
		if ($data[3] == $artist && $data[4] == $title && $data[5] == $album && $data[12] == $length && $data[6] == $trackno) {
//	                echo "Skipping ".strtoupper($data[1])." File: ".$artist." - ".$title." (Already in database (SongID ".$data[0].")\n";
		} else {
			updateData($data[0],$file,$filetype,$artist,$title,$album,$length,$trackno);
		}
		return true;
	}
	return false;
}

function addData($file,$filetype,$artist,$title,$album,$length,$trackno) {
	global $mysql;
	$artist = trim($artist);
	$title = trim($title);
	$album = trim($album);
        $data = $mysql->row();
        $mysql->query("INSERT INTO songlist (filetype,filename,artist,title,album,length,track) VALUES('".$filetype."','".$mysql->escape($file)."','".$mysql->escape($artist)."','".$mysql->escape($title)."','".$mysql->escape($album)."',".$length.",'".$trackno."');");
	$mysql->query("SELECT ID FROM songlist ORDER BY ID DESC LIMIT 1;");
        $data = $mysql->row();
        echo "Added ".strtoupper($filetype)." File ".$file.": ".$artist." - ".$title."\n";
}

function updateData($songid,$file,$filetype,$artist,$title,$album,$length,$trackno) {
	global $mysql;
        $data = $mysql->row();
        $mysql->query("UPDATE songlist SET artist = '".$mysql->escape($artist)."', title = '".$mysql->escape($title)."', album = '".$mysql->escape($album)."', length = ".$length.", track = '".$trackno."' WHERE ID = ".$songid);
        echo "Updated ".strtoupper($filetype)." File ".$file.": ".$artist." - ".$title."\n";
}

?>
