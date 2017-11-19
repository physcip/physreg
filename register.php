<?php

header('Content-type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'physcip_users.inc.php';
require_once 'ipcheck.inc.php';
require_once 'config.inc.php';
require_once 'util.inc.php';

if (!checkip($_SERVER['REMOTE_ADDR'], $allowed_v4, $allowed_v6))
{
	physreg_err('IP_NOT_ALLOWED');
}

# Helper function: Get DN of user in TIK / RUS Active Directory server
function get_rus_dn($rususer)
{
	global $TIK_LDAPSERVER, $TIK_LDAPSPECIALUSER, $TIK_LDAPSPECIALUSERPW, $TIK_LDAPSEARCHBASE;

	$conn = ldap_connect($TIK_LDAPSERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, false);
	$bind = @ldap_bind($conn, $TIK_LDAPSPECIALUSER, $TIK_LDAPSPECIALUSERPW) or physreg_err('LDAPSPECIAL_AUTH_FAILED');
	$result = ldap_search($conn, $TIK_LDAPSEARCHBASE, '(&(samaccountname=' . $rususer . '))');
	$info = ldap_get_entries($conn, $result);
	ldap_close($conn);

	if ($info['count'] != 1)
		physreg_err('RUS_USER_INVALID');

	return $info[0]['dn'];
}

# Helper function: Fails with RUS_PW_INVALID in case TIK password is wrong
function check_rus_pw($rususer, $ruspw)
{
	global $TIK_LDAPSERVER, $TIK_LDAPSEARCHBASE, $TIK_LDAPSPECIALUSER, $TIK_LDAPSPECIALUSERPW, $ALLOWEDUSERS, $ALLOWEDGROUPS;

	# Sanitize username input - only alphanumeric strings allowed and make sure all parameters have been specified
	if (!ctype_alnum($rususer) || $ruspw == null)
		physreg_err('PHYSCIP_INVALID_INPUT');

	$userdn = get_rus_dn($rususer);

	# check password
	$conn = ldap_connect($TIK_LDAPSERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, false);
	$bind = @ldap_bind($conn, $userdn, $ruspw) or physreg_err('RUS_PW_INVALID');
	ldap_close($conn);
}

function checkuser($rususer, $ruspw)
{
	check_rus_pw($rususer, $ruspw);
	return array('error' => false);
}

function reset_password($rususer, $ruspw, $physcippw)
{
	check_rus_pw($rususer, $ruspw);
	physcip_setpassword($rususer, $physcippw);
	return array('error' => false);
}

function createuser($rususer, $ruspw, $email, $physcippw, $lang)
{
	global $TIK_LDAPSERVER, $TIK_LDAPSEARCHBASE, $TIK_LDAPSPECIALUSER, $TIK_LDAPSPECIALUSERPW, $ALLOWEDUSERS, $ALLOWEDGROUPS;

	# Sanitize username input - only alphanumeric strings allowed
	if (!ctype_alnum($rususer) || $ruspw == null || $email == null || $physcippw == null || $lang == null)
		physreg_err('PHYSCIP_INVALID_INPUT');

	$userdn = get_rus_dn($rususer);

	# check password and get user attributes
	$conn = ldap_connect($TIK_LDAPSERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, false);
	$bind = @ldap_bind($conn, $userdn, $ruspw) or physreg_err('RUS_PW_INVALID');
	$result = ldap_search($conn, $userdn, '(&(objectClass=*))');
	$info = ldap_get_entries($conn, $result);

	# Parse user info and TIK account username
	# Extract UID from username: 'St123456' --> $usersplit = ['st123456', 'st', '123456']
	preg_match('/^([a-z]+)([0-9]+)$/', strtolower($rususer), $usersplit);
	$username = $usersplit[0];
	$uidnumber = $usersplit[2];
	$firstname = $info[0]['givenname'][0];
	$lastname = $info[0]['sn'][0];

	# Make sure user has permission to create an account by checking group membership
	# On Windows a group can have `member` and a user can have `memberOf` attributes, so check both
	# Possibility 1: User is specifically excempt from permission checking by being listed in a special file (see config)
	$allowed = in_array($username, $ALLOWEDUSERS);

	# Possibility 2: Check allowed group membership by `memberOf` attribute in user entry
	$allowed = $allowed || (count(array_intersect($ALLOWEDGROUPS, $info[0]['memberof'])) > 0);

	# Possibility 3: Check group membership by `member` attribute for user in allowed group
	if (!$allowed)
	{
		foreach ($ALLOWEDGROUPS as $group)
		{
			$result = ldap_search($conn, $group, '(&(objectClass=*))');
			$members = ldap_get_entries($conn, $result);
			if (in_array($USERDN, $members[0]['member']))
			{
				$allowed = true;
				break;
			}
		}
	}

	if (!$allowed)
		physreg_err('USER_NOT_ALLOWED');

	ldap_close($conn);

	# Check whether user already exists
	if (physcip_userexists($username))
		physreg_err('USER_ALREADY_EXISTS');

	# Sanitize language
	if (preg_match('/[^a-z]/', $lang) || strlen($lang) > 2)
		$lang = 'de';

	# Actually create new user
	physcip_createuser($username, $uidnumber, $physcippw, $firstname, $lastname, $email, $lang);

	return array('error' => false);
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
		$data = array('error' => false);
	break;

	case 'set_password':
		$data = reset_password($_POST['rususer'], $_POST['ruspw'], $_POST['password']);
	break;
}


echo json_encode($data);
?>
