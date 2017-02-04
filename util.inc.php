<?php
function physreg_err($msg)
{
	$data = array();
	$data['error'] = TRUE;
	$data['errormsg'] = $msg;
	echo json_encode($data);
	exit;
}
?>
