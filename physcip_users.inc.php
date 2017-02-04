<?php

require_once 'config.inc.php';
require_once 'util.inc.php';

# TODO: Add PHYSCIP_BIND_FAILED, PHYSCIP_ADD_FAILED and PHYSCIP_PRIMARY_FAILED, PHYSCIP_SEARCH_FAILED, PHYSCIP_INVALID_INPUT errors to web client

# AD wants the password in a unicode format
# This seems to be the commonly accepted hack to encode the password
function encode_password($password)
{
	$password = "\"" . $password . "\"";
	$len = strlen($password);

	$unicodepwd = "";
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
	if (!ldap_bind($conn, $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW))
		physreg_err("PHYSCIP_BIND_FAILED");

	$res = ldap_search($conn, $PHYSCIP_USER_CONTAINER, "(uid=" . $username . ")");
	if (!$res)
		physreg_err("PHYSCIP_SEARCH_FAILED");

	$exists = false;
	if (ldap_count_entries($conn, $res) == 1)
		$exists = true;

	ldap_close($conn);
	return $exists;
}

# Add user to Active Directory (Samba4) via LDAP and create home directory via ssh
# Parameters:
# $username: "st123456" / "phy12345" / ...
# $password: String
# $uidnumber: 123456 / 12345 / ... = usually number in username
# $firstname: "Max"
# $lastname: "Mustermann"
# $email: "maxmustermann@example.org"
# $lang: "de" / "en" / ...
function physcip_createuser($username, $uidnumber, $password, $firstname, $lastname, $email, $lang)
{
	global $PHYSCIP_NISDOMAIN, $PHYSCIP_GIDNUMBER, $PHYSCIP_PRIMARYGROUP, $PHYSCIP_PRIMARYGROUPID;
	global $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW, $PHYSCIP_SERVER, $PHYSCIP_USER_CONTAINER;
	global $PHYSCIP_UPN_REALM, $PHYSCIP_HOME_SSH, $PHYSCIP_HOME_SSH_ID, $PHYSCIP_HOME_COMMAND;

	# Sanitize inputs: Make sure $username and $lang only contain alphanumeric characters.
	# This is CRUCIAL for ensuring users can't inject code into the command that creates the home directories via SSH.
	if (!ctype_alnum($username) || !ctype_alnum($lang)) {
		physreg_err("PHYSCIP_INVALID_INPUT");
	}

	$fullname = $firstname . " " . $lastname . " (" . $uidnumber . ")";
	$newuser_dn = "cn=" . $username . "," . $PHYSCIP_USER_CONTAINER;

	$info = [
		"objectclass" => [
			"top", "user", "organizationalPerson", "person", "posixAccount"
		],
		"cn" => $username,
		"displayName" => $fullname,
		"gidNumber" => $PHYSCIP_GIDNUMBER,
		"givenName" => $firstname,
		"loginShell" => "/bin/bash",
		"mail" => $email,
		"msSFU30Name" => $username,
		"msSFU30NisDomain" => $PHYSCIP_NISDOMAIN,
		"sAMAccountName" => $username,
		"sn" => $lastname,
		"uid" => $username,
		"uidNumber" => $uidnumber,
		"unixHomeDirectory" => "/home/" . $username,
		"apple-user-homeurl" => "<home_dir><url>afp://home.physcip.uni-stuttgart.de/home/" . $username . "</url><path></path></home_dir>",
		"unicodePwd" => encode_password($password),
		"userPrincipalName" => $username . "@" . $PHYSCIP_UPN_REALM
	];

	$info_groupmembership = [
		"member" => $newuser_dn
	];

	$info_makeprimary = [
		"primaryGroupID" => $PHYSCIP_PRIMARYGROUPID
	];

	# Step 1: Connect to Samba4 AD DC Server
	$conn = ldap_connect($PHYSCIP_SERVER);
	ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($conn, LDAP_OPT_REFERRALS, FALSE);
	if (!ldap_bind($conn, $PHYSCIP_PHYREGGER_DN, $PHYSCIP_PHYREGGER_PW))
		physreg_err("PHYSCIP_BIND_FAILED");

	# Step 2: Add user
	if(!ldap_add($conn, $newuser_dn, $info))
		physreg_err("PHYSCIP_ADD_FAILED");

	# Step 3: Make user member of cipuser group
	if(!ldap_modify($conn, $PHYSCIP_PRIMARYGROUP, $info_groupmembership))
		physreg_err("PHYSCIP_PRIMARY_FAILED");

	# Step 4: Change user's primary group membership to cipuser
	if(!ldap_modify($conn, $newuser_dn, $info_makeprimary))
		physreg_err("PHYSCIP_PRIMARY_FAILED");

	# Step 5: Create home directory via SSH
	$sshopts = "-q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -o StrictHostKeyChecking=no -o StrictHostKeyChecking=no";
	$sshlogin = "ssh " . $sshopts . " -i " . $PHYSCIP_HOME_SSH_ID . " " . $PHYSCIP_HOME_SSH;
	$sshcommand = $sshlogin . " " . $PHYSCIP_HOME_COMMAND . " " . $username . " " . $lang;
	exec($sshcommand);

	ldap_close($conn);
}
?>

