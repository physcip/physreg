<?php
$LDAPSERVER="studsv12.stud.uni-stuttgart.de";
$LDAPSEARCHBASE="dc=stud,dc=uni-stuttgart,dc=de";
$LDAPSPECIALUSER="cn=ldapqueryPhys,ou=ServiceAccounts,ou=IuK-IS,dc=stud,dc=uni-stuttgart,dc=de";
$LDAPSPECIALUSERPW="";

$ALLOWEDGROUPS=array(
	"CN=Stg1590-08-128,OU=OrgGroups,OU=IDMGroups,OU=SIAM,DC=stud,DC=uni-stuttgart,DC=de",
	"CN=Stg1590-08-918,OU=OrgGroups,OU=IDMGroups,OU=SIAM,DC=stud,DC=uni-stuttgart,DC=de",
);
$KEEPGROUPS=array(
	// Doktoranden sind bei der Fakultät geführt. Mathe-Doktoranden sollen sich aber nicht registrieren können.
	// Deswegen müssen neue Physik-Doktoranden manuell freigeschaltet werden, werden aber nicht automatisch deaktiviert.
	"CN=Stg1590-08-P82,OU=OrgGroups,OU=IDMGroups,OU=SIAM,DC=stud,DC=uni-stuttgart,DC=de",
);
$allowfile = "/etc/phyreg-allow";
if (file_exists($allowfile))
	$ALLOWEDUSERS=file($allowfile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
else
	trigger_error("$allowfile missing.", E_USER_WARNING);

$allowed_v4 = array('129.69.0.0/16', '141.58.0.0/16', '192.108.35.0/24', '192.108.36.0/22', '192.108.40.0/22', '192.108.44.0/24');
$allowed_v6 = array('fe80::/64', '2001:7C0:7C0::/48', '2001:7C0:2000::/40', '2001:638:202::/48', 'FC5C:983E:D7E3::/48');
?>
