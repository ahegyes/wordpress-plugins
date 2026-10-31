# Security policy

## Reporting a vulnerability

Report it privately through [GitHub's security advisory form](https://github.com/ahegyes/wp-internal-comments/security/advisories/new), never in a public issue. The first response comes within 7 days.

## Scope

In scope: the plugin's PHP, JavaScript and CSS, its prefixing configuration (`scoper.inc.php`) and its CI workflows.

Out of scope, reported upstream instead:
- plugins generated from this repository, to their maintainers;
- the wp-framework packages, to `ahegyes/wordpress-framework`;
- WordPress, WooCommerce and the other Composer and npm dependencies, to their projects. Composer refuses to resolve a dependency version with a known advisory through `roave/security-advisories`. The Supply-Chain Audit workflow fails on an advisory anywhere in the Composer lock, and on a high-severity one among the npm runtime dependencies, of which the template has none; advisories in the npm development tools reach Dependabot alerts instead.
