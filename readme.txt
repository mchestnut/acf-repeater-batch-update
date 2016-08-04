=== ACF Repeater Batch Update ===
Contributors: mchestnut
Tags: custom fields, acf, performance
Requires at least: 4.0
Tested up to: 4.5
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Modifies the ACF Repeater addon to batch update queries for performance improvement.


== Description ==

Modifies the Advanced Custom Fields Repeater addon to batch update queries for performance improvement. This is necessary for posts that contain complex nested repeater fields. If you have issues with server timeouts while publishing changes, this plugin may help solve that.


= How It Works =

This plugin uses a custom action that bypasses the core update_metadata function to update repeater fields. This custom action pulls all subfields from the repeater into a single SQL statement to reduce the requests going to the database.

**This plugin requires both the [Advanced Custom Fields](http://wordpress.org/extend/plugins/advanced-custom-fields/) plugin (version 4.0+ only) *AND* [Repeater Field](http://www.advancedcustomfields.com/add-ons/repeater-field/) paid add-ons.**


= Requirements =

* Advanced Custom Fields
* ACF Repeater Field


== Installation ==

1. Upload the `acf-repeater-batch-update` directory to `/wp-content/plugins/`, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress


== Changelog ==

= 1.0.0 =

* First version