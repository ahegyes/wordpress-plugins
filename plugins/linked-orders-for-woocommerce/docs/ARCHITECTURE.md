# Architecture

This document describes how the plugin loads, boots, stores its data and leaves a site, and where each kind of code lives.

## Loading

`linked-orders-for-woocommerce.php` is the only file WordPress includes, and it parses on PHP 7.4 so that a site below the floors gets a notice instead of a fatal error:

1. It defines `DWS_LOWC_FILE` and registers `languages/`, which WordPress skips for a network-activated plugin.
2. It requires `vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php`, registers the GitHub updater, then checks the `Requires PHP` and `Requires at least` headers. The updater comes first, so a release that lowers the floors still reaches a site below them. On a site below a floor, WordPress's own notice shows and nothing else loads.
3. It loads `includes/safeguards.php`, which holds even when the rest of the build fails.
4. It stops with a notice when the prefixed framework or the built blocks are missing. It then requires the autoloader and `functions.php`; a missing file throws, which local and development environments rethrow and every other environment logs, showing the same notice.

`functions.php` defines the `dws_lowc_plugin()` accessor, boots the plugin on `plugins_loaded` at priority 15, after WooCommerce and Action Scheduler have loaded, registers the deactivation callback, declares the plugin's WooCommerce feature compatibility, and requires every `includes/*.php` in name order except those whose names start with `_`.

## Booting

`src/Plugin.php` is the composition root. Its constructor builds the logger, the scheduler and the framework's `PluginBoot`, which runs the composition and reports its outcome:

- The scheduler registers its hooks first, so the plugin's recurring jobs keep their schedules while the plugin is blocked.
- `WooCommerceRequirement` checks WooCommerce against the `WC requires at least` header; without it, the boot is `blocked`, `dws_lowc/boot/blocked` fires, and users who can activate plugins see why the plugin stays off.
- The migration runner registers, and runs pending steps on `wp_loaded`.
- The kernel builds each class in `Plugin::COMPONENTS` through a PHP-DI container and calls its `register_hooks()`; a composite adds its children, and a conditional component loads only when its static `should_load()` returns true.
- Once every component has registered, the boot is `booted` and `dws_lowc/boot/succeeded` fires. A throw anywhere in the composition makes it `failed`: `dws_lowc/boot/failed` fires, the failure is logged, and administrators see a notice, while local and development environments rethrow it.

The request that activates the plugin does not boot it, because WordPress includes the plugin's file after `plugins_loaded`; its post types, routes and migrations first run on the next request.

## Where code lives

- `src/` holds the composition root and its components: final classes that implement the framework's `ComponentInterface` and add their hooks in `register_hooks()`. A component takes ungated services in its constructor, such as the logger, the scheduler or `AdminNotices`, which `Plugin::get_definitions()` provides.
- `includes/` holds code that registers no hooks and is not part of the tree, as prefixed functions and constants: the option keys, defaults and typed readers (`options.php`), the footprint (`footprint.php`), the migration steps (`migrations.php`), the asset registrars (`assets.php`) and the slug, name and version getters a peer plugin reads (`metadata.php`). Quality's syntax job parses the folder on PHP 7.4, because `safeguards.php` and what it requires run before the floor check passes on a site without the built files.
- `blocks/src/` and `assets/src/` hold the TypeScript and SCSS sources, which `npm run build` compiles into `blocks/build/` and `assets/build/`; `includes/assets.php` reads the `*.asset.php` file wp-scripts writes beside each build, and `src/Blocks.php` registers the blocks from `blocks/build/blocks-manifest.php`.
- `models/` and `templates/` are empty extension points, for classes on Composer's classmap and for template partials.

## Data

- **Options** are prefixed constants in `includes/options.php`, read only through its typed readers, which guard each stored value and fall back to the key's default. Translated defaults come from `dws_lowc_get_option_text()` after `init`; a field shows one as its placeholder and stores an empty value, which its reader reads as the default, so no save stores the admin's language.
- **Migrations** are the append-only steps `dws_lowc_get_migration_steps()` returns. The framework's runner runs each pending step once per site on `wp_loaded`, behind a lock, and records the revision reached in `dws_lowc_revision`. A step converges from any partial state and throws when a write fails, so a failed step runs again on a later request.
- **The footprint**, `dws_lowc_get_footprint()`, names every kind of data the plugin stores: option and user meta prefixes, network options, scheduled hooks and Action Scheduler groups. Options, transients and user meta under the `dws_lowc_` prefix are covered; meta on posts, terms, comments or orders needs a `meta_prefixes` entry per object type, and data under another name its own entry, which no check enforces.
- **Deactivation** unschedules every hook in the footprint's `cron_hooks` and `as_hooks` on the current site.
- **Uninstall** runs `uninstall.php`, which calls the framework's uninstall engine over the footprint, with WooCommerce's order meta types, on every site whose `dws_lowc_delete_data` setting consents; a site that never consented keeps its data. Below the floors or with an incomplete build, a web request deletes the files, keeps the data and logs why, while WP-CLI fails and keeps both for a rerun.

## Prefixing and distribution

Every development install runs php-scoper through wordpress-configs, which copies the framework, PHP-DI and their dependencies into `vendor-prefixed/` under `DeepWebSolutions\LinkedOrders\Scoped\`, so each plugin carries its own copy and never meets another plugin's. The release workflow builds `linked-orders-for-woocommerce.zip` from a production install, checks it with Plugin Check, the archive and header checks, activates it on a site and runs the end-to-end suite against it. A pull request rehearses the same build without publishing. A tag publishes a GitHub release, which the bundled updater offers through WordPress's own update check while the `Update URI` header points at the repository.
