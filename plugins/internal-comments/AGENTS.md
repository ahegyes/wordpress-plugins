# wp-internal-comments

Internal Comments, the v2 port of the wordpress.org plugin of that name, built on four wp-framework packages (`ahegyes/wp-framework-{bootstrap,shared,core,settings}`) from the plugin template, with WooCommerce as an optional host for orders. It ships its own copy of the framework, PHP-DI and their dependencies, prefixed under `DeepWebSolutions\InternalComments\Scoped` by php-scoper through wordpress-configs into `vendor-prefixed/` on every development install. Its architecture follows the framework's conventions 1–10 in [wordpress-framework's AGENTS.md](https://github.com/ahegyes/wordpress-framework/blob/trunk/AGENTS.md); this file covers what is particular to the plugin. `docs/ARCHITECTURE.md` describes the boot and the data, `tests/README.md` the suites, and the template's [recipes](https://github.com/ahegyes/wordpress-plugin-template/blob/trunk/docs/recipes.md) the common changes, under the template's `dws_tpl` prefix.

v1 sites upgrade in place, so the port keeps v1's comment type, settings rows, capabilities, comment meta, settings page and helper API, and fires each v1 hook, deprecated, before its v2 name.

## Layout

```
internal-comments.php        the entry: bootstrap load.php, the inert updater, the floor check, safeguards, the build gate, the autoloader
functions.php                dws_ic_plugin(), the boot on plugins_loaded 15, deactivation, the order-tables declaration, v1's boot hooks, the includes/ loader
uninstall.php                the framework's uninstall engine over the footprint, with v1's remove-data-uninstall consent
src/                         the composition root and its components only
├── Plugin.php               SLUG, PREFIX (dws_ic), COMPONENTS, the logger, the scheduler, the migration runner and the kernel
├── Settings.php             v1's settings page under Settings, its General and Plugin tabs, and the plugin row's links
├── Permissions.php          v1's three meta capabilities, and core's edit_comment on an internal comment
├── Comments.php             the guards that keep internal comments under their status, and the revision record
├── MetaBox.php              the meta box on the edit screens of posts and orders of the selected types
├── RestRoutes.php           the dws_ic/v1 routes the meta box and the quick view use
└── AdminScreens.php         the posts and orders list column with its quick view, the Comments screen view, the comment edit screen
models/DWS_Internal_Comment.php  v1's global model class on the classmap, wrapping an internal comment with the user's permissions on it
includes/                    hook-free code as dws_ic_ functions and DWS_IC_ constants, loaded in name order, _-prefixed files skipped
├── safeguards.php           restrictions that hold when the build fails; loaded by the entry before the autoloader
├── capabilities.php         v1's twelve capabilities and the roles CapabilityGrants gives them
├── options.php              v1's two settings rows, their fields and their defaults
├── settings.php             v1's settings API: the raw and validated reads, by v1 field id and group
├── comments.php             the comment type and status, and v1's comment API: create, read, thread, trash meta, privacy
├── markup.php               the comment list, comment and form markup the meta box prints and the REST routes answer with
├── deprecated.php           v1's root getters, hook-tag builders, constants and boot-hook relays, deprecated
├── footprint.php            dws_ic_get_footprint(), including what v1 stored under its own names
├── migrations.php           dws_ic_get_migration_steps(): v1's default rows, then the batched move of v1's comments to the status
├── assets.php               the *.asset.php reader and the script and style registrars
└── metadata.php             the slug, name and version getters, the plugin's surface for a peer plugin
assets/src/                  the meta box's and quick view's TypeScript and SCSS, built into assets/build/ (untracked)
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
npm run build                # the meta box's and quick view's assets through the local wp-scripts; npm run start watches them in a POSIX shell
npm run lint:scripts         # ESLint, beside lint:styles (Stylelint) and lint:types (tsc)
npm run test:e2e             # Playwright on .wp-env.tests.json, starting it if needed; the scripts must be built
```

Run wp-scripts only through the npm scripts or `node_modules/.bin/wp-scripts`: `npx wp-scripts` can install an unrelated package of that name.

## Environments

- `.wp-env.json`: development, as `local`, on port 8824, behind `npm run setup` and `wp-env:*`. The framework rethrows boot and migration failures there.
- `.wp-env.tests.json`: Integration and end to end, as `production`, on port 8825, behind `wp-env:tests:*`, `composer test:integration` and `npm run test:e2e`. The end-to-end suite deactivates and reactivates WooCommerce there.

Both run PHP 8.5 with WooCommerce from `vendor/` and its order tables on; CI runs Integration and end to end on every WordPress branch from the entry's `Requires at least` up, and nightly on WordPress trunk.

## Conventions

1. **Placement.** `src/` holds only the composition root and its components. Code that registers no hooks, such as keys, readers, v1's helper API, markup, the footprint, migration steps and asset registrars, is `dws_ic_` functions and `DWS_IC_` constants in `includes/`, which parses on PHP 7.4: no `mixed` or union types, typed constants, `match`, nullsafe calls or first-class callables there. `DWS_Internal_Comment` keeps v1's global name in `models/`, outside that limit. Container definitions are composition and stay in `Plugin::get_definitions()`.
2. **Variants.** A component that exists only in one build variant is a child in a branch of its parent's `get_child_component_classes()`, never a second entry in `COMPONENTS`.
3. **Request context.** `is_admin()` is false on REST requests and true in admin-ajax ones, so an admin-only feature decides the request context in its callbacks, never in `should_load()`.
4. **Add-ons.** An add-on extends a base plugin only through the base plugin's documented hooks. It reads a template-built base plugin's slug, name and version only through that plugin's `{prefix}_get_plugin_*()` getters, from `plugins_loaded` on, since the add-on may load first, and behind `function_exists()`, since a base plugin below its floors or with a partial build may stay active without defining them.
5. **Request reads.** An integer or boolean is read as `filter_var( wp_unslash( $_POST['key'] ?? '' ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? $default` (`FILTER_VALIDATE_BOOL` for a boolean, where an absent key reads false, as an unchecked checkbox posts nothing), and a string through an `is_string()` guard and the field's sanitizer; never `filter_input()`, the `default` filter option or a cast on request input. A REST route declares each parameter's type and sanitizer in its `args`.
6. **Options and data.** A setting is read through v1's API in `includes/settings.php`, whose validation is each setting's one typed read and whose reads apply v1's filters, safeguards excepted. v1's settings keep their `dws-internal-comments_` rows and its comments their `dws_internal` type and `dws_ic_is_private` and `_dws_ic_` meta; anything new lives under `dws_ic_`, or `_dws_ic_` for hidden meta. The footprint covers those prefixes and v1's other rows by name.
7. **Internal status.** An internal comment is stored under the `dws_internal` status, which keeps it out of WordPress's queries, counts, feeds and REST API without the plugin's help, so no code stores one under another status, and a query that lists them names the status. `Comments` puts back any that WordPress or another plugin moves, and until the migration has moved the comments v1 stored approved, it leaves the type out of every comment query and feed that names neither it nor the status. Every check of what a user may do with one goes through the `see_`, `edit_` and `trash_dws_internal_comment` meta capabilities.
8. **Order storage.** Order code takes ids WooCommerce resolves in either storage, through `dws_ic_get_post_type()` and `edit_post`, which WooCommerce maps for orders, and WooCommerce's screen ids and list hooks, never the posts table; every Integration row whose outcome the order storage can change runs on all four storages through `OrderStorage`.
9. **v1 hooks.** A hook v1 fired keeps its v1 literal, fired first through `apply_filters_deprecated()` or `do_action_deprecated()` with `'2.0.0'` and the v2 name, then the v2 name.
10. **Safeguards** live in `includes/safeguards.php`, call only WordPress and that file, and relax only through their own filter, through `dws_ic_is_relaxed()` (the template's recipes).
11. **Migrations** are append-only: a step converges from any partial state, verifies its writes and throws when one fails; bulk work is a batched step, which runs only from the runner's scheduled hook.

## Style

- Every PHP file opens with `<?php declare( strict_types=1 );`, uses `array()`, `final` classes, `protected` over `private` and `#[\Override]` on overrides, and groups class members in `// region NAME` blocks outside `tests/`.
- Docblocks open with one third-person sentence naming the role, such as "Returns …", plus a second paragraph only for a non-obvious why; property and constant docblocks open with "The …", constructors read "Constructor.", overrides read `{@inheritDoc}`. Every other function docblock ends with `@return`, `@return  void` included; tag values align at column 10, each `@param` stays on one line, and `@throws` reads "Thrown when …". Every public `do_action()` and `apply_filters()` carries a hook docblock.
- A hook the plugin fires is named `dws_ic/area/name`, with `/` between levels and `_` between words, as the framework names its own, and its last level says what kind of hook it is: a past participle for an event (`dws_ic/boot/succeeded`), `before_` or `after_` for a position (`dws_ic/internal_comment/before_insert`), an imperative for a scheduled hook, a predicate (`is_`, `has_`, `can_`, `should_`) for a boolean filter, and the noun of the value for any other filter, plural for a list (`dws_ic/meta_box/comment_classes`).
- An element v1 had keeps v1's `@since`, and its `@version` names the release that last changed it.
- Inline comments state a non-obvious why in one capitalized sentence, and every file describes the present, never the change; a `phpcs:ignore` names its sniff and gives a reason.
- Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice.
- Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period. The body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

- Integration and end to end share the tests site, so a row that changes shared data in a separate process flushes the parent's object cache.
- The end-to-end suite runs on the order tables only, as the tests site stores orders; the Integration rows cover the posts storage.
- A site that returns to 1.2.4 after the upgrade stores its new internal comments approved, and the migration that moved v1's comments does not run again on the next upgrade, so those comments stay public until moved by hand.
- Core's comment edit screen shows author, date and status fields for an internal comment, which `Comments` keeps as stored.
- When a private comment is deleted permanently, its replies become private under their own authors, who then also see the replies beneath them that the deleted comment's privacy hid from them.
- Core's Comments screen and REST API trash or delete an internal comment for any user who can edit it, because core checks only `edit_comment` there and offers no hook to refuse; the meta box's route checks the trash capability.
- The release zip carries the TypeScript and SCSS sources beside the built files.
- The bundled GitHub updater ships inert, since the entry file has no `Update URI`, and wordpress.org's reviewers may ask to remove it.
