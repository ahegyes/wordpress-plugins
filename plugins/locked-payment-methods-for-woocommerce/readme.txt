=== Locked Payment Methods for WooCommerce ===
Contributors: ahegyes, deepwebsolutions
Tags: payment methods, woocommerce, checkout, manual permission
Tested up to: 7.1
Stable tag: 2.0.0
Requires at least: 7.1
Requires PHP: 8.5
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Hides the payment methods you lock from customers at checkout until you unlock them for a user, a role, an order, a group or a membership plan.

== Description ==

**Locked Payment Methods for WooCommerce hides the payment methods you lock from every customer you have not unlocked them for.**

A typical use-case involves locking high-risk payment methods like *Cash on delivery* or *Pay by invoice*, but you can lock any payment method registered with WooCommerce, for any reason.

Locked payment methods are removed from the checkout and the order payment page for every customer they are not unlocked for. You can then unlock them case by case, or by criteria for logged-in users you trust, while everyone else sees only the payment methods you leave unlocked.

### Unlocking a payment method for your customers

* Unlock payment methods for a customer from their user profile.
* Unlock every payment method for all customers with given user roles.
* Unlock payment methods for one unpaid order from its edit screen.
* Unlock payment methods for every active member of a [WooCommerce Memberships](https://woocommerce.com/products/woocommerce-memberships/) plan.
* Unlock payment methods for every member of a [Groups](https://wordpress.org/plugins/groups/) group and of its subgroups.

Developers can lock and unlock payment methods by their own rules through the plugin's filters.

== Installation ==

This plugin requires WooCommerce 11.2 or newer. If you're running a lower version, please update it first.

### INSTALL FROM WITHIN WORDPRESS

1. Visit the plugins page within your dashboard and select `Add New Plugin`.
1. Search for `Locked Payment Methods for WooCommerce` and click the `Install Now` button.
1. Activate the plugin from within your `Plugins` page.

### INSTALL MANUALLY

1. Download the plugin from https://wordpress.org/plugins/locked-payment-methods-for-woocommerce/ and unzip the archive.
1. Copy the unzipped `locked-payment-methods-for-woocommerce` folder into your site's `wp-content/plugins` folder, through SFTP or your host's file manager.
1. Activate the plugin through the `Plugins` menu in WordPress.

### AFTER ACTIVATION

If WooCommerce 11.2 or newer is active, you will find a section called `Locked Payment Methods` under `WooCommerce` → `Settings` → `Advanced`, which the plugin's `Settings` link on the `Plugins` page also opens. There you will be able to:

1. Choose which payment methods are locked by default.
1. Choose which ways of unlocking them you use, and which user roles have access to every payment method.
1. Choose whether to remove the plugin's data, the unlocked payment methods included, when it is uninstalled.

== Frequently Asked Questions ==

= What is a locked payment method? =

That's what we call payment methods that you choose to hide from your customers by default. Granting a customer access is called *unlocking* the payment method.

= Which payment methods can I lock? =

Any payment method registered with WooCommerce, disabled ones included. A locked payment method that you disable stays locked, so it is still locked once you enable it again.

= Are unlocked payment methods shared across a multisite network? =

No. A customer's unlocked payment methods are kept per site, so unlocking a payment method for a customer on one store of a network leaves it locked on the others. Payment methods unlocked before version 2.0.0 keep applying on every site.

= How can I get help if I'm stuck? =

You can open a community support ticket here at [wordpress.org](https://wordpress.org/support/plugin/locked-payment-methods-for-woocommerce/).

== Screenshots ==

1. Plugin settings section within the WooCommerce settings.
2. Example of how to unlock payment methods for all users of given roles.
3. Example of how to unlock payment methods for a specific user.

== Upgrade Notice ==

= 2.0.0 =
Requires PHP 8.5, WordPress 7.1 and WooCommerce 11.2. Settings move to WooCommerce → Settings → Advanced, and a customer's unlocked payment methods become per site. Only an explicit false unlocks a payment method. Read the changelog before updating.

== Changelog ==

<!-- Start changelog -->

<!-- End changelog -->

[See the full changelog on GitHub.](https://github.com/ahegyes/wc-locked-payment-methods/blob/trunk/CHANGELOG.md)
