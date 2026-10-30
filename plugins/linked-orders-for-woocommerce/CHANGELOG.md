# Changelog

All notable changes to this plugin are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add`. Aggregate into a release with `composer changelog:write`, which also copies the versions between the markers into `readme.txt`.

<!-- Start changelog -->

## 1.2.2 - 2022-07-12

### Changed
- Tested up to WordPress 6.0.1.
- Tested up to WooCommerce 6.7.0.
- Updated Freemius SDK.

## 1.2.1 - 2022-03-20

### Changed
- Tested up to WordPress 5.9.
- Tested up to WooCommerce 6.3.

### Security
- Updated Freemius SDK.

## 1.2.0 - 2022-01-15

### Added
- Integration with SkyVerge's WC Sequential Order Numbers Pro.

### Changed
- Better support for 3rd-party order types.
- Tweak: updated 'tested up to' headers.
- Dev: added more automated testing.
- Dev: updated DWS framework.

### Removed
- Freemius contact form functionality.

## 1.1.1 - 2021-11-23

### Changed
- Dev: hook generator helpers can now be called before initialization.
- Dev: updated DWS framework.

### Fixed
- Assets version.

## 1.1.0 - 2021-11-11

### Changed
- Changed order actions icons for less overlap with other plugins.
- Tweaked error messages for better end-user clarity.
- Dev: renamed some variables to better express 'shop_order' agnosticism.
- Dev: updated DWS framework.

## 1.0.0 - 2021-10-25

### Added
- First official release.

<!-- End changelog -->
