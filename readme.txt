=== RSS Rocket ===
Contributors: thegolden_game
Tags: rss, import, automation, feeds, content
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically imports the latest post from configured RSS feeds with customizable scheduling.

== Description ==
RSS Rocket is a simple yet powerful plugin that lets you automatically import the most recent post from up to 5 different RSS feeds. Set up the feeds in the admin panel, choose a frequency (like every 6 hours, daily, or weekly), and let the plugin create posts on your WordPress site. Perfect for keeping your site fresh with news or content from external sources.

Features:
- Imports the latest post from each configured feed.
- Supports up to 5 RSS feeds with custom categories.
- Flexible scheduling: every 6 hours, 12 hours, daily, every 2 days, or weekly.
- Secure and lightweight, using native WordPress functions.

== Installation ==
1. Upload the `rss-rocket` folder to the `/wp-content/plugins/` directory of your WordPress site.
2. Activate the plugin through the "Plugins" menu in the WordPress admin panel.
3. Go to the "RSS Rocket" menu in the admin area, enter the RSS feed URLs, and select categories and import frequency.
4. Click "Save Feeds" to set up and "Import Post" to test it manually.
5. Automatic scheduling will start based on the frequency you chose.

== Frequently Asked Questions ==

= How do I configure the RSS feeds? =
In the "RSS Rocket" menu in the admin panel, enter up to 5 RSS feed URLs (e.g., https://bitcoinmagazine.com/.rss/full/), select a category for each, and choose how often to import.

= Can I import more than one post per feed? =
Currently, RSS Rocket imports only the most recent post from each feed to keep your site updated without duplicates. Future updates might add options for more posts.

= What happens if a feed fails to load? =
If a feed can’t be loaded, the plugin skips it and moves to the next one. Check your feed URLs if you notice missing imports.

== Changelog ==

= 1.0 =
* Initial release with RSS feed import and scheduling features.

== Upgrade Notice ==

= 1.0 =
This is the first version of RSS Rocket. No upgrades needed yet!
