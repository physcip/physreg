# Physreg
The Physreg API is called by [the "Physdash" client](https://github.com/physcip/physdash) and performs all account management-related tasks.

## Development environment setup
Copy `config_secret.inc.php.example` to `config_secret.inc.php` and configure `PHYSCIP_PHYREGGER_PW` (password for phyregger user in local physcip Active Directory) and `TIK_LDAPSPECIALUSERPW` (password for query user in TIK Active Directory).

Start PHP webserver: `LDAPTLS_REQCERT=never php -S localhost:8000 -c php.ini` (`LDAPTLS_REQCERT=never` can be omitted if certificates for TIK and physcip AD are installed locally).

## Home Directory Creation
For security purposes, creating the home directory is handled by the separate script `inithomedir.sh` that will be executed *via SSH*. Even if the home directory server is the same machine that is running the physreg API, this makes it harder to exploit physreg and allows for greater flexibility. Setup:

* Copy `inithomedir.sh` to `/usr/local/bin/inithomedir.sh` on the home directory server (must match the `PHYSCIP_HOME_SSH` and `PHYSCIP_HOME_COMMAND` configurations in `config.inc.php`)
* Create a keypair for SSH with an empty passphrase: `ssh-keygen -N '' -f /etc/phyreg-id_rsa` (Make sure that the destination path for the private key matches `PHYSCIP_HOME_SSH_ID` in `config.inc.php`)
* Add the following line to the SSH `authorized_keys` file (probably `/var/root/.ssh/authorized_keys`) on the home directory server (`home.physcip.uni-stuttgart.de`):
```
command="/usr/local/bin/inithomedir.sh" <ID_RSA_PUB>
```
Where `<ID RSA PUB>` is the public SSH key created in the second step (by default at `/etc/phyreg-id_rsa.pub`).
This makes sure physreg can access the home directory server over SSH, but may not execute any commands other than the `inithomedir.sh` script.
