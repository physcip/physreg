#!/bin/bash

# Usage:
# inithomedir.sh <username> <language> <uid> <gid>
# With <language> = "nl", "en", "fr", "de", "it", "jp" or "es"
# <uid> and <gid> must be the uidNumber and gidNumber of the newly created user,
# used to set home directory ownership (since <username> might not already exist on LDAP server)
#
# This script is only supposed to be called by physreg over SSH

LOGFILE=/var/log/physreg-home.log
USER_TEMPLATE_DIR=""/System/Library/User Template""
USER_HOME_DIR=/Volumes/home

if [ "$SSH_ORIGINAL_COMMAND" = "" ]; then
	SSH_ORIGINAL_COMMAND=$*
fi

i=0
for arg in $SSH_ORIGINAL_COMMAND; do
	if [ "$arg" = "$0" ]; then
		continue
	fi
	let i+=1
	if [ "$i" = "1" ]; then
		username=$arg
	elif [ "$i" = "2" ]; then
		lang=$arg
	elif [ "$i" = "3" ]; then
		uid=$arg
	elif [ "$i" = "4" ]; then
		gid=$arg
	fi
done

if [ "$username" = "" ]; then
	echo "No username specified" | tee -a $LOGFILE
	exit
fi

# UID / GID not specified: Propably running this script manually,
# determine UID / GID from username
if [ "$uid" = "" ]; then
	uid=$(id -u $username)
	if [ "$?" -ne "0" ]; then
		echo "Could not find UID for $username. Does this user exist?" | tee -a $LOGFILE
		exit
	fi
fi

if [ "$gid" = "" ]; then
	gid=$(id -g $username)
	if [ "$?" -ne "0" ]; then
		echo "Could not find GID for $username. Does this user exist?" | tee -a $LOGFILE
		exit
	fi
fi

if [ ! -d "$USER_TEMPLATE_DIR/$lang.lproj" ]; then
	case "$lang" in
		"nl")
			lang=Dutch
		;;
		"en")
			lang=English
		;;
		"fr")
			lang=French
		;;
		"de")
			lang=German
		;;
		"it")
			lang=Italian
		;;
		"jp")
			lang=Japanese
		;;
		"es")
			lang=Spanish
		;;
		*)
			lang=English
		;;
	esac	
fi

echo "Creating homedir for $username with $lang" | tee -a $LOGFILE

cd $USER_HOME_DIR
mkdir $username 2>&1 | tee -a $LOGFILE
chown $uid:$gid $username 2>&1 | tee -a $LOGFILE

if [ ! -d "$username/Library/Preferences" ]; then
	echo "Initializing home directory with user template for $lang" | tee -a $LOGFILE
	rsync -a "$USER_TEMPLATE_DIR/Non_localized/" $username 2>&1 | tee -a $LOGFILE
	rsync -a "$USER_TEMPLATE_DIR/$lang.lproj/" $username 2>&1 | tee -a $LOGFILE
	chown -R $uid:$gid $username 2>&1 | tee -a $LOGFILE
	rm -rf $USER_HOME_DIR/$username/Downloads/About\ Downloads.lpdf 2>&1 | tee -a $LOGFILE
else
	echo "Homedir already initialized" | tee -a $LOGFILE
fi

exit 0
