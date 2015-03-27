<?php
//metaflac --show-tag=title --show-tag=artist --show-tag=album
require("../config/config.php");
require("../classes/mysql.php");
require("../classes/checkApp.php");

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

if ($flags == "-v1") {
	$IDv1 = 1;
}

$check = new checkApp();
if (!$check->check("mplayer")) {
        die("mplayer is required for ZefieCast to work. Please visit http://www.mplayerhq.hu/");
}

if ($check->check("python -h")) {
	doWMAScan(zScanDir($path,"wma",$recursive));
} else {
	echo "WMA Source Disabled. Please install python (for wmainfo.py support)\n";
}

if ($check->check("id3info")) {
	$hasid3info = 1;
} else {
	echo "MP3 Source Enabled, but ID3v1 only. Please install id3tag for ID3v2 Support\n";
}

if ($check->check("mp3info")) {
	doMP3Scan(zScanDir($path,"mp3",$recursive));
} else {
	echo "MP3 Source Disabled. Please install mp3info (http://www.ibiblio.org/mp3info/)\n";
}

if ($check->check("ogginfo")) {
	doOGGScan(zScanDir($path,"ogg",$recursive));
} else {
	echo "OGG Source disabled. Please install vorbis-tools to enable\n";
}

if ($check->check("metaflac")) {
	doFLACScan(zScanDir($path,"flac",$recursive));
} else {
	echo "FLAC Source disabled. Please install flac to enable (http://flac.sf.net/)\n";
}

if ($check->check("faad")) {
	doMP4Scan(zScanDir($path,"mp4",$recursive),"MP4");
	doMP4Scan(zScanDir($path,"m4a",$recursive),"M4A");
} else {
	echo "AAC Source disabled. Please install faad2 to enable\n";
}

function doWMAScan($filelist)
{
	global $mysql;
	echo count($filelist)." WMA files found...\n";
	if (count($filelist) > 0) {
		// This is the WMA section

		foreach ($filelist as $file) {
			if (file_exists($file)) {
	
				$tagdata = `python wmainfo.py "$file" 2>&1`;

				$index = strpos($tagdata,"AlbumTitle:") + 12;
				$index2 = strpos($tagdata,"\n",$index);
				$album = substr($tagdata,$index,($index2-$index));
			
				$index = strpos($tagdata,"Author:") + 8;
				$index2 = strpos($tagdata,"\n",$index);
				$artist = substr($tagdata,$index,($index2-$index));

				$index = strpos($tagdata,"\nTitle:") + 8;
				$index2 = strpos($tagdata,"\n",$index);
				$title = substr($tagdata,$index,($index2-$index));

				$index = strpos($tagdata,"\nplaytime_second:") + 18;
				$index2 = strpos($tagdata,"\n",$index);
				$length = substr($tagdata,$index,($index2-$index));
				
				if ($artist && $title) {
					$check = checkData($file,"wma",$artist,$title,$album,$length);
					if (!$check) { 
						addData($file,"wma",$artist,$title,$album,$length);
					}
				} else {
                                        echo "Skipping WMA File: ".$file." (No tags or incomplete)\n";
                                }
			}
		}
	}
}

function doFLACScan($filelist)
{
	global $mysql;
	echo count($filelist)." FLAC files found...\n";
	if (count($filelist) > 0) {
		// This is the FLAC section

		foreach ($filelist as $file) {
			if (file_exists($file)) {
	
				$tagdata = `metaflac --show-tag=title --show-tag=artist --show-tag=album --show-total-samples --show-sample-rate "$file"`;
				$tagdata = split("\n",$tagdata);
				$metadata = "";
				foreach ($tagdata as $tag) {
					$tag = split("=",$tag);
					if ($tag[1])
						$metadata[strtolower($tag[0])] = $tag[1];
					else
						$metalen[] = $tag[0];
				}
				$title = $metadata['title'];
				$album = $metadata['album'];
				$artist = $metadata['artist'];
				$length = round($metalen[0] / $metalen[1]);

				if ($artist && $title) {
					$check = checkData($file,"flac",$artist,$title,$album,$length);
					if (!$check) { 
						addData($file,"flac",$artist,$title,$album,$length);
					}
				} else {
                                        echo "Skipping FLAC File: ".$file." (No tags or incomplete)\n";
                                }
			}
		}
	}
}


function doMP3Scan($filelist)
{
	global $mysql, $hasid3info, $IDv1;
	echo count($filelist)." MP3 files found...\n";

        if (count($filelist) > 0) {
                // This is the MP3 section

                foreach ($filelist as $file) {
                        if (file_exists($file)) {
				$artist="";
				$title="";
				$album="";
				$id3tags="";
				if ($hasid3info && !$IDv1) {
					$id3dat = `id3info "$file"`;
					$id3dat = split("\n",$id3dat);
					foreach ($id3dat as $tag) {
						$tagtype = substr($tag,4,4);
						$index = strpos($tag,":")+1;
						$tagvalue = substr($tag,$index,(strlen($tag)-$index));
						$id3tags[$tagtype] = $tagvalue;
					}
					$artist = $id3tags['TPE2'];
					$album = $id3tags['TALB'];
					$title = $id3tags['TIT2'];
					if (!$artist) {
						$artist = `mp3info -p "%a" "$file"`;
					}
					if (!$album) {
						$album = `mp3info -p "%l" "$file"`;
					}
					if (!$title) {
						$title = `mp3info -p "%t" "$file"`;
					}
				} else {
					$artist = `mp3info -p "%a" "$file"`;
					$title = `mp3info -p "%t" "$file"`;
					$album = `mp3info -p "%l" "$file"`;
				}
				$length = `mp3info -p "%S" "$file"`;

				if ($artist && $title) {
					$check = checkData($file,"mp3",$artist,$title,$album,$length);
					if (!$check) { 
						addData($file,"mp3",$artist,$title,$album,$length);
					}
				} else {
                                        echo "Skipping MP3 File: ".$file." (No tags or incomplete)\n";
                                }

                        }
                }
        }
}

function doOGGScan($filelist)
{
	global $mysql;
	echo count($filelist)." OGG files found...\n";

        if (count($filelist) > 0) {
                // This is the OGG section

                foreach ($filelist as $file) {
                        if (file_exists($file)) {
                                $mediascan = @popen("ogginfo \"".$file."\" 2>&1","r");
				if (!$mediascan) {
					die("Please install vorbis-tools\n");
				}
                                $hdata = "";
                                while (!feof($mediascan)) {
                                        $hdata .= fread($mediascan,1024);
                                }
                                fclose($mediascan);
                                $index = (strpos($hdata,"Title=") + 6);
                                $index2 = strpos($hdata,"\n",$index);
                                $title = substr($hdata,$index,($index2 - $index));

                                $index = (strpos($hdata,"Artist=") + 7);
                                $index2 = strpos($hdata,"\n",$index);
                                $artist = substr($hdata,$index,($index2 - $index));

                                $index = (strpos($hdata,"Album=") + 6);
                                $index2 = strpos($hdata,"\n",$index);
                                $album = substr($hdata,$index,($index2 - $index));

                                $index = (strpos($hdata,"Playback length:") + 17);
                                $index2 = strpos($hdata,"\n",$index);
                                $length = substr($hdata,$index,($index2 - $index));

				$index = strpos($length,"m");
				$min = substr($length,0,$index);
				$sec = substr($length,($index + 2),(strlen($length) + ($index + 2)));
				$length = $sec + ($min * 60);
		
				if ($artist && $title) {
					$check = checkData($file,"ogg",$artist,$title,$album,$length);
					if (!$check) { 
						addData($file,"ogg",$artist,$title,$album,$length);
					}
				} else {
                                        echo "Skipping OGG File: ".$file." (No tags or incomplete)\n";
                                }
                        }
                }
        }
}

function doMP4Scan($filelist,$fileext)
{
	global $mysql;
	echo count($filelist)." ".$fileext." files found...\n";

        if (count($filelist) > 0) {
                // This is the MP4/AAC section

                foreach ($filelist as $file) {
                        if (file_exists($file)) {
                                $mediascan = @popen("faad -i \"".$file."\" 2>&1","r");
				if (!$mediascan) {
					die("Please install faad\n");
				}
                                $hdata = "";
                                while (!feof($mediascan)) {
                                        $hdata .= fread($mediascan,1024);
                                }


                                fclose($mediascan);
                                $index = (strpos($hdata,"title:") + 7);
                                $index2 = strpos($hdata,"\n",$index);
                                $title = substr($hdata,$index,($index2 - $index));

                                $index = (strpos($hdata,"artist:") + 8);
                                $index2 = strpos($hdata,"\n",$index);
                                $artist = substr($hdata,$index,($index2 - $index));

                                $index = (strpos($hdata,"album:") + 7);
                                $index2 = strpos($hdata,"\n",$index);
                                $album = substr($hdata,$index,($index2 - $index));

                                $index = (strpos($hdata,"\t") + 1);
				$index2 = (strpos($hdata,"secs") - 1);
                                $length = round(substr($hdata,$index,($index2 - $index)));

				if (!preg_match("/\*\*\*\*/",$artist)) {
					$check = checkData($file,"mp4",$artist,$title,$album,$length);
					if (!$check) { 
						addData($file,"mp4",$artist,$title,$album,$length);
					}
				} else {
					echo "Skipping MP4 File: ".$file." (No tags or incomplete)\n";
				}
                        }
                }
        }
}

function zScanDir($path,$type,$recursive = 0)
{

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

	return $files;
}

function checkData($file,$filetype,$artist,$title,$album,$length) {
	global $mysql;
	$mysql->query("SELECT * from songlist WHERE filename = '".$mysql->escape($file)."';");
	$artist = trim($artist);
	$title = trim($title);
	$album = trim($album);
        if ($mysql->count() > 0) {
		$data = $mysql->row();
		if ($data[3] == $artist && $data[4] == $title && $data[5] == $album && $data[11] == $length) {
//	                echo "Skipping ".strtoupper($data[1])." File: ".$artist." - ".$title." (Already in database (SongID ".$data[0].")\n";
		} else {
			updateData($data[0],$file,$filetype,$artist,$title,$album,$length);
		}
		return true;
	}
	return false;
}

function addData($file,$filetype,$artist,$title,$album,$length) {
	global $mysql;
	$artist = trim($artist);
	$title = trim($title);
	$album = trim($album);
        $data = $mysql->row();
        $mysql->query("INSERT INTO songlist (filetype,filename,artist,title,album,length) VALUES('".$filetype."','".$mysql->escape($file)."','".$mysql->escape($artist)."','".$mysql->escape($title)."','".$mysql->escape($album)."',".$length.");");
	$mysql->query("SELECT ID FROM songlist ORDER BY ID DESC LIMIT 1;");
        $data = $mysql->row();
        echo "Added ".strtoupper($filetype)." File ".$file.": ".$artist." - ".$title."\n";
}

function updateData($songid,$file,$filetype,$artist,$title,$album,$length) {
	global $mysql;
        $data = $mysql->row();
        $mysql->query("UPDATE songlist SET artist = '".$mysql->escape($artist)."', title = '".$mysql->escape($title)."', album = '".$mysql->escape($album)."', length = ".$length." WHERE ID = ".$songid);
        echo "Updated ".strtoupper($filetype)." File ".$file.": ".$artist." - ".$title."\n";
}

?>
