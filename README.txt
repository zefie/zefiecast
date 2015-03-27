REQUIREMENTS:

	PHP v4.1.x or greater.
	MPlayer
	LAME
	vorbis-tools (if you want to use OGG input/output)
	faad (if you want to use MP4/AAC input)


If you are upgrading from v0.4, please update your database using the following command:
mysql -u <user> -p <database> < scripts/sqlupgrade_v0.4_to_v0.4.1.sql

Replacing <user> and <database> with the username and database you made for zefiecast.
Remove -p if your database is not password protected.

--

If you are upgrading from v0.2 or lower you will need to purge your database. Sorry.

Documentation is slim to none at the moment.
I intend to fix this in later versions, but I wanted to release what I have done so far.

We are looking for developers to help. If you are interested, you will 
need the following:

	Decent PHP and MySQL Skills
	Knowledge of Streaming Audio (ShoutCast and Icecast)
	A knowledge of SAM Broadcaster would help but is not required.

We could also use a documentation writer, obviously. :)

Thanks for checking out ZefieCast!


