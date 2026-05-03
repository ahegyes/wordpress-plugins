=== Locked Payment Methods for WooCommerce ===
Contributors: ahegyes
Tags: woocommerce, payment-methods, checkout, restrictions, gateways
Tested up to: 7.0
Stable tag: 2.0.0
Requires at least: 7.0
Requires PHP: 8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restricts WooCommerce payment methods based on cart contents — products, categories, shipping methods.

== Description ==

Restricts which WooCommerce payment methods are available at checkout based on cart contents. Configure rules that lock specific payment gateways to specific products, categories, or shipping methods.

This is the v2 successor to the v1 plugin. Existing v1 installations upgrading to v2 will continue to work — hook names, option keys, the settings page slug, the gateway-locking rules format, and REST routes are preserved. See the Changelog for the full backward-compatibility statement.

== Installation ==

1. Upload the plugin zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin.

== Upgrade Notice ==

= 2.0.0 =
Major rewrite on the DWS v2 framework. Public hooks/options/settings UI/REST routes are preserved. PHP 8.5+, WordPress 7.0+, and a current WooCommerce (HPOS-compatible) now required. PHP code that imported v1 internal namespaces (`DeepWebSolutions\WC_Plugins\LockedPaymentMethods\…`) must update to the new namespace (`DeepWebSolutions\LockedPaymentMethods\`).

== Changelog ==

<!-- Start changelog -->

## 2.0.0 - unreleased

This is the v2 successor to the v1 plugin published under the same wp.org slug. v1's public contract — hook names, option keys, settings page slug, gateway-locking rules format, REST routes — is preserved unless a specific item is listed under "Changed" or "Removed" below. v1 installations upgrading to v2 should continue to work without configuration changes.

### Changed
- Internal architecture rewritten on the DWS v2 framework (composition-based components, PSR-11 container, no service-handler/inheritance hierarchies). v1 PHP namespaces (`DeepWebSolutions\WC_Plugins\LockedPaymentMethods\…`) are dropped — the new namespace is `DeepWebSolutions\LockedPaymentMethods\`. PHP code that imported v1 internals must update; sites that only consumed hooks/options/settings UI are unaffected.
- Minimum PHP raised from 7.4 → 8.5.
- Minimum WordPress raised from 6.0 → 7.0.
- Minimum WooCommerce raised — see plugin header for current floor; HPOS-compatible.

<!-- End changelog -->

[See the full changelog on GitHub.](https://github.com/ahegyes/wc-locked-payment-methods/blob/trunk/CHANGELOG.md)
