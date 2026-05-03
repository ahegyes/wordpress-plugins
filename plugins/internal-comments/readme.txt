=== Internal Comments ===
Contributors: ahegyes
Tags: comments, woocommerce, internal, staff, notes
Tested up to: 7.0
Stable tag: 1.0.0
Requires at least: 7.0
Requires PHP: 8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Internal, staff-only comment threads on WordPress and WooCommerce content. Independent of public comments.

== Description ==

Adds internal threaded comments to posts, pages, custom post types, and WooCommerce orders. Internal comments are stored separately from public comments and are visible only to staff users with the configured capability.

== Installation ==

1. Upload the plugin zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin.

== Changelog ==

<!-- Start changelog -->

## 1.0.0 - unreleased

### Added
- Initial release.
- Per-plugin scoped dependencies (framework + PHP-DI bundled under a plugin-specific namespace prefix).
- Demo `AdminNotice` component implementing `HookableInterface`.

<!-- End changelog -->

[See the full changelog on GitHub.](https://github.com/ahegyes/wp-internal-comments/blob/trunk/CHANGELOG.md)
