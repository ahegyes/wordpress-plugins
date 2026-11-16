# wc-linked-orders

Linked Orders for WooCommerce, the v2 port of the wordpress.org plugin of that name, built on the five wp-framework packages (`ahegyes/wp-framework-{bootstrap,shared,core,settings,woocommerce}`) from the plugin template. It ships its own copy of the framework, PHP-DI and their dependencies, prefixed under `DeepWebSolutions\LinkedOrders\Scoped` by php-scoper through wordpress-configs into `vendor-prefixed/` on every development install. Its architecture follows the framework's conventions 1–10 in [wordpress-framework's AGENTS.md](https://github.com/ahegyes/wordpress-framework/blob/trunk/AGENTS.md); this file covers what is particular to the plugin. `docs/ARCHITECTURE.md` describes the boot and the data, `tests/README.md` the suites, and the template's [recipes](https://github.com/ahegyes/wordpress-plugin-template/blob/trunk/docs/recipes.md) the common changes, under the template's `dws_tpl` prefix.

v1 sites upgrade in place, so the port keeps v1's option keys, order meta, capabilities, settings section, admin-post action and helper API, and fires each v1 hook whose name changed, deprecated, before its v2 name.

## Layout

```
linked-orders-for-woocommerce.php      the entry: the @api DWS_LOWC_FILE, the safeguards, then, in one try, bootstrap load.php, the inert updater, the floor check, the build gate, the autoloader and functions.php, whose catch shows the build notice
Safeguards.php               an empty slot for restrictions that hold whenever the plugin cannot boot, which the entry requires before the floor check
functions.php                the @api dws_lowc_plugin(), the includes/ loader, then the boot on plugins_loaded 15, the order-tables declaration, v1's boot hooks and deactivation, as static closures
uninstall.php                the framework's uninstall engine over Plugin::FOOTPRINT, with v1's Options::DELETE_DATA consent
src/                         the plugin's classes, each named for its concept
├── Plugin.php               SLUG, PREFIX (dws_lowc), COMPONENTS, FOOTPRINT, the identity, the logger, the scheduler, the migration runner and the kernel
├── Options.php              v1's option keys, their defaults in stored form and typed readers, which pass v1's validated read, v1's settings map and its lookup
├── Capabilities.php         v1's four capabilities and the roles CapabilityGrants gives them
├── Migrations.php           get_steps() over the protected steps, append-only
├── LinkRepair.php           the batched step that repairs v1's links
├── Linking.php              links and unlinks under the linking lock, firing link/created and link/removed: the link meta keys, rerooting, depths, the queries by link and the maximum depth
├── OrderTypes.php           the order types' names, add-child phrase and admin lists
├── Settings.php             the dws-linked-orders section on WooCommerce's Advanced tab, v1's two groups from Options' map, and the plugin row's links
├── ChildCreation.php        v1's admin_post_dws_lowc_create_linked_child action, its URL and refusal, with one-shot notices
├── MetaBox.php              the parent, children and add-child button beside an order's editor, on either storage
├── ListTable.php            the orders list's depth column, filters and row actions, on either storage
├── Autocompletion.php       completes an order's descendants when it completes, where the setting is on
├── OrderDeletion.php        makes the children of an order deleted for good the roots of their trees
└── Integration/             SequentialOrderNumbersPro, gated on wc_seq_order_number_pro()
models/DWS_Order_Node.php    v1's global node class on the classmap, reading the _dws_lo_ order meta, with v1's setters deprecated
includes/                    v1's @api functions and constants, grouped by topic, loaded in name order, _-prefixed files skipped
├── settings.php             v1's deprecated settings API: the raw, validated and validating reads, by v1 field id and group
├── post-type.php            v1's order-type API and dws_lowc_create_linked_child()
├── linking.php              the linking API: nodes, links and unlinks, roots and trees
├── deprecated.php           v1's root getters, hook-tag builders and constants, deprecated
└── metadata.php             the slug, name and version getters, the plugin's surface for a peer plugin
assets/src/                  the orders list's TypeScript and SCSS, built into assets/build/ (untracked)
changelog/                   the pending entries changelog:write turns into CHANGELOG.md and readme.txt's changelog
```

## Commands

```sh
composer packages-install    # dependencies, then php-scoper into vendor-prefixed/
composer test:unit
composer test:integration    # starts .wp-env.tests.json, then runs test:integration:run, which builds the scripts first
composer test:integration:multisite  # starts .wp-env.multisite.json, then runs the Integration suite's multisite group, which the default run skips
composer quality-check       # manifest and changelog validation, PHPCS (both profiles), PHPStan (both profiles), Unit and Integration
composer changelog:add       # stages an entry in changelog/, which the release step in the monorepo's CONTRIBUTING.md writes
npm run setup                # both installs, the scripts' build, then the development site
npm run build                # the orders list's assets through the local wp-scripts; npm run start watches them
npm run lint:scripts         # ESLint, beside lint:styles (Stylelint) and lint:types (tsc)
npm run test:e2e             # Playwright on .wp-env.tests.json, starting it if needed; the scripts must be built
```

Run wp-scripts only through the npm scripts or `node_modules/.bin/wp-scripts`: `npx wp-scripts` can install an unrelated package of that name.

## Environments

- `.wp-env.json`: development, as `local`, on port 8822, behind `npm run setup` and `wp-env:*`. The framework rethrows boot and migration failures there.
- `.wp-env.tests.json`: Integration and end to end, as `production`, on port 8823, behind `wp-env:tests:*`, `composer test:integration` and `npm run test:e2e`. The end-to-end suite deactivates and reactivates WooCommerce there.
- `.wp-env.multisite.json`: a network with WooCommerce and the plugin network-activated, as `production`, on port 8828, behind `wp-env:multisite:*` and `composer test:integration:multisite`, for the Integration suite's `multisite` group.

Each runs PHP 8.5 with WooCommerce from `vendor/` and its order tables on; CI runs Integration and end to end on every WordPress branch from the entry's `Requires at least` up, and nightly on WordPress trunk.

## Conventions

1. **Placement and surface.** The global namespace holds only the plugin's contract, each global `@api`, which `GlobalsTest` checks, and tested: the entry's `DWS_LOWC_FILE`, the functions and constants `functions.php` and `includes/` declare, among them v1's helper API and the accessor, whose composition root is internal, and `DWS_Order_Node`, which keeps v1's global name in `models/`; `functions.php` registers its hooks as static closures. The globals load before the boot, so they stay defined whether or not it succeeds, and until it has, the `src/` code an `@api` function or the model calls reaches nothing the boot checks, such as WooCommerce, and does something neutral in its place: `OrderTypes` reads orders and their statuses only once the plugin has booted. Integrators touch only those globals, the plugin's hooks and WordPress's and WooCommerce's APIs on the contract's literal names, never `src/` or `Safeguards.php`. A symbol enters without a named caller when it exposes a noun or verb the plugin's screens give an admin, a decision the plugin makes, an event when it changes its data or the inverse of an operation it exposes; when the literal's direct use would need something the plugin enforces, such as a lock, a per-site key, a list's read-modify-write or paired keys; when the plugin's own code runs the same implementation, an exception taking the owner's ruling; and when its signature holds only scalars, WordPress and WooCommerce types and the plugin's models. One model serves each noun, a new capability of a concept being a method on its model; an open set of interchangeable behaviours is a `models/` interface behind register, get-one and get-all functions, never a function per variant; a setting gets no reader, and the decision it feeds is exposed instead; each state change gets an action; and nothing lock-bound or php-scoper-typed enters. Every other symbol is a member of a final class in `src/` named for its concept, `protected` wherever only its class uses it: the composition root, which holds the footprint, a component, `Options`, `Migrations` or another class that registers no hooks, such as `Linking`. No namespace or folder is named "Internal". Container definitions are composition and stay in `Plugin::get_definitions()`; a component takes a service such as `Assets` by constructor.
2. **Request context.** `is_admin()` is false on REST requests and true in admin-ajax ones, so an admin-only feature decides the request context in its callbacks, never in `should_load()`.
3. **Add-ons.** An add-on extends a base plugin only through the base plugin's contract. It reads a template-built base plugin's slug, name and version through that plugin's `{prefix}_get_plugin_*()` getters, asks its decision functions, such as this plugin's `dws_lowc_is_supported_order()`, and otherwise reads its options through `get_option()` on their contract names. It calls the base plugin's functions no earlier than `plugins_loaded`, or than the hook a function's docblock names, since the add-on may load first, and behind `function_exists()`, since a base plugin below its floors or with a partial build may stay active without defining them, and registers its extensions on the base plugin's `{root}/boot/succeeded`, which fires only once that plugin has booted, adding that callback before `plugins_loaded` reaches priority 15, where the base plugin boots, such as from its `functions.php`.
4. **Request reads.** An integer or boolean is read as `filter_var( wp_unslash( $_POST['key'] ?? '' ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? $default` (`FILTER_VALIDATE_BOOL` for a boolean, where an absent key reads false, as an unchecked checkbox posts nothing), and a string through an `is_string()` guard and the field's sanitizer; never `filter_input()`, the `default` filter option or a cast on request input.
5. **Options and data.** Each setting is one autoloaded option, its key on `Options` and its default in stored form in `DEFAULTS`, which the first migration seeds, a release that adds settings seeds again, and its field reads. Outside the safeguards and the migration steps, settings are read only through `Options`' typed readers, which pass a setting v1 had through v1's validated read, so v1's filters keep applying; v1's API in `includes/settings.php` is deprecated. v1's settings keep their `dws-lowc_` keys, mapped from v1's group and field ids by `Options::V1_SETTINGS`, and its order links their `_dws_lo_` meta; anything new lives under `dws_lowc_`, or `_dws_lowc_` for hidden meta. The footprint covers those prefixes and v1's other rows by name.
6. **Order storage.** Order code goes through WC CRUD, `get_edit_order_url()` and WooCommerce's screen ids and list hooks, never the posts table, and every Integration row whose outcome the order storage can change runs on all four storages through `OrderStorageTrait`: posts and the order tables, each with the sync on and off. Rows that check only the order of hooks, or a stand-in plugin, use one.
7. **v1 hooks.** A hook v1 fired keeps its v1 literal. Where its v2 name differs, the literal fires first through `apply_filters_deprecated()` or `do_action_deprecated()` with `'2.0.0'` and the v2 name, then the v2 name, and `DeprecatedHooksTest` names the pair; the six whose v2 name is v1's fire once, as `DeprecatedHooksTest::shared_hooks()` lists them. v1's settings value filters have no v2 name: they name the decision the setting feeds, `node/can_create_child` for the maximum depth, or the option's own `option_{$option}` filter.
8. **Safeguards.** `Safeguards.php` holds none yet. A safeguard added there follows the template's safeguard recipe: `register()` hooks it as a static closure, which no code can remove by name, and the entry requires the class before the floor check, so it parses on PHP 7.4 and calls only what its hook hands it, WordPress APIs present at the oldest WordPress the bootstrap package runs on and WooCommerce APIs present since 3.6, guarding anything newer, which `phpstan.safeguards.neon` checks for WordPress. A safeguard that calls WooCommerce is hooked from one closure that `register()` runs on `woocommerce_loaded`, and it relaxes only through its own filter, with an explicit `false`, after `linked_orders_for_woocommerce/boot/succeeded`.
9. **Migrations** are append-only: a step converges from any partial state, verifies its writes and throws when one fails. A step names each stored name it touches, such as an option or meta key, and each default as a literal and reads stored rows directly, never through `Options`, `DWS_Order_Node` or a constant another class declares, so a later rename leaves what it does as it shipped; the seeding step alone reads `Options::DEFAULTS`.

## Contract

The plugin's contract is the elements tagged `@api`, the hooks it fires and the names it stores, checks or documents: its option, meta and capability names, the admin-post action and nonce that create a linked child, the `DWS_LOWC_DEBUG` constant a site defines, and the `linked_orders_for_woocommerce_add_new_child` label an order type sets for it. Each stored or checked name is written whole as a constant on the `src/` class that owns it, never as a global constant or an enum, and its literal is the contract. Breaking the contract ships as the next major. Every other function, constant and class, `src/` included, is internal and changes in any release.

## Style

- Every PHP file opens with `<?php declare( strict_types=1 );`, uses `array()`, `final` classes, `protected` over `private` and `#[\Override]` on overrides, and groups class members in `// region NAME` blocks outside `tests/`.
- Docblocks open with one third-person sentence naming the role, such as "Returns …", plus a second paragraph only for a non-obvious why; property and constant docblocks open with "The …", constructors read "Constructor.", overrides read `{@inheritDoc}`. Every other function docblock ends with `@return`, `@return  void` included; tag values align at column 10, each `@param` stays on one line, and `@throws` reads "Thrown when …". Every public `do_action()` and `apply_filters()` carries a hook docblock.
- A hook the plugin fires is named `linked_orders_for_woocommerce/area/name`, with `/` between levels and `_` between words, as the framework names its own, and its last level says what kind of hook it is: a past participle for an event (`linked_orders_for_woocommerce/linked_order/created`), `before_` or `after_` for a position (`linked_orders_for_woocommerce/meta_box/after_add_new_child_button`), an imperative for a scheduled hook, a predicate (`is_`, `has_`, `can_`, `should_`) for a boolean filter (`linked_orders_for_woocommerce/node/can_create_child`), and the noun of the value for any other filter, plural for a list (`linked_orders_for_woocommerce/post_type/supported_order_types`).
- An element v1 had keeps v1's `@since`, and its `@version` names the release that last changed it.
- Inline comments state a non-obvious why in one capitalized sentence, and every file describes the present, never the change; a `phpcs:ignore` names its sniff and gives a reason.
- Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice.
- Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period. The body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

- Integration and end to end share the tests site, so a row that changes shared data in a separate process flushes the parent's object cache.
- The end-to-end suite runs on the order tables only, as the tests site stores orders; the Integration rows cover the posts storage.
- The release zip carries the TypeScript and SCSS sources beside the built files.
- The bundled GitHub updater ships inert, since the entry file has no `Update URI`, and wordpress.org's reviewers may ask to remove it.
