<?php

class LogicReactor
{
	function queueSong($id = 0,$position = 0,$request = 0)
	{
		global $artistplay, $titleplay, $albumplay, $mysql;

		$art = ($artistplay * 60);
		$tit = ($titleplay * 60);
		$alb = ($albumplay * 60);

		$mysql->query("SELECT songid FROM queuelist");
	
		if ($id == 0) {
			$mysql->query("SELECT ID, artist, title, album FROM songlist WHERE (artistlastplayed < FROM_UNIXTIME(UNIX_TIMESTAMP() - ".$art.")) AND (titlelastplayed < FROM_UNIXTIME(UNIX_TIMESTAMP() - ".$tit.")) AND (albumlastplayed < FROM_UNIXTIME(UNIX_TIMESTAMP() - ".$alb.")) ORDER BY RAND() LIMIT 1");
			if ($mysql->count() == 0) {
				echo "Could not find a song that matchs the current rotation rules!\n";
				echo "Queueing a random file as a fallback...\n";
				$mysql->query("SELECT ID,artist,title,album FROM songlist ORDER BY RAND() LIMIT 1");
			}
		} else {
			$mysql->query("SELECT ID, artist, title, album FROM songlist WHERE ID = ".$id." LIMIT 1");
		}
	
		$data = $mysql->row();
		if (!$data[0]) {
			die("Error: No songs in database!");
		}
		$songid = $data[0];
		$artist = $data[1];
		$title = $data[2];
		$album = $data[3];
	
		if ($position == 0) {
			$mysql->query("SELECT sortid FROM queuelist ORDER BY sortid DESC LIMIT 1");
			if ($mysql->count() != 0) {
				$data = $mysql->row();
				$sortid = ($data[0] + 1);
			} else {
				$sortid = 1;
			}
		} else {
			$sortid = $position;
		}
		
		$mysql->query("INSERT INTO queuelist (songid,sortid,requested) VALUES(".$songid.",".$sortid.",'".($request+0)."')");
		$mysql->query("UPDATE songlist SET lastplayed = CURRENT_TIMESTAMP() WHERE ID = ".$songid.";");
		if ($artist) {
			$mysql->query("UPDATE songlist SET artistlastplayed = CURRENT_TIMESTAMP() WHERE artist = '".$mysql->escape($artist)."';");
		}
		if ($title) {
			$mysql->query("UPDATE songlist SET titlelastplayed = CURRENT_TIMESTAMP() WHERE title = '".$mysql->escape($title)."';");
		}
		if ($album) {
			$mysql->query("UPDATE songlist SET albumlastplayed = CURRENT_TIMESTAMP() WHERE album = '".$mysql->escape($album)."';");
		}
	}

	function playSong($id)
	{
		global $mysql;
		$mysql->query("SELECT songlist.ID, songlist.artist, songlist.title, songlist.album, songlist.filename, queuelist.requested FROM songlist,queuelist WHERE songlist.ID = ".$id.";");
		$data = $mysql->row();
		$artist = $data[1];
		$title = $data[2];
		$album = $data[3];
		$filename = $data[4];
		$requested = $data[5];

		$mysql->query("DELETE FROM queuelist WHERE songid = ".$id." ORDER BY sortid ASC LIMIT 1");
		$mysql->query("INSERT INTO historylist (songid,requested) VALUES(".$id.",'".($requested+0)."');");
		return array(artist => $artist, title => $title, album => $album, filename => $filename, requested => ($requested+0));
	}

	function queueLastSong()
	{
		global $mysql;
		$mysql->query("SELECT songid from historylist ORDER BY played DESC LIMIT 2,1;");
		$data = $mysql->row();
		$this->queueSong($data[0],"1",0);
	}

	function getSong()
	{
		global $queuesongs, $mysql;
		$mysql->query("SELECT songid,sortid FROM queuelist ORDER BY sortid ASC");
		if ($mysql->count() == 0) {
			if (!$queuesongs) {
				$this->queueSong();
			} else {
				echo "Queueing ".$queuesongs." song";
				if ($queuesongs != 1) {
					echo "s";
				}
				echo "...\n\n";
				$i = 0;
				while ($i != ($queuesongs+1)) {
					$this->queueSong();
					$i++;
				}
			}
			$mysql->query("SELECT songid,sortid FROM queuelist ORDER BY sortid ASC");
		}
		if ($queuesongs && $mysql->count() < ($queuesongs+1)) {
			$i = $mysql->count();
                        while ($i != ($queuesongs+1)) {
                                $this->queueSong();
                                $i++;
                        }
			$mysql->query("SELECT songid,sortid FROM queuelist ORDER BY sortid ASC");
		}
		$data = $mysql->row();
		return $this->playSong($data[0]);
	}
	
	function checkSong($id) {
		global $artistreq, $titlereq, $albumreq, $mysql;

		$art = ($artistreq * 60);
		$tit = ($titlereq * 60);
		$alb = ($albumreq * 60);

		$mysql->query("SELECT COUNT(*) FROM queuelist WHERE songid = ".$id.";");
		$data = $mysql->row();
		if ($data[0] > 0) {
			return "Song already in queue";
		}
		$mysql->query("SELECT artist,album FROM songlist WHERE id = ".$id.";");
		$data = $mysql->row();
		$art = $data[0];
		$album = $data[1];
	
		$mysql->query("SELECT COUNT(*) FROM songlist,queuelist WHERE queuelist.songid = songlist.id AND songlist.artist = \"".$art."\";");
		$data = $mysql->row();
		if ($data[0] > 0) {
			return "Artist already in queue";
		}

		$mysql->query("SELECT COUNT(*) FROM songlist,queuelist WHERE queuelist.songid = songlist.id AND songlist.album = \"".$album."\";");
		$data = $mysql->row();
		if ($data[0] > 0) {
			return "Album already in queue";
		}
		
		$mysql->query("SELECT artistlastplayed,titlelastplayed,albumlastplayed FROM songlist WHERE ID = ".$id.";");
		$data = $mysql->row();
		$mysql->query("SELECT CURRENT_TIME();");
		$time = $mysql->row();
		$time = $time[0];
		if (strtotime($data[1]) > ($time - $tit)) {
			return "Title Recently Played";
		}
		elseif (strtotime($data[2]) > ($time - $album)) {
			return "Album Recently Played";
		}
		elseif (strtotime($data[0]) > ($time - $art)) {
			return "Artist Recently Played";
		}
		else {
			return "OK";
		}
	}
	
	function checkRequest($address) {
		global $reqlimit, $reqtime, $mysql;
	
		$rtime = ($reqtime * 60);

                $mysql->query("SELECT COUNT(*) FROM requestlist WHERE address = '".$address."' AND (date > FROM_UNIXTIME(UNIX_TIMESTAMP() - ".$rtime."));");
		$data = $mysql->row();
		if ($data[0] >= $reqlimit) {
			return "Request limit (".$reqlimit." songs in ".$reqtime." minutes) exceeded.";
		} else {
			return "OK";
		}
	}

	function doRequest($id,$address) {
		global $enablereq, $mysql;
		if ($enablereq == 1) {
			$res = $this->checkSong($id);
			if ($res != "OK") {
				return $res;
			}

			$res = $this->checkRequest($address);
			if ($res != "OK") {
				return $res;
			}
			
			$mysql->query("INSERT INTO requestlist (songid,address) VALUES(".$id.",'".$address."')");
			$this->queueSong($id,0,1);
			$mysql->query("SELECT COUNT(*) FROM queuelist");
			$count = $mysql->row();
			return $count[0];
		} else {
			return "Requests are disabled";
		}
	}	
}

?>
