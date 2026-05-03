# Changelog

Monorepo-level changes (scaffold, workflows, shared tooling). Per-plugin changelogs live under `plugins/<name>/CHANGELOG.md`.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add`. Aggregate into a release with `composer changelog:write`.

<!-- Start changelog -->

## 1.0.0 - unreleased

### Added
- Initial monorepo scaffold for `internal-comments`, `linked-orders-for-woocommerce`, `locked-payment-methods-for-woocommerce`.
- Cross-plugin wp-env config on port 8820, mounting all 3 plugins + WooCommerce.
- splitsh-lite split-publish workflow for per-plugin GitHub mirrors.

<!-- End changelog -->
