# Architecture

This document describes how the plugin loads, boots, stores its data and leaves a site, and where each kind of code lives.

## Loading

`internal-comments.php` is the only file WordPress includes, and it parses on PHP 7.4 so that a site below the floors gets a notice instead of a fatal error:

1. It defines `DWS_IC_FILE`; translations come from wordpress.org's language packs, which WordPress loads just in time.
2. It requires `vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php`, registers the GitHub updater, which stays inert because the entry file has no `Update URI` header, then checks the `Requires PHP` and `Requires at least` headers. On a site below a floor, WordPress's own notice shows and nothing else loads.
3. It loads `includes/safeguards.php`, which holds even when the rest of the build fails.
4. It stops with a notice when the prefixed framework or the meta box's built script is missing. It then requires the autoloader and `functions.php`; a missing file throws, which local and development environments rethrow and every other environment logs, showing the same notice.

`functions.php` defines the `dws_ic_plugin()` accessor, boots the plugin on `plugins_loaded` at priority 15, after WooCommerce and Action Scheduler have loaded where they are active, registers the deactivation callback, declares the plugin compatible with WooCommerce's order tables, relays the framework's boot hooks to v1's `dws_ic_initialized` and `dws_ic_initialization_failure`, and requires every `includes/*.php` in name order except those whose names start with `_`.

## Booting

`src/Plugin.php` is the composition root. Its constructor builds the logger, the scheduler and the framework's `PluginBoot`, which runs the composition and reports its outcome:

- The scheduler, on WP-Cron, registers its hooks first, so the migration runner's scheduled hook keeps running.
- The migration runner registers, and runs pending steps on `wp_loaded` and from its scheduled hook.
- The kernel builds each class in `Plugin::COMPONENTS` through a PHP-DI container and calls its `register_hooks()`.
- `CapabilityGrants` gives v1's twelve capabilities to administrators, and the five that post, see and mark comments private to editors, authors, contributors and shop managers, on `wp_loaded`, once per change of the declaration or the roles.
- Once every component has registered, the boot is `booted` and `dws_ic/boot/succeeded` fires. A throw anywhere in the composition makes it `failed`: `dws_ic/boot/failed` fires, the failure is logged, and administrators see a notice, while local and development environments rethrow it.

WooCommerce is optional: without it, every feature but the order screens and the orders list works. The request that activates the plugin does not boot it, because WordPress includes the plugin's file after `plugins_loaded`; its screens and migrations first run on the next request.

## Where code lives

- `src/` holds the composition root and its components: final classes that implement the framework's `ComponentInterface` and add their hooks in `register_hooks()`. `Settings` declares v1's settings page to the framework's `OptionsPage`; `Permissions` maps the meta capabilities and holds core's REST API and XML-RPC to them, which otherwise let moderators and a comment's author past; `Comments` keeps internal comments under their status and records their revisions; `MetaBox` adds the meta box to posts and orders of the selected types; `RestRoutes` registers the routes the meta box and quick view call; `AdminScreens` adds the list column and quick view, the Comments screen view and the edit screen's revisions and privacy. Each checks the user's capabilities in its callbacks.
- `models/DWS_Internal_Comment.php` keeps v1's global class on Composer's classmap: it wraps an internal comment, passes reads and calls to it, and answers whether a user may see, edit or trash it.
- `includes/` holds code that registers no hooks and is not part of the tree, as prefixed functions and constants: v1's capabilities (`capabilities.php`), the settings rows, fields and defaults (`options.php`), v1's settings and comment APIs (`settings.php`, `comments.php`), the markup the meta box prints and the routes answer with (`markup.php`), v1's deprecated root functions, constants and boot-hook relays (`deprecated.php`), the footprint (`footprint.php`), the migration steps (`migrations.php`), the asset registrars (`assets.php`) and the slug, name and version getters a peer plugin reads (`metadata.php`). Quality's syntax job parses the folder on PHP 7.4, because `safeguards.php` and what it requires run before the floor check passes on a site without the built files.
- `assets/src/` holds the meta box's and quick view's TypeScript and SCSS, which `npm run build` compiles into `assets/build/`; `includes/assets.php` reads the `*.asset.php` file wp-scripts writes beside each build.

## Data

- **Internal comments** are comments of v1's `dws_internal` type, stored under the `dws_internal` status. WordPress's comment queries, counts, feeds and REST API include only the statuses they name, so they leave internal comments out without the plugin's help, active or not. `Comments` puts back under the status any internal comment that WordPress or another plugin stores, approves, holds, marks spam or restores under another, and keeps every field but the text as stored when one is updated. Until the migration has moved the comments v1 stored approved, it also leaves their type out of every comment query and feed that names neither the type nor the status. A post's comments on a trashed post are read from what WordPress recorded when it trashed the post.
- **Comment meta** keeps v1's keys: `dws_ic_is_private`, `yes` on a comment marked private, which only its author changes; and, per change to a comment's text, `_dws_ic_change_id` with `_dws_ic_previous_content_{id}`, `_dws_ic_changed_by_{id}` and `_dws_ic_changed_time_{id}`.
- **Options** keep v1's two rows, `dws-internal-comments_general` with `post-types` and `enable-private-comments`, and `dws-internal-comments_plugin` with `remove-data-uninstall`, as constants in `includes/options.php`. Code reads them through v1's API in `includes/settings.php`, which validates each stored value, falls back to v1's default, and applies v1's filters and then `dws_ic/settings/validated_value`.
- **Migrations** are the append-only steps `dws_ic_get_migration_steps()` returns. The framework's runner runs each pending step once per site, behind a lock, and records the revision reached in `dws_ic_revision`. The first step stores v1's default rows where a site, v1 or new, has none. The second is batched, so it runs only from the runner's scheduled hook: it walks the comments table by id window, moves the internal comments v1 stored approved, pending or as spam to the status and recounts their posts, keeping its position in `dws_ic_v1_comments_cursor` until the walk ends. A step converges from any partial state and throws when a write fails, so a failed step runs again on a later request.
- **The footprint**, `dws_ic_get_footprint()`, names every kind of data the plugin stores: the `dws_ic_` and `dws-internal-comments_` option prefixes, the `dws_internal` comments with their meta, v1's version and notice rows, user meta, network options, the capabilities, scheduled hooks and Action Scheduler groups. Freemius's `fs_` rows from v1 stay, since they belong to Freemius.
- **Deactivation** unschedules every hook in the footprint's `cron_hooks` and `as_hooks` on the current site. Internal comments stay hidden, since their status hides them.
- **Uninstall** runs `uninstall.php`, which calls the framework's uninstall engine over the footprint on every site whose `remove-data-uninstall` setting consents, as a v1 site's consent carries over; a site that never consented keeps its data. Below the floors or with an incomplete build, a web request deletes the files, keeps the data and logs why, while WP-CLI fails and keeps both for a rerun.

## Prefixing and distribution

Every development install runs php-scoper through wordpress-configs, which copies the framework, PHP-DI and their dependencies into `vendor-prefixed/` under `DeepWebSolutions\InternalComments\Scoped\`, so each plugin carries its own copy and never meets another plugin's. The release workflow builds `internal-comments.zip` from a production install, checks it with Plugin Check, the archive and header checks, activates it on a site and runs the end-to-end suite against it. A pull request rehearses the same build without publishing. A tag publishes a GitHub release and deploys the zip to the wordpress.org directory, which offers it to sites; `composer changelog:write` turns `changelog/` into `CHANGELOG.md` and copies its versions into `readme.txt`. The listing's banners, icons and screenshots stay in the directory's SVN, since the plugin ships no `.wordpress-org/`.
