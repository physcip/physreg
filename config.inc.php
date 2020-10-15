<?php
# Secret Credentials: config_secret.inc.php must contain:
# --> $TIK_LDAPSPECIALUSERPW (password for TIK AD server)
# --> $PHYSCIP_PHYREGGER_PW (password for physcip AD server)
require_once 'config_secret.inc.php';

# TIK LDAP Credentials
# This is the server users authenticate to with their TIK account and the server that contains the list of allowed student groups
$TIK_LDAPSERVER="ldaps://studsv10.stud.uni-stuttgart.de ldaps://studsv11.stud.uni-stuttgart.de ldaps://studsv15.stud.uni-stuttgart.de";
$TIK_LDAPSEARCHBASE="dc=stud,dc=uni-stuttgart,dc=de";
$TIK_LDAPSPECIALUSER="cn=ldapqueryPhys,ou=ServiceAccounts,ou=IuK-IS,dc=stud,dc=uni-stuttgart,dc=de";

# Physcip LDAP server Credentials
$PHYSCIP_SERVER = "ldaps://dc01.physcip.uni-stuttgart.de ldaps://dc02.physcip.uni-stuttgart.de";	# Primary (and secondary) domain controller, requires LDAPS
$PHYSCIP_PHYREGGER_DN = "phyregger@physcip.uni-stuttgart.de";						# DN or userPrincipalName for phyregger Account

# Home directory creation via SSH
# Physreg creates home directories by logging in to the home directory server via SSH and executing
# the given command with parameters username, language, UID number and GID number (for user's primary group).
$PHYSCIP_HOME_SSH = "root@home.physcip.uni-stuttgart.de";						# Username / server to log in to via SSH for creating home directories
$PHYSCIP_HOME_COMMAND = "/usr/local/bin/inithomedir.sh";						# Command that will be executed on homedir server
$PHYSCIP_HOME_SSH_ID = "/etc/phyreg-id_rsa";								# Key to use for authentication, can be restricted to PHYSCIP_HOME_COMMAND

# Default attributes for new users. The $PHYSCIP_PRIMARYGROUP, $PHYSCIP_PRIMARYGROUPID and
# $PHYSCIP_GIDNUMBER configuration options describe the group new users are added to (e.g. "cipuser").
# They MUST be attributes of the SAME group.
$PHYSCIP_NISDOMAIN = "physcip";										# msSFU30NisDomain
$PHYSCIP_UPN_REALM = "physcip.uni-stuttgart.de";							# Realm for userPrincipalName
$PHYSCIP_USER_CONTAINER = "ou=Students,ou=People,dc=physcip,dc=uni-stuttgart,dc=de";			# Where to put new users
$PHYSCIP_PRIMARYGROUP = "cn=cipuser,ou=Groups,dc=physcip,dc=uni-stuttgart,dc=de";			# DN of primary group
$PHYSCIP_PRIMARYGROUPID = 1104;										# Last block of SID (= RID), for Windows primary group
$PHYSCIP_GIDNUMBER = 10000;										# gidNumber of primary group

# $ALLOWEDGROUPS: Groups whose members are allowed to register new accounts
$ALLOWEDGROUPS=array(
	"CN=Stg1590-08-128,OU=OrgGroups,OU=IDMGroups,OU=SIAM,DC=stud,DC=uni-stuttgart,DC=de",
	"CN=Stg1590-08-918,OU=OrgGroups,OU=IDMGroups,OU=SIAM,DC=stud,DC=uni-stuttgart,DC=de",
	"CN=Stg1590-08-686,OU=OrgGroups,OU=IDMGroups,OU=SIAM,DC=stud,DC=uni-stuttgart,DC=de",
);

# $ALLOWEDUSERS: List of users that have a special permission to create new accounts
# (despite not being a member of a group in $ALLOWEDGROUPS), e.g. students minoring in physics
# These special permissions are usually only assigned for the duration of a single semester.
$ALLOWFILE = "/etc/phyreg-allow";
$ALLOWEDUSERS = [];
if (file_exists($ALLOWFILE))
	$ALLOWEDUSERS=file($ALLOWFILE, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
else
	trigger_error("$ALLOWFILE missing.", E_USER_WARNING);

$allowed_v4 = array('129.69.0.0/16', '141.58.0.0/16', '192.108.35.0/24', '192.108.36.0/22', '192.108.40.0/22', '192.108.44.0/24', '127.0.0.1/8', '172.22.0.1/24');
$allowed_v6 = array('fe80::/64', '2001:7C0:7C0::/48', '2001:7C0:2000::/40', '2001:638:202::/48', 'FC5C:983E:D7E3::/48');
?>
