=== Internal Comments ===
Contributors: ahegyes
Tags: comments, woocommerce, internal, staff, notes
Tested up to: 7.0
Stable tag: 2.0.0
Requires at least: 7.0
Requires PHP: 8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Internal, staff-only comment threads on WordPress and WooCommerce content. Independent of public comments.

== Description ==

Adds internal threaded comments to posts, pages, custom post types, and WooCommerce orders. Internal comments are stored separately from public comments and are visible only to staff users with the configured capability.

This is the v2 successor to the v1 plugin. Existing v1 installations upgrading to v2 will continue to work — hook names, option keys, the `internal_comment` comment type, capability names, and REST routes are preserved. See the Changelog for the full backward-compatibility statement.

== Installation ==

1. Upload the plugin zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin.

== Upgrade Notice ==

= 2.0.0 =
Major rewrite on the DWS v2 framework. Public hooks/options/capabilities/REST routes are preserved. PHP 8.5+ and WordPress 7.0+ now required. PHP code that imported v1 internal namespaces (`DeepWebSolutions\WC_Plugins\InternalComments\…`) must update to the new namespace (`DeepWebSolutions\InternalComments\`).

== Changelog ==

<!-- Start changelog -->

## 2.0.0 - unreleased

This is the v2 successor to the v1 plugin published under the same wp.org slug. v1's public contract — hook names, option keys, shortcodes, capability names, comment-type slug, REST routes — is preserved unless a specific item is listed under "Changed" or "Removed" below. v1 installations upgrading to v2 should continue to work without configuration changes.

### Changed
- Internal architecture rewritten on the DWS v2 framework (composition-based components, PSR-11 container, no service-handler/inheritance hierarchies). v1 PHP namespaces (`DeepWebSolutions\WC_Plugins\InternalComments\…`) are dropped — the new namespace is `DeepWebSolutions\InternalComments\`. PHP code that imported v1 internals must update; sites that only consumed hooks/options/shortcodes are unaffected.
- Minimum PHP raised from 7.4 → 8.5.
- Minimum WordPress raised from 6.0 → 7.0.

<!-- End changelog -->

[See the full changelog on GitHub.](https://github.com/ahegyes/wp-internal-comments/blob/trunk/CHANGELOG.md)
