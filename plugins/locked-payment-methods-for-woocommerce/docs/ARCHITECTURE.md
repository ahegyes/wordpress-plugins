# Architecture

This document describes how the plugin loads, boots, stores its data and leaves a site, and where each kind of code lives.

## Loading

`locked-payment-methods-for-woocommerce.php` is the only file WordPress includes, and it parses on PHP 7.4 so that a site below the floors gets a notice instead of a fatal error:

1. It defines `DWS_LPMWC_FILE`; translations come from wordpress.org's language packs, which WordPress loads just in time.
2. It loads `includes/safeguards.php`, whose lock holds on any runtime that parses the plugin: below the floors, on a partial deploy and when the boot fails.
3. It requires `vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php`, registers the GitHub updater, which stays inert because the entry file has no `Update URI` header, then checks the `Requires PHP` and `Requires at least` headers. On a site below a floor, WordPress's own notice shows and nothing past the safeguards loads.
4. It stops with a notice when the prefixed framework is missing. It then requires the autoloader and `functions.php`; a missing file throws, which local and development environments rethrow and every other environment logs, showing the same notice.

`functions.php` defines the `dws_lpmwc_plugin()` accessor, boots the plugin on `plugins_loaded` at priority 15, after WooCommerce and Action Scheduler have loaded, registers the deactivation callback, declares the plugin compatible with WooCommerce's order tables, relays the framework's boot hooks to v1's `dws_lpmwc_initialized` and `dws_lpmwc_initialization_failure`, and requires every `includes/*.php` in name order except those whose names start with `_`.

## Booting

`src/Plugin.php` is the composition root. Its constructor builds the logger, the scheduler and the framework's `PluginBoot`, which runs the composition and reports its outcome:

- The scheduler registers its hooks first, so the plugin's recurring jobs keep their schedules while the plugin is blocked.
- `WooCommerceRequirement` checks WooCommerce against the `WC requires at least` header; without it, the boot is `blocked`, `dws_lpmwc/boot/blocked` fires, and users who can activate plugins see why the plugin stays off.
- The migration runner registers, and runs pending steps on `wp_loaded`.
- The kernel builds each class in `Plugin::COMPONENTS` through a PHP-DI container and calls its `register_hooks()`; a conditional component, such as each unlock strategy, loads only when its static `should_load()` returns true.
- `CapabilityGrants` gives administrators v1's four capabilities, and shop managers the user-profile and order ones, on `wp_loaded`, once per change of the declaration or the roles.
- Once every component has registered, the boot is `booted` and `dws_lpmwc/boot/succeeded` fires. A throw anywhere in the composition makes it `failed`: `dws_lpmwc/boot/failed` fires, the failure is logged, and administrators see a notice, while local and development environments rethrow it.

The request that activates the plugin does not boot it, because WordPress includes the plugin's file after `plugins_loaded`; its strategies and migrations first run on the next request.

## The lock

`includes/safeguards.php` filters `woocommerce_available_payment_gateways` at 999, which WooCommerce applies on the classic checkout, the order payment page and the Store API's checkout:

1. It reads `dws_lpmwc_locked_payment_methods`, or v1's `dws-wc-lpm_general_locked-payment-methods` while the plugin has stored none, since the migration that copies v1's list runs only once the plugin boots, then lets v1's value and list filters and `dws_lpmwc/payment_method/locked_ids` add ids.
2. For each offered payment method on the list, it fires v1's two lock filters, then `dws_lpmwc/payment_method/should_lock` with the current user and, on an order's own payment page with that order's key, the order.
3. It removes the payment method unless `should_lock` returns an explicit `false` after `dws_lpmwc/boot/succeeded`, so the lock binds everyone while the plugin cannot boot.

Each unlock strategy in `src/UnlockStrategies/` hooks `should_lock` while its switch is on, and returns `false` when its subject unlocks the payment method: the user's roles, the user's grants on the site, the order's grants, the user's Groups groups or the plans the user is an active member of. `dws_lpmwc_is_payment_method_locked()` runs the same filter for a user and an order outside checkout.

## Where code lives

- `src/` holds the composition root and its components: final classes that implement the framework's `ComponentInterface` and add their hooks in `register_hooks()`. `Settings` adds the `dws-locked-payment-methods` section to WooCommerce's Advanced tab; the strategies add their forms: `UserProfileStrategy` an `ObjectForm` on the profile and the Users screen's column and filter, `OrderStrategy` an `ObjectForm` in a box on the order screen on either storage, `GroupsStrategy` a field on Groups' own group forms, and `WooCommerceMembershipsStrategy` an `ObjectForm` in a tab of the plan data through `EditScreen\Hosts\MembershipPlanTab`. A component takes ungated services in its constructor, such as `AdminNotices`, which `Plugin::get_definitions()` provides.
- `includes/` holds code that registers no hooks and is not part of the tree, as prefixed functions and constants: the lock (`safeguards.php`), v1's capabilities (`capabilities.php`), the grants' keys and readers (`grants.php`), the settings' keys, defaults, readers and v1's settings map (`options.php`), v1's deprecated helper API (`deprecated.php`), the footprint (`footprint.php`), the migration steps (`migrations.php`) and the slug, name and version getters a peer plugin reads (`metadata.php`). Quality's syntax job parses the folder on PHP 7.4, because `safeguards.php` runs before the floor check, and `phpstan.safeguards.neon` analyzes that file at PHP 7.4 and the oldest WordPress the bootstrap package runs on.

## Data

- **Settings** are flat options under `dws_lpmwc_`, as constants in `includes/options.php`: the locked list, a switch per strategy, the full-access roles and the uninstall consent. The first migration step copies each of v1's eight settings from its `dws-wc-lpm_{group}_{field}` row where it holds a value of the setting's kind, and leaves v1's rows until uninstall, so a downgrade to 1.3.5 still locks what it locked.
- **Grants** are one list of payment method ids per subject: the user option `dws_lpmwc_unlocked_payment_methods` under each site's table prefix, the order meta and the Memberships plan meta `_dws_lpmwc_unlocked_payment_methods`, and the `dws_lpmwc_groups_unlocked_payment_methods` map from Groups group id to list, from which deleting a group removes its entry. The second to fifth migration steps move v1's grants into these shapes and delete v1's rows: user meta into every site's list, so v1's grants keep their network reach, order meta through WC CRUD on either storage, the per-group options and the plan meta.
- **Migrations** are the append-only steps `dws_lpmwc_get_migration_steps()` returns. The framework's runner runs each pending step once per site on `wp_loaded`, behind a lock, and records the revision reached in `dws_lpmwc_revision`. A step converges from any partial state and throws when a write fails, so a failed step runs again on a later request.
- **The footprint**, `dws_lpmwc_get_footprint()`, names every kind of data the plugin stores: the `dws_lpmwc_` and `dws-wc-lpm_` option prefixes, v1's version and notice rows, the order meta under `_dws_lpmwc_` and `_dws_wc_lpm_` on both storages, the plan meta under both keys, the site user meta under `dws_lpmwc_`, v1's global user grants under `dws_wc_lpm_`, network options, the capabilities, scheduled hooks and Action Scheduler groups. Freemius's `fs_` rows from v1 stay, since they belong to Freemius.
- **Deactivation** unschedules every hook in the footprint's `cron_hooks` and `as_hooks` on the current site.
- **Uninstall** runs `uninstall.php`, which calls the framework's uninstall engine over the footprint, with WooCommerce's order meta types, on every site whose `dws_lpmwc_delete_data` setting consents, which the first migration step copies from v1's; a site that never consented keeps its data. Below the floors or with an incomplete build, a web request deletes the files, keeps the data and logs why, while WP-CLI fails and keeps both for a rerun.

## Prefixing and distribution

Every development install runs php-scoper through wordpress-configs, which copies the framework, PHP-DI and their dependencies into `vendor-prefixed/` under `DeepWebSolutions\LockedPaymentMethods\Scoped\`, so each plugin carries its own copy and never meets another plugin's. The release workflow builds `locked-payment-methods-for-woocommerce.zip` from a production install, checks it with Plugin Check, the archive and header checks, activates it on a site and runs the end-to-end suite against it. A pull request rehearses the same build without publishing. A tag publishes a GitHub release and deploys the zip to the wordpress.org directory, which offers it to sites; `composer changelog:write` turns `changelog/` into `CHANGELOG.md` and copies its versions into `readme.txt`. The listing's banners, icons and screenshots stay in the directory's SVN, since the plugin ships no `.wordpress-org/`.
