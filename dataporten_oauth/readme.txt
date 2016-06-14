=== Plugin Name ===
Contributors: kasperrt
Tags: authentication,oauth,dataporten,oauth2.0,uninett
Requires at least: 4.0
Tested up to: 4.5.2
Stable tag: 0.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Authentication plugin for oAuth2.0 with UNINETTS Dataporten.

== Description ==

This plugin enables login with Dataporten, UNINETTS oAuth2.0 platform. The plugin works both with and without Docker. When used with docker, environment variables for client_id, client_secret, redirect_uri and much more can be used.

The plugin enables new and existing users on a wordpress blog to be automaticly assigned to a role, fetched from an array of defined roles decided from user-groups on Dataporten. 

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/dataporten-oauth` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Plugin Name screen to configure the plugin

= Quick Start =

1. Download and install the dataporten-oauth plugin.
2. Setup the oAuth2.0 settings in *Settings > Dataporten-oAuth*.
3. Enable the plugin with *Settings > Dataporten-oAuth > Enabled*


== Frequently Asked Questions ==

= Is it easy to change from Dataporten to any other system? =

Yes, probably.

= Am I allowed to change the plugin to fit my needs? =

Yes.

== Changelog ==

= 0.2 =
* Bug fixes
* Redirecting works better now.