# wc-locked-payment-methods

Locked Payment Methods for WooCommerce, the v2 port of the wordpress.org plugin of that name, built on the five wp-framework packages (`ahegyes/wp-framework-{bootstrap,shared,core,settings,woocommerce}`) from the plugin template. It ships its own copy of the framework, PHP-DI and their dependencies, prefixed under `DeepWebSolutions\LockedPaymentMethods\Scoped` by php-scoper through wordpress-configs into `vendor-prefixed/` on every development install. Its architecture follows the framework's conventions 1–10 in [wordpress-framework's AGENTS.md](https://github.com/ahegyes/wordpress-framework/blob/trunk/AGENTS.md); this file covers what is particular to the plugin. `docs/ARCHITECTURE.md` describes the boot and the data, `tests/README.md` the suites, and the template's [recipes](https://github.com/ahegyes/wordpress-plugin-template/blob/trunk/docs/recipes.md) the common changes, under the template's `dws_tpl` prefix.

v1 sites upgrade in place: the port moves v1's settings and grants under `dws_lpmwc_` through migration steps, keeps v1's capabilities, settings section id and helper API, and fires each v1 hook, deprecated, where v1 fired it. v1's premium build's strategies are part of the plugin.

## Layout

```
locked-payment-methods-for-woocommerce.php   the entry: safeguards, bootstrap load.php, the inert updater, the floor check, the build gate, the autoloader
functions.php                dws_lpmwc_plugin(), the boot on plugins_loaded 15, deactivation, the order-tables declaration, v1's boot hooks, the includes/ loader
uninstall.php                the framework's uninstall engine over the footprint, with DWS_LPMWC_OPTION_DELETE_DATA's consent
src/                         the composition root, its components and the plan tab host
├── Plugin.php               SLUG, PREFIX (dws_lpmwc), COMPONENTS, the logger, the scheduler, the migration runner and the kernel
├── Settings.php             the dws-locked-payment-methods section on WooCommerce's Advanced tab, v1's three groups, and the plugin row's links
├── UnlockStrategies/        one component per way of unlocking, each gated on its switch, answering should_lock and owning its screen
│   ├── UserRoleStrategy.php           every locked method for the full-access roles
│   ├── UserProfileStrategy.php        per-site user grants on the profile, the Users column and filter
│   ├── OrderStrategy.php              order grants in a box on the order screen, on either storage
│   ├── GroupsStrategy.php             group grants on Groups' own forms, gated on Groups too
│   └── WooCommerceMembershipsStrategy.php  plan grants in a tab of the plan data, gated on Memberships too
└── EditScreen/Hosts/MembershipPlanTab.php   an ObjectForm host for Memberships' plan data box
includes/                    hook-free code as dws_lpmwc_ functions and DWS_LPMWC_ constants, loaded in name order, _-prefixed files skipped
├── safeguards.php           the lock, its v1 filters and dws_lpmwc_should_lock(); loaded by the entry before the floor check
├── capabilities.php         v1's four capabilities and the roles CapabilityGrants gives them
├── grants.php               the grants' keys and readers, the grants fields' choices and dws_lpmwc_is_payment_method_locked()
├── options.php              the settings' keys, defaults and typed readers, and v1's settings map
├── deprecated.php           v1's root getters, hook-tag builders, constants, boot-hook relays, access checks and setting readers, deprecated
├── footprint.php            dws_lpmwc_get_footprint(), including what v1 stored under its own names
├── migrations.php           dws_lpmwc_get_migration_steps() and one function per step, append-only
└── metadata.php             the slug, name and version getters, the plugin's surface for a peer plugin
changelog/                   the pending entries changelog:write turns into CHANGELOG.md and readme.txt's changelog
```

## Commands

```sh
composer packages-install    # dependencies, then php-scoper into vendor-prefixed/
composer test:unit
composer test:integration    # starts .wp-env.tests.json, then runs test:integration:run
composer quality-check       # manifest and changelog validation, PHPCS (both profiles), PHPStan (both profiles), Unit and Integration
composer changelog:add       # stages an entry in changelog/; composer changelog:write, at release, also syncs readme.txt
npm run setup                # both installs, then the development site
npm run lint:scripts         # ESLint over tests/e2e, beside lint:types (tsc); the plugin builds no scripts or styles
npm run test:e2e             # Playwright on .wp-env.tests.json, starting it if needed
```

Run wp-scripts only through the npm scripts or `node_modules/.bin/wp-scripts`: `npx wp-scripts` can install an unrelated package of that name.

## Environments

- `.wp-env.json`: development, as `local`, on port 8826, behind `npm run setup` and `wp-env:*`. The framework rethrows boot and migration failures there.
- `.wp-env.tests.json`: Integration and end to end, as `production`, on port 8827, behind `wp-env:tests:*`, `composer test:integration` and `npm run test:e2e`. The end-to-end suite deactivates and reactivates WooCommerce there.

Both run PHP 8.5 with WooCommerce and Groups from `vendor/` and WooCommerce's order tables on; CI runs Integration and end to end on every WordPress branch from the entry's `Requires at least` up, and nightly on WordPress trunk. The monorepo's shared environment runs the Integration suite beside Linked Orders and Internal Comments, without the `groups` and `wc-memberships` groups.

## Conventions

1. **Placement.** `src/` holds the composition root, its components and the plan tab host. Code that registers no hooks, such as keys, readers, v1's helper API, the footprint and migration steps, is `dws_lpmwc_` functions and `DWS_LPMWC_` constants in `includes/`, which parses on PHP 7.4: no `mixed` or union types, typed constants, `match`, nullsafe calls or first-class callables there. An include that reads `Plugin` while it loads must sort after `metadata.php`, so `capabilities.php` and `grants.php` name their keys as literals. Container definitions are composition and stay in `Plugin::get_definitions()`.
2. **Strategies.** Each way of unlocking is a component in `src/UnlockStrategies/`, gated on its switch, and an integration on its plugin too through a static `is_available()` that the settings read. It answers `dws_lpmwc/payment_method/should_lock` from the arguments alone, through an `@internal` static `unlocks()` that v1's per-strategy checks call, and returns `false` or the lock it was given. The safeguard passes the current user and, on an order's own payment page with its key, that order.
3. **Request context.** `is_admin()` is false on REST requests and true in admin-ajax ones, so an admin-only feature decides the request context in its callbacks, never in `should_load()`.
4. **Add-ons.** An add-on extends a base plugin only through the base plugin's documented hooks. It reads a template-built base plugin's slug, name and version only through that plugin's `{prefix}_get_plugin_*()` getters, from `plugins_loaded` on, since the add-on may load first, and behind `function_exists()`, since a base plugin below its floors or with a partial build may stay active without defining them.
5. **Request reads.** An integer or boolean is read as `filter_var( wp_unslash( $_POST['key'] ?? '' ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? $default` (`FILTER_VALIDATE_BOOL` for a boolean, where an absent key reads false, as an unchecked checkbox posts nothing), and a string through an `is_string()` guard and the field's sanitizer; never `filter_input()`, the `default` filter option or a cast on request input.
6. **Options and data.** The settings are read only through `includes/options.php`'s typed readers, safeguards excepted, and each subject's grants only through `includes/grants.php`'s readers. Everything stored lives under `dws_lpmwc_`, or `_dws_lpmwc_` for hidden meta; a user's grants are a user option under the site's table prefix, read from that key alone. The plugin's settings fire no settings hooks of their own, since WordPress's `option_{key}` reaches every read; v1's value filters fire where v1 read through them.
7. **Grants forms** offer the locked payment methods and the subject's other grants, through `dws_lpmwc_get_grant_choices()`, since a `checkboxes` save keeps only the choices it offers. The profile, order and plan forms are the framework's `ObjectForm`s; the Groups field rides Groups' own forms, whose nonce and capability Groups checks before its save actions, and checks the plugin's capability in each callback.
8. **Order storage.** Order code goes through WC CRUD and WooCommerce's screen ids, never the posts table, and every Integration row whose outcome the order storage can change runs on all four storages through `OrderStorage`.
9. **v1 hooks.** A hook v1 fired keeps its v1 literal, fired through `apply_filters_deprecated()` or `do_action_deprecated()` with `'2.0.0'` and its replacement, before the v2 name; a filter on the locked list only adds ids.
10. **Safeguards** live in `includes/safeguards.php`, call only WordPress, WooCommerce and that file, and relax only through their own filter, through `dws_lpmwc_is_relaxed()` (the template's recipes). The entry loads them before the floor check, so they parse on PHP 7.4 and call only WordPress APIs present at the oldest WordPress the bootstrap package runs on, or guard them, which `phpstan.safeguards.neon`, the file's only PHPStan profile, checks with WooCommerce's stubs.
11. **Migrations** are append-only: a step converges from any partial state, verifies its writes and throws when one fails.

## Style

- Every PHP file opens with `<?php declare( strict_types=1 );`, uses `array()`, `final` classes, `protected` over `private` and `#[\Override]` on overrides, and groups class members in `// region NAME` blocks outside `tests/`.
- Docblocks open with one third-person sentence naming the role, such as "Returns …", plus a second paragraph only for a non-obvious why; property and constant docblocks open with "The …", constructors read "Constructor.", overrides read `{@inheritDoc}`. Every other function docblock ends with `@return`, `@return  void` included; tag values align at column 10, each `@param` stays on one line, and `@throws` reads "Thrown when …". Every public `do_action()` and `apply_filters()` carries a hook docblock.
- A hook the plugin fires is named `dws_lpmwc/area/name`, with `/` between levels and `_` between words, as the framework names its own, and its last level says what kind of hook it is: a past participle for an event, `before_` or `after_` for a position, an imperative for a scheduled hook, a predicate (`is_`, `has_`, `can_`, `should_`) for a boolean filter (`dws_lpmwc/payment_method/should_lock`), and the noun of the value for any other filter, plural for a list (`dws_lpmwc/payment_method/locked_ids`).
- An element v1 had keeps v1's `@since`, and its `@version` names the release that last changed it.
- Inline comments state a non-obvious why in one capitalized sentence, and every file describes the present, never the change; a `phpcs:ignore` names its sniff and gives a reason.
- Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice.
- Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period. The body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

- Integration and end to end share the tests site, so a row that changes shared data in a separate process flushes the parent's object cache.
- The Memberships rows run against a double of its one function and its three hooks, since Memberships is a paid plugin absent from WP Packages; they are unverified against a licensed copy.
- No environment runs a multisite network, so the migration that copies v1's user grants into every site's list runs only on a single site in the suites.
- The bundled GitHub updater ships inert, since the entry file has no `Update URI`, and wordpress.org's reviewers may ask to remove it.
