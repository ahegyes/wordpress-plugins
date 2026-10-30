# Changelog

All notable changes to this plugin are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add`. Aggregate into a release with `composer changelog:write`, which also copies the versions between the markers into `readme.txt`.

<!-- Start changelog -->

## 1.2.4 - 2022-07-17

### Changed
- Tested up to WordPress 6.0.1.
- Updated Freemius SDK.

## 1.2.3 - 2022-03-08

### Fixed
- Notice on inline-save AJAX calls.

### Security
- Updated Freemius SDK.

### Changed
- Updated DWS framework.

## 1.2.2 - 2022-02-08

### Changed
- Tested up to WordPress 5.9.
- Updated DWS framework.

## 1.2.1 - 2022-01-14

### Changed
- Tested up to PHP 8.1.
- Updated DWS framework.

## 1.2.0 - 2021-12-10

### Fixed
- Readme.

### Changed
- Moved most HTML to dedicated files.
- Updated DWS framework.
- Added more automated tests.

## 1.1.2 - 2021-09-27

### Fixed
- Meta box output on every post type when no post types were selected on the settings page.
- Readme formatting for WordPress.org.

## 1.1.1 - 2021-09-26

### Fixed
- Fatal error on cron-like requests.
- Language domain inside the dependencies folder.

### Changed
- Tested with the latest version of WordPress.
- Scripts and styles are only enqueued on relevant admin pages.
- Rebuilt the entire settings page. Please re-save your settings after updating.
- Updated DWS framework.

## 1.1.0 - 2021-09-26

### Changed
- Released together with 1.1.1, whose entries cover both.

## 1.0.0 - 2021-07-27

### Added
- First official release.

<!-- End changelog -->
