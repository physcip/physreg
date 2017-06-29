# Physreg
## Development environment setup
Copy `config_secret.inc.php.example` to `config_secret.inc.php` and configure `PHYSCIP_PHYREGGER_PW` (password for phyregger user in local physcip Active Directory) and `TIK_LDAPSPECIALUSERPW` (password for query user in TIK Active Directory).

Start PHP webserver: `LDAPTLS_REQCERT=never php -S localhost:8000 -c php.ini` (`LDAPTLS_REQCERT=never` can be omitted if certificates for TIK and physcip AD are installed locally).


