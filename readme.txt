=== Wordpress Sentinel ===
Contributors: blogrescue
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=GFVCGSUFKX2CU
Tags: Wordpress, Installation, Sentinel, Hack Detection
Requires at least: 3.0
Tested up to: 3.3
Stable tag: 3.1

This plugin acts as a sentinel that watches over your core Wordpress programs (plus installed themes and plugins) and tells you when changes happen.

== Description ==

Wordpress Sentinel tracks all files in a Wordpress installation (core, themes, plugins) and then periodically rechecks and notifies the administrator of any files that have changed in any way.  

Most attacks against Wordpress sites will install rogue code wherever they can - in new and existing files in the themes, plugins and even in the Wordpress core files.  This plugin is designed to tell the administrator exactly what files have been touched and when in order to make hack detection and recovery much easier.

== Installation ==

1. Install the plugin
2. Select the plugin option under the Settings menu
3. Press the <strong>Snapshot Everything New</strong> button

This causes the plugin to scan the Wordpress install and track details on all files used by Wordpress, as well as for all installed plugins and themes.

It is also possible to disable watching specific files.  This is done in the detail view by clicking on the Eye Icon next to the filename.  A crossed out Eye Icon will appear and the status will change to <strong>Not Watched</strong>.  To enable watching on that file, just click on the crossed out Eye Icon.

== Frequently Asked Questions ==

= How does this thing work? =

As Wordpress grows in popularity, it also becomes a bigger target for the hacking community. It is hard to think of anything more frustrating than finding that your site is redirecting or displaying content which is not your own.

If you are hacked, there are four questions that you have to address:

1. How did they get in?
2. What did they change?
3. How do I undo the damage that was done?
4. How do I prevent them from getting in again?

The purpose of this plugin is to alert you when you have been hacked and to address questions 2 & 3. Wordpress Sentinel acts as a watchdog that knows how your install is supposed to look and then alert you when something gets changed.

= How do I use it? =

First, install the plugin and go to the Wordpress Sentinel option under Settings. It should list content under Wordpress, Themes and Plugins.

Second, click the <strong>Snapshot Everything New</strong> button, and every file in your Wordpress install, as well as installed Themes and Plugins will be catalogued.

Periodically, the plugin will check a portion of the items for which snapshots have been taken. If any changes are detected, an administrative message will be displayed in Wordpress Admin. If this happens, go back to the Wordpress Sentinel option under Settings. The offending item will be marked as <strong>Changed</strong>. If you click details, you can see what files have been changed and you can determine if this was a valid change or an intrusion and take the appropriate action.

= What if I'm the one making changes? =

Obviously, the plugin cannot differentiate between a good change and a bad change, so if you make changes to a Theme or install a new Plugin, or even Upgrade Wordpress to a newer version, it is simply going to notice the change and let you know. When this happens (and it will happen), just go to the Wordpress Sentinel option, find the item that you changed or added, and Refresh the Snapshot. (The <strong>Snapshot Everything New</strong> button is a handy way to create initial snapshots after installing new themes and plugins.  It does not touch items which have previously been catalogued.)

= What are Checksums and Why do I need Them? =

Checksums are a way of looking at the contents of a file and building a hash.  If the file changes in any way, even if the size remains the same, the checksum will be different.  Enabling checksums adds extra security however, however this comes at a cost.  The added overhead can slow down a site if there are an inordinate number of files or if there are extremely large files that have to be processed.  The basic file checks compare the modification date and the file size.  This should provide adequate protection in most situations.
  
= It is complaining because my sitemap updated - How do I fix this? =

To stop watching your sitemap files, do the following:

1. Go to the Wordpress Sentinel interface
2. Under Wordpress Root, click the <strong>Detail</strong> link
3. Find <em>sitemap.xml</em> in the list and click on the Eye Icon to the left of the filename
4. Find <em>sitemap.xml.gz</em> (if it exists) in the list and click on the Eye Icon to the left of the filename
5. Click the <strong>Back</strong> link to get back to the Sentinel main screen
6. Under Wordpress Root, click the <strong>Perform Check</strong> link

The same process can be used to ignore changes for any file.

= I have a plugin that creates temp files in the plugin directory and gives false positives.  How do I fix this? =

To stop watching a specific plugin or theme, do the following:

1. Go to the Wordpress Sentinel interface
2. Find the plugin or theme that you would like to not have watched
3. Click on the Eye Icon to the left of the plugin or theme
4. The Eye Icon will now show a red X indicating that the plugin or theme is not being watched
  
= What do I do if I really have been hacked? =

The first thing to do is to look at the Wordpress Sentinel page and figure out what items have been changed. Take a screenshot and then look at the details of those items to see what files have been affected. If Wordpress is changed, you need to replace every file that is changed, although usually removing the existing install and replacing it with a clean install is the best course.

If a plugin has been corrupted, it needs to be completely removed and reinstalled. Just updating over the existing install is not advised, as any malicious files that have been added would remain.

If a theme has been corrupted, then things may get complicated. If it is a stock theme that can be removed and reinstalled, then do that. If it is a custom theme, then every modified file needs to be carefully examined and cleaned up. You may need someone with advanced skills in site development to help separate the template content from the injected code.

= How do I stop the hacker from getting back in? =

That is really beyond the scope of this plugin. The best course of action is to keep Wordpress as well as all plugins and themes up to date. If you know the time the hack occurred (and this plugin helps you determine that) then it is also a good idea to have an Analyst look through your server logs and try to isolate the entry point.

== Screenshots ==

1. Wordpress Sentinel Administration Screen - shows all Top Level items (Wordpress Core, Themes, Plugins) and status for each.
2. Item detail screen (In this screenshot: Wordpress Root)

== Changelog ==

= 1.31 =
* Blew stable tag on the last update so no one automatically updated.  Now Fixed!
* Tweaked the options on the Plugins page so settings shows next to Deactivate.

= 1.3 =
* The plugin would occasionally show a mystery change alert when a plugin or them had been changed and then removed.  It now handles this situation and removes stored snapshot data on themes and plugins that are no longer installed.

= 1.2.1 =
* Increased location field length for the section table to accomodate extremely long install paths

= 1.2 =
* Any folder or file named 'cache' is now always ignored
* Any folder or file named 'error_log' is now always ignored
* A new option that enables/disabled checksum evaluation has been added (Default and recommended setting is Disabled)

= 1.1 =
* Plugin now allows an entire plugin or theme to be ignored (not watched)

= 1.0.2 =
* Details now sort by path & filename
* Ignored files are now still ignored after refreshing a snapshot

= 1.0.1 =
* Fixed Security vulnerabilities, thanks to Julio from <a href='http://boiteaweb.fr'>boiteaweb.fr</a>.
* Changed "Snapshot Everything" button to "Snapshot Everything New" to prevent untentional overwriting of existing snapshot data.
* Changed Detail so it also shows current file information (size and date) in red below the snapshot values.

= 1.0.0 =
* Initial Plugin Release

== Upgrade Notice ==

= 1.0.1 =
This new release plugs several potential security holes.  Please Upgrade Immediately.
