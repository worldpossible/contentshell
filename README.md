# contentshell

The RACHEL Content Management System

## Description

PHP for serving and managing content on [RACHEL devices](https://rachel.worldpossible.org).
Content is available at [our module repository](https://rachel.worldpossible.org/content).

## Usage

The `contentshell` is desiged to run on RACHEL systems. As of 2024 this is primarily
the [RACHEL 5](https://worldpossible.org/products/rachel-5-500). 

Technically, contentshell can be used on any system with PHP & SQLite, but many of the content
modules require support software. These include:

* [ZIM](https://library.kiwix.org/) modules require that [Kiwix](https://kiwix.org/) be installed
* The KA-Lite module requires [KA-Lite](http://kalite.learningequality.org/) software
* The Kolibri module requires [Kolibri](https://learningequality.org/) software
* The Moodle module requires [Moodle](https://moodle.org) software
* Some search features require SQLite and stem-1.5.1 installed for PHP

Still, there are many modules that are static HTML that can be installed on any system and
displayed by `contentshell`.

## Where is stuff

The contentshell should be installed in /media/RACHEL/rachel. This should also be the
document root for your webserver.

Modules downloaded from [our module repository](https://rachel.worldpossible.org/content)
must be installed in /media/RACHEL/rachel/modules

For more information on modules, please check out [modules-template](https://github.com/rachelproject/module-template).

# License

Creative Commons - BY, SA, NC
