<?php

// MySQL Class for ZefieCast v1.0

class MySQL {

	var $mysqlconn;
	var $mysqlquery;

	function connect($db,$user,$pass,$host = "localhost")
	{
		$mysqlconn = mysqli_connect ($host,$user,$pass);
		$this->mysqlconn = $mysqlconn;

		if ($this->mysqlconn) {
			$this->selectDB($db);
			return true;
		} else {
			$this->doFail();
		}
	}

	function selectDB($db)
	{
		mysqli_select_db($this->mysqlconn,$db);
	}

	function close()
	{
		mysqli_close($this->mysqlconn);
	}

	function query($query)
	{
		// echo $query."\n";
		$mysqlquery =  mysqli_query($this->mysqlconn,$query);
                if ($mysqlquery) {
                        $this->mysqlquery = $mysqlquery;
                        return true;
                } else {
			 $this->doFail();
                }
	}		
	
	function count()
	{
                $mysqlres =  mysqli_num_rows($this->mysqlquery);
                return $mysqlres;
	}

        function row()
        {
                $mysqlres =  mysqli_fetch_row($this->mysqlquery);
                if ($mysqlres) {
                        return $mysqlres;
                } else {
			$this->doFail();
                }
        }

	function escape($string) {
		return mysqli_escape_string($this->mysqlconn,$string);
	}

	function errorMsg() {
		return mysqli_error($this->mysqlconn);
	}
	
	function doFail() {
		$error = $this->errorMsg();
		if (!$error) {
			return false;
		}
		die($error."\n");
	}

	function getConfig() {
		global $mysql, $artistplay, $titleplay, $albumplay, $queuesongs, $enablereq, $artistreq, $titlereq, $albumreq, $reqtime, $reqlimit, $filters;

		$mysql->query("SELECT * FROM config");
		$data = $mysql->row();
		$artistplay = $data[1];
		$titleplay = $data[2];
		$albumplay = $data[3];
		$queuesongs = $data[4];
		$enablereq = $data[5];
		$artistreq = $data[6];
		$titlereq = $data[7];
		$albumreq = $data[8];
		$reqtime = $data[9];
		$reqlimit = $data[10];
		$filters = $data[11];
	}

}

?>
