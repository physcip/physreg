<?php
require_once "config.inc.php";
$ALLOWEDGROUPS = array_merge($ALLOWEDGROUPS, $KEEPGROUPS);
$LOCALSEARCHBASE = 'dc=purple,dc=physcip,dc=uni-stuttgart,dc=DE';
$LOCALLDAPSERVER = 'purple.physcip.uni-stuttgart.de';

$conn = ldap_connect('ldaps://' . $LDAPSERVER);
ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
$bind = ldap_bind($conn, $LDAPSPECIALUSER, $LDAPSPECIALUSERPW) or err("LDAPSPECIAL_AUTH_FAILED");

$conn2 = ldap_connect('ldaps://' . $LOCALLDAPSERVER);
ldap_set_option($conn2, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($conn2, LDAP_OPT_REFERRALS, FALSE);

$result = ldap_search($conn2, $LOCALSEARCHBASE, 'objectClass=inetOrgPerson');
$info = ldap_get_entries($conn2, $result);

foreach ($info as $user)
{
	$uid = $user['uid'][0];
	$uidnumber = (int) $user['uidnumber'][0];
	$USERDN = $user['dn'];
	
	// skip disabled users
	if (version_compare(php_uname("r"), "14.0.0", ">=")) // OS X 10.10 and higher
	{
		if (strpos(shell_exec('pwpolicy -u ' . $uid . ' -authentication-allowed 2>/dev/null'), 'Policy allows user') === FALSE)
			continue;
	}
	else
	{
		if (strpos(shell_exec('pwpolicy -u ' . $uid . ' -getpolicy 2>/dev/null'), 'isDisabled=0') === FALSE)
			continue;
	}
	
	if ($uidnumber < 10000)
		continue;
	if (!array_key_exists('givenname', $user))
		$fullname = $user['cn'][0];
	else
		$fullname = $user['sn'][0] . ' ' . $user['givenname'][0];
	
	$result = ldap_search($conn, $LDAPSEARCHBASE, '(&(samaccountname=' . $uid . '))');
	$info = ldap_get_entries($conn, $result);
	
	if ($info['count'] != 1)
	{
		echo sprintf("%d: %s (%s) does not exist.\n", $uidnumber, $fullname, $uid);
		continue;
	}
	
	$allowed = false;
	if (count(array_intersect($ALLOWEDGROUPS, $info[0]['memberof'])) > 0) // user has memberOf attribute for allowed group
		$allowed = TRUE;
	else // check whether allowed group has member attribute for user
	{
		foreach ($ALLOWEDGROUPS as $group)
		{
			$result = ldap_search($conn, $group, '(&(objectClass=*))');
			$members = ldap_get_entries($conn, $result);
			if (in_array($USERDN, $members[0]['member']))
			{
				$allowed = TRUE;
				break;
			}
		}
	}
	if (!$allowed)
	{
		echo sprintf("%d: %s (%s) not in group.", $uidnumber, $fullname, $uid);
		$i = 0;
		foreach($info[0]['memberof'] as $g)
		{
			$g = reset(explode(',', $g));
			if (is_numeric($g) || in_array($g,array("CN=UniS-Studenten", "CN=" . substr($uid,0,3), "CN=CIP-Benutzer", "CN=Drucken", "CN=SubRosa")) || (strpos($g, "CN=DreamSpark") === 0))
				continue;
			if (!$i); echo " Only in ";
			echo $g . " ";
			$i++;
		}
		echo "\n";
		continue;
	}
}

?>
