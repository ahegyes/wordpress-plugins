# wc-linked-orders

A WordPress plugin built on the five wp-framework packages (`ahegyes/wp-framework-{bootstrap,shared,core,settings,woocommerce}`), from the template that starts every such plugin. It ships its own copy of the framework, PHP-DI and their dependencies, prefixed under `DeepWebSolutions\LinkedOrders\Scoped` by php-scoper through wordpress-configs into `vendor-prefixed/` on every development install. Its architecture follows the framework's conventions 1–10 in [wordpress-framework's AGENTS.md](https://github.com/ahegyes/wordpress-framework/blob/trunk/AGENTS.md); this file covers what is particular to the plugin. `docs/ARCHITECTURE.md` describes the boot, `docs/recipes.md` the common changes, `tests/README.md` the suites.

## Layout

```
linked-orders-for-woocommerce.php      the entry: bootstrap load.php, the updater, the floor check, safeguards, the build gate, the autoloader
functions.php                dws_lowc_plugin(), the boot on plugins_loaded 15, deactivation, the HPOS declaration (false), the includes/ loader
uninstall.php                the framework's uninstall engine over the footprint, with DWS_LOWC_OPTION_DELETE_DATA's consent
src/                         the composition root and its components only
├── Plugin.php               SLUG, PREFIX (dws_lowc), COMPONENTS, the logger, the scheduler, the migration runner and the kernel
├── Settings.php             the WooCommerce settings tab dws_lowc: the greeting's fields and the uninstall consent
├── Blocks.php               the blocks from blocks/build/blocks-manifest.php and the block-editor script
└── Feature/                 the greeting: a composite gated on DWS_LOWC_OPTION_FEATURE and its account-dashboard child
includes/                    hook-free code as dws_lowc_ functions and DWS_LOWC_ constants, loaded in name order, _-prefixed files skipped
├── safeguards.php           restrictions that hold when the build fails; loaded by the entry before the autoloader
├── options.php              the option keys, their defaults, the typed readers, the translated text and the validator
├── footprint.php            dws_lowc_get_footprint(), every kind of data the plugin stores
├── migrations.php           dws_lowc_get_migration_steps() and one function per step, append-only
├── assets.php               the *.asset.php reader and the script and style registrars
└── metadata.php             the slug, name and version getters, the plugin's surface for a peer plugin
blocks/src/, assets/src/     TypeScript and SCSS, built into blocks/build/ and assets/build/ (untracked)
models/, templates/          empty extension points
.github/scripts/fill-in-scaffold.sh  the fill-in's sweep, which fill-in-scaffold.yml runs once in a new plugin and Quality rehearses here
```

## Commands

```sh
composer packages-install    # dependencies, then php-scoper into vendor-prefixed/
composer test:unit
composer test:integration    # starts .wp-env.tests.json, then runs test:integration:run, which builds the scripts first
composer quality-check       # manifest and changelog validation, PHPCS (both profiles), PHPStan, Unit and Integration
npm run setup                # both installs, the scripts' build, then the development site
npm run build                # blocks and assets through the local wp-scripts; npm run start watches them in a POSIX shell
npm run lint:scripts         # ESLint, beside lint:styles (Stylelint) and lint:types (tsc)
npm run test:e2e             # Playwright on .wp-env.tests.json, starting it if needed; the scripts must be built
```

Run wp-scripts only through the npm scripts or `node_modules/.bin/wp-scripts`: `npx wp-scripts` can install an unrelated package of that name.

## Environments

- `.wp-env.json`: development, as `local`, on port 8822, behind `npm run setup` and `wp-env:*`. The framework rethrows boot and migration failures there.
- `.wp-env.tests.json`: Integration and end to end, as `production`, on port 8823, behind `wp-env:tests:*`, `composer test:integration` and `npm run test:e2e`. The end-to-end suite deactivates and reactivates WooCommerce there.

Both run PHP 8.5 with WooCommerce from `vendor/` and its order tables on; CI runs Integration and end to end on every WordPress branch from the entry's `Requires at least` up, and nightly on WordPress trunk.

## Conventions

1. **Placement.** `src/` holds only the composition root and its components. Code that registers no hooks, such as keys, readers, the footprint, migration steps and asset registrars, is `dws_lowc_` functions and `DWS_LOWC_` constants in `includes/`, which parses on PHP 7.4: no `mixed` or union types, typed constants, `match` or first-class callables there. Container definitions are composition and stay in `Plugin::get_definitions()`.
2. **Variants.** A component that exists only in one build variant is a child in a branch of its parent's `get_child_component_classes()`, never a second entry in `COMPONENTS`.
3. **Request context.** `is_admin()` is false on REST requests and true in admin-ajax ones, so an admin-only feature decides the request context in its callbacks, never in `should_load()`.
4. **Add-ons.** An add-on extends a base plugin only through the base plugin's documented hooks. It reads a template-built base plugin's slug, name and version only through that plugin's `{prefix}_get_plugin_*()` getters, from `plugins_loaded` on, since the add-on may load first, and behind `function_exists()`, since a base plugin below its floors or with a partial build may stay active without defining them.
5. **Request reads.** An integer or boolean is read as `filter_var( wp_unslash( $_POST['key'] ?? '' ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? $default` (`FILTER_VALIDATE_BOOL` for a boolean, where an absent key reads false, as an unchecked checkbox posts nothing), and a string through an `is_string()` guard and the field's sanitizer; never `filter_input()`, the `default` filter option or a cast on request input.
6. **Options and data.** Options are read only through `includes/options.php`'s typed readers, safeguards excepted. Everything stored lives under `dws_lowc_`, or `_dws_lowc_` for hidden meta. The footprint covers the prefix's options, transients and user meta; object meta needs a `meta_prefixes` entry per type, and anything else its own entry.
7. **Safeguards** live in `includes/safeguards.php`, call only WordPress and that file, and relax only through their own filter, through `dws_lowc_is_relaxed()` (`docs/recipes.md`).
8. **Migrations** are append-only: a step converges from any partial state, verifies its writes and throws when one fails.

## Style

- Every PHP file opens with `<?php declare( strict_types=1 );`, uses `array()`, `final` classes, `protected` over `private` and `#[\Override]` on overrides, and groups class members in `// region NAME` blocks outside `tests/`.
- Docblocks open with one third-person sentence naming the role, such as "Returns …", plus a second paragraph only for a non-obvious why; property and constant docblocks open with "The …", constructors read "Constructor.", overrides read `{@inheritDoc}`. Every other function docblock ends with `@return`, `@return  void` included; tag values align at column 10, each `@param` stays on one line, and `@throws` reads "Thrown when …". Every public `do_action()` and `apply_filters()` carries a hook docblock.
- A hook the plugin fires is named `dws_lowc/area/name`, with `/` between levels and `_` between words, as the framework names its own, and its last level says what kind of hook it is: a past participle for an event, `before_` or `after_` for a position, an imperative for a scheduled hook (`dws_lowc/records/prune`), a predicate (`is_`, `has_`, `can_`, `should_`) for a boolean filter (`dws_lowc/data_export/should_restrict`), and the noun of the value for any other filter, plural for a list.
- Inline comments state a non-obvious why in one capitalized sentence, and every file describes the present, never the change; a `phpcs:ignore` names its sniff and gives a reason.
- Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice.
- Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period. The body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

- The fill-in pushes with the workflow's own token, which GitHub refuses for workflow changes, so it never edits `.github/workflows/`; the workflows read the slug from `composer.json`'s `extra.text-domain`.
- Integration and end to end share the tests site, so a row that changes shared data in a separate process flushes the parent's object cache.
- The release zip carries the TypeScript and SCSS sources beside the built files, since a plugin's repository may be private and the zip is then the only place its users find them.
