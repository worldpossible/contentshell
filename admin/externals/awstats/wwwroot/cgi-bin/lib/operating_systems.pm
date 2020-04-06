# AWSTATS OPERATING SYSTEMS DATABASE
#-------------------------------------------------------
# If you want to add an OS to extend AWStats database detection capabilities,
# you must add an entry in OSSearchIDOrder, in OSHashID and in OSHashLib.
#-------------------------------------------------------

# 2005-08-19 Sean Carlos http://www.antezeta.com/awstats.html
#              - added specific Linux distributions in addition to 
#              the generic Linux.  
#              Included documentation link to Distribution home pages.
#              - added links for each operating systems.

# 2013-01-08 Joe CC Ho - added iOS, Windows 8 and Windows Phone.

#package AWSOS;

# Relocated from main file for easier editing
%OSFamily = (
	'win'   => 'Windows',
	'mac'   => 'Macintosh',
	'ios'   => 'iOS',
	'linux' => 'Linux',
	'bsd'   => 'BSD'
);

# OSSearchIDOrder
# This list is used to know in which order to search Operating System IDs
# (Most frequent one are first in this list to increase detect speed).
# It contains all matching criteria to search for in log fields.
# Note: OS IDs are in lower case and '_', ' ' and '+' are changed into '[_+ ]'
#-------------------------------------------------------------------------
@OSSearchIDOrder	= (
# Windows OS family
'windows[_+ ]?2005', 'windows[_+ ]nt[_+ ]6\.0',
'windows[_+ ]?2008', 'windows[_+ ]nt[_+ ]6\.1', # Must be before windows_nt_6
'windows[_+ ]?2012', 'windows[_+ ]nt[_+ ]6\.2', # Must be before windows_nt_6 = windows 8
'windows[_+ ]nt[_+ ]6\.3', # Must be before windows_nt_6 = windows 8.1 
'windows[_+ ]nt[_+ ]10', # Windows 10
'windows[_+ ]?vista', 'windows[_+ ]nt[_+ ]6',
'windows[_+ ]?2003','windows[_+ ]nt[_+ ]5\.2',	# Must be before windows_nt_5
'windows[_+ ]xp','windows[_+ ]nt[_+ ]5\.1',		# Must be before windows_nt_5
'windows[_+ ]me','win[_+ ]9x',					# Must be before windows_98
'windows[_+ ]?2000','windows[_+ ]nt[_+ ]5',
'windows[_+ ]phone',
'winnt','windows[_+ \-]?nt','win32',
'win(.*)98',
'win(.*)95',
'win(.*)16','windows[_+ ]3',					# This works for windows_31 and windows_3.1
'win(.*)ce',
# iOS family
#'iphone[_+ ]os',  #Must be Before Mac OS Family
#'ipad[_+ ]os',  #Must be Before Mac OS Family
#'ipod[_+ ]os',  #Must be Before Mac OS Family
'iphone',
'ipad',
'ipod',
# Macintosh OS family
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]12',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]11',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]10',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]9',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]8',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]7',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]6',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]5',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]4',
'mac[_+ ]os[_+ ]x',
'mac[_+ ]?p',									# This works for macppc and mac_ppc and mac_powerpc
'mac[_+ ]68',									# This works for mac_6800 and mac_68k
'macweb',
'macintosh',
# Linux family
'linux(.*)android',
'linux(.*)asplinux',
'linux(.*)centos',
'linux(.*)debian',
'linux(.*)fedora',
'linux(.*)gentoo',
'linux(.*)mandr',
'linux(.*)momonga',
'linux(.*)pclinuxos',
'linux(.*)red[_+ ]hat',
'linux(.*)suse',
'linux(.*)ubuntu',
'linux(.*)vector',
'linux(.*)vine',
'linux(.*)white\sbox',
'linux(.*)zenwalk',
'linux',
'android',
'debian',
# Hurd family
'gnu.hurd',
# BSDs family
'bsdi',
'gnu.kfreebsd',								    # Must be before freebsd
'freebsd',
'openbsd',
'netbsd',
'dragonfly',
# Other Unix, Unix-like
'aix',
'sunos',
'irix',
'osf',
'hp\-ux',
'unix',
'x11',
'gnome\-vfs',
# Other famous OS
'beos',
'os/2',
'amiga',
'atari',
'vms',
'commodore',
'qnx',
'inferno',
'palmos',
'syllable',
# Miscellaneous OS
'blackberry',
'cp/m',
'crayos',
'dreamcast',
'risc[_+ ]?os',
'symbian',
'webtv',
'playstation',
'xbox',
'wii',
'vienna',
'newsfire',
'applesyndication',
'akregator',
'plagger',
'syndirella',
'j2me',
'java',
'microsoft',									# Pushed down to prevent mis-identification
'msie[_+ ]',									# by other OS spoofers.
'ms[_+ ]frontpage',
'windows'
);


# OSHashID
# Each OS Search ID is associated to a string that is the AWStats id and
# also the name of icon file for this OS.
#--------------------------------------------------------------------------
%OSHashID	= (
# Windows OS family
'windows[_+ ]?2005','winlong','windows[_+ ]nt[_+ ]6\.0','winlong',
'windows[_+ ]?2008','win2008','windows[_+ ]nt[_+ ]6\.1','win7',
'windows[_+ ]?2012','win2012','windows[_+ ]nt[_+ ]6\.2','win8',
'windows[_+ ]nt[_+ ]6\.3','win8.1',
'windows[_+ ]nt[_+ ]10','win10',
'windows[_+ ]?vista','winvista','windows[_+ ]nt[_+ ]6','winvista',
'windows[_+ ]?2003','win2003','windows[_+ ]nt[_+ ]5\.2','win2003',
'windows[_+ ]xp','winxp','windows[_+ ]nt[_+ ]5\.1','winxp', 'syndirella', 'winxp',
'windows[_+ ]me','winme','win[_+ ]9x','winme',
'windows[_+ ]?2000','win2000','windows[_+ ]nt[_+ ]5','win2000',
'winnt','winnt','windows[_+ \-]?nt','winnt','win32','winnt',
'windows[_+ ]phone','winphone',
'win(.*)98','win98',
'win(.*)95','win95',
'win(.*)16','win16','windows[_+ ]3','win16',
'win(.*)ce','wince',
'microsoft','winunknown',
'msie[_+ ]','winunknown',
'ms[_+ ]frontpage','winunknown',
# iOS family
#'iphone[_+ ]os','ios_iphone',       #Must be Before Mac OS Family
#'ipad[_+ ]os','ios_ipad',       #Must be Before Mac OS Family
#'ipod[_+ ]os','ios_ipod',       #Must be Before Mac OS Family
'iphone','ios_iphone', #Must be Before Mac OS Family
'ipad','ios_ipad', #Must be Before Mac OS Family
'ipod','ios_ipod',  #Must be Before Mac OS Family
# Macintosh OS family
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]12','macosx12',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]11','macosx11',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]10','macosx10',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]9','macosx9',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]8','macosx8',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]7','macosx7',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]6','macosx6',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]5','macosx5',
'mac[_+ ]os[_+ ]x[_+ ]10[_\.]4','macosx4',
'mac[_+ ]os[_+ ]x','macosx', 'vienna', 'macosx', 'newsfire', 'macosx', 'applesyndication', 'macosx',
'mac[_+ ]?p','macintosh','mac[_+ ]68','macintosh','macweb','macintosh','macintosh','macintosh',
# Linux family (linuxyyy)
'linux(.*)android','linuxandroid',
'linux(.*)asplinux','linuxasplinux',
'linux(.*)centos','linuxcentos',
'linux(.*)debian','linuxdebian',
'linux(.*)fedora','linuxfedora',
'linux(.*)gentoo','linuxgentoo',
'linux(.*)mandr','linuxmandr',
'linux(.*)momonga','linuxmomonga',
'linux(.*)pclinuxos','linuxpclinuxos',
'linux(.*)red[_+ ]hat','linuxredhat',
'linux(.*)suse','linuxsuse',
'linux(.*)ubuntu','linuxubuntu',
'linux(.*)vector','linuxvector',
'linux(.*)vine','linuxvine',
'linux(.*)white\sbox','linuxwhitebox',
'linux(.*)zenwalk','linuxzenwalk',
'linux','linux',
'android','linuxandroid',
'debian','linuxdebian',
# Hurd family
'gnu.hurd','gnu',
# BSDs family (bsdyyy)
'bsdi','bsdi',
'gnu.kfreebsd','bsdkfreebsd',						    # Must be before freebsd
'freebsd','bsdfreebsd',
'openbsd','bsdopenbsd',
'netbsd','bsdnetbsd',
'dragonflybsd','bsddflybsd',
# Other Unix, Unix-like
'aix','aix',
'sunos','sunos',
'irix','irix',
'osf','osf',
'hp\-ux','hp\-ux',
'unix','unix',
'x11','unix',
'gnome\-vfs','unix',
'plagger', 'unix',
# Other famous OS
'beos','beos',
'os/2','os/2',
'amiga','amigaos',
'atari','atari',
'vms','vms',
'commodore','commodore',
'j2me', 'j2me',
'java', 'java',
'qnx','qnx',
'inferno','inferno',
'palmos','palmos',
'syllable','syllable',
# Miscellaneous OS
'akregator', 'linux',
'blackberry','blackberry',
'cp/m','cp/m',
'crayos','crayos',
'dreamcast','dreamcast',
'risc[_+ ]?os','riscos',
'symbian','symbian',
'webtv','webtv',
'playstation', 'psp',
'xbox', 'winxbox',
'wii', 'wii',
'windows','winunknown'
);

# OS name list ('os unique id in lower case','os clear text')
# Each unique ID string is associated to a label
#-----------------------------------------------------------
%OSHashLib      = (
# Windows family OS
'win10','Windows 10',
'win8.1','Windows 8.1',
'win8','Windows 8',
'win7','Windows 7',
'winlong','Windows Vista (LongHorn)',
'win2008','Windows 2008',
'win2012','Windows Server 2012',
'winvista','Windows Vista',
'win2003','Windows 2003',
'winxp','Windows XP',
'winme','Windows ME',
'win2000','Windows 2000',
'winnt','Windows NT',
'win98','Windows 98',
'win95','Windows 95',
'win16','Windows 3.xx',
'wince','Windows Mobile',
'winphone','Windows Phone',
'winunknown','Windows (unknown version)',
'winxbox','Microsoft XBOX',
# Macintosh OS
'macosx12','Mac OS X 10.12 Sierra',
'macosx11','Mac OS X 10.11 El Capitan',
'macosx10','Mac OS X 10.10 Yosemite',
'macosx9','Mac OS X 10.9 Mavericks',
'macosx8','Mac OS X 10.8 Mountain Lion',
'macosx7','Mac OS X 10.7 Lion',
'macosx6','Mac OS X 10.6 Snow Leopard',
'macosx5','Mac OS X 10.5 Leopard',
'macosx4','Mac OS X 10.4 Tiger',
'macosx','Mac OS X others',
'macintosh','Mac OS',
# Linux
'linuxandroid','Google Android',
'linuxasplinux','ASPLinux',
'linuxcentos','Centos',
'linuxdebian','Debian',
'linuxfedora','Fedora',
'linuxgentoo','Gentoo',
'linuxmandr','Mandriva (or Mandrake)',
'linuxmomonga','Momonga Linux',
'linuxpclinuxos','PCLinuxOS',
'linuxredhat','Red Hat',
'linuxsuse','Suse',
'linuxubuntu','Ubuntu',
'linuxvector','VectorLinux',
'linuxvine','Vine Linux',
'linuxwhitebox','White Box Linux',
'linuxzenwalk','Zenwalk GNU Linux',
'linux','Linux (Unknown/unspecified)',
'linux','GNU Linux (Unknown or unspecified distribution)',
# Hurd
'gnu','GNU Hurd',
# BSDs
'bsdi','BSDi',
'bsdkfreebsd','GNU/kFreeBSD',
'freebsd','FreeBSD',    # For backard compatibility
'bsdfreebsd','FreeBSD',
'openbsd','OpenBSD',    # For backard compatibility
'bsdopenbsd','OpenBSD',
'netbsd','NetBSD', # For backard compatibility
'bsdnetbsd','NetBSD',
'bsddflybsd','DragonFlyBSD',
# Other Unix, Unix-like
'aix','Aix',
'sunos','Sun Solaris',
'irix','Irix',
'osf','OSF Unix',
'hp\-ux','HP UX',
'unix','Unknown Unix system',
# iOS
'ios_iphone','iOS (iPhone)',
'ios_ipad','iOS (iPad)',
'ios_ipod','iOS (iPod)',
# Other famous OS
'beos','BeOS',
'os/2','OS/2',
'amigaos','AmigaOS',
'atari','Atari',
'vms','VMS',
'commodore','Commodore 64',
'j2me','Java Mobile',
'java','Java',
'qnx','QNX',
'inferno','Inferno',
'palmos','Palm OS',
'syllable','Syllable',
# Miscellaneous OS
'blackberry','BlackBerry',
'cp/m','CP/M',
'crayos','CrayOS',
'dreamcast','Dreamcast',
'riscos','RISC OS',
'symbian','Symbian OS',
'webtv','WebTV',
'psp', 'Sony PlayStation',
'wii', 'Nintendo Wii'
);


1;


# Informations from microsoft for detecting windows version
#  Windows 95 retail, OEM     4.00.950                     7/11/95
#  Windows 95 retail SP1      4.00.950A                    7/11/95-12/31/95
#  OEM Service Release 2      4.00.1111* (4.00.950B)       8/24/96
#  OEM Service Release 2.1    4.03.1212-1214* (4.00.950B)  8/24/96-8/27/97  
#  OEM Service Release 2.5    4.03.1214* (4.00.950C)       8/24/96-11/18/97
#  Windows 98 retail, OEM     4.10.1998                    5/11/98
#  Windows 98 Second Edition  4.10.2222A                   4/23/99
#  Windows Me                 4.90.3000
