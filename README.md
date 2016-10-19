# contentshell

The RACHEL Content Management System

## Description

A set of HTML & PHP files for serving and managing RACHEL content
modules, which are available at [our module repository](http://dev.worldpossible.org/cgi/rachelmods.pl).

## Usage

To make use of contentshell, you first must set up a system that
includes the prerequisites. The easiest way to do this is using
either our [rachelplus](https://github.com/rachelproject/rachelplus)
or [rachelpiOS](https://github.com/rachelproject/rachelpiOS) repositories, which build in
all the prerequisites on an Intel CAP or Raspberry Pi respectively.

Technically, contentshell can be used on any system, but it requires the following:

* for search to work you must have SQLite and stem-1.5.1 installed for PHP
* to use any ZIM based modules you must have kiwix installed
* to use the ka-lite modules you must have kalite installed

Further, there are some rules about where things are installed, so it
gets a bit complicated. The best route is to refer to the code in
our [rachelplus](https://github.com/rachelproject/rachelplus)
or [rachelpiOS](https://github.com/rachelproject/rachelpiOS) repositories. Or better yet, use one of
the pre-built images available at rachelfriends.org for free.

## Where is stuff

Modules downloaded from [the RACHEL module repository](http://dev.worldpossible.org/cgi/rachelmods.pl)
must be installed in the "modules" directory in the root of contentshell.

* On the RACHEL Pi this will be /var/www/modules
* On the RACHEL Plus this will be /media/RACHEL/rachel/modules

For more information on modules, please check out [modules-template](https://github.com/rachelproject/module-template).

# License

Creative Commons - BY, SA, NC
