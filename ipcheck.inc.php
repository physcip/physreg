<?php
function ip62bin($ip)
{
	// unpack ::
	$blocks = substr_count($ip, ':') ;
	if ($blocks < 8)
		$ip = str_replace('::', ':' . str_repeat('0:', 8-$blocks), $ip);
	if ($ip{0} == ':')
		$ip = '0' . $ip;
	if ($ip{strlen($ip)-1} == ':')
		$ip = $ip . '0';
	
	// to binary
	$ip = explode(':', $ip);
	$binip = '';
	foreach ($ip as $block => $val)
		$binip .= str_pad(decbin(hexdec($val)), 16, '0', STR_PAD_LEFT);
	
	return $binip;
}

function checkip($ip, $allowed_v4, $allowed_v6)
{
	if (strpos($ip, '.') !== FALSE) // IPv4
	{
		$ip = decbin(ip2long($ip));
		foreach ($allowed_v4 as $subnet)
		{
			list($net, $mask) = explode('/', $subnet);
			$net = decbin(ip2long($net));
			if (substr($net, 0, $mask) === substr($ip, 0, $mask))
				return TRUE;
		}
	}
	else // IPv6
	{
		$ip = ip62bin($ip);
		foreach ($allowed_v6 as $subnet)
		{
			list($net, $mask) = explode('/', $subnet);
			$net = ip62bin($net);
			if (substr($net, 0, $mask) === substr($ip, 0, $mask))
				return TRUE;
		}
	}
	return FALSE;
}
?>