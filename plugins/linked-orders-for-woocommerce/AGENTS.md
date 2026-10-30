# wc-linked-orders

Linked Orders for WooCommerce, the v2 port of the wordpress.org plugin of that name, built on the five wp-framework packages (`ahegyes/wp-framework-{bootstrap,shared,core,settings,woocommerce}`) from the plugin template. It ships its own copy of the framework, PHP-DI and their dependencies, prefixed under `DeepWebSolutions\LinkedOrders\Scoped` by php-scoper through wordpress-configs into `vendor-prefixed/` on every development install. Its architecture follows the framework's conventions 1–10 in [wordpress-framework's AGENTS.md](https://github.com/ahegyes/wordpress-framework/blob/trunk/AGENTS.md); this file covers what is particular to the plugin. `docs/ARCHITECTURE.md` describes the boot and the data, `tests/README.md` the suites, and the template's [recipes](https://github.com/ahegyes/wordpress-plugin-template/blob/trunk/docs/recipes.md) the common changes, under the template's `dws_tpl` prefix.

v1 sites upgrade in place, so the port keeps v1's option keys, order meta, capabilities, settings section, admin-post action and helper API, and fires each v1 hook, deprecated, before its v2 name.

## Layout

```
linked-orders-for-woocommerce.php      the entry: bootstrap load.php, the inert updater, the floor check, safeguards, the build gate, the autoloader
functions.php                dws_lowc_plugin(), the boot on plugins_loaded 15, deactivation, the order-tables declaration, v1's boot hooks, the includes/ loader
uninstall.php                the framework's uninstall engine over the footprint, with v1's DWS_LOWC_OPTION_DELETE_DATA consent
src/                         the composition root and its components only
├── Plugin.php               SLUG, PREFIX (dws_lowc), COMPONENTS, the logger, the scheduler, the migration runner and the kernel
├── Settings.php             the dws-linked-orders section on WooCommerce's Advanced tab, v1's two groups, and the plugin row's links
├── ChildCreation.php        v1's admin_post_dws_lowc_create_linked_child action, with one-shot notices
├── MetaBox.php              the parent, children and add-child button beside an order's editor, on either storage
├── ListTable.php            the orders list's depth column, filters and row actions, on either storage
├── Autocompletion.php       completes an order's descendants when it completes, where the setting is on
└── Integration/             SequentialOrderNumbersPro, gated on wc_seq_order_number_pro()
models/DWS_Order_Node.php    v1's global node class on the classmap, reading and saving the _dws_lo_ order meta
includes/                    hook-free code as dws_lowc_ functions and DWS_LOWC_ constants, loaded in name order, _-prefixed files skipped
├── safeguards.php           restrictions that hold when the build fails; loaded by the entry before the autoloader
├── capabilities.php         v1's four capabilities and the roles CapabilityGrants gives them
├── options.php              v1's option keys, their defaults and the depth validator
├── settings.php             v1's settings API: the raw, validated and validating reads, by v1 field id and group
├── post-type.php            v1's order-type API and dws_lowc_create_linked_child()
├── linking.php              v1's linking API: nodes, links, roots and trees
├── deprecated.php           v1's root getters, hook-tag builders, constants and boot-hook relays, deprecated
├── footprint.php            dws_lowc_get_footprint(), including what v1 stored under its own names
├── migrations.php           dws_lowc_get_migration_steps() and one function per step, append-only
├── assets.php               the *.asset.php reader and the script and style registrars
└── metadata.php             the slug, name and version getters, the plugin's surface for a peer plugin
assets/src/                  the orders list's TypeScript and SCSS, built into assets/build/ (untracked)
changelog/                   the pending entries changelog:write turns into CHANGELOG.md and readme.txt's changelog
```

## Commands

```sh
composer packages-install    # dependencies, then php-scoper into vendor-prefixed/
composer test:unit
composer test:integration    # starts .wp-env.tests.json, then runs test:integration:run, which builds the scripts first
composer quality-check       # manifest and changelog validation, PHPCS (both profiles), PHPStan, Unit and Integration
composer changelog:add       # stages an entry in changelog/; composer changelog:write, at release, also syncs readme.txt
npm run setup                # both installs, the scripts' build, then the development site
npm run build                # the orders list's assets through the local wp-scripts; npm run start watches them in a POSIX shell
npm run lint:scripts         # ESLint, beside lint:styles (Stylelint) and lint:types (tsc)
npm run test:e2e             # Playwright on .wp-env.tests.json, starting it if needed; the scripts must be built
```

Run wp-scripts only through the npm scripts or `node_modules/.bin/wp-scripts`: `npx wp-scripts` can install an unrelated package of that name.

## Environments

- `.wp-env.json`: development, as `local`, on port 8822, behind `npm run setup` and `wp-env:*`. The framework rethrows boot and migration failures there.
- `.wp-env.tests.json`: Integration and end to end, as `production`, on port 8823, behind `wp-env:tests:*`, `composer test:integration` and `npm run test:e2e`. The end-to-end suite deactivates and reactivates WooCommerce there.

Both run PHP 8.5 with WooCommerce from `vendor/` and its order tables on; CI runs Integration and end to end on every WordPress branch from the entry's `Requires at least` up, and nightly on WordPress trunk.

## Conventions

1. **Placement.** `src/` holds only the composition root and its components. Code that registers no hooks, such as keys, readers, v1's helper API, the footprint, migration steps and asset registrars, is `dws_lowc_` functions and `DWS_LOWC_` constants in `includes/`, which parses on PHP 7.4: no `mixed` or union types, typed constants, `match`, nullsafe calls or first-class callables there. `DWS_Order_Node` keeps v1's global name in `models/`, outside that limit. Container definitions are composition and stay in `Plugin::get_definitions()`.
2. **Variants.** A component that exists only in one build variant is a child in a branch of its parent's `get_child_component_classes()`, never a second entry in `COMPONENTS`.
3. **Request context.** `is_admin()` is false on REST requests and true in admin-ajax ones, so an admin-only feature decides the request context in its callbacks, never in `should_load()`.
4. **Add-ons.** An add-on extends a base plugin only through the base plugin's documented hooks. It reads a template-built base plugin's slug, name and version only through that plugin's `{prefix}_get_plugin_*()` getters, from `plugins_loaded` on, since the add-on may load first, and behind `function_exists()`, since a base plugin below its floors or with a partial build may stay active without defining them.
5. **Request reads.** An integer or boolean is read as `filter_var( wp_unslash( $_POST['key'] ?? '' ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? $default` (`FILTER_VALIDATE_BOOL` for a boolean, where an absent key reads false, as an unchecked checkbox posts nothing), and a string through an `is_string()` guard and the field's sanitizer; never `filter_input()`, the `default` filter option or a cast on request input.
6. **Options and data.** A setting is read through v1's API in `includes/settings.php`, whose validation is each setting's one typed read and whose reads apply v1's filters, safeguards excepted. v1's settings keep their `dws-lowc_` keys and its order links their `_dws_lo_` meta; anything new lives under `dws_lowc_`, or `_dws_lowc_` for hidden meta. The footprint covers those prefixes and v1's other rows by name.
7. **Order storage.** Order code goes through WC CRUD, `get_edit_order_url()` and WooCommerce's screen ids and list hooks, never the posts table, and every Integration row that touches orders runs on all four storages through `OrderStorage`: posts and the order tables, each with the sync on and off.
8. **v1 hooks.** A hook v1 fired keeps its v1 literal, fired first through `apply_filters_deprecated()` or `do_action_deprecated()` with `'2.0.0'` and the v2 name, then the v2 name, and `DeprecatedHooksTest` names the pair.
9. **Safeguards** live in `includes/safeguards.php`, call only WordPress and that file, and relax only through their own filter, through `dws_lowc_is_relaxed()` (`docs/recipes.md`).
10. **Migrations** are append-only: a step converges from any partial state, verifies its writes and throws when one fails.

## Style

- Every PHP file opens with `<?php declare( strict_types=1 );`, uses `array()`, `final` classes, `protected` over `private` and `#[\Override]` on overrides, and groups class members in `// region NAME` blocks outside `tests/`.
- Docblocks open with one third-person sentence naming the role, such as "Returns …", plus a second paragraph only for a non-obvious why; property and constant docblocks open with "The …", constructors read "Constructor.", overrides read `{@inheritDoc}`. Every other function docblock ends with `@return`, `@return  void` included; tag values align at column 10, each `@param` stays on one line, and `@throws` reads "Thrown when …". Every public `do_action()` and `apply_filters()` carries a hook docblock.
- A hook the plugin fires is named `dws_lowc/area/name`, with `/` between levels and `_` between words, as the framework names its own, and its last level says what kind of hook it is: a past participle for an event (`dws_lowc/linked_order/created`), `before_` or `after_` for a position (`dws_lowc/meta_box/after_add_new_child_button`), an imperative for a scheduled hook, a predicate (`is_`, `has_`, `can_`, `should_`) for a boolean filter (`dws_lowc/node/can_create_child`), and the noun of the value for any other filter, plural for a list (`dws_lowc/post_type/supported_order_types`).
- An element v1 had keeps v1's `@since`, and its `@version` names the release that last changed it.
- Inline comments state a non-obvious why in one capitalized sentence, and every file describes the present, never the change; a `phpcs:ignore` names its sniff and gives a reason.
- Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice.
- Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period. The body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

- Integration and end to end share the tests site, so a row that changes shared data in a separate process flushes the parent's object cache.
- The end-to-end suite runs on the order tables only, as the tests site stores orders; the Integration rows cover the posts storage.
- The release zip carries the TypeScript and SCSS sources beside the built files.
- The bundled GitHub updater ships inert, since the entry file has no `Update URI`, and wordpress.org's reviewers may ask to remove it.
