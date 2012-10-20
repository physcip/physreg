PHYREGGER = 'phyregger'
with open('/etc/phyreg-password') as f:
	PHYREGGERPW = f.readline()[0:-1]
primarygroupid = 10000
homequota = 1048576000