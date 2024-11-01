=== WP Enterprise Launch Deploy ===
Contributors: kasigi, sagetarian
Tags: install, export, import, migrate, upload, live server, development, deploy
Requires at least: 3.1
Tested up to: 3.8.1
License: GPLv2 or later

Automates the entire process for migrating your wordpress install from a test/development/source server to the live/destination server.

== Description ==

Automates the entire process for migrating your wordpress install from a test/development/source to the live/destination domain. Also allows the ignoring of certain files/directories not needed to upload (e.g /cache, /.git, /.svn /.gitignore etc etc).

Here’s a list of what is accomplished during an automated deployment:




Disclaimer: Please backup any files or mysql tables on the live server.  This plugin is intended for engineers with a working knowledge of linux and web development. Use at own risk.

This plugin is a fork/derivative of WP Live Server Deploy (http://wordpress.org/plugins/wp-live-server-deploy/)

== Installation ==


== Frequently Asked Questions ==

= My FTP Root isn't in a subfolder like "/public_html", what now? =

Then you must save the ftp root as "/"



== Screenshots ==

* No screenshots

== Changelog ==

0.1.0 Initial Release

0.1.1 Patch - Change serialized array recalculation function

== Upgrade Notice ==

