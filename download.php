<?php
if (isset($_GET['rn'])) {
	$rn = $_GET['rn'];
	session_start();
	if (isset($_SESSION["f_$rn"]) && isset($_SESSION["n_$rn"])) {
		$n = $_SESSION["n_$rn"]; $l = 0;
		foreach($_SESSION["f_$rn"] as $i)
			$l += strlen($i) + 2; //assume Windows(\r\n)
		
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.basename($n).'"');
		header('Content-Length: '.$l);
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Expires: 0');
		
		foreach($_SESSION["f_$rn"] as $i) {
			echo $i."\r\n";
			ob_flush(); flush();
		}
		exit;
	}
}
?>
