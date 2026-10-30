# Architecture

This document describes how the plugin loads, boots, stores its data and leaves a site, and where each kind of code lives.

## Loading

`linked-orders-for-woocommerce.php` is the only file WordPress includes, and it parses on PHP 7.4 so that a site below the floors gets a notice instead of a fatal error:

1. It defines `DWS_LOWC_FILE`; translations come from wordpress.org's language packs, which WordPress loads just in time.
2. It requires `vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php`, registers the GitHub updater, which stays inert because the entry file has no `Update URI` header, then checks the `Requires PHP` and `Requires at least` headers. On a site below a floor, WordPress's own notice shows and nothing else loads.
3. It loads `includes/safeguards.php`, which holds even when the rest of the build fails.
4. It stops with a notice when the prefixed framework or the orders list's built script is missing. It then requires the autoloader and `functions.php`; a missing file throws, which local and development environments rethrow and every other environment logs, showing the same notice.

`functions.php` defines the `dws_lowc_plugin()` accessor, boots the plugin on `plugins_loaded` at priority 15, after WooCommerce and Action Scheduler have loaded, registers the deactivation callback, declares the plugin compatible with WooCommerce's order tables, relays the framework's boot hooks to v1's `dws_lowc_initialized` and `dws_lowc_initialization_failure`, and requires every `includes/*.php` in name order except those whose names start with `_`.

## Booting

`src/Plugin.php` is the composition root. Its constructor builds the logger, the scheduler and the framework's `PluginBoot`, which runs the composition and reports its outcome:

- The scheduler registers its hooks first, so the plugin's recurring jobs keep their schedules while the plugin is blocked.
- `WooCommerceRequirement` checks WooCommerce against the `WC requires at least` header; without it, the boot is `blocked`, `dws_lowc/boot/blocked` fires, and users who can activate plugins see why the plugin stays off.
- The migration runner registers, and runs pending steps on `wp_loaded`.
- The kernel builds each class in `Plugin::COMPONENTS` through a PHP-DI container and calls its `register_hooks()`; a composite adds its children, and a conditional component loads only when its static `should_load()` returns true.
- `CapabilityGrants` gives administrators and shop managers v1's four capabilities on `wp_loaded`, once per change of the declaration or the roles.
- Once every component has registered, the boot is `booted` and `dws_lowc/boot/succeeded` fires. A throw anywhere in the composition makes it `failed`: `dws_lowc/boot/failed` fires, the failure is logged, and administrators see a notice, while local and development environments rethrow it.

The request that activates the plugin does not boot it, because WordPress includes the plugin's file after `plugins_loaded`; its screens and migrations first run on the next request.

## Where code lives

- `src/` holds the composition root and its components: final classes that implement the framework's `ComponentInterface` and add their hooks in `register_hooks()`. `Settings` adds the `dws-linked-orders` section to WooCommerce's Advanced tab; `ChildCreation` answers v1's admin-post action; `MetaBox` and `ListTable` add the links to an order's editor and to the orders list, on either order storage, checking the user's capabilities in each callback; `Autocompletion` completes descendants; `Integration\SequentialOrderNumbersPro` loads only beside that plugin. A component takes ungated services in its constructor, such as `AdminNotices`, which `Plugin::get_definitions()` provides.
- `models/DWS_Order_Node.php` keeps v1's global class on Composer's classmap: a node wraps an order of a supported type, reads its depth, parent and children from the order's meta once, and saves them back through WC CRUD.
- `includes/` holds code that registers no hooks and is not part of the tree, as prefixed functions and constants: v1's capabilities (`capabilities.php`), the option keys, defaults and the depth validator (`options.php`), v1's settings, order-type and linking APIs (`settings.php`, `post-type.php`, `linking.php`), v1's deprecated root functions, constants and boot-hook relays (`deprecated.php`), the footprint (`footprint.php`), the migration steps (`migrations.php`), the asset registrars (`assets.php`) and the slug, name and version getters a peer plugin reads (`metadata.php`). Quality's syntax job parses the folder on PHP 7.4, because `safeguards.php` and what it requires run before the floor check passes on a site without the built files.
- `assets/src/` holds the orders list's TypeScript and SCSS, which `npm run build` compiles into `assets/build/`; `includes/assets.php` reads the `*.asset.php` file wp-scripts writes beside each build.

## Data

- **Order links** are three meta keys on each linked order, as v1 stored them: `_dws_lo_depth`, where a root is 0, `_dws_lo_parent` and `_dws_lo_children`, and `_dws_lo_created_by` on a child created through the plugin. WC CRUD writes them to whichever storage WooCommerce uses. Every link reads and rewrites the parent's `_dws_lo_children`, so `dws_lowc_link_orders()` holds the `dws_lowc_linking` option lock while it links, re-reads both orders' meta from the database under it, with WooCommerce's order-tables data caching off for the reads, and waits up to five seconds for another request's link to finish before it returns false; `dws_lowc_create_linked_child()` then deletes the child it created and returns a `WP_Error`.
- **Options** keep v1's keys, `dws-lowc_general_max-depth`, `dws-lowc_general_autocomplete-descendants` and `dws-lowc_plugin_remove-data-uninstall`, as constants in `includes/options.php`. Code reads them through v1's API in `includes/settings.php`, which validates each stored value, falls back to the key's default, and applies v1's filters and then `dws_lowc/settings/validated_value`.
- **Migrations** are the append-only steps `dws_lowc_get_migration_steps()` returns. The framework's runner runs each pending step once per site on `wp_loaded`, behind a lock, and records the revision reached in `dws_lowc_revision`. The first step stores each setting's default where a site, v1 or new, has none. A step converges from any partial state and throws when a write fails, so a failed step runs again on a later request.
- **The footprint**, `dws_lowc_get_footprint()`, names every kind of data the plugin stores: the `dws_lowc_` and `dws-lowc_` option prefixes, the `_dws_lo_` order meta on both storages, v1's version and notice rows, user meta, network options, the capabilities, scheduled hooks and Action Scheduler groups. Freemius's `fs_` rows from v1 stay, since they belong to Freemius.
- **Deactivation** unschedules every hook in the footprint's `cron_hooks` and `as_hooks` on the current site.
- **Uninstall** runs `uninstall.php`, which calls the framework's uninstall engine over the footprint, with WooCommerce's order meta types, on every site whose `dws-lowc_plugin_remove-data-uninstall` setting consents, as a v1 site's consent carries over; a site that never consented keeps its data. Below the floors or with an incomplete build, a web request deletes the files, keeps the data and logs why, while WP-CLI fails and keeps both for a rerun.

## Prefixing and distribution

Every development install runs php-scoper through wordpress-configs, which copies the framework, PHP-DI and their dependencies into `vendor-prefixed/` under `DeepWebSolutions\LinkedOrders\Scoped\`, so each plugin carries its own copy and never meets another plugin's. The release workflow builds `linked-orders-for-woocommerce.zip` from a production install, checks it with Plugin Check, the archive and header checks, activates it on a site and runs the end-to-end suite against it. A pull request rehearses the same build without publishing. A tag publishes a GitHub release and deploys the zip to the wordpress.org directory, which offers it to sites; `composer changelog:write` turns `changelog/` into `CHANGELOG.md` and copies its versions into `readme.txt`. The listing's banners, icons and screenshots stay in the directory's SVN, since the plugin ships no `.wordpress-org/`.
