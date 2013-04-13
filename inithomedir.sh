#!/bin/bash

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
	fi
done

if [ "$username" = "" ]; then
	echo "No username specified"
	exit
fi

if [ ! -d "/System/Library/User Template/$lang.lproj" ]; then
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

echo "Creating homedir for $username with $lang"

#createhomedir -c -u $username

groupname=$(id -g -n $username)

cd /Volumes/home
sudo mkdir $username
sudo chown $username:$groupname $username

if [ ! -d "$username/Library/Preferences" ]; then
	echo "Initializing home directory with user template for $lang"
	ditto /System/Library/User\ Template/Non_localized $username
	ditto /System/Library/User\ Template/$lang.lproj $username
	sudo chown -R $username:$groupname $username
fi

exit 0
