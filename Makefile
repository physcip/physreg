LAUNCHDAEMON=de.uni-stuttgart.physcip.reg.plist
BINFILE=process_registration.py
LOGFILE=/var/log/physreg.log
PLISTBUDDY=/usr/libexec/PlistBuddy

install:
	test -d tasks || mkdir tasks && chown _www:admin tasks && chmod 770 tasks
	test -f /Library/LaunchDaemons/$(LAUNCHDAEMON) && launchctl unload /Library/LaunchDaemons/$(LAUNCHDAEMON) || true
	
	cp $(LAUNCHDAEMON) /Library/LaunchDaemons
	$(PLISTBUDDY) -c "Set :ProgramArguments:2 $(shell pwd)" /Library/LaunchDaemons/$(LAUNCHDAEMON)
	$(PLISTBUDDY) -c "Set :QueueDirectories:0 $(shell pwd)/tasks" /Library/LaunchDaemons/$(LAUNCHDAEMON)
	chown root:wheel /Library/LaunchDaemons/$(LAUNCHDAEMON)
	chmod 644 /Library/LaunchDaemons/$(LAUNCHDAEMON)
	
	launchctl load /Library/LaunchDaemons/$(LAUNCHDAEMON)
	
	cp $(BINFILE) /usr/local/bin
	chown root:wheel /usr/local/bin/$(BINFILE)
	chmod 644 /usr/local/bin/$(BINFILE)

uninstall:
	test -f /Library/LaunchDaemons/$(LAUNCHDAEMON) && launchctl unload /Library/LaunchDaemons/$(LAUNCHDAEMON) || true
	rm -f /Library/LaunchDaemons/$(LAUNCHDAEMON)
	rm -f /usr/local/bin/$(BINFILE)
	rm -f $(LOGFILE)