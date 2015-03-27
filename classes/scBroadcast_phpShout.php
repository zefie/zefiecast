<?php /*

ShoutCAST Broadcasting Class phpShout Edition v1.1 (C) 2006-2009 Zefie Hosting

This version requirest libshout v2.1 or greater, as well as phpShout
http://phpshout.sf.net/

CHANGES:

v1.1
	- Proper closing and reading of end of data from lame.
v1.0
	- Initial

*/

dl('shout.so');

class scBroadcast
{
	var $host;
	var $pass;
	var $port;
	var $errorMsg;
	var $scconn;
	var $usePB;
	var $error;
	var $skipsong = 0;

	function Connect($host, $port = 8000)
	{
		// Defines our destination server host and port
		$this->host = $host;
		$this->port = $port;
	}

	function Auth($pass, $streamBitrate, $streamPublic = 0, $streamTitle = "My Station Name", $stationGenre = "", $streamAddress = "http://shoutcast.com/", $streamAIM = "", $streamICQ = "", $streamIRC = "")
	{
		global $uselame, $version, $srate;

		// This function attempts to authenticate with the remote server.
		// Returns true if connected
		// Returns false otherwise. To get the reason why the connection failed, use scBroadcast->getError();

		// Once true is returned, you should start broadcasting with scBroadcast->broadcast();

		$this->pass = $pass;

		if ($uselame) {
			$streamFormat = "mpeg";
		}	

		$this->scconn = shout_create($this->host.':'.$this->port."/", '', $this->pass, SHOUT_FORMAT_MP3);

		shout_set_name($this->scconn, $streamTitle);
		shout_set_agent($this->scconn, "ZefieCast v".$version." - scBroadcast phpShout");
		shout_set_genre($this->scconn, $stationGenre);
		shout_set_protocol($this->scconn, SHOUT_PROTOCOL_ICY);
		shout_set_audio_info($this->scconn, SHOUT_AI_BITRATE, $streamBitrate);
		shout_set_audio_info($this->scconn, SHOUT_AI_SAMPLERATE, $srate);
		shout_set_public($this->scconn,$streamPublic);
		shout_set_url($this->scconn,$streamAddress);

		if (shout_open($this->scconn) != SHOUTERR_SUCCESS) {
           		return false;
	    	}
		return true;
	}

        function Update($streamMetadata)
        {
                // Update the metadata on the DNAS.
                // Returns true for success
                // False for failure. Again, use getError() for failure reason.

                $scUpdateConn = @fsockopen ($this->host, $this->port, $errno, $errstr, 3);
                if (!$scUpdateConn) {
                        $this->errorMsg = "Server \"".$this->host.":".$this->port."\" is down. (".$errno.": ".$errstr.")";
                        return false;
                }

                fputs($scUpdateConn,"GET /admin.cgi?pass=".$this->pass."&mode=updinfo&song=".rawurlencode($streamMetadata)."&url= HTTP/1.0\n");
                fputs($scUpdateConn,"User-Agent: ShoutcastDSP (Mozilla Compatible)\n\n");

                fclose($scUpdateConn);
                return true;
        }

	function getError()
	{
		return $this->errorMsg;
	}

	function broadcast($file)
	{	
	    global $useaacp, $uselame, $bitrate, $srate, $vbr, $mysql, $filters, $vbrquality;
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
		    $decoderproc = proc_open("mplayer -ao stderr -af ".$filters." \"".$file."\" -really-quiet ".$mplayeropts, $decoderdesc, $decoderpipes);
	    } else {
		    $decoderproc = proc_open("mplayer -ao stderr \"".$file."\" -really-quiet ".$mplayeropts, $decoderdesc, $decoderpipes);
	    }
	    if ($uselame == 1) {
		    if ($vbr) {
			if ($vbrquality) {
		            $encoderproc = proc_open("lame --vbr-new -V".($vbrquality-1)." --quiet --resample ".$srate." - -", $encoderdesc, $encoderpipes);
			} else {
		            $encoderproc = proc_open("lame --vbr-new -b 32 -B ".$bitrate." --quiet --resample ".$srate." - -", $encoderdesc, $encoderpipes);
			}
		    } else {
		            $encoderproc = proc_open("lame --cbr -b ".$bitrate." --quiet --resample ".$srate." - -", $encoderdesc, $encoderpipes);
    		    }
    		    $decoderbuffer = 176474; // Best not to touch. 1 second of 44100hz 2ch data.
	    }
	    if (is_resource($decoderproc) && is_resource($encoderproc)) {
		
		stream_set_blocking($encoderpipes[1], 0); // need non-blocking on the encoder because sometimes it isnt going to send data
		$riffheader = 0;
		$data2 = "";
		$cycles = 0;
	
	        while (!feof($decoderpipes[2]) && $this->skipsong == 0) {
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
				$data = fread($encoderpipes[1],$decoderbuffer);
                		if (!$this->sendData($data)) break;
			}
	        }
	
		// Get the last bit of data from the encoder

		fclose($decoderpipes[0]);
		fclose($decoderpipes[1]);
		fclose($decoderpipes[2]);
		fclose($encoderpipes[0]);

		while (!feof($encoderpipes[1])) {
			$data = fread($encoderpipes[1],$decoderbuffer);
               		if (!$this->sendData($data)) break;
		}

		fclose($encoderpipes[1]);
		fclose($encoderpipes[2]);
		proc_close($decoderproc);
		proc_close($encoderproc);
		$this->skipsong = 0;
	    }
	}

	function sendData($data) {
		shout_send($this->scconn,$data);
	//	echo "[".microtime(true)."] Server received ".strlen($data)." bytes...\n";
		if (shout_get_error($this->scconn) == "Socket error") {
			$this->error = 1;
			return false;		
		}
		shout_sync($this->scconn);
		return true;
	}

	function skipSong() {
		$this->skipsong = 1;
	}
}


?>


