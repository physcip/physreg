<?php

header('Content-type: application/json');

require_once 'config.inc.php';
require_once 'util.inc.php';
require_once 'ipcheck.inc.php';
$timeout = 25;

if (!checkip($_SERVER['REMOTE_ADDR'], $allowed_v4, $allowed_v6))
{
	physreg_err('IP_NOT_ALLOWED');
}

function checkuser($rususer, $ruspw)
{
	global $TIK_LDAPSERVER, $TIK_LDAPSEARCHBASE, $TIK_LDAPSPECIALUSER, $TIK_LDAPSPECIALUSERPW, $ALLOWEDUSERS, $ALLOWEDGROUPS, $timeout;
	
	// get user DN
	$conn = ldap_connect('ldaps://' . $TIK_LDAPSERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
	$bind = @ldap_bind($conn, $TIK_LDAPSPECIALUSER, $TIK_LDAPSPECIALUSERPW) or physreg_err("LDAPSPECIAL_AUTH_FAILED");
	$result = ldap_search($conn, $TIK_LDAPSEARCHBASE, '(&(samaccountname=' . $rususer . '))');
	$info = ldap_get_entries($conn, $result);
	ldap_close($conn);
	
	if ($info['count'] != 1)
		physreg_err("RUS_USER_INVALID");
	
	$USERDN = $info[0]['dn'];
	
	// check password
	$conn = ldap_connect('ldaps://' . $TIK_LDAPSERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
	$bind = ldap_bind($conn, $USERDN, $ruspw) or physreg_err("RUS_PW_INVALID");
	ldap_close($conn);
	
	return array('error' => FALSE);
}

function createuser($rususer, $ruspw, $email, $newpw, $lang)
{
	global $TIK_LDAPSERVER, $TIK_LDAPSEARCHBASE, $TIK_LDAPSPECIALUSER, $TIK_LDAPSPECIALUSERPW, $ALLOWEDUSERS, $ALLOWEDGROUPS, $timeout;
	
	// get user DN
	$conn = ldap_connect('ldaps://' . $TIK_LDAPSERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
	$bind = @ldap_bind($conn, $TIK_LDAPSPECIALUSER, $TIK_LDAPSPECIALUSERPW) or physreg_err("LDAPSPECIAL_AUTH_FAILED");
	$result = ldap_search($conn, $TIK_LDAPSEARCHBASE, '(&(samaccountname=' . $rususer . '))');
	$info = ldap_get_entries($conn, $result);
	ldap_close($conn);
	
	if ($info['count'] != 1)
		physreg_err("RUS_USER_INVALID");
	
	$USERDN = $info[0]['dn'];
	
	// extract UID
	preg_match('/^([a-z]+)([0-9]+)$/', strtolower($rususer), $user);
	
	// check password
	$conn = ldap_connect('ldaps://' . $TIK_LDAPSERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
	$bind = ldap_bind($conn, $USERDN, $ruspw) or physreg_err("RUS_PW_INVALID");
	$result = ldap_search($conn, $USERDN, '(&(objectClass=*))');
	$info = ldap_get_entries($conn, $result);
	
	// compose fields
	$userinfo['firstname'] = $info[0]['givenname'][0];
	$userinfo['lastname'] = $info[0]['sn'][0];
	$userinfo['uid'] = $user[2];
	$userinfo['username'] = $user[0];
	$userinfo['password'] = $newpw;
	$userinfo['fullname'] = $userinfo['firstname'] . ' ' . $userinfo['lastname'] . ' (' . $userinfo['uid'] . ')';
	
	$allowed = FALSE;
	if (in_array($userinfo['username'], $ALLOWEDUSERS)) // is allowed user
		$allowed = TRUE;
	
	// check group membership (on Windows a group can have members and a user can have memberOfs, so check both)
	elseif (count(array_intersect($ALLOWEDGROUPS, $info[0]['memberof'])) > 0) // user has memberOf attribute for allowed group
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
		physreg_err('USER_NOT_ALLOWED');
	
	ldap_close($conn);
	
	// check whether user already exists
	if (is_array(posix_getpwnam($userinfo['username'])) || is_array(posix_getpwuid($userinfo['uid'])))
		physreg_err('USER_ALREADY_EXISTS');
	
	// sanitize language
	if (preg_match('/[^a-z]/', $lang) || strlen($lang) > 2)
		$lang = 'de';
	
	// write to task file
	// Warning: Never change the order of these fields. If you add fields, add them to the end.
	$task = sprintf("%s\n%s\n%s\n%s\n%s\n%s\n%s\n", $userinfo['username'], $userinfo['password'], $userinfo['fullname'], $userinfo['firstname'], $userinfo['lastname'], $email, $lang);
	$taskfile = 'tasks/' . $userinfo['uid'];
	file_put_contents($taskfile, $task);
	
	// wait for task file to be deleted, indicating the process is complete
	$starttime = time();
	while (time() - $starttime < $timeout) // timeout
	{
		if (!file_exists($taskfile))
		{
			$logfile = 'log/' . $userinfo['uid'];
			if (file_exists($logfile))
				return array('error' => TRUE, 'errormsg' => "Error: see $logfile");
			else
				return array('error' => FALSE);
		}
		sleep(1);
	}
	return array('error' => TRUE, 'errormsg' => 'EXTERNAL_SCRIPT_TIMEOUT');
}

switch ($_GET['action'])
{
	case 'checkuser':
		$data = checkuser($_POST['rususer'], $_POST['ruspw']);
	break;
	
	case 'createuser':
		$data = createuser($_POST['rususer'], $_POST['ruspw'], $_POST['email'], $_POST['password'], $_POST['lang']);
	break;
	
	case 'ipcheck':
		$data = array('error' => FALSE);
	break;
}


echo json_encode($data);
?>
