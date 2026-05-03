# Changelog

All notable changes to this plugin are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add`. Aggregate into a release with `composer changelog:write`, which also syncs `readme.txt`.

<!-- Start changelog -->

## 2.0.0 - unreleased

This is the v2 successor to the v1 plugin published under the same wp.org slug. v1's public contract — hook names, option keys, order meta keys, capability names, REST routes — is preserved unless a specific item is listed under "Changed" or "Removed" below. v1 installations upgrading to v2 should continue to work without configuration changes.

### Changed
- Internal architecture rewritten on the DWS v2 framework (composition-based components, PSR-11 container, no service-handler/inheritance hierarchies). v1 PHP namespaces (`DeepWebSolutions\WC_Plugins\LinkedOrders\…`) are dropped — the new namespace is `DeepWebSolutions\LinkedOrders\`. PHP code that imported v1 internals must update; sites that only consumed hooks/options/order-meta are unaffected.
- Minimum PHP raised from 7.4 → 8.5.
- Minimum WordPress raised from 6.0 → 7.0.
- Minimum WooCommerce raised — see plugin header for current floor; HPOS-compatible.

<!-- End changelog -->
