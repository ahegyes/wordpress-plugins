# Changelog

All notable changes to this plugin are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add`. Aggregate into a release with `composer changelog:write`, which also copies the versions between the markers into `readme.txt`.

<!-- Start changelog -->

## 1.3.5 - 2022-07-13

### Changed
- Tested up to WordPress 6.0.1.
- Tested up to WooCommerce 6.7.0.
- Updated Freemius SDK.

## 1.3.4 - 2022-03-20

### Changed
- Tested up to WooCommerce 6.3.

### Security
- Updated Freemius SDK.

## 1.3.3 - 2022-02-08

### Changed
- Tested up to WordPress 5.9.
- Updated DWS framework.

## 1.3.2 - 2022-01-14

### Changed
- Tested up to WooCommerce 6.1.
- Tested up to PHP 8.1.
- Updated DWS framework.

### Fixed
- Handling of a framework initialization failure.

## 1.3.1 - 2021-12-02

### Changed
- Tested up to the latest version of WordPress.
- Optimized the PSR-4 autoloader.
- Hook tags can be generated before the plugin is initialized.
- Updated DWS framework.

## 1.3.0 - 2021-11-15

### Changed
- Tested up to the latest versions of WordPress and WooCommerce.
- Additional licensing terms for redistribution.
- Updated the dependencies' namespace.
- Updated DWS framework.

### Fixed
- Readme formatting.

## 1.2.1 - 2021-09-26

### Fixed
- Language domain inside the dependencies folder.
- PHP 8 error on the settings page.

## 1.2.0 - 2021-09-24

### Changed
- Tested up to the latest versions of WordPress and WooCommerce.
- Updated DWS framework.
- Added more automated tests.

## 1.1.0 - 2021-07-26

### Changed
- Tested up to the latest versions of WordPress and WooCommerce.
- Renamed the capabilities after WordPress core's.
- Moved the bootstrap functions to the global namespace.
- Updated DWS framework.

## 1.0.0 - 2021-05-29

### Added
- First official release.

<!-- End changelog -->
