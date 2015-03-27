<?php
// Transcoder v0.3
//
// Supports any icyx based streaming server
// Replace http:// with icyx:// for mplayer

$relay = "icyx://75.119.197.228:8000";
$uselame = 0;
$useaacp = 1;
$bitrate = 32;
//$srate = 11025;
$stationname = "" // Your Station's Name
$stationgenre = ""; // Your Station's Genre
$stationurl = ""; // Your Station's Website
$stationpub = 1; // 1 = List on Directory, 0 = Don't.
$scserv = ""; // Shoutcast Server IP
$scport = 8050; // Shoutcast Server Port
$scpass = "" // Broadcaster Password, doesn't HAVE to be admin password.

$stationirc = ""; // Your Station IRC
$stationicq = ""; // Who uses ICQ anymore?
$stationaim = ""; // Your Station AIM Name





$transcount = 0;




if ($uselame) {
	$stationFormat = "mpeg";
}
if ($useaacp) {
	$stationFormat = "aacp";
}

$scLoginConn = @fsockopen ($scserv, ($scport+1), $errno, $errstr, 3);
if (!$scLoginConn) {
	die("Could not connect to output server ($scserv:$scport)\n");
}

fputs($scLoginConn,$scpass."\n");
$response = fgets($scLoginConn,128);
if (!preg_match("/OK2/",$response)) {
	die("Could not authorize on output server ($scserv:$scport) (Is the server in use?)\n");
}

fputs($scLoginConn,"icy-name:".$stationname."\n");
fputs($scLoginConn,"icy-genre:".$stationgenre."\n");
fputs($scLoginConn,"icy-url:".$stationurl."\n");
fputs($scLoginConn,"icy-irc:".$stationirc."\n");
fputs($scLoginConn,"icy-icq:".$stationicq."\n");
fputs($scLoginConn,"icy-aim:".$stationaim."\n");
fputs($scLoginConn,"icy-pub:".$stationpub."\n");
fputs($scLoginConn,"icy-br:".$bitrate."\n");
fputs($scLoginConn,"content-type:audio/".$stationFormat."\n\n");

echo "Relaying $relay to $scserv:$scport...\n";


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

            $decoderproc = proc_open("mplayer -quiet -ao stderr \"".$relay."\"", $decoderdesc, $decoderpipes);

            $decoderbuffer = 176474; // Best not to touch. 1 second of 44100hz 2ch data.
            if ($uselame) {
                    $encoderproc = proc_open("lame --cbr -b ".$bitrate." --quiet --resample ".$srate." -m m - -", $encoderdesc, $encoderpipes);
            }
            if ($useaacp) {
                    $encoderproc = proc_open("aacplusenc - - ".$bitrate, $encoderdesc, $encoderpipes);
            }
            if (is_resource($decoderproc) && is_resource($encoderproc)) {

                stream_set_blocking($encoderpipes[1], 0); // need non-blocking on the encoder because sometimes it isnt going to send data
                stream_set_blocking($decoderpipes[1], 0);
                $riffheader = 0;
		$data = "";
                $data2 = "";
                $packets = (($bitrate+4)/8);
                if (!$lasttime)
                        $lasttime = microtime_float();
                $data2send = $packets*1024;
                $buffer = "";
                $read = $data2send;

                while (!feof($decoderpipes[2])) {
                        $data = fread($decoderpipes[2],$decoderbuffer);
			checkForMetaData($decoderpipes[1]);
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
                                        sendData($buffer);
                                        $time = microtime_float();
                                        $read = ceil($data2send*($time-$lasttime));
                                        $lasttime = $time;
                                        if (strlen($extrabuffer) > 0) {
                                                $read = $read - strlen($extrabuffer);
                                                $buffer = $extrabuffer;
                                        } else {
                                                $buffer = "";
                                        }
                                }
                        }
                }
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
                while (strlen($data) != 0) {
                        $data = "";
                        $data = fread($encoderpipes[1], $read);
                        $buffer .= $data;
                }

                while (strlen($buffer) == 0) {
                        $time = microtime_float();
                        $read = ceil($data2send*($time-$lasttime));
                        $lasttime = $time;
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
                        sendData($buffer);
                }

                fclose($encoderpipes[1]);
                fclose($encoderpipes[2]);
                proc_close($decoderproc);
                proc_close($encoderproc);

            }
       function sendData($data) {
           global $scLoginConn, $relay, $transcount, $oldmetadata;
           fwrite($scLoginConn,$data);
//              echo "[".microtime(true)."] Server received ".strlen($data)." bytes...\n";
                sleep(1);
        }
        function microtime_float() {
                list($usec, $sec) = explode(" ", microtime());
                return ((float)$usec + (float)$sec);
        }

        function metaUpdate($stationMetadata)
        {
		global $scserv, $scport, $scpass;
                // Update the metadata on the DNAS.
                // Returns true for success
                // False for failure. Again, use getError() for failure reason.

                $scUpdateConn = @fsockopen ($scserv, $scport, $errno, $errstr, 3);
                fputs($scUpdateConn,"GET /admin.cgi?pass=".$scpass."&mode=updinfo&song=".rawurlencode($stationMetadata)."&url= HTTP/1.0\n");
                fputs($scUpdateConn,"User-Agent: ShoutcastDSP (Mozilla Compatible)\n\n");

                fclose($scUpdateConn);
                return true;
        }

	function checkForMetadata($res) {
		$data = fread($res,2048);
		//ICY Info: StreamTitle='(ABSURD) minds - Body (The Focus)';StreamUrl='';
		$index = strpos($data,"StreamTitle");
		if ($index > 0) {
			$index = ($index + 13);
			$index2 = strpos($data,"';StreamUrl",$index);
			$metadata = substr($data,$index,($index2-$index));
			echo strftime("[%H:%M]",time())." ".$metadata."\n";
			metaUpdate($metadata);
		}
	}
