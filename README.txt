=== Plugin Report ===
Contributors: roytanck, zodiac1978, pedromendonca
Tags: admin, plugins, multisite
Requires at least: 4.6
Tested up to: 5.3.2
Requires PHP: 5.6
Stable tag: 1.6
License: GPLv3

A WordPress plugin that provides detailed information about currently installed plugins.

##Plugin Report will allow you to:

* Spot plugins that are no longer maintained.
* Get a quick overview of the "plugin health" of your site.
* Provide clients with a detailed report, right from their own dashboard, or as .xls spreadsheet.
* Find plugins that are no longer active on multisite installs


== Screenshots ==
 
1. Your plugin report is found under the Plugins menu, or in the Network section if your site is a multisite install.


== Changelog ==

= 1.6 (2020-03-14) =
* Further i18n improvements (thanks @pedromendonca)
* Minor version differences no longer shown as "medium" risk
* When a plugin upgrade requires platform upgrades, this is now shown (suggested by @zodiac1978)
* Improved the way CSS and javascript files are enqueued

= 1.5 (2020-02-09) =
* The activation column now shows the number of activations on multisite
* I18n improvements (thanks @pedromendonca!)

= 1.4 (2020-01-17) =
* Adds an .xls export function (experimental)
* Adds an "activated" column to the report table

= 1.3 (2019-11-29) =
* Better table styles, inluding call background colors
* Improved error messages
* Cached information is now automatically refreshed when a plugin is updated through wp-admin

= 1.2 (2019-11-26) =
* Removed the "compatibility" column (data no longer provided by API)
* Fixed an issue with long plugin slugs causing invalide transient keys
* Fixed an issue where version number colors were inconsistent

= 1.1 (2019-11-23) =
* Code cleanup
* Adds proper multisite support
* Accessibility and internationalisation improvements
 
= 1.0 (2017-01-17) =
* Initial version
