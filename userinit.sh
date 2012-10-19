#!/bin/bash

lang=$1

if [ "$lang" = "en" ]; then
	defaults write "Apple Global Domain" AppleLanguages "(en, de)"
else
	defaults write "Apple Global Domain" AppleLanguages "(de, en)"
fi

exit 0