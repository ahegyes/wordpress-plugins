# Security policy

## Reporting a vulnerability

Report privately via [GitHub's security advisory form](https://github.com/ahegyes/wordpress-plugins/security/advisories/new). Do not open a public issue. Initial response within 7 days.

## Scope

In scope:
- All plugins under `plugins/<name>/` (Internal Comments, Linked Orders for WooCommerce, Locked Payment Methods for WooCommerce).
- The shared scoping pipeline (per-plugin `scoper.inc.php`), monorepo CI workflows.

Out of scope:
- WordPress core, WooCommerce, or upstream Composer / npm dependencies — report upstream. Each plugin's `roave/security-advisories` transitive constraint already fails `composer install --dev` on any known CVE in the dep graph.

## Per-plugin advisories

Each plugin's mirror repo (e.g. `wp-internal-comments`) accepts security advisories too — both routes reach the same maintainer. Reporting via this monorepo is preferred for broader-scope issues; the mirror is fine for plugin-specific CVEs.
