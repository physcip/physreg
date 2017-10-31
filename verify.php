<?php

require_once "config.inc.php";
require_once "util.inc.php";

$ALLOWEDGROUPS = array_merge($ALLOWEDGROUPS, $KEEPGROUPS);

$conn = ldap_connect($TIK_LDAPSERVER);
ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
$bind = ldap_bind($conn, $TIK_LDAPSPECIALUSER, $TIK_LDAPSPECIALUSERPW) or physcip_err("LDAPSPECIAL_AUTH_FAILED");

$conn2 = ldap_connect($PHYSCIP_SERVER);
ldap_set_option($conn2, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($conn2, LDAP_OPT_REFERRALS, FALSE);
if (!@ldap_bind($conn2, $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW))
	physreg_err('PHYSCIP_BIND_FAILED');

$result = ldap_search($conn2, $PHYSCIP_USER_CONTAINER, '(&(objectClass=user)(userAccountControl=512))');
$info = ldap_get_entries($conn2, $result);

$whitelist = array('ac102610', 'phy31642', 'phy53542');
$disable = array();

foreach ($info as $user)
{
	$uid = $user['uid'][0];
	if (in_array($uid, $whitelist))
		continue;
	$uidnumber = (int) $user['uidnumber'][0];
	$USERDN = $user['dn'];
	
	if ($uidnumber < 10000)
		continue;
	if (!array_key_exists('givenname', $user))
		$fullname = $user['cn'][0];
	else
		$fullname = $user['sn'][0] . ' ' . $user['givenname'][0];
	
	$result = ldap_search($conn, $TIK_LDAPSEARCHBASE, '(&(samaccountname=' . $uid . '))');
	$info = ldap_get_entries($conn, $result);
	
	if ($info['count'] != 1)
	{
		echo sprintf("%s (%s) does not exist.\n", $uid, $fullname);
		$disable[] = $USERDN;
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
		echo sprintf("%s (%s) not in group.", $uid, $fullname);
		$disable[] = $USERDN;
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

if (count($argv) < 2 || $argv[1] != $PHYSCIP_PHYREGGER_PW)
	die('Permission denied\n');

foreach ($disable as $USERDN)
{
	ldap_modify($conn2, $USERDN, array('userAccountControl' => 514));
	echo $USERDN . " disabled\n";
}

?>
