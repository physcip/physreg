import glob
import os
import pwd
import subprocess
import datetime
import sys
import time

if os.geteuid() != 0:
	raise Exception("Need to run as root!")

os.chdir(sys.argv[1])
sys.path.append(sys.argv[1])
from config import *

dscl = [ '/usr/bin/dscl', '-u', PHYREGGER, '-P', PHYREGGERPW, DSNODE ]

tasks = glob.glob('tasks/*')
for task in tasks:
	print '== Starting %s ==' % task
	try:
		userid = int(os.path.basename(task))
		f = open(task)
		username = f.readline()[0:-1]
		password = f.readline()[0:-1]
		fullname = f.readline()[0:-1]
		firstname = f.readline()[0:-1]
		lastname = f.readline()[0:-1]
		email = f.readline()[0:-1]
		language = f.readline()[0:-1]
		
		# make sure UID is above 10000
		if userid < 10000:
			raise Exception("UID_TOO_LOW")
		
		cmds = []
		unused_output = open(os.devnull)
		
		# check whether user already exists and delete it if it does
		cmd = dscl + ['-read', '/Users/' + username]
		if subprocess.call(cmd, stdout=unused_output, stderr=unused_output) == 0:
			# delete it
			cmd = dscl + ['-delete', '/Users/' + username]
			print cmd
			subprocess.check_call(cmd)
		
		# create user
		timestamp = datetime.datetime.today().strftime('%Y-%m-%dT%H:%M:%SZ')
		cmds.append(dscl + [ '-create', '/Users/' + username])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'UserShell', '/bin/bash'])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'RealName', fullname])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'FirstName', firstname])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'LastName', lastname])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'UniqueID', str(userid)])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'PrimaryGroupID', str(primarygroupid)])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'NFSHomeDirectory', '/home/' + username])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'HomeDirectory', '<home_dir><url>afp://home.physcip.uni-stuttgart.de/home/' + username + '</url><path></path></home_dir>'])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'HomeDirectoryQuota', str(homequota)])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'MailAttribute', '<?xml version="1.0" encoding="UTF-8"?><dict><key>kAPOPRequired</key><string>APOPNotRequired</string><key>kAltMailStoreLoc</key><string></string><key>kAttributeVersion</key><string>Apple Mail 1.0</string><key>kAutoForwardValue</key><string>%s</string><key>kIMAPLoginState</key><string>IMAPAllowed</string><key>kMailAccountLocation</key><string>purple.physcip.uni-stuttgart.de</string><key>kMailAccountState</key><string>Forward</string><key>kPOP3LoginState</key><string>POP3Allowed</string><key>kUserDiskQuota</key><string>0</string></dict>' % email])
		cmds.append(dscl + [ '-create', '/Users/' + username, 'PrintServiceUserData', '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd"><plist version="1.0"><dict><key>lastmod</key><date>%s</date><key>quotaconfig</key><dict><key>byqueue</key><array/><key>default</key><dict><key>lastmod</key><date>%s</date><key>limit</key><integer>200</integer><key>period</key><integer>365</integer><key>start</key><date>%s</date><key>units</key><integer>1</integer></dict><key>mode</key><string>ALL</string></dict><key>version</key><integer>1</integer></dict></plist>' % (timestamp,timestamp,timestamp)])
		cmds.append(dscl + [ '-passwd', '/Users/' + username, password ])
		
		# create homedir
		#cmds.append([ '/usr/sbin/createhomedir', '-c', '-u', username ])
		sshopts = [ '-q', '-o', 'UserKnownHostsFile=/dev/null', '-o', 'StrictHostKeyChecking=no', '-o', 'PasswordAuthentication=no', '-o', 'PubkeyAuthentication=yes', '-i', '/etc/phyreg-id_rsa' ]
		cmds.append([ '/usr/bin/ssh' ] + sshopts + [ 'root@home.physcip.uni-stuttgart.de', '/usr/local/bin/inithomedir.sh', username, language ])
		# set language
		#cmds.append(['sudo', '-u', username, './userinit.sh', language])

		#cmds.append([ '/usr/local/bin/dbclient', '-y', '-i', '/etc/phyreg-id_rsa.db', 'root@home.physcip.uni-stuttgart.de', '/usr/local/bin/inithomedir.sh', username, language ])
		
		# actually run the commands
		for cmd in cmds:
			if cmd.count(password) == 0: # don't print password to log
				print cmd
			subprocess.check_call(cmd)
	
	except Exception as e:
		print e
		f = open('log/' + str(userid), 'a')
		f.write('%s' % e)
		f.close()
	
	finally:
		# delete taskfile
		if os.path.exists('/usr/bin/srm'):
			subprocess.check_call([ '/usr/bin/srm', task])
		else
			subprocess.check_call([ '/bin/rm', task])
	
	print '== Finished %s ==' % task
