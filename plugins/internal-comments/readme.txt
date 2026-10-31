=== Internal Comments ===
Contributors: ahegyes, deepwebsolutions
Tags: internal comments, private comments
Tested up to: 7.1
Stable tag: 2.0.0
Requires at least: 7.1
Requires PHP: 8.5
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Lets the people who run a site leave comments on its posts, pages, products and orders that only they can read.

== Description ==

**Internal Comments lets you post admin-facing comments, visible only to users with admin-area access, on any registered post type.**

After selecting the post types that support internal comments in the plugin's settings, a new meta box appears on the edit screen of posts belonging to those post types. Users with the appropriate permissions can post internal comments there, and reply to, edit and trash them in place.

Here is a short overview of the features this plugin offers:

* Internal comments can be enabled on every registered post type for which WordPress generates the admin screens.
* Internal comments can be replied to and edited in place, and each change to a comment's text is kept in its revision history.
* Internal comments can be marked as private, so that only their author and the users allowed to read private comments see them.
* Administrators can view a list of all internal comments posted on the site through a submenu of the *Comments* admin menu.
* The number of internal comments a post has is shown in a column of the posts list, from which they can be quick-viewed without opening the post.
* Internal comments work on WooCommerce orders, whether WooCommerce stores them in its own order tables or as posts.

== Installation ==

There are no prerequisites other than the minimum WordPress and PHP versions. Internal comments on WooCommerce orders need WooCommerce 11.2 or newer.

### INSTALL FROM WITHIN WORDPRESS

1. Visit the plugins page within your dashboard and select `Add New Plugin`.
1. Search for `Internal Comments` and click the `Install Now` button.
1. Activate the plugin from within your `Plugins` page.

### INSTALL MANUALLY

1. Download the plugin from https://wordpress.org/plugins/internal-comments/ and unzip the archive.
1. Copy the unzipped `internal-comments` folder into your site's `wp-content/plugins` folder, through SFTP or your host's file manager.
1. Activate the plugin through the `Plugins` menu in WordPress.

### AFTER ACTIVATION

If the minimum WordPress and PHP requirements are met, you will find a submenu called `Internal Comments` under the `Settings` admin menu, which the plugin's `Settings` link on the `Plugins` page also opens. There you will be able to:

1. Choose which post types support internal comments.
1. Choose whether internal comments can be marked as private.
1. Choose whether to remove the plugin's data, internal comments included, when it is uninstalled.

== Frequently Asked Questions ==

= Do internal comments show up on the front-end of the site? =

No. Internal comments are stored under a status of their own, which WordPress leaves out of every comment list, comment count, feed and REST API response, even while the plugin is inactive.

= Can I post sensitive information in internal comments? =

Internal comments are meant strictly for internal use, but they are not encrypted in the database, so avoid storing sensitive information in them. Think of them more as a digital version of sticky notes.

= Is this compatible with WooCommerce? =

Yes. You can use internal comments on WooCommerce orders, products and coupons. On orders, the meta box and the orders list column work whether WooCommerce stores orders in its order tables or as posts, with or without the compatibility sync between the two.

= Is this compatible with [insert plugin name]? =

Likely yes. Internal Comments works with all registered post types that use the WordPress admin screens. If you don't find your post type in the plugin's settings, please raise a support question.

= How can I get help if I'm stuck? =

You can open a community support ticket here at [wordpress.org](https://wordpress.org/support/plugin/internal-comments/).

== Screenshots ==

1. Plugin settings page.
2. Example of how the meta box looks in the block editor.
3. Example of how the meta box looks in the classic editor.
4. Example of the posts list column.

== Upgrade Notice ==

= 2.0.0 =
Requires PHP 8.5 and WordPress 7.1. Internal comments move to a status of their own, so 1.2.4 no longer shows them, and any it posts stay public after a later update. Reading one needs access to edit its post. Hooks move to dws_ic/; v1's AJAX actions, accessors and framework hooks are removed.

== Changelog ==

<!-- Start changelog -->

<!-- End changelog -->

[See the full changelog on GitHub.](https://github.com/ahegyes/wp-internal-comments/blob/trunk/CHANGELOG.md)
