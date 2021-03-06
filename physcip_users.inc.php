<?php

require_once 'config.inc.php';
require_once 'util.inc.php';

# AD wants the password in a unicode format
# This seems to be the commonly accepted hack to encode the password
function encode_password($password)
{
	$password = "\"" . $password . "\"";
	$len = strlen($password);

	$unicodepwd = '';
	for($i=0; $i < $len; $i++)
		$unicodepwd .= "{$password{$i}}\000";
	return $unicodepwd;
}

# Check if user with $username already exists on the AD Server
# Returns true if username could be found
function physcip_userexists($username)
{
	global $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW, $PHYSCIP_SERVER, $PHYSCIP_USER_CONTAINER;

	$conn = ldap_connect($PHYSCIP_SERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
	if (!@ldap_bind($conn, $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW))
		physreg_err('PHYSCIP_BIND_FAILED');

	$res = ldap_search($conn, $PHYSCIP_USER_CONTAINER, '(sAMAccountName=' . $username . ')');
	if (!$res)
		physreg_err('PHYSCIP_SEARCH_FAILED');

	$exists = false;
	if (ldap_count_entries($conn, $res) == 1)
		$exists = true;

	ldap_close($conn);
	return $exists;
}

# Add user to Active Directory (Samba4) via LDAP and create home directory via ssh
# Parameters:
# $username: 'st123456' / 'phy12345' / ...
# $password: String
# $uidnumber: 123456 / 12345 / ... = usually number in username
# $firstname: 'Max'
# $lastname: 'Mustermann'
# $email: 'maxmustermann@example.org'
# $lang: 'de' / 'en' / ...
function physcip_createuser($username, $uidnumber, $password, $firstname, $lastname, $email, $lang)
{
	global $PHYSCIP_NISDOMAIN, $PHYSCIP_GIDNUMBER, $PHYSCIP_PRIMARYGROUP, $PHYSCIP_PRIMARYGROUPID;
	global $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW, $PHYSCIP_SERVER, $PHYSCIP_USER_CONTAINER;
	global $PHYSCIP_UPN_REALM, $PHYSCIP_HOME_SSH, $PHYSCIP_HOME_SSH_ID, $PHYSCIP_HOME_COMMAND;

	# Sanitize inputs: Make sure $username and $lang contain only alphanumeric characters.
	# This is CRUCIAL for ensuring users can't inject code into the command that creates the home directories via SSH.
	if (!ctype_alnum($username) || !ctype_alnum($lang))
		physreg_err('PHYSCIP_INVALID_INPUT');

	$fullname = $firstname . ' ' . $lastname . ' (' . $uidnumber . ')';
	$newuser_dn = 'cn=' . $username . ',' . $PHYSCIP_USER_CONTAINER;

	# Attributes get written to AD via LDAP
	# msDS-SupportedEncryptionTypes 31 enables stronger authentication hashes
	# userAccountControl 512 enables account
	$info = [
		'objectclass' => [
			'top', 'user', 'organizationalPerson', 'person', 'posixAccount'
		],
		'cn' => $username,
		'displayName' => $fullname,
		'gidNumber' => $PHYSCIP_GIDNUMBER,
		'givenName' => $firstname,
		'loginShell' => '/bin/bash',
		'mail' => $email,
		'msSFU30Name' => $username,
		'msSFU30NisDomain' => $PHYSCIP_NISDOMAIN,
		'sAMAccountName' => $username,
		'sn' => $lastname,
		'uid' => $username,
		'uidNumber' => $uidnumber,
		'unixHomeDirectory' => '/home/' . $username,
		'apple-user-homeurl' => '<home_dir><url>afp://home.physcip.uni-stuttgart.de/home/' . $username . '</url><path></path></home_dir>',
		'unicodePwd' => encode_password($password),
		'userPrincipalName' => $username . '@' . $PHYSCIP_UPN_REALM,
		'msDS-SupportedEncryptionTypes' => '31',
		'userAccountControl' => '512'
	];

	$info_groupmembership = [
		'member' => $newuser_dn
	];

	$info_makeprimary = [
		'primaryGroupID' => $PHYSCIP_PRIMARYGROUPID
	];

	# Step 1: Connect to Samba4 AD DC Server
	$conn = ldap_connect($PHYSCIP_SERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
	if (!@ldap_bind($conn, $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW))
		physreg_err('PHYSCIP_BIND_FAILED');

	# Step 2: Add user
	if (!ldap_add($conn, $newuser_dn, $info))
		physreg_err('PHYSCIP_ADD_FAILED');

	# Step 3: Make user member of cipuser group
	# Unfortunately, setting the user's primaryGroupID directly will be denied by Samba 4,
	# the user has to be a member of the cipuser group beforehand. This is why we add the
	# "member" attribute entry here, even though that gets deleted by setting primaryGroupID in Step 4.
	if (!ldap_modify($conn, $PHYSCIP_PRIMARYGROUP, $info_groupmembership))
		physreg_err('PHYSCIP_PRIMARY_FAILED');

	# Step 4: Change user's primary group membership to cipuser
	if (!ldap_modify($conn, $newuser_dn, $info_makeprimary))
		physreg_err('PHYSCIP_PRIMARY_FAILED');

	# Step 6: Remove user from 'Domain User' group (user gets automatically added)
	$domainusers_dn = 'cn=Domain Users,cn=Users,dc=physcip,dc=uni-stuttgart,dc=de';
	$domainusers_delmember['member'] = $newuser_dn;
	if (!ldap_mod_del($conn, $domainusers_dn, $domainusers_delmember))
		physreg_err('PHYSCIP_DELMEMBER_FAILED');

	# Step 7: Create home directory via SSH
	$sshopts = '-q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -o PasswordAuthentication=no -o PubkeyAuthentication=yes';
	$sshlogin = 'ssh ' . $sshopts . ' -i ' . $PHYSCIP_HOME_SSH_ID . ' ' . $PHYSCIP_HOME_SSH;
	$sshcommand = $sshlogin . ' ' . $PHYSCIP_HOME_COMMAND . ' ' . $username . ' ' . $lang . ' ' . $uidnumber . ' ' . $PHYSCIP_GIDNUMBER;
	exec($sshcommand, $output, $exitcode);
	if ($exitcode != 0)
		physreg_err('PHYSCIP_CREATEHOME_FAILED');

	ldap_close($conn);
}

# Connect to Active Directory (Samba 4) via LDAP and force-set $password for $username
function physcip_setpassword($username, $password)
{
	global $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW, $PHYSCIP_SERVER, $PHYSCIP_USER_CONTAINER;

	# Sanitize inputs: Make sure $username contains only alphanumeric characters.
	if (!ctype_alnum($username))
		physreg_err('PHYSCIP_INVALID_INPUT');

	# Step 1: Connect to Samba4 AD DC Server
	$conn = ldap_connect($PHYSCIP_SERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
	if (!@ldap_bind($conn, $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW))
		physreg_err('PHYSCIP_BIND_FAILED');

	# Step 2: Find user by username
	$res = ldap_search($conn, $PHYSCIP_USER_CONTAINER, '(sAMAccountName=' . $username . ')');
	if (!$res)
		physreg_err('PHYSCIP_SEARCH_FAILED');

	# Step 3: Get DN from user entry
	$user_entry = ldap_first_entry($conn, $res);
	$user_dn = ldap_get_dn($conn, $user_entry);

	# Step 4: Encode password and modify
	$modentry = [
		'unicodePwd' => encode_password($password)
	];

	if (ldap_modify($conn, $user_dn, $modentry) === false)
		physreg_err('PHYSCIP_PW_CHANGE_FAILED');

	ldap_close($conn);
}
?>

