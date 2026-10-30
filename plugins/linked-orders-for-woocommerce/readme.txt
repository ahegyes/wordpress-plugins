=== Linked Orders for WooCommerce ===
Contributors: ahegyes, deepwebsolutions
Tags: linked orders, woocommerce
Tested up to: 7.1
Stable tag: 2.0.0
Requires at least: 7.1
Requires PHP: 8.5
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A WooCommerce extension for creating orders that are logically connected to existing ones.

== Description ==

**Linked Orders is a WooCommerce extension for marking newer orders as being logically connected to older ones.**

What that means depends on your use-case. For example, an order containing a warranty extension should probably be linked to the order containing the product that the extension is for. Or an express processing fee paid separately. Or anything else that you'd like to keep track of without having to open up all the customer's orders to figure it out.

Empty linked orders can be created using the admin interfaces (e.g., for email or phone orders), or you can link orders programmatically to fit your existing workflow.

Linked orders work whether WooCommerce stores orders in its own order tables or as posts.

== Installation ==

This plugin requires WooCommerce 11.2 or newer to run. If you're running a lower version, please update first. After you made sure that you're running a supported version of WooCommerce, you may install `Linked Orders for WooCommerce` either manually or through your site's plugins page.

### INSTALL FROM WITHIN WORDPRESS

1. Visit the plugins page within your dashboard and select `Add New Plugin`.
1. Search for `Linked Orders for WooCommerce` and click the `Install Now` button.
1. Activate the plugin from within your `Plugins` page.

### INSTALL MANUALLY

1. Download the plugin from https://wordpress.org/plugins/linked-orders-for-woocommerce/ and unzip the archive.
1. Copy the unzipped `linked-orders-for-woocommerce` folder into your site's `wp-content/plugins` folder, through SFTP or your host's file manager.
1. Activate the plugin through the `Plugins` menu in WordPress.

### AFTER ACTIVATION

If the minimum required version of WooCommerce is present, you will find a section present in the `Advanced` tab of the WooCommerce `Settings` page. This section is called `Linked Orders`, and the plugin's `Settings` link on the `Plugins` page opens it. There you will be able to:

1. Choose the maximum depth level you want to allow.
1. Choose whether to autocomplete descendant orders upon completion of a parent order.
1. Choose whether to remove the plugin's data when it is uninstalled.

== Frequently Asked Questions ==

= What are linked orders? =

Linked orders are ones that have a logical connection to one another. That can mean whatever you need it to. An order can have several children, and each child can have its own children, down to the maximum depth set in the plugin's settings.

= Does it work with WooCommerce's order tables? =

Yes. The links, the meta box on the order screen and the additions to the orders list work whether WooCommerce stores orders in its order tables or as posts, with or without the compatibility sync between the two.

= How can I get help if I'm stuck? =

You can open a community support ticket here at [wordpress.org](https://wordpress.org/support/plugin/linked-orders-for-woocommerce/).

== Screenshots ==

1. Plugin settings section within the WooCommerce settings.
2. Example of how to visualize order depth on the orders list table.
3. Example of how to visualize order relations on the edit order screen.
4. Example of how to create a new linked child order from the orders list table.

== Upgrade Notice ==

= 2.0.0 =
Requires PHP 8.5, WordPress 7.1 and WooCommerce 11.2. Hooks move to dws_lowc/ names and their v1 names still fire, deprecated, but eight settings hooks, the v1 framework's notice-markup hooks and its accessors, such as dws_lowc_instance(), are removed.

== Changelog ==

<!-- Start changelog -->

<!-- End changelog -->

[See the full changelog on GitHub.](https://github.com/ahegyes/wc-linked-orders/blob/trunk/CHANGELOG.md)
