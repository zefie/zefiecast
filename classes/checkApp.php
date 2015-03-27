<?php
class checkApp
{
	function check($appname)
	{
		$dummy = shell_exec("$appname 2>&1");
		if (preg_match("/Permission denied/i",$dummy) || preg_match("/not found/i",$dummy)) {
		        return false;
		} else {
			return true;
		}
	}
}
?>

