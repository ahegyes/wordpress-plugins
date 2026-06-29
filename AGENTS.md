# wordpress-plugins

DWS v2 plugins monorepo. 3 plugins in `plugins/`, split-published to per-plugin mirrors via `.github/workflows/split-plugins.yml` (splitsh-lite v1.0.1).

## Plugins

| Slug                                       | Mirror                       | wp-env port | Status |
|--------------------------------------------|------------------------------|-------------|--------|
| `internal-comments`                        | `wp-internal-comments`       | 8821        | shell  |
| `linked-orders-for-woocommerce`            | `wc-linked-orders`           | 8822        | shell  |
| `locked-payment-methods-for-woocommerce`   | `wc-locked-payment-methods`  | 8823        | shell  |

Monorepo cross-plugin wp-env runs on **port 8820**. Workspace port scheme: 880X = framework, 881X = plugin templates, 882X = real plugins (8820 = monorepo-shared, 8821-8823 = per-plugin). Reserve 8888 + 8889 (wp-env defaults).

## Per-plugin self-containment

Each plugin under `plugins/<slug>/` is a fully runnable WordPress plugin:

- Own `composer.json` with own scoping prefix (`DeepWebSolutions\<NS>\Scoped\`)
- Own `package.json` for plugin-isolated wp-env + Playwright
- Own `.wp-env.tests.json` on a plugin-specific port
- Own `phpcs.dist.xml`, `phpstan.dist.neon`, `phpunit.dist.xml`, `scoper.inc.php`
- Own `CHANGELOG.md`, `readme.txt`, `changelog/` fragments

After splitsh-lite split, the mirror tree is identical to `plugins/<slug>/` — no monorepo files leak into mirrors.

## Monorepo root

Root files are NOT included in mirror splits. They orchestrate cross-plugin work:

- `composer.json` — `plugins:install`, `plugins:lint`, `plugins:quality-check` etc. (delegate to per-plugin via `--working-dir`)
- `package.json` — single Node setup with cross-plugin wp-env + Playwright
- `playwright.config.js` — points at `tests/e2e/` (cross-plugin specs)
- `.wp-env.tests.json` — port 8820, mounts all 3 plugins + WooCommerce, `testsEnvironment: false`
- `tests/Fixtures/multi-plugin-smoke/` — fixture mounted into wp-env for cross-plugin smoke
- `changelog/` — monorepo-level changelog fragments (scaffold/workflow changes only; per-plugin changes go in `plugins/<X>/changelog/`)

## Test modes — shared env vs fan-out

Two distinct testing axes at root, both intentional:

| Mode | Entry point | wp-env | Use case |
|---|---|---|---|
| **Cross-plugin shared** | `npm run test:integration` / `npm run test:e2e` | Root `.wp-env.tests.json` (port 8820, all 3 + WC) | "Do these plugins coexist? Is there cross-plugin pollution?" |
| **Per-plugin isolated fan-out** | `composer plugins:test:integration` / `npm run plugins:test:e2e` | Each plugin's own `.wp-env.tests.json` (ports 8821/8822/8823) | "Do each plugin's tests still pass standalone?" — same as what split-published mirrors run in CI |

Composer is canonical entry for `test:integration` (PHPUnit is PHP); npm is canonical entry for `test:e2e` (Playwright is npm). Per-plugin mirrors this asymmetry — root respects it.

## v1 successor policy

The 3 plugins are v2 successors to existing v1 plugins published under the same wp.org slugs. Initial monorepo release is **2.0.0**, not 1.0.0.

When migrating v1 features into `plugins/<slug>/src/`:
- **Preserve v1's public contract by default** — hook names (`dws_…` prefix), option keys, capability names, REST routes, shortcodes, comment-type slugs, order-meta keys, settings page slugs, gateway-locking rule formats. Existing v1 installations must keep working without config changes.
- **PHP namespaces are not part of the public contract.** v1 used `DeepWebSolutions\WC_Plugins\<Plugin>\…` (or `DeepWebSolutions\Plugins\<Plugin>\…`). v2 uses the flat `DeepWebSolutions\<Plugin>\…`. Sites that imported v1 internals must update; this is documented in each plugin's `Upgrade Notice` section.
- **PHP 8.5+ / WP 7.0+ / current WC (HPOS) are mandatory floors** — these are documented as deliberate breaks in each plugin's `## 2.0.0 - unreleased` Changed block.
- **If a public-contract break is unavoidable**, document it in the per-plugin CHANGELOG `### Removed` or `### Changed` block AND in the `Upgrade Notice` section of `readme.txt` so wp.org auto-update users see the warning.

## Substitution map (when adding a new plugin)

Copy `wordpress-plugin-template` into `plugins/<slug>/`, strip duplicated root files (`.editorconfig`, `LICENSE`, `.gitignore`, `.github/`, `README.md`, `CONTRIBUTING.md`, `SECURITY.md`, `CLAUDE.md`, `AGENTS.md` — those live at monorepo root only), then sed-substitute:

- `dws-plugin-template` → `<slug>`
- `DWS_PLUGIN_TEMPLATE` → `DWS_<ABBR>` (e.g., `DWS_IC`, `DWS_LOWC`, `DWS_LPMWC` — WC plugins suffix `WC`)
- `dws_plugin_template_` → `dws_<abbr>_` (lowercase)
- `PluginTemplate` → `<NS>` (e.g., `InternalComments`, `LinkedOrders`, `LockedPaymentMethods`)
- `DWS Plugin Template` → display name (WC plugins: `X for WooCommerce`)
- `wordpress-plugin-template` → mirror repo (e.g., `wp-internal-comments`)
- port `8811` → assigned port (882X)
- version `2.0.0` → starting version for the new plugin (entry header `Version`, `_VERSION` constant, readme.txt `Stable tag`, all `@since`/`@version`). For brand-new plugins start at `1.0.0`. For v1 successors that ship under an existing wp.org slug, start at `2.0.0` (or the next major after v1's last release).

Restore per-plugin `package.json` + `playwright.config.js` from template after strip (so split-published mirrors are runnable standalone).

## Cache layout

Per-plugin: `plugins/<slug>/tests/.cache/{phpunit,artifacts}/`.
Root: `tests/.cache/artifacts/` (cross-plugin Playwright outputs).

`.gitignore` ignores both via `plugins/*/tests/.cache` and `tests/.cache`.

## Deferred

- **`release.yml` per-plugin** — wp.org publish triggered by tag on each mirror (lives in plugin template's deferred set, also applies here).
- **Monorepo `tests/Fixtures/multi-plugin-smoke/`** — actual smoke-fixture content beyond the `.gitkeep` placeholder.
