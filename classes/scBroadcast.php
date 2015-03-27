<?php /*

ShoutCAST Broadcasting Class v3.1 (C) 2006-2009 Zefie Hosting

CHANGES:

v3.1
	- Proper closing and reading end of buffer from lame

v3.0
	- Changed buffer code thanks to Cynagen @ irc.irchighway.net

v2.2
	- Rewrote buffer system again, this time it should work, since we are using a multipler of the bitrate,
	  and sending just the right about of data every second. Also added $enableskip option for MySQL Sanity

v2.1.8
	- Tweaked for compatiblity with latest SVN release of mplayer

v2.1.7
	- Integrated $streamFormat

v2.1.6
	- More tweaks to the buffer system, this time to the EOF handling. (End of song encoder reading)

v2.1.5
	- Maybe some day...

v2.1.4
	- Will I ever get the buffer right? :(

v2.1.3
	- Corrected buffer so that it works with bitrates other than 64kbps >.> (Hopefully)

v2.1.2
	- Technically reverted back to v2.1 after realizing the buffer issue was induced by cpu load. >.>

v2.1.1
	- Fixed buffer for real this time, hopefully.

v2.1
	- Added skip function

v2.0.1
	- Bug fixes

v2.0a
	- Added scBroadcast->broadcast

v1.0.1
	- Removed unused variable definitions
	- Optimized Update function to use rawurlencode instead of hacky str_replaces (function I created before knowledge of rawurlencode)

v1.0
	- Initial

*/

class scBroadcast
{
	var $host;
	var $pass;
	var $port;
	var $errorMsg;
	var $scconn;
	var $bitrate;
	var $lasttime;
	var $transcount;
	var $metadata;

	function Connect($host, $port = 8000)
	{
		// Defines our destination server host and port
		$this->host = $host;
		$this->port = $port;
	}

	function Auth($pass, $streamBitrate, $streamPublic = 0, $streamTitle = "My Station Name", $stationGenre = "", $streamAddress = "http://shoutcast.com/", $streamAIM = "", $streamICQ = "", $streamIRC = "")
	{
		global $uselame, $useaacp;

		// This function attempts to authenticate with the remote server.
		// Returns true if connected
		// Returns false otherwise. To get the reason why the connection failed, use scBroadcast->getError();

		// Once true is returned, you should start broadcasting with scBroadcast->broadcast();

		$this->pass = $pass;

		if ($uselame) {
			$streamFormat = "mpeg";
		}
		if ($useaacp) {
			$streamFormat = "aacp";
		}

		$scLoginConn = @fsockopen ($this->host, ($this->port+1), $errno, $errstr, 3);
		if (!$scLoginConn) {
	        $this->errorMsg = "Server \"".$this->host.":".$this->port."\" is down. (".$errno.": ".$errstr.")";
	        return false;
		}

		fputs($scLoginConn,$pass."\n");
		$response = fgets($scLoginConn,128);
		if (!preg_match("/OK2/",$response)) {
        		$auth = 0;
         		$this->errorMsg = "Authorization Failed. This can occur if the password is invalid, or the station is already broadcasting.";
           		return false;
	    	}

	        fputs($scLoginConn,"icy-name:".$streamTitle."\n");
        	fputs($scLoginConn,"icy-genre:".$stationGenre."\n");
	        fputs($scLoginConn,"icy-url:".$streamAddress."\n");
	        fputs($scLoginConn,"icy-irc:".$streamIRC."\n");
	        fputs($scLoginConn,"icy-icq:".$streamICQ."\n");
	        fputs($scLoginConn,"icy-aim:".$streamAIM."\n");
	        fputs($scLoginConn,"icy-pub:".$streamPublic."\n");
	        fputs($scLoginConn,"icy-br:".$streamBitrate."\n");
	        fputs($scLoginConn,"content-type:audio/".$streamFormat."\n\n");
	
		$this->bitrate = $streamBitrate;

	        $this->scconn = $scLoginConn;
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
	    global $useaacp, $uselame, $srate, $mysql, $filters, $transcoder;
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
		    $decoderproc = proc_open("mplayer -ao pcm:file=/dev/stderr -af ".$filters." \"".$file."\" -really-quiet ".$mplayeropts, $decoderdesc, $decoderpipes);
	    } else {
		    $decoderproc = proc_open("mplayer -ao pcm:file=/dev/stderr \"".$file."\" -really-quiet ".$mplayeropts, $decoderdesc, $decoderpipes);
	    }
	    $decoderbuffer = 176474; // Best not to touch. 1 second of 44100hz 2ch data.
	    if ($uselame == 1) {
	            $encoderproc = proc_open("lame --cbr -b ".$this->bitrate." --quiet --resample ".$srate." - -", $encoderdesc, $encoderpipes);
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
	        $data2send = $packets*1024;
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
				}
			}
	        }
	
		// Get the last bit of data from the encoder

		fclose($decoderpipes[0]);
		fclose($decoderpipes[1]);
		fclose($decoderpipes[2]);
		fclose($encoderpipes[0]);

		if (strlen($extrabuffer) > 0) {
			$read = $read - strlen($extrabuffer);
			$buffer = $extrabuffer;
		} else {
			$buffer = "";
		}
	        while (!feof($decoderpipes[2])) {
			$data = "";
			$data = fread($encoderpipes[1], $read);
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

		fclose($encoderpipes[1]);
		fclose($encoderpipes[2]);
		proc_close($decoderproc);
		proc_close($encoderproc);

	    }
	}

	function sendData($data) {
		global $transcoder, $relay;
                fwrite($this->scconn,$data);
//		echo "[".microtime(true)."] Server received ".strlen($data)." bytes...\n";
		sleep(1);
	}

	function microtime_float() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
}

?>


