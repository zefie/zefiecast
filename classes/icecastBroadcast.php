<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
/*

Icecast Broadcasting Class v1.1.3 (C) 2006 Zefie Hosting

NOTES:
	- Icecast v1 is untested!!

CHANGES:

v1.1.3
        - Tweaked for compatiblity with latest SVN release of mplayer

v1.1.2
	- Integrated $streamFormat

v1.1.1
	- Removed lamest bug in the world. End of file was chopped off because I was using = instead of .= :/

v1.1
	- Vorbis encoding added

v1.0
	- Let the fun begin! Ported scBroadcast to icecastBroadcast
	- Icecast auth and metadata systems thanks to liboddcast (http://www.oddsock.org/).

*/

class icecastBroadcast
{
	var $host;
	var $user;
	var $pass;
	var $port;
	var $mount;
	var $errorMsg;
	var $bitrate;
	var $lasttime;
	var $scconn;
	var $usePB;
	var $globalbuf;


	function Connect($host, $port = 8000)
	{
		// Defines our destination server host and port
		$this->host = $host;
		$this->port = $port;
	}

	function Auth($user, $pass, $mount, $streamBitrate, $streamPublic = 0, $streamTitle = "My Station Name", $stationGenre = "", $streamAddress = "http://icecast.com/", $streamDesc = "My Icecast Station")
	{
		global $srate, $stereo, $uselame, $useogg;

		// This function attempts to authenticate with the remote server.
		// Returns true if connected
		// Returns false otherwise. To get the reason why the connection failed, use scBroadcast->getError();

		// Once true is returned, you should start broadcasting with scBroadcast->broadcast()


		$this->user = $user;
		$this->pass = $pass;
		$this->mount = $mount;

		if ($uselame) {
			$streamFormat = "mpeg";
		}
		if ($useaacp) {
			$streamFormat = "aacp";
		}		

		$scLoginConn = @fsockopen ($this->host, $this->port, $errno, $errstr, 3);
		if (!$scLoginConn) {
	        $this->errorMsg = "Server \"".$this->host.":".$this->port."\" is down. (".$errno.": ".$errstr.")";
	        return false;
		}
		$this->bitrate = $streamBitrate;
		if ($useogg) {
			$contype = "application/ogg";
		} else {
			$contype = "audio/".$streamFormat;
		}

		if ($icecast1) {
			$loginreq = "SOURCE ".$mount." ".$pass."\r\n";
			$loginreq .= "content-type: ".$contype."\r\n";
			$loginreq .= "x-audiocast-name: ".$streamTitle."\r\n";
			$loginreq .= "x-audiocast-url: ".$streamAddress."\r\n";
			$loginreq .= "x-audiocast-genre: ".$stationGenre."\r\n";
			$loginreq .= "x-audiocast-bitrate: ".$streamBitrate."\r\n";
			$loginreq .= "x-audiocast-public: ".$streamPublic."\r\n";
			$loginreq .= "x-audiocast-description: ".$streamDesc."\r\n\r\n";
		} else {

			$b64auth = base64_encode($this->user.":".$this->pass);

			$loginreq = "SOURCE ".$mount." ICE/1.0\n";
			$loginreq .= "content-type: ".$contype."\n";
			$loginreq .= "Authorization: Basic ".$b64auth."\n";
			$loginreq .= "ice-name: ".$streamTitle."\n";
			$loginreq .= "ice-url: ".$streamAddress."\n";
			$loginreq .= "ice-genre: ".$stationGenre."\n";
			$loginreq .= "ice-birate: ".$streamBitrate."\n";
			if ($streamPublic) {
				$loginreq .= "ice-private: 0\n";
				$loginreq .= "ice-public: 1\n";
			} else {
				$loginreq .= "ice-private: 1\n";
				$loginreq .= "ice-public: 0\n";
			}
			$loginreq .= "ice-description: ".$streamDesc."\n";
			if ($stereo) {
				$channels = 2;
			} else {
				$channels = 1;
			}
			$loginreq .= "ice-audio-info: ice-samplerate=".$srate.";ice-bitrate=".$streamBitrate.";ice-channels=".$channels."\n\n";
		}


		fputs($scLoginConn,$loginreq);
		$response = fgets($scLoginConn,128);

		if (!preg_match("/OK/",$response)) {
        		$auth = 0;
         		$this->errorMsg = "Authorization Failed. This can occur if the password is invalid, or the station is already broadcasting.";
           		return false;
	    	}

	        $this->scconn = $scLoginConn;
		$this->usePB = 1;
		return true;
	}

	function Update($streamMetadata)
	{
		global $version, $useogg;
		// Update the metadata on the server.
		// Returns true for success
		// False for failure. Again, use getError() for failure reason.

		// For OGG, this will silently fail.
	        $scUpdateConn = @fsockopen ($this->host, $this->port, $errno, $errstr, 3);
        	if (!$scUpdateConn) {
	                $this->errorMsg = "Server \"".$this->host.":".$this->port."\" is down. (".$errno.": ".$errstr.")";
		        return false;
			}

		if ($icecast1) {
			fputs($scUpdateConn,"GET /admin.cgi?pass=".$this->pass."&mode=updinfo&mount=".rawurlencode($this->mount)."&song=".rawurlencode($streamMetadata)." HTTP/1.0\r\n");
			fputs($scUpdateConn,"User-Agent: (Mozilla Compatible)\r\n\r\n");
		} else {
			$b64auth = base64_encode($this->user.":".$this->pass);
			fputs($scUpdateConn,"GET /admin/metadata?song=".rawurlencode($streamMetadata)."&mount=".urlencode($this->mount)."&mode=updinfo HTTP/1.0\r\n");
			fputs($scUpdateConn,"Authorization: Basic ".$b64auth."\r\n");
			fputs($scUpdateConn,"User-Agent: ZefieCast/".$version." (Mozilla Compatible)\r\n\r\n"); 
		}

	        fclose($scUpdateConn);
	        return true;
	}

	function getError()
	{
		return $this->errorMsg;
	}

	function broadcast($file)
	{	
	    global $useaacp, $useogg, $uselame, $bitrate, $srate, $powerbuffer, $mysql, $filters, $stereo;
	    $type = strtolower(substr($file,(strlen($file) - 3),3));

	    $decoderdesc = array(
			0 => array("pipe", "w"),
			1 => array("pipe", "w"),
      		2 => array("pipe", "w")
	    );
	
	    $encoderdesc = array(
	       	0 => array("pipe", "r"),
	        1 => array("pipe", "w"),
     		2 => array("pipe", "w")
	    );

	    if ($filters) {
		    $decoderproc = proc_open("mplayer -ao pcm:file=/dev/stderr -af ".$filters." ".escapeshellarg($file)." -really-quiet ".$mplayeropts, $decoderdesc, $decoderpipes);
	    } else {
		    $decoderproc = proc_open("mplayer -ao pcm:file=/dev/stderr ".escapeshellarg($file)." -really-quiet ".$mplayeropts, $decoderdesc, $decoderpipes);
	    }
		$decoderbuffer = 176444; // Best not to touch. 1 second of 44100hz 2ch data.
	    if ($uselame) {
	            $encoderproc = proc_open("lame -b ".$bitrate." --quiet --cbr --resample ".$srate." - -", $encoderdesc, $encoderpipes);
	    }
	    if ($useogg) {
		    // OGG streaming requires the metadata be in the OGG file itself, and will not allow
		    // and HTTP metadata update.

		    $mysql->query("SELECT artist,title,album FROM songlist WHERE filename = '".$mysql->escape($file)."';");
		    $data = $mysql->row();
		    $encopts = "-a \"".$data[0]."\" -t \"".$data[1]."\" -l \"".$data[2]."\"";

		    if (!$stereo) {
				$encopts = "--downmix";
		    }

            $encoderproc = proc_open("oggenc -b ".$bitrate." --quiet --resample=".$srate." ".$encopts." -", $encoderdesc, $encoderpipes);
	    }
		if ($useaacp) {
	            $encoderproc = proc_open("aacplusenc - - ".$this->bitrate, $encoderdesc, $encoderpipes);
	    }
	    if (is_resource($decoderproc) && is_resource($encoderproc)) {
		
		stream_set_blocking($encoderpipes[1], 0); // need non-blocking on the encoder because sometimes it isnt going to send data
		$riffheader = 0;
		$data2 = "";
		$packets = $this->bitrate/8;	
		if (!$this->lasttime)
			$this->lasttime = $this->microtime_float();
	        $data2send = $packets*1100;
			$buffer = "";
	        $read = $data2send;

	        while (!feof($decoderpipes[2])) {
				$data = fread($decoderpipes[2],$decoderbuffer);

				// This part parses the mplayer data and sends only the data from the RIFF header and beyond.
				if (!$riffheader) {
					$data2 .= $data;
					$index = strpos($data2,"RIFF");
					if ($index > -1) {
						$data = substr($data2,$index,(strlen($data2) - $index));
						$riffheader = 1;
						$data2 = "";
					}
				}
				if ($riffheader) {
					fwrite($encoderpipes[0],$data);
					if (strlen($buffer) < $read) {
						$buffer .= fread($encoderpipes[1], $read);
						if (strlen($buffer) > $read) {
							$extrabuffer = substr($buffer,$read,(strlen($buffer)-$read));
							$buffer = substr($buffer,0,$read);
						}
					}
			

					if (strlen($buffer) == $read) {
						$this->sendData($buffer);
						$time = $this->microtime_float();
						$read = ceil($data2send*($time-$this->lasttime));
						$this->lasttime = $time;
						if (strlen($extrabuffer) > 0) {
							$read = $read - strlen($extrabuffer);
							$buffer = $extrabuffer;
						} else {
							$buffer = "";
						}
						$mysql->query("SELECT skip FROM config;");
						$data = $mysql->row();
						if ($data[0] == 1) {
							$mysql->query("UPDATE config SET skip = '0';");
							echo "Song skip requested...\n";
							break;
						}
						usleep(750000);
					}
				
				}
			}
	
		// Get the last bit of data from the encoder
		fclose($decoderpipes[0]);
		fclose($decoderpipes[1]);
		fclose($encoderpipes[0]);

		if (strlen($extrabuffer) > 0) {
			$read = $read - strlen($extrabuffer);
			$buffer = $extrabuffer;
		} else {
			$buffer = "";
		}
	    while (!feof($decoderpipes[2])) {
			$data = "";
			if ($read == 0) { break; }
			$data = @fread($encoderpipes[1], $read);
			$buffer .= $data;
		        $time = $this->microtime_float();
		        $read = ceil($data2send*($time-$this->lasttime));
		        $this->lasttime = $time;
			if (strlen($extrabuffer) > 0) {
				$read = $read - strlen($extrabuffer);
				$buffer = $extrabuffer;
			} else {
				$buffer = "";
			}
			if (strlen($buffer) > $read) {
				$extrabuffer = substr($buffer,$read,(strlen($buffer)-$read));
				$buffer = substr($buffer,0,$read);
			}
			$this->sendData($buffer);
		}

		fclose($decoderpipes[2]);
		fclose($encoderpipes[1]);
		fclose($encoderpipes[2]);
		proc_close($decoderproc);
		proc_close($encoderproc);

	    }
	}

	function sendData($data) {
		if (strlen($data) > 0) {
			fwrite($this->scconn,$data);			
//			echo "[".microtime(true)."] Server received ".strlen($data)." bytes...\n";
		}
	}
	
	function microtime_float() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
}

?>
