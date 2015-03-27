<?php
define ('CRLF', "\r\n");
function cleanup($chunk, $key)
{
	$toreturn = substr($chunk, strpos($chunk, "<$key>") + strlen($key) + 2, strpos($chunk, "</$key>") - strpos($chunk, "<$key>") - strlen($key) - 2);
	return $toreturn;
}
class Shoutcast
{
	var $host;
	var $pass;
	var $port;
	var $listeners;
	var $streamTitle;
	var $songTitle;
	var $siteRaw;
	var $status;
	var $lastSongString;
	//Set this string to whatever
	var $down = 0;

	function Shoutcast($host, $pass = "", $port = "")
	{
		$this->host = $host;
		$this->pass = $pass;
		$this->port = $port;
	}
	/*function Shoutcast($host)
	{
		$this->host = $host;
		$this->port = 80;
	{*/

	function NormConnect()
	{
		$shoutcast = fopen($this->host, "r");
		$this->status = 1;
		while ($line=fgets($shoutcast,4096))
		{
			$siteRaw .= $line;
		}
		$this->siteRaw = $siteRaw;

	}
	function ShoutConnect()
	{
		$shoutcast = fsockopen($this->host, $this->port, &$errno, &$errstr, 10);
		if ($shoutcast)
		{
			stream_set_timeout($shoutcast, 8);
			if ($this->pass == "") //just got an xml file from somewhere
			{
				fputs($shoutcast, "GET /xml/metadata.xml HTTP/1.0" . CRLF . "Host: " . $this->host . ":" . $this->port . CRLF . "User-Agent: Mozilla/5.0" . CRLF .  "Accept: text/html" . CRLF . CRLF);
			}
			else //it's actually a Shoutcast server
			{
				fputs($shoutcast, "GET /admin.cgi?pass=" . $this->pass . "&mode=viewxml&page=0 HTTP/1.0" . CRLF . "Host: " . $this->host . ":" . $this->port . CRLF . "User-Agent: Mozilla/5.0" . CRLF .  "Accept: text/html" . CRLF . CRLF);
			}
			while ($line=fgets($shoutcast,4096))
			{
				$siteRaw .= $line;
			}
			$this->status = 1;
		}
		else
		{
			$siteRaw = '';
			$this->status = 0;
		}
		#$siteRaw = html_entity_decode($siteRaw, NULL, "UTF-8");
		#$siteRaw = iconv("UTF-8", "Shift_JIS", $siteRaw);
		$siteRaw = str_replace('&#x27;','\'',$siteRaw);//some common "weird" character replacements
		$siteRaw = str_replace('&#x26;','&',$siteRaw);

		$this->siteRaw = $siteRaw;
		//return $siteRaw;
	}
	function getRaw()
	{
		if ($this->status == 1)
		return $this->siteRaw;
		else return '';
	}
	function getHost()
	{
		return $this->host;
	}
	function getStatus()
	{
		if ($this->status == 1)
		{
			return cleanup($this->siteRaw, "STREAMSTATUS");
		}
		else return $down;
	}

	function getListeners()
	{
		if ($this->status == 1)
		{
			return cleanup($this->siteRaw, "CURRENTLISTENERS");
		}
		else return $down;
	}

	function getStreamtitle()
	{
		if ($this->status == 1)
		{
			return cleanup($this->siteRaw, "SERVERTITLE");
		}
		else return $down;
	}

	function getBitrate()
	{
		if ($this->status == 1)
		{
			return cleanup($this->siteRaw, "BITRATE");
		}
		else return $down;
	}

	function getSongtitle()
	{
		if ($this->status == 1)
		{
			return cleanup($this->siteRaw, "SONGTITLE");
		}
		else return $down;
	}

	function prevSongTitle($number)
	{
		if ($number >= 0 & $number < 11)
		{
			preg_match_all ( "/<SONG>([(\S|\s)+]+)<\/SONG>/Ui", $this->siteRaw, $results, PREG_SET_ORDER);
			$lastSongString = $results[$number][0];
			$lastSong = cleanup($lastSongString, "TITLE");
			return $lastSong;
		}
		else return null;
	}

	function prevSongTime($number)
	{
		if ($number >= 0 & $number < 11)
		{
			preg_match_all ( "/<SONG>([(\S|\s)+]+)<\/SONG>/Ui", $this->siteRaw, $results, PREG_SET_ORDER);
			$lastSongString = $results[$number][0];
			$playTime = cleanup($lastSongString, "PLAYEDAT");
			return $playTime;
		}
		else return null;
	}
}
?>
