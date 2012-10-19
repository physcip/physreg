<?php
$LDAPSERVER="studsv12.stud.uni-stuttgart.de";
$LDAPSEARCHBASE="dc=stud,dc=uni-stuttgart,dc=de";
$LDAPSPECIALUSER="cn=ldapqueryPhys,ou=ServiceAccounts,ou=IuK-IS,dc=stud,dc=uni-stuttgart,dc=de";
$LDAPSPECIALUSERPW="";

$ALLOWEDGROUPS=array("CN=phy,OU=StudGroups,DC=stud,DC=uni-stuttgart,DC=de");
$ALLOWEDUSERS=file("/etc/phyreg-allow", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
?>